# Smart Water Monitoring Project Document

## 1. Project Overview

Smart Water Monitoring is a Laravel-based water telemetry and billing platform designed for household water usage tracking, municipality device management, and operational monitoring of smart water meters. The system supports live meter readings, estimated billing, alerts, reports, and municipal administration workflows.

The application is intended for household consumers and local government administrators. It combines IoT-style device telemetry with a web dashboard, API services, and device lifecycle tracking.

---

## 2. Objectives

The system aims to:

- Monitor household water consumption in near real time
- Support utility billing based on usage and service data
- Provide access for administrators to manage devices and municipal records
- Track meter lifecycle status, maintenance, and device commissioning
- Support alerts, analytics, and operational reporting
- Handle offline/field deployment for ESP32-based metering hardware

---

## 3. System Components

### 3.1 Web Application
The main application is built with Laravel and provides:

- Household dashboard
- Administrative control panel
- Device management screens
- Billing and statement features
- Analytics pages
- Notification and configuration settings

### 3.2 API Layer
The project exposes JSON API endpoints under the application’s API controllers to support:

- Telemetry ingestion
- Household analytics
- Admin analytics
- Device-related workflows
- Meter registration and access management

### 3.3 Device/IoT Layer
The system supports ESP32-S3 and M5Stack CoreS3 devices. These meters read flow pulses and transmit readings periodically to the Laravel API.

### 3.4 Reporting and Documentation
The app includes support for statements, receipts, PDF generation, offline payment records, and audit trail logging.

---

## 4. Technical Stack

### Backend
- PHP 8.3+
- Laravel 13.x
- Laravel Sanctum
- MySQL or SQLite support
- Composer package management

### Frontend
- Node.js and npm
- Vite for asset bundling
- Blade templates and JavaScript assets

### Hardware
- ESP32-S3 / M5Stack CoreS3
- Flow sensor pulse input
- Wi-Fi/LAN communication to the server

---

## 5. Project Requirements

Before running the project, ensure the following are installed:

- PHP 8.3 or newer
- Composer
- Node.js and npm
- Arduino IDE 2.x for firmware development

---

## 6. Quick Start Guide

Run the commands below from the project root:

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
composer demo
```

This initializes the app, creates the database schema and seed data, compiles frontend assets, and starts the demo environment.

---

## 7. Demo Accounts

The project includes demo accounts for testing:

- Household Account
  - Email: test@example.com
  - Password: password

- Administrator Account
  - Email: admin@gmail.com
  - Password: admin123

These credentials are created through the development seeder and are intended for local development and demonstration.

---

## 8. Project Structure

Key folders in the repository include:

- app/Http/Controllers/Api — API controllers
- app/Models — Eloquent models
- app/Services — billing and logic services
- arduino/smartwater — device firmware
- config — app and system configuration
- database — migrations, factories, and seeders
- resources/views — Blade template files
- resources/css and resources/js — frontend assets
- routes — API, web, and console route definitions
- tests — feature and unit tests

---

## 9. Municipal Device Registry

The municipal administrator manages every smart water meter through the device management area. Each device record may include:

- Municipal asset number
- Manufacturer serial
- Assigned household
- Installation date
- Hardware ID
- Firmware version
- Connectivity status
- Maintenance state
- Lifecycle status

When a meter is registered, the system generates a UUID and a one-time activation code. The code expires after 30 minutes and is stored as a password hash rather than plain text. During commissioning, the hardware exchanges the code and hardware ID for a scoped access token.

---

## 10. ESP32 Commissioning Workflow

To commission a smart meter:

1. Register the meter in Admin → Device Management.
2. Copy the UUID and activation code.
3. Copy the example firmware secrets file to the project’s ignored secrets file.
4. Enter Wi-Fi, LAN server URL, UUID, and activation code.
5. Upload the firmware to the microcontroller using the Arduino IDE.
6. Confirm the device appears with the correct hardware ID, firmware version, and recent status.

The firmware stores unsent readings and sends them using a stable reading ID derived from the device UUID and sequence.

---

## 11. Local Network and Deployment Notes

For LAN testing, the server should be configured to listen on the local network interface instead of localhost. The project documentation recommends:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

The server URL should use the reachable LAN IPv4 address, not 127.0.0.1 or localhost. The port should be opened through the firewall if needed.

---

## 12. Analytics, Audit, and Privacy

The application supports admin and household analytics through API endpoints such as:

- GET /api/analytics/admin
- GET /api/analytics/household

These endpoints accept ranges such as:

- last_7_days
- last_30_days
- last_3_months
- last_12_months
- custom ranges using from and to

Administrative and security actions are logged in an audit trail, and sensitive values like credentials, passwords, activation codes, and access tokens are redacted from audit metadata.

---

## 13. Resident Services and Functionality

The household-facing system supports:

- Statement downloads
- Receipt downloads
- Monthly usage goals
- Forecasting
- In-app notifications and quiet hours
- English and Filipino interface labels
- Low-bandwidth mode
- Municipal service requests

The administrator side includes:

- Action center
- Request assignment workflow
- Response management
- Offline-payment ledger
- PDF receipt generation

---

## 14. Verification and Validation

The project documentation recommends validation with:

```bash
php artisan test
npm run build
php artisan test
```

This verifies the application logic and ensures the frontend builds successfully.

---

## 15. Summary

Smart Water Monitoring is a complete Laravel-based smart utility management solution that connects smart meters, household usage data, billing logic, analytics, audit logging, and municipal operations. It is designed for real-world utility deployment while supporting a local development and demo workflow.

---

## 16. Suggested Next Step

The next practical step is to install dependencies, configure the environment file, run migrations, and start the application locally to confirm the system is working end-to-end.
