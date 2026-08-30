#pragma once

#include <Arduino.h>

void beginSensorManager();
void pollFlowSensorInput();
void updateFlowSensor(unsigned long elapsedMs);
void setSensorMonitoring(bool enabled);

unsigned long getFlowPulseCount();
