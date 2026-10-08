# Smart Water Operations Guide

This guide covers the minimum operational controls required beyond a local capstone demonstration.

## Production deployment

1. Copy `.env.production.example` to the server as `.env` and replace every `CHANGE_TO_...` value.
2. Use an HTTPS domain and keep `APP_DEBUG=false`.
3. Create a dedicated database account with access only to the Smart Water database. Do not use the MySQL root account.
4. Run `composer install --no-dev --optimize-autoloader`, `npm ci`, and `npm run build`.
5. Run `php artisan migrate --force`, `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache`.
6. Run a persistent queue worker for the database queue and restart it after deployments.
7. Run `php artisan schedule:run` every minute using Windows Task Scheduler or the production server scheduler.
8. Confirm `/up` responds successfully and verify the administrator System Health page.

The Laravel development server is suitable for LAN demonstrations only. Use IIS, Apache, or Nginx with HTTPS for actual deployment.

## Backup and restore

Create a database backup before migrations, billing-policy changes, or data repair. Store backups outside the public web directory and copy them to a second protected location.

Example backup with Laragon MySQL:

```powershell
mysqldump --host=127.0.0.1 --port=3306 --user=smartwater_app --single-transaction --routines --triggers --result-file=smartwater-backup.sql smartwater
```

Test restoration in a separate database, never over the active database:

```powershell
mysql --host=127.0.0.1 --port=3306 --user=smartwater_app smartwater_restore
```

After restoration, confirm user counts, the active billing configuration, the latest reading ID, statements, payments, and audit logs.

## Queue and scheduler checks

- Start the queue worker with `php artisan queue:work --tries=3 --timeout=60`.
- Check failed jobs with `php artisan queue:failed`.
- Run scheduled notification delivery manually with `php artisan notifications:deliver-scheduled` when diagnosing delays.
- Prune expired login tokens using the configured scheduler.

## Device credential handling

- `arduino/SmartWater_Modular/secrets.h` must remain local and untracked.
- Copy `secrets.example.h` when provisioning a new development device.
- Rotate a Wi-Fi password, activation code, or device token if it is exposed.
- Decommission a lost device from the administrator dashboard to revoke its credentials.

## Recovery checks

After a server, database, or power restart:

1. Confirm the database, web server, queue worker, and scheduler are running.
2. Open `/up` and the administrator System Health page.
3. Confirm the latest meter transitions online after its next telemetry submission.
4. Confirm a retried `reading_id` returns `duplicate: true` and does not increase consumption twice.
5. Generate a test statement in a non-production household account and verify the PDF.

## Historical threshold repair

Use a dry run first:

```powershell
php artisan water:repair-threshold-history
```

After confirming a backup exists, apply the repair:

```powershell
php artisan water:repair-threshold-history --apply
```

The command recalculates monthly threshold flags, archives only invalid threshold notifications, preserves leak and announcement records, and writes an audit log.
