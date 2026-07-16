#include <M5Unified.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <Preferences.h>
#include "secrets.h"

#ifndef SMARTWATER_DEVICE_UUID
#define SMARTWATER_DEVICE_UUID ""
#endif
#ifndef SMARTWATER_ACTIVATION_CODE
#define SMARTWATER_ACTIVATION_CODE ""
#endif
#ifndef SMARTWATER_DEVICE_TOKEN
#define SMARTWATER_DEVICE_TOKEN ""
#endif

constexpr char DEVICE_NAME[] = "M5Stack CoreS3";
constexpr char FIRMWARE_VERSION[] = "1.1.0";
constexpr unsigned long SAMPLE_INTERVAL_MS = 1000;
constexpr unsigned long SEND_INTERVAL_MS = 3000;
constexpr float WATER_LIMIT_LITERS = 500.0F;

float flowRate = 0.0F;
float intervalLiters = 0.0F;
float totalLiters = 0.0F;
bool serverConnected = false;
bool monitoring = true;
bool leakDetected = false;
unsigned long lastSample = 0;
unsigned long lastSend = 0;
Preferences preferences;
String deviceToken;

String jsonStringValue(const String &json, const String &key) {
  const String marker = "\"" + key + "\":\"";
  const int start = json.indexOf(marker);
  if (start < 0) return "";
  const int valueStart = start + marker.length();
  const int end = json.indexOf('"', valueStart);
  return end < 0 ? "" : json.substring(valueStart, end);
}

int wifiPercent() {
  return constrain(2 * (WiFi.RSSI() + 100), 0, 100);
}

void connectWifi() {
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  for (int attempt = 0; WiFi.status() != WL_CONNECTED && attempt < 30; ++attempt) {
    delay(500);
  }
}

bool activateDevice() {
  if (WiFi.status() != WL_CONNECTED || strlen(SMARTWATER_DEVICE_UUID) == 0 || strlen(SMARTWATER_ACTIVATION_CODE) == 0) return false;

  HTTPClient http;
  http.begin(String(SMARTWATER_SERVER_URL) + "/api/device-activate");
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  String body = "{\"device_uuid\":\"" + String(SMARTWATER_DEVICE_UUID) + "\",\"activation_code\":\"" + String(SMARTWATER_ACTIVATION_CODE) + "\",\"hardware_id\":\"" + WiFi.macAddress() + "\",\"firmware_version\":\"" + String(FIRMWARE_VERSION) + "\"}";
  const int status = http.POST(body);
  const String response = http.getString();
  http.end();
  if (status != 200) {
    Serial.printf("Activation failed (%d): %s\n", status, response.c_str());
    return false;
  }
  deviceToken = jsonStringValue(response, "token");
  if (deviceToken.isEmpty()) return false;
  preferences.putString("api_token", deviceToken);
  Serial.println("Device securely activated. Token stored in ESP32 nonvolatile storage.");
  return true;
}

void updateDummySensor(unsigned long elapsedMs) {
  if (!monitoring) {
    flowRate = 0;
    return;
  }

  flowRate = random(150, 401) / 100.0F;
  const float sampleLiters = flowRate * (elapsedMs / 60000.0F);
  intervalLiters += sampleLiters;
  totalLiters += sampleLiters;
}

void sendReading() {
  if (WiFi.status() != WL_CONNECTED) {
    connectWifi();
  }
  if (deviceToken.isEmpty() && !activateDevice()) {
    serverConnected = false;
    return;
  }
  if (WiFi.status() != WL_CONNECTED) {
    serverConnected = false;
    return;
  }

  HTTPClient http;
  http.begin(String(SMARTWATER_SERVER_URL) + "/api/water-data");
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.addHeader("Authorization", String("Bearer ") + deviceToken);

  String body = "{";
  body += "\"device_name\":\"" + String(DEVICE_NAME) + "\",";
  body += "\"flow_rate\":" + String(flowRate, 2) + ",";
  body += "\"liters_used\":" + String(intervalLiters, 4) + ",";
  body += "\"total_water_liters\":" + String(totalLiters, 4) + ",";
  body += "\"cubic_meter\":" + String(intervalLiters / 1000.0F, 6) + ",";
  body += "\"water_limit_liters\":" + String(WATER_LIMIT_LITERS, 2) + ",";
  body += "\"monitoring_status\":\"" + String(monitoring ? "online" : "paused") + "\",";
  body += "\"wifi_signal_strength\":" + String(wifiPercent()) + ",";
  body += "\"device_connection_status\":\"connected\",";
  body += "\"server_connection_status\":\"connected\"";
  body += "}";

  const int status = http.POST(body);
  const String response = http.getString();
  serverConnected = status == 200 || status == 201;
  if (serverConnected) leakDetected = response.indexOf("\"is_possible_leak\":true") >= 0;
  Serial.printf("POST %d: %s\n", status, response.c_str());
  http.end();

  if (serverConnected) {
    intervalLiters = 0;
  }
}

void drawScreen() {
  M5.Display.fillScreen(TFT_SKYBLUE);
  M5.Display.setTextSize(2);
  M5.Display.setTextColor(TFT_NAVY);
  M5.Display.setCursor(15, 10);
  M5.Display.println("SMART WATER METER");
  M5.Display.setTextColor(WiFi.status() == WL_CONNECTED ? TFT_GREEN : TFT_RED);
  M5.Display.setCursor(15, 45);
  M5.Display.printf("WiFi:%s  Server:%s", WiFi.status() == WL_CONNECTED ? "OK" : "OFF", serverConnected ? "OK" : "OFF");
  M5.Display.setTextColor(TFT_BLACK);
  M5.Display.setCursor(15, 90);
  M5.Display.printf("Flow  : %.2f L/min", flowRate);
  M5.Display.setCursor(15, 125);
  M5.Display.printf("Total : %.3f L", totalLiters);
  M5.Display.setCursor(15, 160);
  M5.Display.printf("Usage : %.6f m3", totalLiters / 1000.0F);
  M5.Display.setCursor(15, 195);
  M5.Display.printf("Limit : %.0f L", WATER_LIMIT_LITERS);
  M5.Display.setTextColor(leakDetected ? TFT_RED : TFT_GREEN);
  M5.Display.setCursor(15, 240);
  M5.Display.println(leakDetected ? "Leak: DETECTED" : "Leak: NORMAL");
  M5.Display.setTextColor(TFT_BLUE);
  M5.Display.setCursor(15, 285);
  M5.Display.println("Tap center to pause/resume");
}

void setup() {
  Serial.begin(115200);
  auto config = M5.config();
  M5.begin(config);
  M5.Display.setRotation(1);
  randomSeed(esp_random());
  preferences.begin("smartwater", false);
  deviceToken = preferences.getString("api_token", "");
  // Compatibility for an already commissioned unit. New deployments use the one-time activation code.
  if (deviceToken.isEmpty() && strlen(SMARTWATER_DEVICE_TOKEN) > 0) {
    deviceToken = SMARTWATER_DEVICE_TOKEN;
    preferences.putString("api_token", deviceToken);
  }
  connectWifi();
  if (deviceToken.isEmpty()) activateDevice();
  lastSample = lastSend = millis();
  drawScreen();
}

void loop() {
  M5.update();
  const unsigned long nowMs = millis();
  if (M5.BtnB.wasPressed() || M5.Touch.getCount() > 0) {
    monitoring = !monitoring;
    delay(250);
  }
  if (nowMs - lastSample >= SAMPLE_INTERVAL_MS) {
    updateDummySensor(nowMs - lastSample);
    lastSample = nowMs;
    drawScreen();
  }
  if (nowMs - lastSend >= SEND_INTERVAL_MS) {
    lastSend = nowMs;
    sendReading();
    drawScreen();
  }
  delay(20);
}
