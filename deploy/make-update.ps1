# Builds the small update zip for an existing install (see deploy\update\update.ps1).
#
# Usage:  powershell -ExecutionPolicy Bypass -File deploy\make-update.ps1 [-Name shifts] [-SkipBuild]
#
# Ships code and assets only: app, bootstrap\app.php, database\migrations, lang, resources, routes,
# public\build|css|js|fonts, the deploy scripts and the guide. Never .env, the database, storage,
# vendor or config, so the owner's data and settings are untouched. If composer.lock changed
# since the last full release, a full release is needed instead (vendor is not shipped).

[CmdletBinding()]
param(
    [string]$Name = 'update',
    [switch]$SkipBuild
)

$ErrorActionPreference = 'Stop'
$AppDir = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$Out = Join-Path (Split-Path $AppDir -Parent) "cafepos-$Name"
$Zip = "$Out.zip"

if (-not $SkipBuild) {
    Push-Location $AppDir
    try {
        & npm run build
        if ($LASTEXITCODE -ne 0) { throw 'npm run build failed' }
    } finally {
        Pop-Location
    }
}

if (Test-Path $Out) { Remove-Item $Out -Recurse -Force }
New-Item -ItemType Directory -Path (Join-Path $Out 'files') | Out-Null
Copy-Item (Join-Path $PSScriptRoot 'update\update.ps1'), (Join-Path $PSScriptRoot 'update\Mise a jour CafePOS.bat') $Out

$folders = @('app', 'database\migrations', 'lang', 'resources', 'routes',
    'public\build', 'public\css', 'public\js', 'public\fonts')
foreach ($folder in $folders) {
    & robocopy (Join-Path $AppDir $folder) (Join-Path $Out "files\$folder") /E /NFL /NDL /NJH /NJS /NP /R:1 /W:1 | Out-Null
    if ($LASTEXITCODE -ge 8) { throw "copy of $folder failed" }
}
New-Item -ItemType Directory -Path (Join-Path $Out 'files\bootstrap'), (Join-Path $Out 'files\deploy'), (Join-Path $Out 'files\docs') -Force | Out-Null
Copy-Item (Join-Path $AppDir 'bootstrap\app.php') (Join-Path $Out 'files\bootstrap\app.php')
foreach ($file in 'launch-cafepos.ps1', 'setup.bat', 'setup-app.bat', 'start-cafepos.bat', 'backup.bat', 'fix-cafepos.bat', 'cafepos.ico') {
    Copy-Item (Join-Path $PSScriptRoot $file) (Join-Path $Out "files\deploy\$file")
}
& robocopy (Join-Path $PSScriptRoot 'update') (Join-Path $Out 'files\deploy\update') /E /NFL /NDL /NJH /NJS /NP | Out-Null
Copy-Item (Join-Path $AppDir 'docs\CafePOS-Guide.pdf') (Join-Path $Out 'files\docs\CafePOS-Guide.pdf')

if (Test-Path $Zip) { Remove-Item $Zip -Force }
Compress-Archive -Path (Join-Path $Out '*') -DestinationPath $Zip
$size = [math]::Round((Get-Item $Zip).Length / 1MB, 1)
Write-Host "Update ready: $Zip ($size MB)"
