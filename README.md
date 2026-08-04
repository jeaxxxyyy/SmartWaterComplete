# Smart Water Monitoring

Smart Water Monitoring is a Laravel application for household water telemetry, estimated billing, alerts, reports, and municipal device lifecycle management. ESP32-S3/M5Stack CoreS3 meters send readings to the Laravel API and the dashboard refreshes live data every three seconds.

## Requirements

- PHP 8.3+ with PDO SQLite or MySQL
- Composer
- Node.js and npm
- Arduino IDE 2.x for the physical meter panel

## Quick start

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
composer demo
```

Open `http://127.0.0.1:8000` after the demo command starts the application. The demo runs Laravel, Vite, and realistic sensor readings concurrently.

Demo accounts:

- Household: `test@example.com` / `password`
- Administrator: `admin@gmail.com` / `admin123`
- Demo panel identity: managed internally; it has no interactive login

Change all development credentials before deployment.

## Project map

| Directory | Responsibility |
| --- | --- |
| `app/Http/Controllers/Api` | JSON API controllers grouped by feature |
| `app/Models` | Eloquent models and relationships |
| `app/Services` | Billing, consumption, and audit business logic |
| `arduino/smartwater` | ESP32-S3/M5Stack firmware |
| `config` | Application and Smart Water configuration |
| `database` | Migrations, factories, and seed data |
| `resources/views` | Blade layout, page, and report templates |
| `resources/css` and `resources/js` | Vite-managed frontend assets |
| `routes` | Web, API, and console route definitions |
| `tests` | Feature and unit coverage |

## Municipal device registry

The municipal administrator registers and assigns every meter in **Admin → Device Management**. Each device record tracks the municipal asset number, manufacturer serial, household, installation date, hardware ID, firmware version, connectivity, maintenance state, and lifecycle status.

Registration displays a device UUID and one-time activation code. The code expires after 30 minutes and is stored only as a password hash. During commissioning, the ESP32 exchanges that code and its hardware ID for a scoped Laravel Sanctum credential. Credentials are revoked when an administrator rotates, suspends, or decommissions a meter.

## Commission an ESP32-S3 / M5Stack CoreS3 meter

Firmware lives in `arduino/smartwater`. It displays dummy readings until a sensor is installed and uploads an interval reading every three seconds.

1. Register the meter in **Admin → Device Management** and copy the one-time UUID and activation code.
2. Copy `arduino/smartwater/secrets.example.h` to the ignored `secrets.h` file.
3. Enter only the Wi-Fi details, LAN server URL, UUID, and activation code.
4. Install the ESP32 board package and `M5Unified`, select **M5Stack CoreS3** and the USB/JTAG COM port, then upload `smartwater.ino`.
5. Confirm that the registry shows the expected hardware ID, firmware version, and recent **Last seen** time.

The firmware keeps each unsent interval in ESP32 Preferences and sends a stable `reading_id` derived from the registered device UUID and local sequence. Laravel counts `liters_used` only once per registered device and reading ID; the client-reported cumulative total is retained for diagnostics and does not replace the server total.

Remove the activation code from `secrets.h` after successful commissioning and upload once more before field installation. Use **New activation code** for an authorized replacement or recommissioning; it immediately revokes the previous credential.

For LAN testing, set `SMARTWATER_SERVER_URL` to the computer's reachable LAN IPv4 address, never `localhost` or `127.0.0.1`. Allow port 8000 through the PC firewall and bind Laravel to the network interface:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

## Verification

```bash
php artisan test
npm run build
php artisan smartwater:simulate --once
```

Configuration defaults are documented in `.env.example`, `config/smartwater.php`, and `config/devices.php`.

## Analytics, audit, and privacy controls

Administrator analytics are available at `GET /api/analytics/admin`; household analytics are available at `GET /api/analytics/household`. Both endpoints accept `last_7_days`, `last_30_days`, `last_3_months`, `last_12_months`, or a validated `custom` range using `from` and `to`. Results include the configured `APP_TIMEZONE` and omit metrics when no valid readings exist.

Administrative and security actions are stored in `audit_logs` and written to the rotating `audit` log channel. Credentials, passwords, activation codes, and access tokens are redacted from audit metadata. Household analytics, billing settings, statements, notifications, readings, and device information are scoped to the authenticated household.
