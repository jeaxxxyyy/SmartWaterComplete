# SmartWater Modular Arduino Sketch

Open `SmartWater_Modular.ino` in Arduino IDE. Keep every `.ino`, `.h`, and
`.cpp` file inside the same `SmartWater_Modular` folder.

## Tabs

- `SmartWater_Modular.ino`: setup and loop
- `Config.h`: intervals and constants
- `secrets.h`: Wi-Fi, Laravel URL, UUID and activation code
- `AppState.*`: shared variables and pending-reading state
- `NetworkManager.*`: Wi-Fi connection and signal strength
- `ApiClient.*`: activation and water-data POST requests
- `SensorManager.*`: GPIO2 pulse counting and calibrated ZJ-S201 flow readings
- `DisplayManager.*`: CoreS3 screen
- `JsonHelpers.*`: simple Laravel JSON response parsing

## Configure secrets.h

Use the PC's LAN IPv4 address. Do not use `localhost` or `127.0.0.1`.

```cpp
#define SMARTWATER_SERVER_URL "http://192.168.1.10:8000"
```

## Run Laravel

```bat
cd C:\laragon\www\SmartWater
php artisan optimize:clear
php artisan serve --host=0.0.0.0 --port=8000
```

## Important behavior

- The ZJ-S201 signal is read on GPIO2 using the initial calibration of 450.0 pulses/L.
- Sensor calibration must be confirmed with a measured physical water volume.
- Activation retries are limited to one attempt every 60 seconds.
- Laravel HTTP 200 and 201 are accepted as success.
- Each reading includes a unique `reading_id`.
- Failed readings retain the same ID and values for safe retry.
- Interval liters are cleared only after a successful Laravel response.
