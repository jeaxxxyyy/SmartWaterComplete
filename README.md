# Smart Water Monitoring

Laravel and ESP32-S3/M5Stack CoreS3 monitoring for municipal household water use, estimated billing, alerts, reports, device lifecycle management, and three-second live updates.

## Quick start

Requirements: PHP 8.3 with PDO SQLite/MySQL, Composer, Node.js, npm, and Arduino IDE 2.x for the physical panel.

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
composer demo
```

Open `http://127.0.0.1:8000`. The demo command runs Laravel, Vite, and realistic dummy readings every three seconds.

Demo accounts:

- Household: `test@example.com` / `password`
- Administrator: `admin@gmail.com` / `admin123`
- Demo panel identity: managed internally; it has no interactive login

Change all development credentials before deployment.

## Municipal device registry

The single municipal administrator registers and assigns every meter in **Admin → Device Management**. Each record keeps the municipal asset number, manufacturer serial, household, installation date, hardware ID, firmware version, connectivity, maintenance state, and lifecycle status. Firmware cannot redirect readings to another household by changing its JSON.

Registration displays a device UUID and an eight-character activation code once. The code expires after 30 minutes and is stored only as a password hash. During commissioning, the ESP32 exchanges that code and its hardware ID for a scoped API credential. API credentials are hashed by Laravel Sanctum, are never shown in the admin UI, and are revoked when the admin rotates, suspends, or decommissions a meter.

## ESP32-S3 / M5Stack CoreS3

Firmware lives in `arduino/smartwater`. It displays dummy readings until a sensor is installed and uploads an interval reading every three seconds.

Commission a new meter:

1. Register the meter in **Admin → Device Management** and copy the one-time UUID and activation code.
2. Copy `arduino/smartwater/secrets.example.h` to ignored `secrets.h`.
3. Enter only the Wi-Fi details, LAN server URL, UUID, and activation code.
4. Install the ESP32 board package and `M5Unified`, select **M5Stack CoreS3** and the USB/JTAG COM port, then upload `smartwater.ino`.
5. Confirm the registry reports the expected hardware ID, firmware version, and a recent **Last seen** time.

The ESP32 stores its issued credential in nonvolatile storage. Remove the activation code from `secrets.h` after successful commissioning and upload once more before field installation. Use **New activation code** for authorized replacement or recommissioning; it immediately revokes the previous credential.

The PC firewall must allow port 8000, and Laravel must listen on the LAN:

```bash
php artisan serve --host=0.0.0.0
```

## Verification

```bash
php artisan test
npm run build
php artisan smartwater:simulate --once
```

Configuration defaults are in `.env.example`, `config/smartwater.php`, and `config/devices.php`.
