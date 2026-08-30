#include "ScreenManager.h"

#include <M5Unified.h>

#include "AppState.h"
#include "DisplayManager.h"
#include "SensorManager.h"

namespace {
ScreenPage currentScreen = ScreenPage::Home;

constexpr int SWIPE_DISTANCE = 60;

unsigned long lastNavigationAt = 0;

constexpr unsigned long NAVIGATION_DEBOUNCE_MS = 300;
}

void beginScreenManager() {
  currentScreen = ScreenPage::Home;

  // Distance required before M5Unified considers a movement a flick.
  M5.Touch.setFlickThresh(SWIPE_DISTANCE);
}

ScreenPage getCurrentScreen() {
  return currentScreen;
}

void setCurrentScreen(ScreenPage page) {
  currentScreen = page;
  drawScreen();
}

void nextScreen() {
  switch (currentScreen) {
    case ScreenPage::Home:
      currentScreen = ScreenPage::LiveData;
      break;

    case ScreenPage::LiveData:
      currentScreen = ScreenPage::DeviceStatus;
      break;

    case ScreenPage::DeviceStatus:
      currentScreen = ScreenPage::Home;
      break;
  }

  drawScreen();
}

void previousScreen() {
  switch (currentScreen) {
    case ScreenPage::Home:
      currentScreen = ScreenPage::DeviceStatus;
      break;

    case ScreenPage::LiveData:
      currentScreen = ScreenPage::Home;
      break;

    case ScreenPage::DeviceStatus:
      currentScreen = ScreenPage::LiveData;
      break;
  }

  drawScreen();
}

void handleScreenInput() {
  const auto touch = M5.Touch.getDetail();

  /*
   * Long press on the Live Data screen to pause or resume
   * monitoring. This prevents normal swipes from toggling it.
   */
  if (
    touch.wasHold() &&
    currentScreen == ScreenPage::LiveData
  ) {
    setSensorMonitoring(!monitoring);

    drawScreen();
    return;
  }

  /*
   * Evaluate the gesture after the finger is released.
   */
  if (!touch.wasReleased()) {
    return;
  }

  const unsigned long nowMs = millis();

  if (
    nowMs - lastNavigationAt <
    NAVIGATION_DEBOUNCE_MS
  ) {
    return;
  }

  const int horizontalDistance = touch.distanceX();
  const int verticalDistance = touch.distanceY();

  /*
   * Ignore simple taps and mostly vertical movements.
   */
  if (
    abs(horizontalDistance) < SWIPE_DISTANCE ||
    abs(horizontalDistance) <= abs(verticalDistance)
  ) {
    return;
  }

  lastNavigationAt = nowMs;

  if (horizontalDistance < 0) {
    // Finger moved from right to left.
    nextScreen();
  } else {
    // Finger moved from left to right.
    previousScreen();
  }
}
