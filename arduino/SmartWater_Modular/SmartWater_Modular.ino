#include <M5Unified.h>

#include "AppState.h"
#include "ApiClient.h"
#include "DisplayManager.h"
#include "NetworkManager.h"
#include "SensorManager.h"
#include "ScreenManager.h"

void setup() {
  Serial.begin(115200);

  auto config = M5.config();
  M5.begin(config);
  M5.Power.setExtOutput(true);
  M5.Display.setRotation(1);

  Serial.print("CoreS3 external 5V output: ");
  Serial.println(M5.Power.getExtOutput() ? "ON" : "OFF");

  beginSensorManager();

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

  lastSample = millis();
  lastSend = millis();

  beginScreenManager();
  drawScreen();
}

void loop() {
  M5.update();

  pollFlowSensorInput();

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
