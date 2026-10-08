#include "DisplayManager.h"

#include <M5Unified.h>
#include <WiFi.h>
#include <math.h>

#include "AppState.h"
#include "Config.h"
#include "NetworkManager.h"
#include "ScreenManager.h"

namespace {
void drawPageIndicator(uint8_t activePage) {
  constexpr int centerX = 160;
  constexpr int y = 229;
  constexpr int spacing = 18;

  for (uint8_t page = 0; page < 3; ++page) {
    const int x =
        centerX +
        (static_cast<int>(page) - 1) * spacing;

    if (page == activePage) {
      M5.Display.fillCircle(x, y, 5, TFT_NAVY);
    } else {
      M5.Display.drawCircle(x, y, 4, TFT_DARKGREY);
    }
  }
}

void drawTopBar(const char *title) {
  M5.Display.fillRect(
    0,
    0,
    M5.Display.width(),
    36,
    TFT_NAVY
  );

  M5.Display.setTextSize(2);
  M5.Display.setTextColor(TFT_WHITE);
  M5.Display.setCursor(12, 10);
  M5.Display.print(title);
}

void drawConnectionDots() {
  const uint16_t wifiColor =
      WiFi.status() == WL_CONNECTED
          ? TFT_GREEN
          : TFT_RED;

  const uint16_t serverColor =
      serverConnected
          ? TFT_GREEN
          : TFT_RED;

  M5.Display.fillCircle(277, 18, 6, wifiColor);
  M5.Display.fillCircle(299, 18, 6, serverColor);
}
}

void drawHomeScreen() {
  M5.Display.fillScreen(TFT_SKYBLUE);

  drawTopBar("SMART WATER");
  drawConnectionDots();

  M5.Display.setTextColor(TFT_NAVY);
  M5.Display.setTextSize(3);
  M5.Display.setCursor(26, 62);
  M5.Display.println("Welcome");

  M5.Display.setTextSize(2);
  M5.Display.setTextColor(TFT_BLACK);
  M5.Display.setCursor(26, 108);
  M5.Display.println("M5Stack CoreS3");

  M5.Display.setCursor(26, 138);
  M5.Display.println("Household Meter");

  M5.Display.setTextColor(
    serverConnected ? TFT_DARKGREEN : TFT_RED
  );

  M5.Display.setCursor(26, 174);
  M5.Display.printf(
    "System: %s",
    serverConnected ? "ONLINE" : "OFFLINE"
  );

  M5.Display.setTextColor(TFT_BLUE);
  M5.Display.setTextSize(1);
  M5.Display.setCursor(72, 207);
  M5.Display.println("Swipe left for live data");

  drawPageIndicator(0);
}

void drawLiveDataScreen() {
  M5.Display.fillScreen(TFT_WHITE);

  drawTopBar("LIVE WATER DATA");
  drawConnectionDots();

  M5.Display.setTextColor(TFT_DARKGREY);
  M5.Display.setTextSize(1);
  M5.Display.setCursor(20, 50);
  M5.Display.println("CURRENT FLOW RATE");

  M5.Display.setTextColor(TFT_NAVY);
  M5.Display.setTextSize(4);
  M5.Display.setCursor(20, 68);
  M5.Display.printf("%.2f", flowRate);

  M5.Display.setTextSize(2);
  M5.Display.setCursor(195, 82);
  M5.Display.println("L/min");

  M5.Display.drawFastHLine(
    20,
    116,
    280,
    TFT_LIGHTGREY
  );

  M5.Display.setTextSize(2);
  M5.Display.setTextColor(TFT_BLACK);

  M5.Display.setCursor(20, 130);
  if (isfinite(serverPeriodLiters)) {
    M5.Display.printf("This month: %.3f L", serverPeriodLiters);
  } else {
    M5.Display.print("This month: --");
  }

  M5.Display.setTextColor(
    leakDetected ? TFT_RED : TFT_DARKGREEN
  );

  M5.Display.setCursor(20, 158);
  M5.Display.printf(
    "Leak: %s",
    leakDetected ? "DETECTED" : "NORMAL"
  );

  M5.Display.setTextSize(1);
  M5.Display.setTextColor(TFT_BLUE);
  M5.Display.setCursor(60, 211);

  M5.Display.printf(
    "Hold screen to %s",
    monitoring ? "pause" : "resume"
  );

  drawPageIndicator(1);
}

void drawDeviceStatusScreen() {
  M5.Display.fillScreen(TFT_WHITE);

  drawTopBar("DEVICE STATUS");
  drawConnectionDots();

  M5.Display.setTextSize(2);

  M5.Display.setTextColor(TFT_BLACK);
  M5.Display.setCursor(18, 54);
  M5.Display.print("WiFi");

  M5.Display.setTextColor(
    WiFi.status() == WL_CONNECTED
        ? TFT_DARKGREEN
        : TFT_RED
  );

  M5.Display.setCursor(185, 54);
  M5.Display.printf(
    "%s %d%%",
    WiFi.status() == WL_CONNECTED
        ? "Connected"
        : "Offline",
    wifiPercent()
  );

  M5.Display.setTextColor(TFT_BLACK);
  M5.Display.setCursor(18, 88);
  M5.Display.print("Server");

  M5.Display.setTextColor(
    serverConnected ? TFT_DARKGREEN : TFT_RED
  );

  M5.Display.setCursor(185, 88);
  M5.Display.print(
    serverConnected ? "Connected" : "Offline"
  );

  M5.Display.setTextColor(TFT_BLACK);
  M5.Display.setCursor(18, 122);
  M5.Display.print("Monitoring");

  M5.Display.setCursor(185, 122);
  M5.Display.setTextColor(
    monitoring ? TFT_DARKGREEN : TFT_ORANGE
  );

  M5.Display.print(
    monitoring ? "Active" : "Paused"
  );

  M5.Display.setTextColor(TFT_BLACK);
  M5.Display.setCursor(18, 156);
  M5.Display.print("Firmware");

  M5.Display.setCursor(185, 156);
  M5.Display.print(FIRMWARE_VERSION);

  M5.Display.setTextSize(1);
  M5.Display.setTextColor(TFT_DARKGREY);

  M5.Display.setCursor(18, 190);
  M5.Display.print("IP: ");

  if (WiFi.status() == WL_CONNECTED) {
    M5.Display.print(WiFi.localIP());
  } else {
    M5.Display.print("--");
  }

  M5.Display.setTextColor(TFT_BLUE);
  M5.Display.setCursor(72, 211);
  M5.Display.print("Swipe right to go back");

  drawPageIndicator(2);
}

void drawScreen() {
  switch (getCurrentScreen()) {
    case ScreenPage::Home:
      drawHomeScreen();
      break;

    case ScreenPage::LiveData:
      drawLiveDataScreen();
      break;

    case ScreenPage::DeviceStatus:
      drawDeviceStatusScreen();
      break;
  }
}
