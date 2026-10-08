#include "JsonHelpers.h"

#include <math.h>
#include <stdlib.h>

String jsonStringValue(const String &json, const String &key) {
  const String marker = "\"" + key + "\"";
  int position = json.indexOf(marker);
  if (position < 0) return "";

  position = json.indexOf(':', position + marker.length());
  if (position < 0) return "";
  ++position;

  while (position < static_cast<int>(json.length()) &&
         (json[position] == ' ' || json[position] == '\t' ||
          json[position] == '\r' || json[position] == '\n')) {
    ++position;
  }

  if (position >= static_cast<int>(json.length()) || json[position] != '"') {
    return "";
  }

  const int valueStart = position + 1;
  const int valueEnd = json.indexOf('"', valueStart);
  return valueEnd < 0 ? "" : json.substring(valueStart, valueEnd);
}

bool jsonBooleanValue(const String &json, const String &key, bool fallbackValue) {
  const String marker = "\"" + key + "\"";
  int position = json.indexOf(marker);
  if (position < 0) return fallbackValue;

  position = json.indexOf(':', position + marker.length());
  if (position < 0) return fallbackValue;

  String remaining = json.substring(position + 1);
  remaining.trim();

  if (remaining.startsWith("true")) return true;
  if (remaining.startsWith("false")) return false;
  return fallbackValue;
}

float jsonFloatValue(const String &json, const String &key, float fallbackValue) {
  const String marker = "\"" + key + "\"";
  int position = json.indexOf(marker);
  if (position < 0) return fallbackValue;

  position = json.indexOf(':', position + marker.length());
  if (position < 0) return fallbackValue;

  const char *valueStart = json.c_str() + position + 1;
  char *valueEnd = nullptr;
  const float value = strtof(valueStart, &valueEnd);

  if (valueEnd == valueStart || !isfinite(value) || value < 0.0F) {
    return fallbackValue;
  }

  return value;
}
