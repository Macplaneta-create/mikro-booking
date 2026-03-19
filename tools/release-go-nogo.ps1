param(
    [switch]$NonInteractive,
    [string]$OutputDir = "releases"
)

$ErrorActionPreference = 'Stop'

$scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$repoRoot = Resolve-Path (Join-Path $scriptRoot '..')
Set-Location $repoRoot

$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$outputDirPath = Join-Path $repoRoot $OutputDir
if (-not (Test-Path $outputDirPath)) {
    New-Item -ItemType Directory -Path $outputDirPath -Force | Out-Null
}
$reportPath = Join-Path $outputDirPath ("go-nogo-{0}.txt" -f $timestamp)

$results = New-Object System.Collections.Generic.List[object]

function Add-Result {
    param(
        [string]$Type,
        [string]$Check,
        [string]$Status,
        [string]$Details
    )

    $results.Add([PSCustomObject]@{
        Type = $Type
        Check = $Check
        Status = $Status
        Details = $Details
    }) | Out-Null
}

function Write-Section {
    param([string]$Title)
    Write-Host "`n=== $Title ===" -ForegroundColor Cyan
}

function Test-FileExists {
    param(
        [string]$Path,
        [string]$Label
    )

    if (Test-Path $Path) {
        Add-Result -Type 'AUTO' -Check $Label -Status 'PASS' -Details $Path
    } else {
        Add-Result -Type 'AUTO' -Check $Label -Status 'FAIL' -Details ("Missing: {0}" -f $Path)
    }
}

function Read-VersionFromMainFile {
    param([string]$FilePath)
    if (-not (Test-Path $FilePath)) { return $null }

    $content = Get-Content $FilePath -Raw
    $match = [regex]::Match($content, 'Version:\s*([0-9]+(?:\.[0-9]+){1,3})', [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)
    if ($match.Success) {
        return $match.Groups[1].Value
    }

    return $null
}

function Read-StableTagFromReadme {
    param([string]$FilePath)
    if (-not (Test-Path $FilePath)) { return $null }

    $content = Get-Content $FilePath -Raw
    $match = [regex]::Match($content, 'Stable\s*tag:\s*([^\r\n]+)', [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)
    if ($match.Success) {
        return $match.Groups[1].Value.Trim()
    }

    return $null
}

Write-Host "Release GO/NO-GO runbook" -ForegroundColor Green
Write-Host ("Repo: {0}" -f $repoRoot)
Write-Host ("Tryb: {0}" -f ($(if ($NonInteractive) { 'NonInteractive' } else { 'Interactive' })))

Write-Section 'Automatyczne kontrole'

# 1) Git status
$gitStatus = git status --short 2>$null
if ($LASTEXITCODE -ne 0) {
    Add-Result -Type 'AUTO' -Check 'Repozytorium git dostępne' -Status 'FAIL' -Details 'Nie udało się wykonać: git status --short'
} elseif ([string]::IsNullOrWhiteSpace(($gitStatus | Out-String))) {
    Add-Result -Type 'AUTO' -Check 'Czysty git status' -Status 'PASS' -Details 'Brak lokalnych zmian'
} else {
    $details = ($gitStatus | Out-String).Trim()
    Add-Result -Type 'AUTO' -Check 'Czysty git status' -Status 'WARN' -Details ("Są lokalne zmiany:`n{0}" -f $details)
}

# 2) Core release files
Test-FileExists -Path (Join-Path $repoRoot 'mikroplaneta-booking.php') -Label 'Plik główny pluginu istnieje'
Test-FileExists -Path (Join-Path $repoRoot 'readme.txt') -Label 'readme.txt istnieje'
Test-FileExists -Path (Join-Path $repoRoot 'assets/admin/index.js') -Label 'Zbudowany assets/admin/index.js istnieje'

# 3) Version consistency
$mainVersion = Read-VersionFromMainFile -FilePath (Join-Path $repoRoot 'mikroplaneta-booking.php')
$stableTag = Read-StableTagFromReadme -FilePath (Join-Path $repoRoot 'readme.txt')

if ([string]::IsNullOrWhiteSpace($mainVersion) -or [string]::IsNullOrWhiteSpace($stableTag)) {
    Add-Result -Type 'AUTO' -Check 'Spójność wersji (main file vs readme stable tag)' -Status 'FAIL' -Details ("Version={0}, StableTag={1}" -f $mainVersion, $stableTag)
} elseif ($mainVersion -eq $stableTag) {
    Add-Result -Type 'AUTO' -Check 'Spójność wersji (main file vs readme stable tag)' -Status 'PASS' -Details ("Version={0}" -f $mainVersion)
} else {
    Add-Result -Type 'AUTO' -Check 'Spójność wersji (main file vs readme stable tag)' -Status 'FAIL' -Details ("Version={0}, StableTag={1}" -f $mainVersion, $stableTag)
}

# 4) Basic PHP lint of frontend registration
$lintOutput = php -l public/class-frontend.php 2>&1
if ($LASTEXITCODE -eq 0) {
    Add-Result -Type 'AUTO' -Check 'PHP lint: public/class-frontend.php' -Status 'PASS' -Details (($lintOutput | Out-String).Trim())
} else {
    Add-Result -Type 'AUTO' -Check 'PHP lint: public/class-frontend.php' -Status 'FAIL' -Details (($lintOutput | Out-String).Trim())
}

$manualChecks = @(
    'Frontend: mikroplaneta_availability_calendar laduje siatke miesieczna bez bledu',
    'Frontend: Rezerwuj z kalendarza otwiera formularz i pozwala zapisac testowa rezerwacje',
    'Email: potwierdzenie dochodzi i zawiera spojna tresc PL + .ics',
    'Admin: Testuj przypomnienia (Cron) dziala i nie tworzy duplikatow przy 2 uruchomieniu',
    'Ustawienia: przyciski Kopiuj dzialaja (min. shortcode glowny i kalendarz)',
    'Debug log: brak nowych bledow krytycznych po smoke tescie'
)

Write-Section 'Kontrole manualne (GO/NO-GO)'

if ($NonInteractive) {
    foreach ($check in $manualChecks) {
        Add-Result -Type 'MANUAL' -Check $check -Status 'SKIP' -Details 'Pominieto w trybie NonInteractive'
    }
} else {
    foreach ($check in $manualChecks) {
        while ($true) {
            $answer = Read-Host ("{0}? [y]es / [n]o / [s]kip" -f $check)
            $normalized = ($answer ?? '').Trim().ToLowerInvariant()

            if ($normalized -in @('y', 'yes')) {
                Add-Result -Type 'MANUAL' -Check $check -Status 'PASS' -Details 'Potwierdzone manualnie'
                break
            }
            if ($normalized -in @('n', 'no')) {
                Add-Result -Type 'MANUAL' -Check $check -Status 'FAIL' -Details 'Nie przeszlo manualnie'
                break
            }
            if ($normalized -in @('s', 'skip')) {
                Add-Result -Type 'MANUAL' -Check $check -Status 'SKIP' -Details 'Pominiete manualnie'
                break
            }
        }
    }
}

$autoFails = @($results | Where-Object { $_.Type -eq 'AUTO' -and $_.Status -eq 'FAIL' }).Count
$manualFails = @($results | Where-Object { $_.Type -eq 'MANUAL' -and $_.Status -eq 'FAIL' }).Count
$manualSkips = @($results | Where-Object { $_.Type -eq 'MANUAL' -and $_.Status -eq 'SKIP' }).Count

$decision = 'GO'
$decisionReason = 'Brak faili automatycznych i manualnych.'

if ($autoFails -gt 0 -or $manualFails -gt 0) {
    $decision = 'NO-GO'
    $decisionReason = 'Wykryto co najmniej jeden FAIL.'
} elseif ($manualSkips -gt 0) {
    $decision = 'NO-GO'
    $decisionReason = 'Sa pominiete kontrole manualne.'
}

Write-Section 'Podsumowanie'
Write-Host ("AUTO FAIL: {0}" -f $autoFails)
Write-Host ("MANUAL FAIL: {0}" -f $manualFails)
Write-Host ("MANUAL SKIP: {0}" -f $manualSkips)

if ($decision -eq 'GO') {
    Write-Host ("DECYZJA: {0}" -f $decision) -ForegroundColor Green
} else {
    Write-Host ("DECYZJA: {0}" -f $decision) -ForegroundColor Red
}

$reportLines = @()
$reportLines += ("Release GO/NO-GO report: {0}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'))
$reportLines += ("Repo: {0}" -f $repoRoot)
$reportLines += ("Tryb: {0}" -f ($(if ($NonInteractive) { 'NonInteractive' } else { 'Interactive' })))
$reportLines += ''

$reportLines += '=== Results ==='
foreach ($item in $results) {
    $reportLines += ("[{0}] [{1}] {2}" -f $item.Type, $item.Status, $item.Check)
    if (-not [string]::IsNullOrWhiteSpace($item.Details)) {
        $reportLines += ("  - {0}" -f $item.Details)
    }
}

$reportLines += ''
$reportLines += '=== Summary ==='
$reportLines += ("AUTO FAIL: {0}" -f $autoFails)
$reportLines += ("MANUAL FAIL: {0}" -f $manualFails)
$reportLines += ("MANUAL SKIP: {0}" -f $manualSkips)
$reportLines += ("DECISION: {0}" -f $decision)
$reportLines += ("REASON: {0}" -f $decisionReason)

Set-Content -Path $reportPath -Value $reportLines -Encoding UTF8
Write-Host ("Raport zapisany: {0}" -f $reportPath) -ForegroundColor Cyan

if ($decision -eq 'GO') {
    exit 0
}

exit 1
