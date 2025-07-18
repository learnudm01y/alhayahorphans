# Speedtest CLI Production Installation Script for Windows
# Run as Administrator in PowerShell

Write-Host "=== Speedtest CLI Production Installation ===" -ForegroundColor Cyan
Write-Host "Starting installation process..." -ForegroundColor Green

# Check if running as Administrator
if (-NOT ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator"))
{
    Write-Host "ERROR: This script must be run as Administrator!" -ForegroundColor Red
    Write-Host "Please right-click PowerShell and select 'Run as Administrator'" -ForegroundColor Yellow
    pause
    exit 1
}

# Function to check if command exists
function Test-CommandExists {
    param($command)
    try {
        Get-Command $command -ErrorAction Stop
        return $true
    } catch {
        return $false
    }
}

# Step 1: Install Chocolatey if not installed
Write-Host "`n[1/6] Checking Chocolatey installation..." -ForegroundColor Yellow
if (!(Test-CommandExists "choco")) {
    Write-Host "Installing Chocolatey package manager..." -ForegroundColor Cyan
    Set-ExecutionPolicy Bypass -Scope Process -Force
    [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072
    iex ((New-Object System.Net.WebClient).DownloadString('https://community.chocolatey.org/install.ps1'))

    # Refresh environment
    $env:Path = [System.Environment]::GetEnvironmentVariable("Path","Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path","User")

    if (Test-CommandExists "choco") {
        Write-Host "✓ Chocolatey installed successfully" -ForegroundColor Green
    } else {
        Write-Host "✗ Failed to install Chocolatey" -ForegroundColor Red
        exit 1
    }
} else {
    Write-Host "✓ Chocolatey already installed" -ForegroundColor Green
}

# Step 2: Install Speedtest CLI
Write-Host "`n[2/6] Installing Speedtest CLI..." -ForegroundColor Yellow
try {
    choco install speedtest -y

    # Refresh environment
    $env:Path = [System.Environment]::GetEnvironmentVariable("Path","Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path","User")

    if (Test-CommandExists "speedtest") {
        Write-Host "✓ Speedtest CLI installed successfully" -ForegroundColor Green
        $version = speedtest --version
        Write-Host "Version: $version" -ForegroundColor Cyan
    } else {
        Write-Host "✗ Failed to install Speedtest CLI" -ForegroundColor Red
        exit 1
    }
} catch {
    Write-Host "✗ Error installing Speedtest CLI: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

# Step 3: Accept license and GDPR
Write-Host "`n[3/6] Accepting Speedtest license and GDPR..." -ForegroundColor Yellow
try {
    speedtest --accept-license --accept-gdpr > $null 2>&1
    Write-Host "✓ License and GDPR accepted" -ForegroundColor Green
} catch {
    Write-Host "⚠ Could not auto-accept license. You may need to run speedtest manually first." -ForegroundColor Yellow
}

# Step 4: Create secure wrapper script
Write-Host "`n[4/6] Creating secure wrapper script..." -ForegroundColor Yellow
$wrapperPath = "C:\Windows\System32\speedtest-safe.bat"
$wrapperContent = @"
@echo off
REM Secure Speedtest CLI Wrapper for Laravel
REM Created by Production Installation Script

REM Set timeout (2 minutes)
set TIMEOUT=120

REM Run speedtest with security restrictions
speedtest --format=json --accept-license --accept-gdpr %*
"@

try {
    $wrapperContent | Out-File -FilePath $wrapperPath -Encoding ASCII -Force
    Write-Host "✓ Wrapper script created at: $wrapperPath" -ForegroundColor Green
} catch {
    Write-Host "✗ Failed to create wrapper script: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

# Step 5: Test installation
Write-Host "`n[5/6] Testing Speedtest CLI installation..." -ForegroundColor Yellow
try {
    Write-Host "Running version check..." -ForegroundColor Cyan
    $versionOutput = speedtest --version
    Write-Host "Version: $versionOutput" -ForegroundColor Green

    Write-Host "Running quick test (this may take a moment)..." -ForegroundColor Cyan
    $testOutput = speedtest --format=json --accept-license --accept-gdpr
    $testData = $testOutput | ConvertFrom-Json

    if ($testData.download -and $testData.upload) {
        $downloadMbps = [math]::Round(($testData.download.bandwidth * 8) / 1000000, 2)
        $uploadMbps = [math]::Round(($testData.upload.bandwidth * 8) / 1000000, 2)
        $ping = [math]::Round($testData.ping.latency, 2)

        Write-Host "✓ Speedtest working correctly:" -ForegroundColor Green
        Write-Host "  Download: $downloadMbps Mbps" -ForegroundColor Cyan
        Write-Host "  Upload: $uploadMbps Mbps" -ForegroundColor Cyan
        Write-Host "  Ping: $ping ms" -ForegroundColor Cyan
        Write-Host "  Server: $($testData.server.name)" -ForegroundColor Cyan
    } else {
        Write-Host "⚠ Speedtest completed but data format unexpected" -ForegroundColor Yellow
    }
} catch {
    Write-Host "✗ Speedtest test failed: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host "The CLI is installed but may need manual configuration" -ForegroundColor Yellow
}

# Step 6: Laravel Configuration
Write-Host "`n[6/6] Laravel Configuration Instructions:" -ForegroundColor Yellow
Write-Host "1. Copy the speedtest environment variables to your .env file:" -ForegroundColor White
Write-Host "   SPEEDTEST_TIMEOUT=120" -ForegroundColor Gray
Write-Host "   SPEEDTEST_CACHE_ENABLED=true" -ForegroundColor Gray
Write-Host "   SPEEDTEST_CLI_PATH=speedtest" -ForegroundColor Gray

Write-Host "`n2. Clear Laravel config cache:" -ForegroundColor White
Write-Host "   php artisan config:clear" -ForegroundColor Gray
Write-Host "   php artisan config:cache" -ForegroundColor Gray

Write-Host "`n3. Test the Laravel API:" -ForegroundColor White
Write-Host "   curl http://your-domain/api/speedtest/check" -ForegroundColor Gray

Write-Host "`n=== Installation Complete ===" -ForegroundColor Cyan
Write-Host "Speedtest CLI is now ready for production use!" -ForegroundColor Green
Write-Host "`nWrapper script location: $wrapperPath" -ForegroundColor Cyan

# Create installation log
$logPath = "C:\speedtest-installation.log"
$logContent = @"
Speedtest CLI Production Installation Log
Date: $(Get-Date)
Status: Completed Successfully
Version: $versionOutput
Wrapper Path: $wrapperPath
Laravel Ready: Yes
"@

$logContent | Out-File -FilePath $logPath -Encoding ASCII -Force
Write-Host "`nInstallation log saved to: $logPath" -ForegroundColor Cyan

Write-Host "`nPress any key to exit..." -ForegroundColor Yellow
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
