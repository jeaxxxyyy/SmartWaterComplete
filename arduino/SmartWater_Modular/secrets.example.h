#pragma once

// Copy this file to secrets.h and replace every placeholder locally.
// Never commit secrets.h because it contains household Wi-Fi and device credentials.
#define WIFI_SSID "YOUR_WIFI_SSID"
#define WIFI_PASSWORD "YOUR_WIFI_PASSWORD"

// Use the LAN IPv4 address of the PC running Laravel.
// Do not use localhost or 127.0.0.1 from the CoreS3.
#define SMARTWATER_SERVER_URL "http://192.168.1.3:8000"

// Copy these values from the administrator's device-provisioning screen.
#define SMARTWATER_DEVICE_UUID "YOUR_DEVICE_UUID"
#define SMARTWATER_ACTIVATION_CODE "YOUR_ACTIVATION_CODE"

// Leave empty before first activation. The CoreS3 stores the issued token locally.
#define SMARTWATER_DEVICE_TOKEN ""

// Replace this value after performing a measured-water calibration test.
#define SMARTWATER_SENSOR_PIN 2
#define SMARTWATER_PULSES_PER_LITER 450.0F
