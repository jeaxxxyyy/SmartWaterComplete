#pragma once

#include <Arduino.h>

enum class ScreenPage : uint8_t {
  Home = 0,
  LiveData = 1,
  DeviceStatus = 2
};

void beginScreenManager();
void handleScreenInput();

ScreenPage getCurrentScreen();
void setCurrentScreen(ScreenPage page);

void nextScreen();
void previousScreen();