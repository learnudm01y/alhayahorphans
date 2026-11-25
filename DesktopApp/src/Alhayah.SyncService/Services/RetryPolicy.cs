namespace Alhayah.SyncService.Services;

public sealed class RetryOptions
{
    public TimeSpan InitialDelay { get; init; } = TimeSpan.FromSeconds(1);

    public TimeSpan MaximumDelay { get; init; } = TimeSpan.FromSeconds(30);
}

public sealed class RetryPolicy
{
    private readonly RetryOptions _options;
    private readonly Func<double> _jitterSample;

    public RetryPolicy(RetryOptions? options = null, Func<double>? jitterSample = null)
    {
        _options = options ?? new RetryOptions();
        if (_options.InitialDelay <= TimeSpan.Zero)
        {
            throw new ArgumentOutOfRangeException(nameof(options), "The initial retry delay must be positive.");
        }

        if (_options.MaximumDelay < _options.InitialDelay)
        {
            throw new ArgumentOutOfRangeException(nameof(options), "The maximum retry delay must not be shorter than the initial delay.");
        }

        _jitterSample = jitterSample ?? (() => Random.Shared.NextDouble());
    }

    public TimeSpan GetDelay(int attemptCount)
    {
        if (attemptCount < 1)
        {
            throw new ArgumentOutOfRangeException(nameof(attemptCount));
        }

        double exponentialDelay = _options.InitialDelay.TotalMilliseconds *
            Math.Pow(2, Math.Min(attemptCount - 1, 30));
        double boundedDelay = Math.Min(exponentialDelay, _options.MaximumDelay.TotalMilliseconds);
        double sample = _jitterSample();
        if (double.IsNaN(sample) || sample < 0 || sample >= 1)
        {
            throw new InvalidOperationException("The retry jitter sample must be in the interval [0, 1).");
        }

        return TimeSpan.FromMilliseconds(boundedDelay * (0.5 + sample));
    }
}
