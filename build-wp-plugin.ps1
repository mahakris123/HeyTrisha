# Hey Trisha WordPress Plugin Builder - PowerShell Script
# Creates WordPress plugin ZIP file (WordPress.org ready)
# ONLY includes WordPress plugin files - NO Laravel API

$ErrorActionPreference = "Stop"

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Hey Trisha - WordPress Plugin Builder" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Building: WordPress Plugin (Thin Client Only)" -ForegroundColor Yellow
Write-Host ""

# Get timestamp
$timestamp = Get-Date -Format "yyyyMMdd-HHmm"
$releaseName = "heytrisha-woo-plugin-v1.0-$timestamp"
$scriptDir = $PSScriptRoot
$releaseDir = Join-Path $scriptDir "releases"
$tempDir = Join-Path $scriptDir "temp_build_wp_plugin"
$targetDir = Join-Path $tempDir "heytrisha-woo"

Write-Host "Build Name: $releaseName" -ForegroundColor Yellow
Write-Host ""

# Create directories
Write-Host "Creating build directories..." -ForegroundColor Green
if (Test-Path $tempDir) {
    Remove-Item $tempDir -Recurse -Force
}
if (-not (Test-Path $releaseDir)) {
    New-Item -ItemType Directory -Path $releaseDir | Out-Null
}
New-Item -ItemType Directory -Path $targetDir -Force | Out-Null

Write-Host ""
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Copying WordPress Plugin Files..." -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

# Copy main plugin files
Write-Host "[1/5] Copying main plugin files..." -ForegroundColor Yellow
Copy-Item (Join-Path $scriptDir "heytrisha-woo.php") $targetDir -Force
Copy-Item (Join-Path $scriptDir "readme.txt") $targetDir -Force -ErrorAction SilentlyContinue
Copy-Item (Join-Path $scriptDir "LICENSE") $targetDir -Force -ErrorAction SilentlyContinue
Write-Host "    [OK] Main files copied" -ForegroundColor Green

# Copy includes (only WordPress-related classes)
Write-Host "[2/5] Copying includes (WordPress only)..." -ForegroundColor Yellow
$includesTarget = Join-Path $targetDir "includes"
New-Item -ItemType Directory -Path $includesTarget -Force | Out-Null

# Only copy WordPress plugin includes (NO Laravel-related files)
$wpIncludes = @(
    "class-heytrisha-database.php",
    "class-heytrisha-secure-credentials.php"
)

foreach ($include in $wpIncludes) {
    $sourceFile = Join-Path $scriptDir "includes\$include"
    if (Test-Path $sourceFile) {
        Copy-Item $sourceFile $includesTarget -Force
        Write-Host "    [OK] Copied $include" -ForegroundColor Green
    } else {
        Write-Host "    [WARN] Missing: $include" -ForegroundColor Yellow
    }
}

# Copy assets (CSS, JS, images)
Write-Host "[3/5] Copying assets..." -ForegroundColor Yellow
$assetsDir = Join-Path $targetDir "assets"
New-Item -ItemType Directory -Path $assetsDir -Force | Out-Null

# Copy CSS
if (Test-Path (Join-Path $scriptDir "assets\css")) {
    Copy-Item (Join-Path $scriptDir "assets\css") $assetsDir -Recurse -Force
    Write-Host "    [OK] CSS files copied" -ForegroundColor Green
}

# Copy JS (only WordPress plugin JS files)
$jsTarget = Join-Path $assetsDir "js"
New-Item -ItemType Directory -Path $jsTarget -Force | Out-Null
$jsFiles = @(
    "chatbot.js",
    "chat-admin.js",
    "chats-list.js"
)
foreach ($jsFile in $jsFiles) {
    $sourceJs = Join-Path $scriptDir "assets\js\$jsFile"
    if (Test-Path $sourceJs) {
        Copy-Item $sourceJs $jsTarget -Force
        Write-Host "    [OK] Copied $jsFile" -ForegroundColor Green
    }
}

# Copy images
if (Test-Path (Join-Path $scriptDir "assets\img")) {
    Copy-Item (Join-Path $scriptDir "assets\img") $assetsDir -Recurse -Force
    Write-Host "    [OK] Images copied" -ForegroundColor Green
}

# Skip chatbot UI - contains files with ~ characters not allowed by WordPress.org
# The plugin uses React from CDN, so chatbot build files are not needed
Write-Host "[4/5] Skipping chatbot UI (contains invalid file names)..." -ForegroundColor Yellow
Write-Host "    [SKIP] Chatbot UI excluded (WordPress.org compliance)" -ForegroundColor Yellow

# Copy languages (if exists)
Write-Host "[5/5] Copying language files..." -ForegroundColor Yellow
if (Test-Path (Join-Path $scriptDir "languages")) {
    Copy-Item (Join-Path $scriptDir "languages") $targetDir -Recurse -Force
    Write-Host "    [OK] Language files copied" -ForegroundColor Green
} else {
    Write-Host "    [SKIP] Language files not found" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "[OK] All WordPress plugin files copied successfully" -ForegroundColor Green
Write-Host ""

# Verify exclusions
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Verifying Exclusions..." -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

$excludedDirs = @("api", "vendor", "node_modules", "doc", "releases", ".git")
$foundExclusions = $false

foreach ($excluded in $excludedDirs) {
    $excludedPath = Join-Path $targetDir $excluded
    if (Test-Path $excludedPath) {
        Write-Host "    [ERROR] Found excluded directory: $excluded" -ForegroundColor Red
        $foundExclusions = $true
    }
}

if (-not $foundExclusions) {
    Write-Host "    [OK] No excluded directories found" -ForegroundColor Green
}

Write-Host ""

# Create ZIP
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Creating ZIP File..." -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

$zipPath = Join-Path $releaseDir "$releaseName.zip"
if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

Add-Type -Assembly 'System.IO.Compression.FileSystem'
$zip = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')
$files = Get-ChildItem -Path $targetDir -Recurse -File
$total = $files.Count
$current = 0

$files | ForEach-Object {
    $current++
    $relativePath = $_.FullName.Substring((Resolve-Path $targetDir).Path.Length + 1)
    $relativePath = $relativePath.Replace('\', '/')
    
    # Skip files with ~ or spaces in name (WordPress.org doesn't allow)
    if ($relativePath -match '~' -or $relativePath -match '\s') {
        return
    }
    
    if ($current % 50 -eq 0) {
        $percent = [math]::Round(($current / $total) * 100)
        Write-Progress -Activity 'Creating ZIP' -Status "File $current of $total" -PercentComplete $percent
    }
    
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $relativePath, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
}

Write-Progress -Activity 'Creating ZIP' -Completed
$zip.Dispose()

# Get final size
$zipSize = [math]::Round((Get-Item $zipPath).Length / 1MB, 2)

# Cleanup
Remove-Item $tempDir -Recurse -Force

Write-Host ""
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "WordPress Plugin Build Complete!" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Release: $releaseName.zip" -ForegroundColor Yellow
Write-Host "Location: $releaseDir" -ForegroundColor Yellow
Write-Host "Size: $zipSize MB" -ForegroundColor Yellow
Write-Host ""
Write-Host "[OK] WordPress plugin ZIP created successfully!" -ForegroundColor Green
Write-Host ""
Write-Host "This build contains ONLY WordPress plugin files (thin client)." -ForegroundColor Cyan
Write-Host "For API server build, run: .\build-api-server.ps1" -ForegroundColor Cyan
Write-Host ""

