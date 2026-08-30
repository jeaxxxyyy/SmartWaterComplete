#include "SensorManager.h"

#include <math.h>

#include "AppState.h"
#include "secrets.h"

#ifndef SMARTWATER_SENSOR_PIN
#error "Define SMARTWATER_SENSOR_PIN in secrets.h."
#endif

#ifndef SMARTWATER_PULSES_PER_LITER
#error "Define SMARTWATER_PULSES_PER_LITER in secrets.h."
#endif

static_assert(
    SMARTWATER_PULSES_PER_LITER > 0.0F,
    "SMARTWATER_PULSES_PER_LITER must be greater than zero."
);

namespace {

constexpr float MAX_RATED_FLOW_LITERS_PER_MINUTE = 30.0F;
constexpr float PULSE_SAFETY_MARGIN = 1.25F;
// Reject very closely spaced edges caused by ringing or contact/noise spikes.
// 1 ms still permits 1000 pulses/s, well above the plausible 281.25 pulses/s.
constexpr uint32_t MINIMUM_PULSE_INTERVAL_US = 1000;

portMUX_TYPE pulseMux = portMUX_INITIALIZER_UNLOCKED;
volatile uint32_t pulseCount = 0;
volatile uint32_t rejectedNoisePulseCount = 0;
volatile uint32_t lastAcceptedPulseAtUs = 0;
uint32_t previousPulseCount = 0;
int previousObservedSignalLevel = LOW;
bool observedSignalHigh = false;
bool observedSignalLow = false;
uint32_t observedSignalTransitions = 0;

struct PulseSnapshot {
    uint32_t accepted;
    uint32_t rejectedNoise;
};

void IRAM_ATTR handleFlowPulse() {
    const uint32_t nowUs = micros();

    portENTER_CRITICAL_ISR(&pulseMux);

    if (nowUs - lastAcceptedPulseAtUs >= MINIMUM_PULSE_INTERVAL_US) {
        ++pulseCount;
        lastAcceptedPulseAtUs = nowUs;
    } else {
        ++rejectedNoisePulseCount;
    }

    portEXIT_CRITICAL_ISR(&pulseMux);
}

PulseSnapshot takePulseSnapshot(bool clearRejectedNoise) {
    PulseSnapshot snapshot;

    portENTER_CRITICAL(&pulseMux);
    snapshot.accepted = pulseCount;
    snapshot.rejectedNoise = rejectedNoisePulseCount;

    if (clearRejectedNoise) {
        rejectedNoisePulseCount = 0;
    }

    portEXIT_CRITICAL(&pulseMux);

    return snapshot;
}

void synchronizePulseBaseline() {
    previousPulseCount = takePulseSnapshot(true).accepted;
}

void resetSignalObservationWindow(int currentLevel) {
    observedSignalHigh = currentLevel == HIGH;
    observedSignalLow = currentLevel == LOW;
    observedSignalTransitions = 0;
    previousObservedSignalLevel = currentLevel;
}

}

void beginSensorManager() {
    pinMode(SMARTWATER_SENSOR_PIN, INPUT);

    resetSignalObservationWindow(digitalRead(SMARTWATER_SENSOR_PIN));

    attachInterrupt(
        digitalPinToInterrupt(SMARTWATER_SENSOR_PIN),
        handleFlowPulse,
        RISING
    );

    synchronizePulseBaseline();

    Serial.println("Flow sensor initialized.");
    Serial.print("Flow sensor GPIO: ");
    Serial.println(SMARTWATER_SENSOR_PIN);
    Serial.print("Calibration: ");
    Serial.print(SMARTWATER_PULSES_PER_LITER, 1);
    Serial.println(" pulses/L");
    Serial.print("Pulse noise filter: ");
    Serial.print(MINIMUM_PULSE_INTERVAL_US);
    Serial.println(" us minimum edge spacing");
}

void pollFlowSensorInput() {
    const int currentLevel = digitalRead(SMARTWATER_SENSOR_PIN);

    observedSignalHigh = observedSignalHigh || currentLevel == HIGH;
    observedSignalLow = observedSignalLow || currentLevel == LOW;

    if (currentLevel != previousObservedSignalLevel) {
        ++observedSignalTransitions;
        previousObservedSignalLevel = currentLevel;
    }
}

unsigned long getFlowPulseCount() {
    return static_cast<unsigned long>(takePulseSnapshot(false).accepted);
}

void setSensorMonitoring(bool enabled) {
    if (monitoring == enabled) {
        return;
    }

    // Discard the unfinished pulse window at both pause and resume. Pulses
    // received while paused can never appear as consumption after resuming.
    synchronizePulseBaseline();
    monitoring = enabled;
    flowRate = 0.0F;

    // Start a complete timing window after the state change. The main loop
    // obtains its current timestamp after processing input, avoiding underflow.
    lastSample = millis();

    Serial.println(
        monitoring
            ? "Monitoring resumed; flow sample window restarted."
            : "Monitoring paused; pending sensor pulses discarded."
    );
}

void updateFlowSensor(unsigned long elapsedMs) {
    pollFlowSensorInput();

    const int currentSignalLevel = digitalRead(SMARTWATER_SENSOR_PIN);
    const bool sampleObservedHigh = observedSignalHigh;
    const bool sampleObservedLow = observedSignalLow;
    const uint32_t sampleObservedTransitions = observedSignalTransitions;

    resetSignalObservationWindow(currentSignalLevel);

    const PulseSnapshot snapshot = takePulseSnapshot(true);
    const uint32_t currentPulseCount = snapshot.accepted;
    const uint32_t newPulses =
        currentPulseCount - previousPulseCount;

    previousPulseCount = currentPulseCount;

    if (snapshot.rejectedNoise > 0) {
        Serial.printf(
            "Sensor noise filter rejected %lu closely spaced pulse(s).\n",
            static_cast<unsigned long>(snapshot.rejectedNoise)
        );
    }

    const char* observedSignalDescription =
        sampleObservedHigh && sampleObservedLow
            ? "HIGH+LOW"
            : (sampleObservedHigh ? "HIGH only" : "LOW only");

    Serial.printf(
        "GPIO2 signal: %s now | observed: %s | transitions: %lu\n",
        currentSignalLevel == HIGH ? "HIGH" : "LOW",
        observedSignalDescription,
        static_cast<unsigned long>(sampleObservedTransitions)
    );

    if (!monitoring) {
        flowRate = 0.0F;

        Serial.printf(
            "Flow pulses: 0 | Total pulses: %lu\n"
            "Flow rate: 0.00 L/min\n"
            "Interval liters: 0.0000 L\n"
            "Total liters: %.4f L\n",
            static_cast<unsigned long>(currentPulseCount),
            totalLiters
        );

        return;
    }

    const float maximumPulseRatePerSecond =
        MAX_RATED_FLOW_LITERS_PER_MINUTE *
        static_cast<float>(SMARTWATER_PULSES_PER_LITER) / 60.0F;

    const float maximumPulsesForSample =
        maximumPulseRatePerSecond *
        (elapsedMs / 1000.0F) *
        PULSE_SAFETY_MARGIN;

    const uint32_t maximumPlausiblePulses = elapsedMs == 0
        ? 0
        : static_cast<uint32_t>(ceilf(maximumPulsesForSample));

    if (newPulses > maximumPlausiblePulses) {
        flowRate = 0.0F;

        Serial.printf(
            "WARNING: rejected implausible pulse burst: %lu pulses "
            "(maximum %lu for %lu ms)\n"
            "Flow rate: 0.00 L/min\n"
            "Total liters unchanged: %.4f L\n",
            static_cast<unsigned long>(newPulses),
            static_cast<unsigned long>(maximumPlausiblePulses),
            elapsedMs,
            totalLiters
        );

        return;
    }

    const float intervalLitersMeasured =
        static_cast<float>(newPulses) /
        static_cast<float>(SMARTWATER_PULSES_PER_LITER);

    flowRate = elapsedMs > 0 && newPulses > 0
        ? intervalLitersMeasured * (60000.0F / elapsedMs)
        : 0.0F;

    intervalLiters += intervalLitersMeasured;
    totalLiters += intervalLitersMeasured;

    Serial.printf(
        "Flow pulses: %lu | Total pulses: %lu\n"
        "Flow rate: %.2f L/min\n"
        "Interval liters: %.4f L\n"
        "Total liters: %.4f L\n",
        static_cast<unsigned long>(newPulses),
        static_cast<unsigned long>(currentPulseCount),
        flowRate,
        intervalLitersMeasured,
        totalLiters
    );
}
