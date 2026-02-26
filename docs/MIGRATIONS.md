# DB Migrations

## Run
```bash
php migrate.php
```

## Behavior
- Reads `migrations/*.sql` in filename order
- Tracks applied files in `schema_migrations`
- Runs each migration in a transaction
- Stops immediately on first error

## Add a New Migration
1. Create a new SQL file with next prefix, for example:
   - `004_add_new_table.sql`
2. Put only SQL needed for that change.
3. Run `php migrate.php` on staging, then production.

