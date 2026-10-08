#include <M5Unified.h>

#include "AppState.h"
#include "ApiClient.h"
#include "DisplayManager.h"
#include "NetworkManager.h"
#include "SensorManager.h"
#include "ScreenManager.h"

void setup() {
  Serial.begin(115200);
  Serial.println();
  Serial.println("=== Smart Water Meter starting ===");

  auto config = M5.config();
  M5.begin(config);
  M5.Power.setExtOutput(true);
  M5.Display.setRotation(1);

  Serial.println(M5.Power.getExtOutput()
      ? "[Device] External 5V output ready."
      : "[Device] External 5V output is unavailable.");

  beginSensorManager();

  // Start the sample clock before Wi-Fi/activation, which may block for
  // several seconds. Pulses received during setup then use the correct elapsed
  // time instead of being mistaken for an implausible one-second burst.
  lastSample = millis();

  preferences.begin("smartwater", false);
  deviceToken = preferences.getString("api_token", "");
  readingSequence = preferences.getUInt("reading_seq", 1);
  restoreTelemetryState();

  // Optional fallback for an already commissioned device.
  if (deviceToken.isEmpty() && strlen(SMARTWATER_DEVICE_TOKEN) > 0) {
    deviceToken = SMARTWATER_DEVICE_TOKEN;
    preferences.putString("api_token", deviceToken);
  }

  connectWifi();

  if (deviceToken.isEmpty()) {
    activateDevice();
  }

  lastSend = millis();

  beginScreenManager();
  drawScreen();
}

void loop() {
  M5.update();

  handleScreenInput();

  if (M5.BtnB.wasPressed()) {
    setSensorMonitoring(!monitoring);

    drawScreen();
  }

  // Read this after input handling because a monitoring state change restarts
  // the sensor sample timer.
  const unsigned long nowMs = millis();

  if (
    nowMs - lastSample >=
    SAMPLE_INTERVAL_MS
  ) {
    updateFlowSensor(nowMs - lastSample);

    lastSample = nowMs;
    drawScreen();
  }

  if (
    nowMs - lastSend >=
    SEND_INTERVAL_MS
  ) {
    lastSend = nowMs;

    sendReading();
    drawScreen();
  }

  delay(20);
}
