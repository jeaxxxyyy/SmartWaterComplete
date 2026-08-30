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
#ifndef SMARTWATER_SENSOR_PIN
#error "Define SMARTWATER_SENSOR_PIN in secrets.h for the water-flow sensor signal pin."
#endif
#ifndef SMARTWATER_PULSES_PER_LITER
#error "Define SMARTWATER_PULSES_PER_LITER in secrets.h for the installed sensor."
#endif
static_assert(SMARTWATER_PULSES_PER_LITER > 0.0F, "SMARTWATER_PULSES_PER_LITER must be greater than zero.");

constexpr char DEVICE_NAME[] = "M5Stack CoreS3";
constexpr char FIRMWARE_VERSION[] = "1.2.0";
constexpr unsigned long SAMPLE_INTERVAL_MS = 1000;
constexpr unsigned long SEND_INTERVAL_MS = 3000;
constexpr unsigned long ACTIVATION_RETRY_MS = 30000;
constexpr unsigned long HTTP_TIMEOUT_MS = 5000;
// Reject electrical bounce or duplicate edges that arrive less than 1 ms apart.
// The value is a software noise filter; adjust only if physical testing shows
// that the installed sensor can legitimately produce faster pulses.
constexpr uint32_t MIN_VALID_PULSE_INTERVAL_US = 1000;
constexpr float MAX_ACCEPTED_PULSE_RATE_HZ = 1000.0F;
constexpr float WATER_LIMIT_LITERS = 500.0F;

float flowRate = 0.0F;
float intervalLiters = 0.0F;
float totalLiters = 0.0F;
bool serverConnected = false;
bool monitoring = true;
bool leakDetected = false;
float serverTotalLiters = 0.0F;
float serverWaterLimitLiters = WATER_LIMIT_LITERS;
unsigned long lastSample = 0;
unsigned long lastSend = 0;
unsigned long lastActivationAttempt = 0;
unsigned long readingSequence = 0;
volatile uint32_t flowPulseCount = 0;
volatile uint32_t ignoredPulseCount = 0;
volatile uint32_t lastPulseMicros = 0;
uint32_t lastFlowPulseCount = 0;
Preferences preferences;
String deviceToken;
String pendingReadingId;

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

float jsonNumberValue(const String &json, const String &key, float fallback) {
  const String marker = "\"" + key + "\":";
  const int start = json.indexOf(marker);
  if (start < 0) return fallback;
  const int valueStart = start + marker.length();
  return json.substring(valueStart).toFloat();
}

void persistReadingState() {
  preferences.putFloat("pending_liters", intervalLiters);
  preferences.putFloat("total_liters", totalLiters);
  if (pendingReadingId.isEmpty()) preferences.remove("pending_id");
  else preferences.putString("pending_id", pendingReadingId);
  preferences.putULong("reading_seq", readingSequence);
}

void ensurePendingReadingId() {
  if (!pendingReadingId.isEmpty()) return;
  pendingReadingId = String(SMARTWATER_DEVICE_UUID) + "-" + String(readingSequence + 1);
  persistReadingState();
}

void connectWifi() {
  if (WiFi.status() == WL_CONNECTED) return;
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  for (int attempt = 0; WiFi.status() != WL_CONNECTED && attempt < 20; ++attempt) {
    delay(500);
  }
}

bool activateDevice() {
  if (WiFi.status() != WL_CONNECTED || strlen(SMARTWATER_DEVICE_UUID) == 0 || strlen(SMARTWATER_ACTIVATION_CODE) == 0) return false;
  const unsigned long nowMs = millis();
  if (lastActivationAttempt != 0 && nowMs - lastActivationAttempt < ACTIVATION_RETRY_MS) return false;
  lastActivationAttempt = nowMs;

  HTTPClient http;
  http.setTimeout(HTTP_TIMEOUT_MS);
  http.begin(String(SMARTWATER_SERVER_URL) + "/api/device-activate");
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  String body = "{\"device_uuid\":\"" + String(SMARTWATER_DEVICE_UUID) + "\",\"activation_code\":\"" + String(SMARTWATER_ACTIVATION_CODE) + "\",\"hardware_id\":\"" + WiFi.macAddress() + "\",\"firmware_version\":\"" + String(FIRMWARE_VERSION) + "\"}";
  const int status = http.POST(body);
  const String response = http.getString();
  http.end();
  if (status != 200) {
    Serial.printf("Activation failed (%d)\n", status);
    return false;
  }
  deviceToken = jsonStringValue(response, "token");
  if (deviceToken.isEmpty()) return false;
  preferences.putString("api_token", deviceToken);
  Serial.println("Device activated. Token stored securely.");
  return true;
}

void IRAM_ATTR onFlowPulse() {
  const uint32_t nowMicros = micros();
  if (nowMicros - lastPulseMicros < MIN_VALID_PULSE_INTERVAL_US) {
    ++ignoredPulseCount;
    return;
  }

  lastPulseMicros = nowMicros;
  ++flowPulseCount;
}

void updateSensor(unsigned long elapsedMs) {
  noInterrupts();
  const uint32_t currentPulseCount = flowPulseCount;
  const uint32_t ignoredPulses = ignoredPulseCount;
  ignoredPulseCount = 0;
  interrupts();

  if (!monitoring) {
    flowRate = 0;
    lastFlowPulseCount = currentPulseCount;
    Serial.printf("Flow pulses: 0\nTotal pulses: %lu\nFlow rate: 0.00 L/min\nInterval liters: %.4f L\nTotal liters: %.4f L\nIgnored pulses: %lu\n",
      static_cast<unsigned long>(currentPulseCount),
      0.0F, intervalLiters, totalLiters, static_cast<unsigned long>(ignoredPulses));
    return;
  }

  const uint32_t pulseDelta = currentPulseCount - lastFlowPulseCount;
  lastFlowPulseCount = currentPulseCount;
  const float elapsedSeconds = elapsedMs / 1000.0F;
  const float pulseRateHz = elapsedSeconds > 0.0F ? pulseDelta / elapsedSeconds : 0.0F;
  const bool duplicateNoiseBurst = ignoredPulses >= 10
    && (pulseDelta == 0 || ignoredPulses / pulseDelta > 4U);
  const bool abnormalBurst = pulseRateHz > MAX_ACCEPTED_PULSE_RATE_HZ || duplicateNoiseBurst;
  const float sampleLiters = abnormalBurst
    ? 0.0F
    : pulseDelta / static_cast<float>(SMARTWATER_PULSES_PER_LITER);
  flowRate = abnormalBurst || elapsedMs == 0
    ? 0.0F
    : sampleLiters / (elapsedMs / 60000.0F);
  intervalLiters += sampleLiters;
  totalLiters += sampleLiters;
  persistReadingState();

  Serial.printf("Flow pulses: %lu\nTotal pulses: %lu\nFlow rate: %.2f L/min\nInterval liters: %.4f L\nTotal liters: %.4f L\nIgnored pulses: %lu%s\n",
    static_cast<unsigned long>(pulseDelta),
    static_cast<unsigned long>(currentPulseCount),
    flowRate,
    intervalLiters,
    totalLiters,
    static_cast<unsigned long>(ignoredPulses),
    abnormalBurst ? " (abnormal burst discarded)" : "");
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

  ensurePendingReadingId();

  HTTPClient http;
  http.setTimeout(HTTP_TIMEOUT_MS);
  http.begin(String(SMARTWATER_SERVER_URL) + "/api/water-data");
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.addHeader("Authorization", String("Bearer ") + deviceToken);

  String body = "{";
  body += "\"reading_id\":\"" + pendingReadingId + "\",";
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
  body += ",\"firmware_version\":\"" + String(FIRMWARE_VERSION) + "\"";
  body += "}";

  const int status = http.POST(body);
  const String response = http.getString();
  serverConnected = status == 200 || status == 201;
  if (serverConnected) {
    leakDetected = response.indexOf("\"is_possible_leak\":true") >= 0;
    serverTotalLiters = jsonNumberValue(response, "server_total_liters", serverTotalLiters);
    serverWaterLimitLiters = jsonNumberValue(response, "water_limit_liters", serverWaterLimitLiters);
    ++readingSequence;
    intervalLiters = 0.0F;
    pendingReadingId = "";
    persistReadingState();
  } else if (status == 401) {
    deviceToken = "";
    preferences.remove("api_token");
    Serial.println("Device token rejected; activation will be retried with backoff.");
  } else if (status == 403) {
    Serial.println("Device upload forbidden; check meter status and assignment.");
  } else if (status == 409) {
    Serial.println("Device conflict; check hardware binding.");
  } else if (status == 422) {
    Serial.println("Reading rejected by server validation; pending reading retained.");
  } else {
    Serial.printf("Reading upload failed (%d); pending reading retained.\n", status);
  }
  http.end();
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
  M5.Display.printf("Total : %.3f L", serverTotalLiters > 0 ? serverTotalLiters : totalLiters);
  M5.Display.setCursor(15, 160);
  M5.Display.printf("Usage : %.6f m3", totalLiters / 1000.0F);
  M5.Display.setCursor(15, 195);
  M5.Display.printf("Limit : %.0f L", serverWaterLimitLiters);
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
  preferences.begin("smartwater", false);
  deviceToken = preferences.getString("api_token", "");
  readingSequence = preferences.getULong("reading_seq", 0);
  intervalLiters = preferences.getFloat("pending_liters", 0.0F);
  totalLiters = preferences.getFloat("total_liters", 0.0F);
  pendingReadingId = preferences.getString("pending_id", "");
  // Compatibility for an already commissioned unit. New deployments use the one-time activation code.
  if (deviceToken.isEmpty() && strlen(SMARTWATER_DEVICE_TOKEN) > 0) {
    deviceToken = SMARTWATER_DEVICE_TOKEN;
    preferences.putString("api_token", deviceToken);
  }
  pinMode(SMARTWATER_SENSOR_PIN, INPUT_PULLUP);
  attachInterrupt(digitalPinToInterrupt(SMARTWATER_SENSOR_PIN), onFlowPulse, RISING);
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
    updateSensor(nowMs - lastSample);
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
