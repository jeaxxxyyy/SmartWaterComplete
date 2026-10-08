#include "AppState.h"

#include <math.h>

float flowRate = 0.0F;
float intervalLiters = 0.0F;
float totalLiters = 0.0F;
float serverPeriodLiters = NAN;
float serverWaterLimitLiters = NAN;

float pendingFlowRate = 0.0F;
float pendingLiters = 0.0F;
float pendingTotalLiters = 0.0F;
String pendingReadingId;
uint32_t readingSequence = 1;

bool serverConnected = false;
bool monitoring = true;
bool leakDetected = false;

unsigned long lastSample = 0;
unsigned long lastSend = 0;
unsigned long lastActivationAttempt = 0;
bool activationAttempted = false;

Preferences preferences;
String deviceToken;
