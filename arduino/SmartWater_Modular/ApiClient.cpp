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
      "[Upload] Queued %.4f L at %.2f L/min.\n",
      pendingLiters,
      pendingFlowRate);
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
      "[Upload] Confirmed %.4f L | Waiting to send: %.4f L.\n",
      acknowledgedLiters,
      intervalLiters);

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
  body += "\"cubic_meter\":" + String(pendingLiters / 1000.0F, 6) + ",";
  body += "\"monitoring_status\":\"";
  body += monitoring ? "online" : "paused";
  body += "\",";
  body += "\"wifi_signal_strength\":" + String(wifiPercent()) + ",";
  body += "\"device_connection_status\":\"connected\"";
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

    Serial.println("[Storage] Completed the last confirmed upload after restart.");
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

    } else {
      clearPendingReadingFromMemory();
    }
  }

  if (intervalLiters > 0.0F) {
    Serial.printf("[Storage] Restored %.4f L waiting to upload.\n", intervalLiters);
  } else {
    Serial.println("[Storage] No water is waiting to upload.");
  }
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
          "[Activation] Retrying in %lu seconds.\n",
          remaining / 1000
      );

      lastRetryMessage = millis();
    }

    return false;
  }

  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[Activation] Waiting for Wi-Fi.");
    return false;
  }

  if (strlen(SMARTWATER_DEVICE_UUID) == 0 ||
      strlen(SMARTWATER_ACTIVATION_CODE) == 0) {
    Serial.println(
        "[Activation] Missing device UUID or activation code.");
    return false;
  }

  activationAttempted = true;
  lastActivationAttempt = millis();

  const String endpoint =
      String(SMARTWATER_SERVER_URL) + "/api/device-activate";

  HTTPClient http;

  if (!http.begin(endpoint)) {
    Serial.println("[Activation] Could not start the server request.");
    return false;
  }

  http.setTimeout(10000);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");

  // Do not print this request body because it contains the activation code.
  const int status = http.POST(activationPayload());
  const String response = http.getString();

  Serial.printf("[Activation] Server returned HTTP %d.\n", status);
  // The successful response contains the bearer token. Never print it to the
  // serial console, where it could be copied into logs or screenshots.

  http.end();

  if (status == 429) {
    Serial.println(
        "[Activation] Too many attempts. Waiting 60 seconds.");
    return false;
  }

  // Accept both OK and Created responses.
  if (status != 200 && status != 201) {
    Serial.println("[Activation] Device activation failed.");
    return false;
  }

  deviceToken = jsonStringValue(response, "token");

  if (deviceToken.isEmpty()) {
    Serial.println(
        "[Activation] The server did not return a device token.");
    return false;
  }

  preferences.putString("api_token", deviceToken);

  Serial.println("[Activation] Complete. Device access saved.");

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
    Serial.println("[Upload] Waiting for Wi-Fi. Water usage is saved on the meter.");
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

  if (!http.begin(endpoint)) {
    serverConnected = false;
    Serial.println("[Upload] Could not start the server request.");
    return;
  }

  http.setTimeout(10000);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.addHeader("Authorization", String("Bearer ") + deviceToken);

  const String body = waterReadingPayload();

  const int status = http.POST(body);
  const String response = http.getString();

  serverConnected = status >= 200 && status < 300;

  if (serverConnected) {
    const String acknowledgedReadingId =
        jsonStringValue(response, "reading_id");

    if (acknowledgedReadingId != pendingReadingId) {
      Serial.println("[Upload] Server acknowledgement did not match. The reading remains queued.");
      http.end();
      return;
    }

    leakDetected =
        jsonBooleanValue(response, "is_possible_leak", leakDetected);
    serverPeriodLiters =
        jsonFloatValue(response, "server_total_liters", serverPeriodLiters);
    serverWaterLimitLiters =
        jsonFloatValue(response, "water_limit_liters", serverWaterLimitLiters);

    if (isfinite(serverPeriodLiters) && isfinite(serverWaterLimitLiters)) {
      Serial.printf(
          "[Upload] Saved | HTTP %d | This month: %.4f / %.2f L | Leak: %s\n",
          status,
          serverPeriodLiters,
          serverWaterLimitLiters,
          leakDetected ? "possible" : "normal");
    } else {
      Serial.printf("[Upload] Saved | HTTP %d | Monthly total unavailable.\n", status);
    }

    // A duplicate response is also a successful acknowledgement.
    completePendingReading();
  } else if (status == 401) {
    Serial.println("[Upload] Device access expired. Activation will run again.");
    clearStoredDeviceToken();
  } else if (status == 403) {
    Serial.println("[Upload] The server rejected this device.");
  } else if (status == 404) {
    Serial.println("[Upload] Server route was not found.");
  } else if (status == 422) {
    Serial.println("[Upload] The server rejected this reading format.");
  } else if (status == 429) {
    Serial.println("[Upload] Too many uploads. The reading remains saved for retry.");
  } else if (status < 0) {
    Serial.print("[Upload] Connection error. The reading remains queued for retry: ");
    Serial.println(http.errorToString(status));
  }

  http.end();
}
