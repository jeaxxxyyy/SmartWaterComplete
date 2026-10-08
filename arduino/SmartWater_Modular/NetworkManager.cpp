#include "NetworkManager.h"

#include <Arduino.h>
#include <WiFi.h>

#include "secrets.h"

void connectWifi() {
  if (WiFi.status() == WL_CONNECTED) return;

  Serial.println("[Wi-Fi] Connecting...");

  WiFi.mode(WIFI_STA);
  WiFi.disconnect();
  delay(100);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  for (int attempt = 0;
       WiFi.status() != WL_CONNECTED && attempt < 30;
       ++attempt) {
    delay(500);
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.print("[Wi-Fi] Connected | IP: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.println("[Wi-Fi] Connection failed. Readings will stay saved on the meter.");
  }
}

int wifiPercent() {
  if (WiFi.status() != WL_CONNECTED) return 0;
  return constrain(2 * (WiFi.RSSI() + 100), 0, 100);
}
