param(
    [string]$Host = "127.0.0.1",
    [string]$Port = "3306",
    [string]$Database = "network_platform",
    [string]$User = "root",
    [string]$Password = "",
    [string]$OutDir = "storage/backups"
)

$ErrorActionPreference = "Stop"

if (!(Test-Path $OutDir)) {
    New-Item -ItemType Directory -Path $OutDir | Out-Null
}

$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$outfile = Join-Path $OutDir "${Database}_${timestamp}.sql"

$env:MYSQL_PWD = $Password
try {
    & mysqldump --host=$Host --port=$Port --user=$User --single-transaction --routines --events --triggers $Database > $outfile
    Write-Host "Backup created: $outfile"
} finally {
    Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
}

