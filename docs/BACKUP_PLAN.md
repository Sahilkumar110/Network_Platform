# Backup Plan

## Scope
- MySQL database: `network_platform`
- Critical app files: `.env`, `storage/`, `migrations/`

## Frequency
- Full DB backup: daily (off-peak)
- Keep last 7 daily backups and 4 weekly backups

## Backup Command (Windows/PowerShell)
```powershell
powershell -ExecutionPolicy Bypass -File scripts\backup_db.ps1 `
  -Host "127.0.0.1" -Port "3306" -Database "network_platform" -User "root" -Password "your_password"
```

## Restore Command
```powershell
mysql --host=127.0.0.1 --port=3306 --user=root -p network_platform < storage\backups\network_platform_YYYYMMDD_HHMMSS.sql
```

## Storage
- Local folder: `storage/backups/`
- Recommended: copy backups to offsite storage (S3/Drive/NAS) after each run

## Verification
- Weekly: restore latest backup on staging and validate:
  - Login works
  - Dashboard loads
  - Withdrawals/investment records exist

## Security
- Do not commit backup files
- Do not store plaintext DB passwords in scripts
- Use environment variables or secret manager in production

