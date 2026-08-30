#include "NetworkManager.h"

#include <Arduino.h>
#include <WiFi.h>

#include "secrets.h"

void connectWifi() {
  if (WiFi.status() == WL_CONNECTED) return;

  Serial.println();
  Serial.print("Connecting to WiFi: ");
  Serial.println(WIFI_SSID);

  WiFi.mode(WIFI_STA);
  WiFi.disconnect();
  delay(100);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  for (int attempt = 0;
       WiFi.status() != WL_CONNECTED && attempt < 30;
       ++attempt) {
    Serial.print(".");
    delay(500);
  }

  Serial.println();

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("WiFi connected.");
    Serial.print("M5Stack IP: ");
    Serial.println(WiFi.localIP());
    Serial.print("Laravel base URL: ");
    Serial.println(SMARTWATER_SERVER_URL);
  } else {
    Serial.println("WiFi connection failed.");
  }
}

int wifiPercent() {
  if (WiFi.status() != WL_CONNECTED) return 0;
  return constrain(2 * (WiFi.RSSI() + 100), 0, 100);
}
