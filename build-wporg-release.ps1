param(
    [string]$OutputDir = "releases",
    [switch]$SkipAdminBuild,
    [string]$PackageName,
    [switch]$SkipZipValidation,
    [int]$KeepLatest = 5,
    [switch]$PruneReports,
    [switch]$DryRun,
    [switch]$EmitJson,
    [string]$JsonPath
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

trap {
    $message = $_.Exception.Message
    $position = $_.InvocationInfo.PositionMessage
    $stack = $_.ScriptStackTrace
    Write-Host "[ERROR] $message" -ForegroundColor Red
    if ($position) {
        Write-Host "[ERROR] $position" -ForegroundColor Red
    }
    if ($stack) {
        Write-Host "[ERROR] Stack: $stack" -ForegroundColor DarkRed
    }
    exit 1
}

$root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $root
$rootNormalized = (Resolve-Path $root).Path.TrimEnd('\\') + '\'

function Write-Info([string]$Message) {
    Write-Host "[INFO] $Message" -ForegroundColor Cyan
}

function Write-Success([string]$Message) {
    Write-Host "[OK]   $Message" -ForegroundColor Green
}

function Write-JsonResult {
    param(
        [hashtable]$Result,
        [string]$Path
    )

    $targetPath = $Path
    if ([string]::IsNullOrWhiteSpace($targetPath)) {
        throw 'JSON output path is empty.'
    }

    $targetDir = Split-Path -Parent $targetPath
    if (-not [string]::IsNullOrWhiteSpace($targetDir) -and -not (Test-Path $targetDir)) {
        New-Item -ItemType Directory -Path $targetDir -Force | Out-Null
    }

    $json = $Result | ConvertTo-Json -Depth 8
    Set-Content -Path $targetPath -Value $json -Encoding UTF8
    Write-Info ("JSON report: {0}" -f $targetPath)
    Write-Output ("RELEASE_JSON={0}" -f $targetPath)
}

function Prune-ReleaseArtifacts {
    param(
        [string]$Directory,
        [int]$Keep,
        [switch]$RemoveReports,
        [switch]$DryRun
    )

    $keepCount = [Math]::Max(1, $Keep)

    $zipFiles = Get-ChildItem -Path $Directory -Filter 'mikro-booking-*.zip' -File -ErrorAction SilentlyContinue |
        Sort-Object LastWriteTime -Descending

    if ($zipFiles.Count -le $keepCount) {
        return
    }

    $toDelete = @($zipFiles | Select-Object -Skip $keepCount)
    foreach ($zip in $toDelete) {
        if ($DryRun) {
            Write-Info ("[DryRun] Would prune old package: {0}" -f $zip.Name)
        } else {
            Remove-Item -Path $zip.FullName -Force -ErrorAction SilentlyContinue
            Write-Info ("Pruned old package: {0}" -f $zip.Name)
        }

        if ($RemoveReports) {
            $reportPath = [System.IO.Path]::ChangeExtension($zip.FullName, '.report.txt')
            if (Test-Path $reportPath) {
                if ($DryRun) {
                    Write-Info ("[DryRun] Would prune old report: {0}" -f ([System.IO.Path]::GetFileName($reportPath)))
                } else {
                    Remove-Item -Path $reportPath -Force -ErrorAction SilentlyContinue
                    Write-Info ("Pruned old report: {0}" -f ([System.IO.Path]::GetFileName($reportPath)))
                }
            }
        }
    }
}

Write-Info ("Workspace root: {0}" -f $root)

function Get-PluginVersion {
    $mainFile = Join-Path $root 'mikroplaneta-booking.php'
    if (-not (Test-Path $mainFile)) {
        throw "Cannot find plugin main file: $mainFile"
    }

    $content = Get-Content $mainFile -Raw
    $match = [regex]::Match($content, 'Version:\s*([0-9]+(?:\.[0-9]+){1,3})', [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)
    if (-not $match.Success) {
        throw "Cannot parse plugin version from mikroplaneta-booking.php"
    }

    return $match.Groups[1].Value
}

function Get-DistIgnorePatterns {
    $distIgnorePath = Join-Path $root '.distignore'
    if (-not (Test-Path $distIgnorePath)) {
        return @()
    }

    $lines = Get-Content $distIgnorePath
    $patterns = @()
    foreach ($line in $lines) {
        $trimmed = $line.Trim()
        if ([string]::IsNullOrWhiteSpace($trimmed)) { continue }
        if ($trimmed.StartsWith('#')) { continue }
        $patterns += $trimmed.Replace('\\', '/')
    }

    return $patterns
}

function Test-PathExcluded {
    param(
        [string]$RelativePath,
        [string[]]$Patterns
    )

    $normalizedPath = $RelativePath.Replace('\\', '/')
    $fileName = [System.IO.Path]::GetFileName($normalizedPath)

    foreach ($pattern in $Patterns) {
        $normalizedPattern = $pattern.Replace('\\', '/')

        if ($normalizedPattern.EndsWith('/')) {
            $dirPattern = $normalizedPattern.TrimEnd('/')
            if ($normalizedPath.StartsWith($dirPattern + '/', [System.StringComparison]::OrdinalIgnoreCase)) {
                return $true
            }
            continue
        }

        if ([System.Management.Automation.WildcardPattern]::new($normalizedPattern, [System.Management.Automation.WildcardOptions]::IgnoreCase).IsMatch($normalizedPath)) {
            return $true
        }

        if ([System.Management.Automation.WildcardPattern]::new($normalizedPattern, [System.Management.Automation.WildcardOptions]::IgnoreCase).IsMatch($fileName)) {
            return $true
        }
    }

    return $false
}

function Test-ZipPathMatch {
    param(
        [string]$EntryPath,
        [string]$Pattern
    )

        $normalizedEntry = $EntryPath.Replace('\', '/')
        $normalizedPattern = $Pattern.Replace('\', '/')

    if ($normalizedPattern.EndsWith('/')) {
        $prefix = $normalizedPattern
        return $normalizedEntry.StartsWith($prefix, [System.StringComparison]::OrdinalIgnoreCase)
    }

    return [System.Management.Automation.WildcardPattern]::new(
        $normalizedPattern,
        [System.Management.Automation.WildcardOptions]::IgnoreCase
    ).IsMatch($normalizedEntry)
}

function Validate-ReleaseArchive {
    param([string]$ZipPath)

    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $archive = [System.IO.Compression.ZipFile]::OpenRead($ZipPath)

    try {
            $entries = @($archive.Entries | ForEach-Object { $_.FullName.Replace('\', '/') })

        $required = @(
            'mikro-booking/mikroplaneta-booking.php',
            'mikro-booking/readme.txt',
            'mikro-booking/assets/admin/index.js',
            'mikro-booking/core/class-plugin.php'
        )

        $forbidden = @(
            'mikro-booking/tests/',
            'mikro-booking/docs/',
            'mikro-booking/admin/src/',
            'mikro-booking/admin/node_modules/',
            'mikro-booking/vendor/',
            'mikro-booking/*.md',
            'mikro-booking/force-update.php',
            'mikro-booking/force-repair-db.php',
            'mikro-booking/optimize-dashboard.php'
        )

        $missingRequired = @()
        foreach ($req in $required) {
            if (-not ($entries -contains $req)) {
                $missingRequired += $req
            }
        }

        $violations = @()
        foreach ($pattern in $forbidden) {
            $matches = @($entries | Where-Object { Test-ZipPathMatch -EntryPath $_ -Pattern $pattern })
            foreach ($m in $matches) {
                $violations += [PSCustomObject]@{
                    Pattern = $pattern
                    Entry   = $m
                }
            }
        }

        $reportPath = [System.IO.Path]::ChangeExtension($ZipPath, '.report.txt')
        $report = @()
        $report += "Release archive: $ZipPath"
        $report += "Created at: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
        $report += "Total entries: $($entries.Count)"
        $report += ""

        if ($missingRequired.Count -gt 0) {
            $report += "Missing required entries:"
            $report += ($missingRequired | ForEach-Object { " - $_" })
            $report += ""
        } else {
            $report += "Missing required entries: none"
            $report += ""
        }

        if ($violations.Count -gt 0) {
            $report += "Forbidden entries detected:"
            $report += ($violations | ForEach-Object { " - [$($_.Pattern)] $($_.Entry)" })
            $report += ""
        } else {
            $report += "Forbidden entries detected: none"
            $report += ""
        }

        $report | Set-Content -Path $reportPath -Encoding UTF8
        Write-Info ("Validation report: {0}" -f $reportPath)

        if ($missingRequired.Count -gt 0) {
            throw ("Archive validation failed. Missing required entries: {0}" -f ($missingRequired -join ', '))
        }

        if ($violations.Count -gt 0) {
            throw ("Archive validation failed. Forbidden entries found: {0}" -f $violations.Count)
        }

        Write-Success 'Archive validation passed'
        return $reportPath
    }
    finally {
        $archive.Dispose()
    }
}

if (-not $SkipAdminBuild) {
    $adminDir = Join-Path $root 'admin'
    if (Test-Path (Join-Path $adminDir 'package.json')) {
        if ($DryRun) {
            Write-Info '[DryRun] Would build admin assets (npm run -s build)'
        } else {
            Write-Info 'Building admin assets (npm run -s build)...'
            Push-Location $adminDir
            try {
                npm run -s build
            } finally {
                Pop-Location
            }
            Write-Success 'Admin assets built'
        }
    }
}

$version = Get-PluginVersion
$dateStamp = Get-Date -Format 'yyyyMMdd-HHmmss'
if ([string]::IsNullOrWhiteSpace($PackageName)) {
    $PackageName = "mikro-booking-$version-$dateStamp.zip"
}

$patterns = Get-DistIgnorePatterns
Write-Info ("Loaded {0} .distignore patterns" -f $patterns.Count)

$outputPath = Join-Path $root $OutputDir
if (-not (Test-Path $outputPath)) {
    if ($DryRun) {
        Write-Info ("[DryRun] Would create output directory: {0}" -f $outputPath)
    } else {
        New-Item -ItemType Directory -Path $outputPath | Out-Null
    }
}

if ($DryRun) {
    $zipPathDry = Join-Path $outputPath $PackageName
    Write-Info ("[DryRun] Target archive path: {0}" -f $zipPathDry)
    Write-Info ("[DryRun] SkipZipValidation: {0}" -f [bool]$SkipZipValidation)
    Write-Info ("[DryRun] KeepLatest: {0} (PruneReports: {1})" -f $KeepLatest, [bool]$PruneReports)

    Prune-ReleaseArtifacts -Directory $outputPath -Keep $KeepLatest -RemoveReports:$PruneReports -DryRun

    if ($EmitJson) {
        $jsonOutputPath = $JsonPath
        if ([string]::IsNullOrWhiteSpace($jsonOutputPath)) {
            $jsonOutputPath = [System.IO.Path]::ChangeExtension($zipPathDry, '.json')
        }

        Write-JsonResult -Result @{
            marker = 'RELEASE_DRYRUN_OK'
            status = 'dryrun_ok'
            timestamp = (Get-Date -Format 'o')
            root = $root
            outputDir = $outputPath
            packageName = $PackageName
            targetArchivePath = $zipPathDry
            skipZipValidation = [bool]$SkipZipValidation
            keepLatest = $KeepLatest
            pruneReports = [bool]$PruneReports
            skipAdminBuild = [bool]$SkipAdminBuild
            dryRun = $true
        } -Path $jsonOutputPath
    }

    Write-Output "RELEASE_DRYRUN_OK"
    exit 0
}

$tempRoot = Join-Path ([System.IO.Path]::GetTempPath()) ("mikro-booking-release-" + [guid]::NewGuid().ToString('N'))
$tempPluginDir = Join-Path $tempRoot 'mikro-booking'
New-Item -ItemType Directory -Path $tempPluginDir -Force | Out-Null

try {
    Write-Info 'Staging files with robocopy...'

    $excludeDirs = New-Object System.Collections.Generic.List[string]
    $excludeFiles = New-Object System.Collections.Generic.List[string]

    foreach ($pattern in $patterns) {
        $normalizedPattern = $pattern.Replace('\\', '/').Trim()
        if ([string]::IsNullOrWhiteSpace($normalizedPattern)) {
            continue
        }

        if ($normalizedPattern.EndsWith('/')) {
            $dirPattern = $normalizedPattern.TrimEnd('/')
            if ($dirPattern -ne '') {
                $excludeDirs.Add((Join-Path $root $dirPattern))
            }
            continue
        }

        $excludeFiles.Add($normalizedPattern)
    }

    $robocopyArgs = @(
        $root,
        $tempPluginDir,
        '/E',
        '/R:1',
        '/W:1',
        '/NFL',
        '/NDL',
        '/NJH',
        '/NJS',
        '/NP'
    )

    if ($excludeDirs.Count -gt 0) {
        $robocopyArgs += '/XD'
        $robocopyArgs += $excludeDirs.ToArray()
    }

    if ($excludeFiles.Count -gt 0) {
        $robocopyArgs += '/XF'
        $robocopyArgs += $excludeFiles.ToArray()
    }

    & robocopy @robocopyArgs | Out-Null
    $robocopyExit = $LASTEXITCODE
    if ($robocopyExit -ge 8) {
        throw "robocopy failed with exit code $robocopyExit"
    }

    $includedCount = (Get-ChildItem -Path $tempPluginDir -Recurse -File | Measure-Object).Count

    if ($includedCount -le 0) {
        throw 'No files selected for release package. Check .distignore patterns.'
    }

    Write-Info ("Selected files for package: {0}" -f $includedCount)

    $zipPath = Join-Path $outputPath $PackageName
    if (Test-Path $zipPath) {
        Remove-Item $zipPath -Force
    }

    $compressSource = $tempPluginDir
    Compress-Archive -Path $compressSource -DestinationPath $zipPath -Force

    if (-not (Test-Path $zipPath)) {
        throw "Archive was not created: $zipPath"
    }

    $zipInfo = Get-Item $zipPath
    $sizeMb = [math]::Round($zipInfo.Length / 1MB, 2)

    Write-Success ("Release package created: {0}" -f $zipPath)
    Write-Info ("Included files: {0}" -f $includedCount)
    Write-Info ("Package size: {0} MB" -f $sizeMb)

    $validationReportPath = $null
    if (-not $SkipZipValidation) {
        $validationReportPath = Validate-ReleaseArchive -ZipPath $zipPath
    }

    Prune-ReleaseArtifacts -Directory $outputPath -Keep $KeepLatest -RemoveReports:$PruneReports

    if ($EmitJson) {
        $jsonOutputPath = $JsonPath
        if ([string]::IsNullOrWhiteSpace($jsonOutputPath)) {
            $jsonOutputPath = [System.IO.Path]::ChangeExtension($zipPath, '.json')
        }

        Write-JsonResult -Result @{
            marker = 'RELEASE_OK'
            status = 'ok'
            timestamp = (Get-Date -Format 'o')
            root = $root
            outputDir = $outputPath
            packageName = $PackageName
            archivePath = $zipPath
            archiveSizeBytes = [int64]$zipInfo.Length
            archiveSizeMb = $sizeMb
            includedFiles = $includedCount
            validationEnabled = (-not [bool]$SkipZipValidation)
            validationReportPath = $validationReportPath
            keepLatest = $KeepLatest
            pruneReports = [bool]$PruneReports
            skipAdminBuild = [bool]$SkipAdminBuild
            dryRun = $false
        } -Path $jsonOutputPath
    }

    Write-Output "RELEASE_OK"
}
finally {
    if (Test-Path $tempRoot) {
        Remove-Item $tempRoot -Recurse -Force
    }
}
