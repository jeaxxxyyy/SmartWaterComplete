#pragma once

#include <Arduino.h>
#include <Preferences.h>

#include "Config.h"
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

extern float flowRate;
extern float intervalLiters;
extern float totalLiters;

extern float pendingFlowRate;
extern float pendingLiters;
extern float pendingTotalLiters;
extern String pendingReadingId;
extern uint32_t readingSequence;

extern bool serverConnected;
extern bool monitoring;
extern bool leakDetected;

extern unsigned long lastSample;
extern unsigned long lastSend;
extern unsigned long lastActivationAttempt;
extern bool activationAttempted;

extern Preferences preferences;
extern String deviceToken;
