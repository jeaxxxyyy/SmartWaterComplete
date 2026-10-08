#pragma once

#include <Arduino.h>

String jsonStringValue(const String &json, const String &key);
bool jsonBooleanValue(const String &json, const String &key, bool fallbackValue);
float jsonFloatValue(const String &json, const String &key, float fallbackValue);
