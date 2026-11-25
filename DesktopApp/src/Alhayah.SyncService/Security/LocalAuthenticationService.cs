using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.Json.Serialization;
using Alhayah.Contracts.Domain;
using Alhayah.Contracts.Ipc;
using Alhayah.LocalData.Data;
using Alhayah.SyncService.Ipc;
using Alhayah.SyncService.Storage;
using Konscious.Security.Cryptography;
using Microsoft.EntityFrameworkCore;
using LocalSessionContract = Alhayah.Contracts.Ipc.LocalSession;
using LocalSessionEntity = Alhayah.LocalData.Data.LocalSession;

namespace Alhayah.SyncService.Security;

public sealed record PasswordHashResult(
    byte[] Hash,
    byte[] Salt,
    int MemoryKib,
    int Iterations,
    int Parallelism);

public sealed class Argon2PasswordHasher
{
    public const int MemoryKib = 65_536;
    public const int Iterations = 3;
    public const int Parallelism = 1;
    public const int SaltBytes = 16;
    public const int HashBytes = 32;

    public PasswordHashResult Hash(string password)
    {
        byte[] salt = RandomNumberGenerator.GetBytes(SaltBytes);
        return new PasswordHashResult(
            Derive(password, salt, MemoryKib, Iterations, Parallelism),
            salt,
            MemoryKib,
            Iterations,
            Parallelism);
    }

    public bool Verify(string password, LocalUser user)
    {
        ArgumentNullException.ThrowIfNull(user);
        if (user.PasswordSalt.Length != SaltBytes ||
            user.PasswordHash.Length != HashBytes ||
            user.Argon2MemoryKib != MemoryKib ||
            user.Argon2Iterations != Iterations ||
            user.Argon2Parallelism != Parallelism)
        {
            return false;
        }

        byte[] actual = Derive(
            password,
            user.PasswordSalt,
            user.Argon2MemoryKib,
            user.Argon2Iterations,
            user.Argon2Parallelism);
        try
        {
            return CryptographicOperations.FixedTimeEquals(actual, user.PasswordHash);
        }
        finally
        {
            CryptographicOperations.ZeroMemory(actual);
        }
    }

    private static byte[] Derive(
        string password,
        byte[] salt,
        int memoryKib,
        int iterations,
        int parallelism)
    {
        ValidatePassword(password);
        byte[] passwordBytes = Encoding.UTF8.GetBytes(password);
        try
        {
            using Argon2id argon = new(passwordBytes)
            {
                Salt = salt,
                MemorySize = memoryKib,
                Iterations = iterations,
                DegreeOfParallelism = parallelism
            };
            return argon.GetBytes(HashBytes);
        }
        finally
        {
            CryptographicOperations.ZeroMemory(passwordBytes);
        }
    }

    private static void ValidatePassword(string password)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(password);
        if (password.Length > 256)
        {
            throw new ArgumentException("The local password exceeds the allowed length.", nameof(password));
        }
    }
}

public sealed record LocalPermissionManifest
{
    [JsonPropertyName("userId")]
    public required Guid UserId { get; init; }

    [JsonPropertyName("deviceId")]
    public required string DeviceId { get; init; }

    [JsonPropertyName("sourceId")]
    public required string SourceId { get; init; }

    [JsonPropertyName("permissions")]
    public required IReadOnlyList<string> Permissions { get; init; }

    [JsonPropertyName("manifestVersion")]
    public required long ManifestVersion { get; init; }

    [JsonPropertyName("issuedAtUtc")]
    public required DateTimeOffset IssuedAtUtc { get; init; }
}

public static class PermissionManifestJson
{
    public static string Serialize(LocalPermissionManifest manifest) =>
        JsonSerializer.Serialize(manifest, SyncServiceJson.Options);
}

public sealed class SignedPermissionManifestValidator : IDisposable
{
    private static readonly HashSet<string> AllowedPermissions =
    [
        LocalPermissions.Read,
        LocalPermissions.Search,
        LocalPermissions.Save,
        LocalPermissions.Query,
        LocalPermissions.Backup,
        LocalPermissions.Restore,
        LocalPermissions.ManageSettings,
        LocalPermissions.ResolveConflicts,
        LocalPermissions.Synchronize,
        LocalPermissions.WriteAudit
    ];

    private readonly ECDsa _authority = ECDsa.Create();
    private readonly TimeProvider _timeProvider;

    public SignedPermissionManifestValidator(byte[] authorityPublicKey, TimeProvider? timeProvider = null)
    {
        ArgumentNullException.ThrowIfNull(authorityPublicKey);
        _authority.ImportSubjectPublicKeyInfo(authorityPublicKey, out _);
        _timeProvider = timeProvider ?? TimeProvider.System;
    }

    public LocalPermissionManifest? Verify(LocalUser user)
    {
        ArgumentNullException.ThrowIfNull(user);
        try
        {
            byte[] manifestBytes = Encoding.UTF8.GetBytes(user.PermissionManifestJson);
            if (!_authority.VerifyData(manifestBytes, user.ManifestSignature, HashAlgorithmName.SHA256))
            {
                return null;
            }

            LocalPermissionManifest manifest = JsonSerializer.Deserialize<LocalPermissionManifest>(
                manifestBytes,
                SyncServiceJson.Options) ?? throw new JsonException("The permission manifest is empty.");
            if (manifest.UserId != user.Id ||
                manifest.ManifestVersion <= 0 ||
                manifest.ManifestVersion != user.ManifestVersion ||
                manifest.IssuedAtUtc != user.ManifestIssuedAtUtc ||
                manifest.IssuedAtUtc > _timeProvider.GetUtcNow().AddMinutes(5) ||
                string.IsNullOrWhiteSpace(manifest.DeviceId) ||
                !IsSourceId(manifest.SourceId) ||
                manifest.Permissions.Count == 0 ||
                manifest.Permissions.Distinct(StringComparer.Ordinal).Count() != manifest.Permissions.Count ||
                manifest.Permissions.Any(permission => !AllowedPermissions.Contains(permission)))
            {
                return null;
            }

            return manifest;
        }
        catch (Exception exception) when (exception is JsonException or CryptographicException or ArgumentException)
        {
            return null;
        }
    }

    public void Dispose() => _authority.Dispose();

    private static bool IsSourceId(string sourceId)
    {
        try
        {
            _ = new SyncSourceId(sourceId);
            return true;
        }
        catch (ArgumentException)
        {
            return false;
        }
    }
}

public sealed record AuthenticatedSession(
    LocalUser User,
    LocalSessionEntity Session,
    LocalPermissionManifest Manifest)
{
    public bool HasPermission(string permission) => Manifest.Permissions.Contains(permission, StringComparer.Ordinal);
}

public sealed class LocalAuthenticationService
{
    private static readonly TimeSpan SessionLifetime = TimeSpan.FromHours(12);
    private readonly ISyncServiceStore _store;
    private readonly Argon2PasswordHasher _passwordHasher;
    private readonly SignedPermissionManifestValidator _manifestValidator;
    private readonly TimeProvider _timeProvider;

    public LocalAuthenticationService(
        ISyncServiceStore store,
        Argon2PasswordHasher passwordHasher,
        SignedPermissionManifestValidator manifestValidator,
        TimeProvider timeProvider)
    {
        _store = store ?? throw new ArgumentNullException(nameof(store));
        _passwordHasher = passwordHasher ?? throw new ArgumentNullException(nameof(passwordHasher));
        _manifestValidator = manifestValidator ?? throw new ArgumentNullException(nameof(manifestValidator));
        _timeProvider = timeProvider ?? throw new ArgumentNullException(nameof(timeProvider));
    }

    public async Task<LocalSessionContract> LoginAsync(
        LoginRequest request,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(request);
        ArgumentException.ThrowIfNullOrWhiteSpace(request.UserName);
        ArgumentException.ThrowIfNullOrWhiteSpace(request.DeviceId);
        SyncSourceId sourceId = new(request.SourceId);
        string normalizedUserName = request.UserName.Trim().ToUpperInvariant();
        await using LocalStoreDbContext context = _store.CreateContext();
        LocalUser? user = await context.LocalUsers.SingleOrDefaultAsync(
            item => item.UserNameNormalized == normalizedUserName,
            cancellationToken).ConfigureAwait(false);
        if (user is null || user.DisabledAtUtc is not null || !_passwordHasher.Verify(request.Password, user))
        {
            throw new SyncServiceException("unauthorized");
        }

        LocalPermissionManifest manifest = _manifestValidator.Verify(user)
            ?? throw new SyncServiceException("unauthorized");
        if (!string.Equals(manifest.DeviceId, request.DeviceId, StringComparison.Ordinal) ||
            !string.Equals(manifest.SourceId, sourceId.Value, StringComparison.Ordinal))
        {
            throw new SyncServiceException("unauthorized");
        }

        DateTimeOffset now = _timeProvider.GetUtcNow();
        byte[] proof = RandomNumberGenerator.GetBytes(32);
        byte[] proofHash = SHA256.HashData(proof);
        LocalSessionEntity session = new()
        {
            Id = Guid.NewGuid(),
            UserId = user.Id,
            DeviceId = request.DeviceId,
            SessionProofHash = proofHash,
            ManifestVersion = manifest.ManifestVersion,
            CreatedAtUtc = now,
            LastSeenAtUtc = now,
            ExpiresAtUtc = now.Add(SessionLifetime)
        };
        context.LocalSessions.Add(session);
        await context.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
        try
        {
            return new LocalSessionContract
            {
                SessionId = session.Id,
                SessionProof = Convert.ToBase64String(proof),
                UserId = user.Id,
                DeviceId = session.DeviceId,
                SourceId = manifest.SourceId,
                Permissions = manifest.Permissions,
                ManifestVersion = manifest.ManifestVersion,
                ManifestIssuedAtUtc = manifest.IssuedAtUtc,
                ExpiresAtUtc = session.ExpiresAtUtc
            };
        }
        finally
        {
            CryptographicOperations.ZeroMemory(proof);
            CryptographicOperations.ZeroMemory(proofHash);
        }
    }

    public async Task<AuthenticatedSession> AuthenticateAsync(
        SyncServiceRequest request,
        CancellationToken cancellationToken)
    {
        if (request.SessionId is null || request.SessionId == Guid.Empty || string.IsNullOrWhiteSpace(request.SessionProof))
        {
            throw new SyncServiceException("unauthorized");
        }

        byte[] proof;
        try
        {
            proof = Convert.FromBase64String(request.SessionProof);
        }
        catch (FormatException)
        {
            throw new SyncServiceException("unauthorized");
        }

        try
        {
            if (proof.Length != 32)
            {
                throw new SyncServiceException("unauthorized");
            }

            await using LocalStoreDbContext context = _store.CreateContext();
            LocalSessionEntity? session = await context.LocalSessions
                .Include(item => item.User)
                .SingleOrDefaultAsync(item => item.Id == request.SessionId.Value, cancellationToken)
                .ConfigureAwait(false);
            DateTimeOffset now = _timeProvider.GetUtcNow();
            if (session?.User is null ||
                session.RevokedAtUtc is not null ||
                session.ExpiresAtUtc <= now ||
                session.User.DisabledAtUtc is not null)
            {
                throw new SyncServiceException("unauthorized");
            }

            byte[] proofHash = SHA256.HashData(proof);
            bool proofMatches = CryptographicOperations.FixedTimeEquals(proofHash, session.SessionProofHash);
            CryptographicOperations.ZeroMemory(proofHash);
            if (!proofMatches || session.ManifestVersion != session.User.ManifestVersion)
            {
                throw new SyncServiceException("unauthorized");
            }

            LocalPermissionManifest manifest = _manifestValidator.Verify(session.User)
                ?? throw new SyncServiceException("unauthorized");
            if (!string.Equals(session.DeviceId, manifest.DeviceId, StringComparison.Ordinal))
            {
                throw new SyncServiceException("unauthorized");
            }

            session.LastSeenAtUtc = now;
            await context.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
            return new AuthenticatedSession(session.User, session, manifest);
        }
        finally
        {
            CryptographicOperations.ZeroMemory(proof);
        }
    }

    public async Task<AuthenticatedSession> AuthorizeAsync(
        SyncServiceRequest request,
        string permission,
        CancellationToken cancellationToken)
    {
        AuthenticatedSession session = await AuthenticateAsync(request, cancellationToken).ConfigureAwait(false);
        if (!session.HasPermission(permission))
        {
            throw new SyncServiceException("permission_denied");
        }

        return session;
    }

    public async Task LogoutAsync(SyncServiceRequest request, CancellationToken cancellationToken)
    {
        AuthenticatedSession authenticated = await AuthenticateAsync(request, cancellationToken).ConfigureAwait(false);
        await using LocalStoreDbContext context = _store.CreateContext();
        LocalSessionEntity session = await context.LocalSessions.SingleAsync(
            item => item.Id == authenticated.Session.Id,
            cancellationToken).ConfigureAwait(false);
        session.RevokedAtUtc = _timeProvider.GetUtcNow();
        await context.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
    }
}

public interface IAdministratorBootstrapService
{
    Task<LocalSessionContract> BootstrapAsync(
        BootstrapAdministratorRequest request,
        CancellationToken cancellationToken);
}

public sealed class UnavailableAdministratorBootstrapService : IAdministratorBootstrapService
{
    public Task<LocalSessionContract> BootstrapAsync(
        BootstrapAdministratorRequest request,
        CancellationToken cancellationToken) =>
        Task.FromException<LocalSessionContract>(new SyncServiceException("bootstrap_unavailable"));
}
