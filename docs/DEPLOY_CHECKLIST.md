# Deployment Checklist

## 1. Pre-Deploy
- [ ] Pull latest code to server
- [ ] Copy `.env.example` to `.env`
- [ ] Set production values in `.env`:
  - `APP_ENV=production`
  - `APP_DEBUG=false`
  - `DB_*` values
  - `CRON_SECRET` strong random token
- [ ] Ensure `storage/logs` and `storage/backups` are writable

## 2. Database
- [ ] Run migrations:
```bash
php migrate.php
```
- [ ] Confirm migration output has no failures

## 3. Web/App Security
- [ ] Confirm maintenance/test scripts return `403`
- [ ] Confirm admin actions require CSRF + admin role
- [ ] Confirm login lockout is active
- [ ] Confirm cron endpoints require token in production

## 4. Smoke Tests
- [ ] User login/logout
- [ ] Admin login
- [ ] Investment request submit + admin approval
- [ ] Daily profit run (cron/manual in staging)
- [ ] Withdrawal request + admin approve/reject

## 5. Backups
- [ ] Run backup once manually
- [ ] Confirm backup file created in `storage/backups`
- [ ] Schedule daily backup task

## 6. Post-Deploy Monitoring
- [ ] Check `storage/logs/app.log` for errors
- [ ] Check DB connection and cron execution logs
- [ ] Validate first live transactions

