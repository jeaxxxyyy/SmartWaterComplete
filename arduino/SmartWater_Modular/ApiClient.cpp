#include "ApiClient.h"

#include <Arduino.h>
#include <HTTPClient.h>
#include <WiFi.h>
#include <math.h>

#include "AppState.h"
#include "Config.h"
#include "JsonHelpers.h"
#include "NetworkManager.h"
#include "secrets.h"

namespace {
constexpr char UNSENT_LITERS_KEY[] = "unsent_liters";
constexpr char TOTAL_LITERS_KEY[] = "total_liters";
constexpr char PENDING_ID_KEY[] = "p_read_id";
constexpr char PENDING_FLOW_KEY[] = "p_flow";
constexpr char PENDING_LITERS_KEY[] = "p_liters";
constexpr char PENDING_TOTAL_KEY[] = "p_total";
constexpr char PENDING_ACKED_KEY[] = "p_acked";
constexpr char ACK_REMAINING_KEY[] = "ack_remain";
constexpr char ACK_NEXT_SEQUENCE_KEY[] = "ack_next_seq";

float lastPersistedUnsentLiters = NAN;
float lastPersistedTotalLiters = NAN;

float validLitersOrZero(float value) {
  return isfinite(value) && value >= 0.0F ? value : 0.0F;
}

void persistTelemetryState(bool force = false) {
  const float safeUnsentLiters = validLitersOrZero(intervalLiters);
  const float safeTotalLiters = validLitersOrZero(totalLiters);

  if (force || !isfinite(lastPersistedUnsentLiters) ||
      fabsf(safeUnsentLiters - lastPersistedUnsentLiters) >= 0.0001F) {
    preferences.putFloat(UNSENT_LITERS_KEY, safeUnsentLiters);
    lastPersistedUnsentLiters = safeUnsentLiters;
  }

  if (force || !isfinite(lastPersistedTotalLiters) ||
      fabsf(safeTotalLiters - lastPersistedTotalLiters) >= 0.0001F) {
    preferences.putFloat(TOTAL_LITERS_KEY, safeTotalLiters);
    lastPersistedTotalLiters = safeTotalLiters;
  }
}

void persistPendingReading() {
  // Write the values first and the ID last. The ID is the commit marker that
  // tells restoreTelemetryState() a complete request is ready to retry.
  preferences.putFloat(PENDING_FLOW_KEY, pendingFlowRate);
  preferences.putFloat(PENDING_LITERS_KEY, pendingLiters);
  preferences.putFloat(PENDING_TOTAL_KEY, pendingTotalLiters);
  preferences.putString(PENDING_ID_KEY, pendingReadingId);
}

void clearPendingReadingFromMemory() {
  pendingReadingId = "";
  pendingFlowRate = 0.0F;
  pendingLiters = 0.0F;
  pendingTotalLiters = 0.0F;
}

void clearPersistedPendingReading() {
  // Remove the acknowledgement marker last. If power is interrupted during
  // cleanup, restoreTelemetryState() can still finish the acknowledged update.
  preferences.remove(PENDING_ID_KEY);
  preferences.remove(PENDING_FLOW_KEY);
  preferences.remove(PENDING_LITERS_KEY);
  preferences.remove(PENDING_TOTAL_KEY);
  preferences.remove(ACK_REMAINING_KEY);
  preferences.remove(ACK_NEXT_SEQUENCE_KEY);
  preferences.remove(PENDING_ACKED_KEY);
}

bool activationRetryAllowed() {
  if (!activationAttempted) {
    return true;
  }

  return millis() - lastActivationAttempt >= ACTIVATION_RETRY_MS;
}

void preparePendingReading() {
  if (!pendingReadingId.isEmpty()) {
    return;
  }

  pendingReadingId =
      String(SMARTWATER_DEVICE_UUID) + "-" + String(readingSequence);

  // Freeze this request's values so retries send the same reading.
  pendingFlowRate = flowRate;
  pendingLiters = intervalLiters;
  pendingTotalLiters = totalLiters;

  // Preserve the pulse-derived values and idempotency key before HTTP starts.
  persistTelemetryState();
  persistPendingReading();

  Serial.printf(
      "Telemetry queued: %s | %.4f L pending | %.4f L total\n",
      pendingReadingId.c_str(), pendingLiters, pendingTotalLiters);
}

void completePendingReading() {
  // Samples collected while this request was pending remain unsent.
  const float acknowledgedLiters = validLitersOrZero(pendingLiters);
  float remainingLiters = validLitersOrZero(intervalLiters) - acknowledgedLiters;
  if (remainingLiters < 0.0F) {
    remainingLiters = 0.0F;
  }

  const uint32_t nextSequence = readingSequence + 1;

  // Store an acknowledgement recovery record before changing the live state.
  // This makes a reset during cleanup safe: the same reading is not counted
  // twice, and newer unsent pulses are not discarded.
  preferences.putFloat(ACK_REMAINING_KEY, remainingLiters);
  preferences.putUInt(ACK_NEXT_SEQUENCE_KEY, nextSequence);
  preferences.putBool(PENDING_ACKED_KEY, true);

  intervalLiters = remainingLiters;
  readingSequence = nextSequence;

  persistTelemetryState(true);
  preferences.putUInt("reading_seq", readingSequence);

  Serial.printf(
      "Telemetry acknowledged: %.4f L | Remaining unsent: %.4f L\n",
      acknowledgedLiters, intervalLiters);

  clearPersistedPendingReading();
  clearPendingReadingFromMemory();
}

String activationPayload() {
  String body;
  body.reserve(300);

  body = "{";
  body += "\"device_uuid\":\"" + String(SMARTWATER_DEVICE_UUID) + "\",";
  body += "\"activation_code\":\"" + String(SMARTWATER_ACTIVATION_CODE) + "\",";
  body += "\"hardware_id\":\"" + WiFi.macAddress() + "\",";
  body += "\"firmware_version\":\"" + String(FIRMWARE_VERSION) + "\"";
  body += "}";

  return body;
}

String waterReadingPayload() {
  // A failed request can remain pending while new pulse samples arrive. Keep
  // the inclusive unsent accumulator current before every retry.
  persistTelemetryState();
  preparePendingReading();

  String body;
  body.reserve(600);

  body = "{";
  body += "\"reading_id\":\"" + pendingReadingId + "\",";
  body += "\"device_name\":\"" + String(DEVICE_NAME) + "\",";
  body += "\"flow_rate\":" + String(pendingFlowRate, 2) + ",";
  body += "\"liters_used\":" + String(pendingLiters, 4) + ",";
  body += "\"total_water_liters\":" + String(pendingTotalLiters, 4) + ",";
  body += "\"cubic_meter\":" + String(pendingLiters / 1000.0F, 6) + ",";
  body += "\"water_limit_liters\":" + String(WATER_LIMIT_LITERS, 2) + ",";
  body += "\"monitoring_status\":\"";
  body += monitoring ? "online" : "paused";
  body += "\",";
  body += "\"wifi_signal_strength\":" + String(wifiPercent()) + ",";
  body += "\"device_connection_status\":\"connected\",";
  body += "\"server_connection_status\":\"connected\"";
  body += "}";

  return body;
}
}  // namespace

void restoreTelemetryState() {
  totalLiters = validLitersOrZero(
      preferences.getFloat(TOTAL_LITERS_KEY, 0.0F));
  intervalLiters = validLitersOrZero(
      preferences.getFloat(UNSENT_LITERS_KEY, 0.0F));

  lastPersistedTotalLiters = totalLiters;
  lastPersistedUnsentLiters = intervalLiters;

  // A successful Laravel response may have been received immediately before a
  // reset. Finish that local acknowledgement instead of retrying/subtracting it.
  if (preferences.getBool(PENDING_ACKED_KEY, false)) {
    intervalLiters = validLitersOrZero(
        preferences.getFloat(ACK_REMAINING_KEY, intervalLiters));

    const uint32_t acknowledgedSequence = preferences.getUInt(
        ACK_NEXT_SEQUENCE_KEY, readingSequence);
    if (acknowledgedSequence > readingSequence) {
      readingSequence = acknowledgedSequence;
    }

    persistTelemetryState(true);
    preferences.putUInt("reading_seq", readingSequence);
    clearPersistedPendingReading();
    clearPendingReadingFromMemory();

    Serial.println(
        "Recovered a completed Laravel acknowledgement after restart.");
  } else {
    pendingReadingId = preferences.getString(PENDING_ID_KEY, "");

    if (!pendingReadingId.isEmpty()) {
      pendingFlowRate = validLitersOrZero(
          preferences.getFloat(PENDING_FLOW_KEY, 0.0F));
      pendingLiters = validLitersOrZero(
          preferences.getFloat(PENDING_LITERS_KEY, 0.0F));
      pendingTotalLiters = validLitersOrZero(
          preferences.getFloat(PENDING_TOTAL_KEY, totalLiters));

      // The unsent accumulator includes the frozen pending reading. Repair a
      // partial/stale checkpoint conservatively so acknowledged water is never
      // lost locally.
      if (intervalLiters < pendingLiters) {
        intervalLiters = pendingLiters;
        persistTelemetryState(true);
      }

      Serial.printf(
          "Restored pending telemetry: %s | %.4f L\n",
          pendingReadingId.c_str(), pendingLiters);
    } else {
      clearPendingReadingFromMemory();
    }
  }

  Serial.printf(
      "Restored water state: %.4f L unsent | %.4f L total\n",
      intervalLiters, totalLiters);
}

void clearStoredDeviceToken() {
  deviceToken = "";
  preferences.remove("api_token");

  // Allow one immediate activation attempt after an invalid token.
  activationAttempted = false;
  lastActivationAttempt = 0;
}

bool activateDevice() {
  if (!activationRetryAllowed()) {
  static unsigned long lastRetryMessage = 0;

  if (millis() - lastRetryMessage >= 15000) {
    const unsigned long elapsed =
        millis() - lastActivationAttempt;

    const unsigned long remaining =
        elapsed >= ACTIVATION_RETRY_MS
            ? 0
            : ACTIVATION_RETRY_MS - elapsed;

    Serial.printf(
      "Waiting before activation retry: %lu seconds.\n",
      remaining / 1000
    );

    lastRetryMessage = millis();
  }

  return false;
}

  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("Activation skipped: WiFi is disconnected.");
    return false;
  }

  if (strlen(SMARTWATER_DEVICE_UUID) == 0 ||
      strlen(SMARTWATER_ACTIVATION_CODE) == 0) {
    Serial.println(
        "Activation skipped: UUID or activation code is empty.");
    return false;
  }

  activationAttempted = true;
  lastActivationAttempt = millis();

  const String endpoint =
      String(SMARTWATER_SERVER_URL) + "/api/device-activate";

  HTTPClient http;

  Serial.println();
  Serial.print("Activation endpoint: ");
  Serial.println(endpoint);

  if (!http.begin(endpoint)) {
    Serial.println("Could not initialize activation request.");
    return false;
  }

  http.setTimeout(10000);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");

  // Do not print this request body because it contains the activation code.
  const int status = http.POST(activationPayload());
  const String response = http.getString();

  Serial.printf("Activation HTTP status: %d\n", status);
  Serial.print("Activation response: ");
  Serial.println(response);

  http.end();

  if (status == 429) {
    Serial.println(
        "Laravel rate-limited activation. Waiting 60 seconds.");
    return false;
  }

  // Accept both OK and Created responses.
  if (status != 200 && status != 201) {
    Serial.println("Device activation failed.");
    return false;
  }

  deviceToken = jsonStringValue(response, "token");

  if (deviceToken.isEmpty()) {
    Serial.println(
        "Activation succeeded, but Laravel returned no 'token' field.");
    return false;
  }

  preferences.putString("api_token", deviceToken);

  Serial.println("Device activated successfully.");
  Serial.println("Bearer token saved in ESP32 Preferences.");

  return true;
}

void sendReading() {
  // Checkpoint pulse-derived consumption even when Wi-Fi or activation is
  // unavailable, so a later restart does not discard the offline accumulator.
  persistTelemetryState();

  if (WiFi.status() != WL_CONNECTED) {
    serverConnected = false;
    connectWifi();
  }

  if (WiFi.status() != WL_CONNECTED) {
    serverConnected = false;
    Serial.println("Reading not sent: WiFi is disconnected.");
    return;
  }

  if (deviceToken.isEmpty()) {
    serverConnected = false;

    if (!activateDevice()) {
      return;
    }
  }

  const String endpoint =
      String(SMARTWATER_SERVER_URL) + "/api/water-data";

  HTTPClient http;

  Serial.println();
  Serial.print("Water-data endpoint: ");
  Serial.println(endpoint);

  if (!http.begin(endpoint)) {
    serverConnected = false;
    Serial.println("Could not initialize water-data request.");
    return;
  }

  http.setTimeout(10000);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.addHeader("Authorization", String("Bearer ") + deviceToken);

  const String body = waterReadingPayload();

  Serial.print("Water payload: ");
  Serial.println(body);

  const int status = http.POST(body);
  const String response = http.getString();

  Serial.printf("Water-data HTTP status: %d\n", status);
  Serial.print("Laravel response: ");
  Serial.println(response);

  serverConnected = status >= 200 && status < 300;

  if (serverConnected) {
    leakDetected =
        jsonBooleanValue(response, "is_possible_leak", leakDetected);

    // A duplicate response is also a successful acknowledgement.
    completePendingReading();
  } else if (status == 401) {
    Serial.println("Stored bearer token is invalid or expired.");
    clearStoredDeviceToken();
  } else if (status == 403) {
    Serial.println("Laravel rejected this device or its permissions.");
  } else if (status == 404) {
    Serial.println("Route not found: check /api/water-data.");
  } else if (status == 422) {
    Serial.println("Laravel validation failed. Check field names.");
  } else if (status == 429) {
    Serial.println("Laravel rate-limited the water-data request.");
  } else if (status < 0) {
    Serial.print("HTTP connection error: ");
    Serial.println(http.errorToString(status));
  }

  http.end();
}
