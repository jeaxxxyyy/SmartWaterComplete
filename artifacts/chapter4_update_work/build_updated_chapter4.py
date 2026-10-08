from __future__ import annotations

import copy
import hashlib
import importlib.util
import os
import sys
import zipfile
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont
from docx import Document
from docx.enum.table import WD_CELL_VERTICAL_ALIGNMENT, WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_BREAK
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Inches, Pt, RGBColor


ROOT = Path(r"C:\laragon\capstone\SmartWater_Update")
REFERENCE = Path(r"C:\Users\Jeangxx\Downloads\Capstone\CHAPTER IV.docx")
OUTPUT = Path(r"C:\Users\Jeangxx\Downloads\Capstone\CHAPTER IV - Updated.docx")
WORK = ROOT / "artifacts" / "chapter4_update_work"
LEGACY_MODULE = ROOT / "artifacts" / "chapter4_ref_work" / "expand_chapter4.py"
EXPECTED_REFERENCE_HASH = "94f75d1aa853fa8b46bd1a9a29c6ee0b72d23056f3bf9a7d3e2fa46177e85401"


def load_legacy_dictionary():
    spec = importlib.util.spec_from_file_location("chapter4_legacy", LEGACY_MODULE)
    module = importlib.util.module_from_spec(spec)
    assert spec.loader is not None
    spec.loader.exec_module(module)
    return copy.deepcopy(module.DATA_DICTIONARY)


USE_CASES = [
    {
        "name": "Manage Households", "importance": "High", "actor": "Municipal Administrator", "type": "Essential, Detail",
        "scenario": "The administrator wants to create, review, update, suspend, reactivate, or archive a household account.",
        "brief": "Maintains household identity, service address, contact information, account number, access status, and meter-assignment readiness.",
        "trigger": "The administrator opens Customer Management.",
        "flow": ["The administrator signs in and opens Customer Management.", "The system displays searchable and filterable household records.", "The administrator creates a new household or selects an existing record.", "The administrator enters or updates the permitted account information.", "The system validates required fields, unique account details, role, and status.", "The system saves the household record, applies access changes, and logs the activity."],
        "sub": "The administrator may unassign a meter, reset an authorized household password, or restore an eligible account.",
        "alt": "Duplicate email or account numbers, incomplete addresses, invalid statuses, and unauthorized role changes are rejected.",
    },
    {
        "name": "Manage Meters and Rates", "importance": "High", "actor": "Municipal Administrator", "type": "Essential, Detail",
        "scenario": "The administrator wants to manage municipal smart meters and the billing and alert rules applied to households.",
        "brief": "Combines meter registration, assignment, lifecycle control, credential management, effective-dated rates, and alert configuration.",
        "trigger": "The administrator opens Device Management or Water Rate Management.",
        "flow": ["The administrator opens the required meter or rate module.", "The system displays registered devices or active billing configurations.", "For a meter, the administrator registers, assigns, reassigns, suspends, or decommissions the asset.", "For a rate, the administrator enters the water rate, fixed charge, subsidy, currency, effective date, and alert thresholds.", "The system validates identifiers, household eligibility, numeric ranges, and effective dates.", "The system saves the approved configuration and records the activity."],
        "sub": "The administrator may rotate credentials, issue a new activation code, and review calibration, firmware, last-seen, and maintenance information.",
        "alt": "Duplicate assets, ineligible assignments, invalid rate values, and unauthorized changes are rejected.",
    },
    {
        "name": "Generate Billing", "importance": "High", "actor": "Municipal Administrator", "type": "Essential, Detail",
        "scenario": "The administrator wants to generate a finalized monthly billing statement for an eligible household.",
        "brief": "Aggregates billable interval consumption, applies the effective configuration, and preserves a historical charge snapshot.",
        "trigger": "The administrator chooses Generate Statement in Billing Management.",
        "flow": ["The administrator selects the household and billing month.", "The system validates the household, period, readings, and applicable rate.", "The billing service totals liters and converts them to cubic meters.", "The service applies the water rate, fixed charge, subsidy, and opening balance.", "The system assigns a statement number and due date.", "The statement, charge breakdown, balance, and payment status are stored.", "The administrator reviews or downloads the finalized statement."],
        "sub": "The household may download only its own finalized statement as a PDF.",
        "alt": "A duplicate month, missing billable readings, or unavailable applicable rate prevents creation of a misleading statement.",
    },
    {
        "name": "Record Offline Payment and Receipt", "importance": "High", "actor": "Municipal Administrator", "type": "Essential, Detail",
        "scenario": "Authorized municipal staff want to record an offline payment and issue an official receipt.",
        "brief": "Records cash, bank-deposit, check, or other offline payments without processing money through the website.",
        "trigger": "The administrator selects Record Payment for an unpaid or partially paid statement.",
        "flow": ["The administrator opens the finalized statement.", "The administrator enters the amount, method, reference, notes, and received date.", "The system validates the remaining balance and payment details.", "The payment is stored in the offline-payment ledger.", "The statement amount paid, remaining balance, and status are recalculated.", "The system generates a unique receipt number and PDF receipt.", "The household can download the statement and related receipts."],
        "sub": "Multiple partial payments may be recorded until the remaining balance reaches zero.",
        "alt": "Zero, negative, or excessive amounts and payments against an ineligible statement are rejected.",
    },
    {
        "name": "Review Alerts Reports and Action Center", "importance": "High", "actor": "Municipal Administrator", "type": "Essential, Detail",
        "scenario": "The administrator wants to investigate alerts and prioritize municipal work requiring attention.",
        "brief": "Combines leak follow-up, analytics, reports, exports, failed notices, overdue statements, and the prioritized action center.",
        "trigger": "The administrator opens the Action Center, Leak Monitoring, Reports, Analytics, or Notifications.",
        "flow": ["The system combines open requests, overdue statements, leak follow-ups, and failed notices.", "The administrator selects an item, household, report category, period, or filter.", "The system validates authorization and input parameters.", "Related readings, alerts, billing data, notices, and summaries are retrieved.", "The administrator reviews evidence and records the required follow-up.", "The administrator resolves, marks false, reopens, retries, archives, or exports the supported record."],
        "sub": "Report previews and exports retain the selected household and period filters and write an audit entry.",
        "alt": "Invalid filters, empty export data, missing records, and unauthorized access return clear responses.",
    },
    {
        "name": "Manage Service Requests", "importance": "High", "actor": "Household User; Municipal Administrator", "type": "Essential, Detail",
        "scenario": "A household wants to request municipal assistance and staff want to track the response through resolution.",
        "brief": "Provides a referenced conversation for billing, leak, meter, account, interruption, and general service concerns.",
        "trigger": "The household opens Service Requests or the administrator opens the municipal request queue.",
        "flow": ["The household selects a category and enters the subject and description.", "The system creates a unique request reference and an open status.", "The household and authorized staff view the request conversation.", "The administrator assigns priority, ownership, and status.", "Either permitted participant adds a message; internal notes remain staff-only.", "The administrator resolves or reopens the request as needed.", "Updates are retained and may generate household notifications."],
        "sub": "A request may be associated with a billing statement when the concern involves a bill.",
        "alt": "Users cannot view another household's request, and invalid transitions or internal-note access are rejected.",
    },
    {
        "name": "View Live Consumption", "importance": "High", "actor": "Household User", "type": "Essential, Detail",
        "scenario": "The household user wants to view the latest water consumption and meter state.",
        "brief": "Displays current flow, interval consumption, estimated billing, leak state, meter availability, and recent activity.",
        "trigger": "The household user opens Dashboard or Live Monitoring.",
        "flow": ["The household user signs in.", "The browser requests the latest household-scoped reading and meter heartbeat.", "The server verifies ownership and retrieves current device and consumption state.", "The interface displays flow, liters, billing state, leak state, connection, and activity.", "The dashboard distinguishes live, delayed, offline, missing, and invalid states.", "The interface refreshes according to the configured polling interval."],
        "sub": "Low-bandwidth mode reduces polling and motion while preserving explicit refresh controls.",
        "alt": "When no assigned meter or valid reading exists, the interface shows a specific unavailable state instead of a misleading zero.",
    },
    {
        "name": "Review History and Bills", "importance": "High", "actor": "Household User", "type": "Essential, Detail",
        "scenario": "The household user wants to review past consumption and current or finalized billing information.",
        "brief": "Combines daily, weekly, monthly, or custom history with estimated billing details, statements, and receipts.",
        "trigger": "The user opens Consumption History or Estimated Billing.",
        "flow": ["The user selects the history or billing page.", "For history, the user selects a period or valid custom date range.", "The server retrieves only the household's readings and computes totals and chart data.", "For billing, the server selects the applicable rate and billable interval readings.", "If the current month is empty, the latest metered month is clearly identified.", "The interface displays estimates, finalized statements, balances, due dates, and receipts.", "The user downloads an owned statement or receipt when required."],
        "sub": "Daily, weekly, monthly, and detailed history remain available without exposing another household's data.",
        "alt": "Invalid ranges, missing readings, and missing rate information remain unavailable instead of being reported as zero.",
    },
    {
        "name": "Set Consumption Goal and Forecast", "importance": "Medium", "actor": "Household User", "type": "Supporting, Detail",
        "scenario": "The household wants to establish a monthly water-use goal and review projected end-of-month consumption.",
        "brief": "Stores one household goal per month and compares actual usage, remaining allowance, progress, and forecast.",
        "trigger": "The user opens Goals and Forecast and saves a target in liters.",
        "flow": ["The system loads the household's current-month goal and consumption.", "The user enters or updates a positive target volume.", "The system validates and stores the household-scoped goal.", "Current usage is compared with the target.", "The service estimates end-of-month consumption from valid readings.", "The interface displays progress, remaining allowance, and forecast status."],
        "sub": "The goal may be revised for the active month without changing other households or prior monthly records.",
        "alt": "Missing, zero, negative, or unauthorized goal values are rejected with validation feedback.",
    },
    {
        "name": "Manage Notifications and Preferences", "importance": "Medium", "actor": "Household User", "type": "Supporting, Detail",
        "scenario": "The household wants to control eligible in-app notices, language, quiet hours, and data-use preferences.",
        "brief": "Manages notification categories, read state, quiet hours, English or Filipino labels, and low-bandwidth behavior.",
        "trigger": "The user opens Notifications or Account Settings.",
        "flow": ["The system loads household-scoped notices and saved preferences.", "The user reads, marks, or removes an eligible notice from the personal view.", "The user selects notification categories and optional quiet hours.", "The user selects English or Filipino and chooses low-bandwidth mode.", "The system validates and stores the preferences.", "The interface immediately applies supported language and data-use changes."],
        "sub": "Critical account-security notices remain enabled, and user-side deletion does not erase the municipality's source record.",
        "alt": "Invalid quiet hours, unsupported language values, and access to another household's notices are rejected.",
    },
    {
        "name": "Manage Account Access and Recovery", "importance": "High", "actor": "Household User", "type": "Essential, Detail",
        "scenario": "The household wants to update contact information, change a password, or recover access.",
        "brief": "Protects household profile updates and password changes while preserving administrator-controlled account fields.",
        "trigger": "The user opens Account Settings or selects Forgot Password on the login page.",
        "flow": ["The authenticated user loads the household profile.", "The user updates permitted contact details or submits the current and new password.", "The system validates the request and protects role, status, and account number.", "For recovery, the user requests a reset link for an eligible approved account.", "The reset token and replacement password are validated.", "The profile or password is updated and security activity is recorded."],
        "sub": "An authorized administrator may reset a household password and revoke existing sessions.",
        "alt": "Incorrect current passwords, invalid reset tokens, ineligible accounts, and protected-field changes are rejected.",
    },
    {
        "name": "Install and Use Resilient Website", "importance": "Medium", "actor": "Household User", "type": "Supporting",
        "scenario": "The household wants an installable interface that remains understandable during weak or interrupted connectivity.",
        "brief": "Provides the web manifest, service worker, app-shell caching, user-scoped session snapshots, and accessible responsive controls.",
        "trigger": "The user installs the website or revisits it with limited connectivity.",
        "flow": ["The browser loads the web manifest and registers the service worker.", "Static application-shell resources are cached for resilient loading.", "Authenticated API responses are excluded from service-worker storage.", "User-scoped dashboard snapshots may be retained only in session storage.", "The interface reports offline or unavailable live state rather than showing stale data as current.", "The user reconnects or refreshes to obtain authoritative meter information."],
        "sub": "Theme, responsive navigation, keyboard labels, and reduced-motion behavior support accessible use across devices.",
        "alt": "Without a network connection, actions requiring current server data remain unavailable and are not silently queued as successful.",
    },
    {
        "name": "Activate Device", "importance": "High", "actor": "Smart Meter / IoT Device", "type": "Essential",
        "scenario": "A registered M5Stack smart meter wants to exchange a one-time code for a scoped device credential.",
        "brief": "Binds the physical hardware to the municipal device record and activates authenticated telemetry access.",
        "trigger": "The technician enters the administrator-issued activation code on the registered device.",
        "flow": ["The administrator registers the meter and issues a short-lived activation code.", "The device submits its UUID, activation code, hardware identity, and firmware version.", "The server verifies that the code is valid, unused, and unexpired.", "The server checks device status and hardware-binding rules.", "The hardware identity is bound and the device is marked active.", "The server issues a scoped credential.", "The M5Stack stores the credential without printing the token to the serial console."],
        "sub": "Credential rotation or authorized reassignment requires a new activation step.",
        "alt": "Expired, reused, incorrect, or mismatched activation information is rejected and no credential is issued.",
    },
    {
        "name": "Upload Water Reading", "importance": "High", "actor": "Smart Meter / IoT Device", "type": "Essential",
        "scenario": "An activated smart meter wants to submit a measured water-consumption interval.",
        "brief": "Authenticates the device, stores the interval once, and returns the server's monthly total, water limit, and leak state.",
        "trigger": "Firmware version 1.1.1 reaches the configured upload interval with a pending measurement.",
        "flow": ["The sensor counts valid pulses and calculates flow rate and interval liters.", "The device creates a stable reading identifier and stores the pending interval.", "The reading is submitted using the scoped device credential.", "The server derives the device and household from the authenticated identity.", "The server validates and stores the interval idempotently.", "Laravel evaluates thresholds and returns the accepted reading ID, monthly total, water limit, and leak state.", "The device verifies that the response acknowledges the same reading ID.", "Only then does the meter clear the pending interval and advance its sequence."],
        "sub": "The display separates the persistent lifetime Meter value from Laravel's authoritative monthly Period value.",
        "alt": "Failed requests and malformed successful responses remain queued for retry; duplicate identifiers never double-count consumption.",
    },
    {
        "name": "Report Meter Health", "importance": "Medium", "actor": "Smart Meter / IoT Device", "type": "Supporting",
        "scenario": "The smart meter wants to report connection, firmware, and operational health to the server.",
        "brief": "Updates municipal visibility of connectivity, last-seen activity, telemetry state, firmware version, and diagnostics.",
        "trigger": "The device activates, uploads telemetry, reconnects, or reaches a health-reporting interval.",
        "flow": ["The device evaluates Wi-Fi state and prepares hardware and firmware information.", "Health information accompanies authenticated telemetry where applicable.", "The server validates the device identity and household assignment.", "Last-seen, last-telemetry, firmware, signal, and operational fields are updated.", "Server connectivity is derived from a successful acknowledged response.", "The administrator workspace displays the latest device and maintenance status."],
        "sub": "Sampling begins before network activation so setup delays do not distort the first measured interval.",
        "alt": "Invalid credentials or mismatched hardware are rejected; network loss retains pending state until reconnection.",
    },
]


NEW_TABLES = {
    "billing_payments": [
        ("id", "BIGINT", "20", "Primary key of the recorded offline payment."),
        ("billing_statement_id", "BIGINT", "20", "Finalized statement receiving the payment."),
        ("recorded_by", "BIGINT", "20", "Administrator who recorded the payment; nullable after staff deletion."),
        ("receipt_number", "VARCHAR", "255", "Unique official receipt identifier."),
        ("amount", "DECIMAL", "12,2", "Amount received through an offline method."),
        ("payment_method", "VARCHAR", "255", "Cash, bank deposit, check, or other offline method."),
        ("reference_number", "VARCHAR", "255", "Optional external transaction or deposit reference."),
        ("notes", "TEXT", "-", "Optional municipal payment notes."),
        ("received_at", "DATETIME", "-", "Date and time the payment was received."),
        ("created_at", "DATETIME", "-", "Record creation timestamp."),
        ("updated_at", "DATETIME", "-", "Record update timestamp."),
    ],
    "service_requests": [
        ("id", "BIGINT", "20", "Primary key of the service request."),
        ("reference_number", "VARCHAR", "255", "Unique household-facing request reference."),
        ("user_id", "BIGINT", "20", "Household that owns the request."),
        ("assigned_to", "BIGINT", "20", "Administrator assigned to the request; nullable."),
        ("billing_statement_id", "BIGINT", "20", "Optional related billing statement."),
        ("category", "VARCHAR", "255", "Billing, leak, account, interruption, meter, or general category."),
        ("subject", "VARCHAR", "255", "Short description supplied by the household."),
        ("description", "TEXT", "-", "Initial detailed concern or request."),
        ("priority", "VARCHAR", "255", "Municipal priority such as normal, high, or urgent."),
        ("status", "VARCHAR", "255", "Workflow state such as open, in progress, resolved, or closed."),
        ("resolved_at", "DATETIME", "-", "Date and time the request was resolved."),
        ("created_at", "DATETIME", "-", "Record creation timestamp."),
        ("updated_at", "DATETIME", "-", "Record update timestamp."),
    ],
    "service_request_messages": [
        ("id", "BIGINT", "20", "Primary key of the request message."),
        ("service_request_id", "BIGINT", "20", "Service request containing the message."),
        ("user_id", "BIGINT", "20", "Household or administrator who authored the message."),
        ("message", "TEXT", "-", "Conversation message or staff note."),
        ("is_internal", "BOOLEAN", "1", "Indicates a staff-only message hidden from the household."),
        ("created_at", "DATETIME", "-", "Record creation timestamp."),
        ("updated_at", "DATETIME", "-", "Record update timestamp."),
    ],
    "notification_preferences": [
        ("id", "BIGINT", "20", "Primary key of the household preference record."),
        ("user_id", "BIGINT", "20", "Unique household that owns the preferences."),
        ("announcements", "BOOLEAN", "1", "Enables general municipal announcements."),
        ("billing_updates", "BOOLEAN", "1", "Enables billing-related updates."),
        ("consumption_alerts", "BOOLEAN", "1", "Enables consumption-change notifications."),
        ("leak_alerts", "BOOLEAN", "1", "Enables leak-related notifications."),
        ("service_request_updates", "BOOLEAN", "1", "Enables service-request status and reply notifications."),
        ("quiet_hours_start", "TIME", "-", "Optional start of the quiet-hours window."),
        ("quiet_hours_end", "TIME", "-", "Optional end of the quiet-hours window."),
        ("created_at", "DATETIME", "-", "Record creation timestamp."),
        ("updated_at", "DATETIME", "-", "Record update timestamp."),
    ],
    "consumption_goals": [
        ("id", "BIGINT", "20", "Primary key of the monthly goal."),
        ("user_id", "BIGINT", "20", "Household that owns the goal."),
        ("month", "DATE", "-", "Month to which the goal applies."),
        ("target_liters", "DECIMAL", "12,2", "Household target consumption in liters."),
        ("created_at", "DATETIME", "-", "Record creation timestamp."),
        ("updated_at", "DATETIME", "-", "Record update timestamp."),
    ],
}


DISPLAY_NAMES = {
    "users": "Users", "devices": "Devices", "water_readings": "Water Readings",
    "billings": "Billing Configurations", "billing_statements": "Billing Statements",
    "billing_payments": "Billing Payments", "notifications": "Notifications",
    "notification_preferences": "Notification Preferences", "consumption_goals": "Consumption Goals",
    "service_requests": "Service Requests", "service_request_messages": "Service Request Messages",
    "leak_alerts": "Leak Alerts", "audit_logs": "Audit Logs",
}


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def font(size: int, bold: bool = False):
    filename = "arialbd.ttf" if bold else "arial.ttf"
    candidates = [Path(r"C:\Windows\Fonts") / filename, Path(filename)]
    for candidate in candidates:
        try:
            return ImageFont.truetype(str(candidate), size)
        except OSError:
            pass
    return ImageFont.load_default()


def fit_text(draw, text, box, max_size=24, min_size=14, bold=False):
    x1, y1, x2, y2 = box
    for size in range(max_size, min_size - 1, -1):
        f = font(size, bold)
        bounds = draw.multiline_textbbox((0, 0), text, font=f, spacing=2, align="center")
        if bounds[2] - bounds[0] <= x2 - x1 - 14 and bounds[3] - bounds[1] <= y2 - y1 - 10:
            return f
    return font(min_size, bold)


def draw_box(draw, box, text, fill="#E9F3F8", outline="#2A77A0", bold=False):
    draw.rounded_rectangle(box, radius=12, fill=fill, outline=outline, width=2)
    f = fit_text(draw, text, box, 22, 13, bold)
    x1, y1, x2, y2 = box
    draw.multiline_text(((x1 + x2) / 2, (y1 + y2) / 2), text, anchor="mm", align="center", spacing=2, font=f, fill="#172033")


def make_use_case_diagram(path: Path):
    width, height = 1448, 662
    img = Image.new("RGB", (width, height), "white")
    draw = ImageDraw.Draw(img)
    draw.text((width // 2, 20), "SMART WATER MONITORING CONSUMPTION SYSTEM", anchor="ma", font=font(27, True), fill="#173F5F")
    groups = [
        ("MUNICIPAL\nADMINISTRATOR", 72, ["Manage\nhouseholds", "Manage meters\nand rates", "Generate\nbilling", "Record payment\nand receipt", "Alerts reports\nand action center", "Manage service\nrequests"]),
        ("HOUSEHOLD\nUSER", 286, ["Manage service\nrequests", "View live\nconsumption", "History bills\nand receipts", "Goals and\nforecast", "Notices and\npreferences", "Account access\nand recovery", "Install resilient\nwebsite"]),
        ("SMART METER\nIOT DEVICE", 518, ["Activate\ndevice", "Upload water\nreading", "Report meter\nhealth"]),
    ]
    for actor, y, labels in groups:
        actor_box = (22, y, 175, y + 112)
        draw_box(draw, actor_box, actor, fill="#D8EEF3", outline="#137F8C", bold=True)
        n = len(labels)
        gap = 12
        left, right = 215, width - 20
        box_w = (right - left - gap * (n - 1)) / n
        for idx, label in enumerate(labels):
            x1 = int(left + idx * (box_w + gap))
            box = (x1, y + 12, int(x1 + box_w), y + 100)
            draw.line((175, y + 56, x1, y + 56), fill="#4A90B8", width=2)
            draw_box(draw, box, label)
    img.save(path)


def make_admin_map(path: Path):
    width, height = 1282, 729
    img = Image.new("RGB", (width, height), "#F4F8FA")
    d = ImageDraw.Draw(img)
    d.rectangle((0, 0, width, 76), fill="#0E596D")
    d.text((32, 38), "ADMINISTRATOR OPERATIONS WORKSPACE", anchor="lm", font=font(27, True), fill="white")
    d.rectangle((0, 76, 278, height), fill="#E5F1F4")
    nav = ["Overview", "Customer Management", "Device Management", "Water Rate Management", "Billing and Payments", "Service Requests", "Consumption Monitoring", "Leak Monitoring", "Reports", "Notification Management"]
    y = 98
    for idx, item in enumerate(nav):
        if idx == 0:
            d.rounded_rectangle((20, y - 5, 258, y + 43), radius=8, fill="#147B8A")
        d.text((38, y + 18), item, anchor="lm", font=font(16, idx == 0), fill="white" if idx == 0 else "#173F5F")
        y += 56
    cards = [(315, 105, 520, 215, "Active households"), (545, 105, 750, 215, "Online meters"), (775, 105, 980, 215, "Open requests"), (1005, 105, 1210, 215, "Overdue bills")]
    for x1, y1, x2, y2, label in cards:
        d.rounded_rectangle((x1, y1, x2, y2), radius=12, fill="white", outline="#BDD7E2", width=2)
        d.text((x1 + 18, y1 + 28), label, font=font(16, True), fill="#173F5F")
        d.text((x1 + 18, y1 + 73), "--", font=font(25, True), fill="#147B8A")
    panels = [(315, 248, 1210, 422, "ACTION CENTER", "Open service requests, overdue statements, leak follow-ups, and failed notices"), (315, 452, 755, 690, "BILLING AND PAYMENT LEDGER", "Statements, balances, due dates, offline payments, and PDF receipts"), (780, 452, 1210, 690, "MUNICIPAL OPERATIONS", "Reports, alerts, notification delivery, device health, and audit records")]
    for x1, y1, x2, y2, title, detail in panels:
        d.rounded_rectangle((x1, y1, x2, y2), radius=12, fill="white", outline="#BDD7E2", width=2)
        d.text((x1 + 22, y1 + 32), title, font=font(18, True), fill="#173F5F")
        d.multiline_text((x1 + 22, y1 + 75), detail, font=font(16), fill="#334155", spacing=8)
    img.save(path)


def make_household_map(path: Path):
    width, height = 1442, 841
    img = Image.new("RGB", (width, height), "#F4F8FA")
    d = ImageDraw.Draw(img)
    d.rectangle((0, 0, width, 80), fill="#0E7A83")
    d.text((34, 40), "HOUSEHOLD MONITORING WORKSPACE", anchor="lm", font=font(28, True), fill="white")
    d.rectangle((0, 80, 290, height), fill="#E5F3F4")
    nav = ["Dashboard", "Live Monitoring", "Consumption History", "Estimated Billing", "Goals and Forecast", "Service Requests", "Leak Detection", "Notifications", "Account Settings"]
    y = 108
    for idx, item in enumerate(nav):
        if idx == 0:
            d.rounded_rectangle((22, y - 6, 268, y + 44), radius=8, fill="#1B8992")
        d.text((42, y + 19), item, anchor="lm", font=font(17, idx == 0), fill="white" if idx == 0 else "#173F5F")
        y += 62
    cards = [(330, 110, 545, 225, "Current flow"), (570, 110, 785, 225, "Today's usage"), (810, 110, 1025, 225, "Estimated bill"), (1050, 110, 1265, 225, "Meter status")]
    for x1, y1, x2, y2, label in cards:
        d.rounded_rectangle((x1, y1, x2, y2), radius=12, fill="white", outline="#B7DDE0", width=2)
        d.text((x1 + 18, y1 + 28), label, font=font(17, True), fill="#173F5F")
        d.text((x1 + 18, y1 + 76), "--", font=font(26, True), fill="#13838A")
    panels = [(330, 260, 855, 500, "USAGE TREND AND FORECAST", "Daily, weekly, and monthly consumption\nGoal progress and end-of-month projection"), (880, 260, 1395, 500, "BILLING AND RECEIPTS", "Latest estimate or finalized statement\nBalances, due dates, PDFs, and receipts"), (330, 530, 855, 790, "SERVICE REQUESTS", "Submit concerns and review municipal replies\nTrack reference, category, priority, and status"), (880, 530, 1395, 790, "PREFERENCES AND ACCESS", "Notification categories and quiet hours\nEnglish or Filipino labels and low-bandwidth mode")]
    for x1, y1, x2, y2, title, detail in panels:
        d.rounded_rectangle((x1, y1, x2, y2), radius=12, fill="white", outline="#B7DDE0", width=2)
        d.text((x1 + 24, y1 + 36), title, font=font(19, True), fill="#173F5F")
        d.multiline_text((x1 + 24, y1 + 90), detail, font=font(17), fill="#334155", spacing=10)
    img.save(path)


def make_erd(path: Path):
    width, height = 1856, 813
    img = Image.new("RGB", (width, height), "white")
    d = ImageDraw.Draw(img)
    d.text((45, 28), "SMART WATER OPERATIONAL ENTITY RELATIONSHIPS", font=font(37, True), fill="#173F5F")
    rows = [
        [("USERS", "accounts and preferences"), ("DEVICES", "household meter registry"), ("WATER READINGS", "interval telemetry"), ("BILLINGS", "rates and alert rules")],
        [("BILLING STATEMENTS", "monthly charge snapshots"), ("BILLING PAYMENTS", "offline payment ledger"), ("NOTIFICATIONS", "household and admin notices"), ("LEAK ALERTS", "incident workflow")],
        [("SERVICE REQUESTS", "tracked resident concerns"), ("REQUEST MESSAGES", "conversation and notes"), ("NOTIFICATION PREFS", "categories and quiet hours"), ("CONSUMPTION GOALS", "monthly target liters"), ("AUDIT LOGS", "security and admin activity")],
    ]
    row_y = [118, 350, 582]
    centers = {}
    for row_index, entries in enumerate(rows):
        gap = 24
        left, right = 48, width - 48
        box_w = (right - left - gap * (len(entries) - 1)) / len(entries)
        for idx, (title, detail) in enumerate(entries):
            x1 = int(left + idx * (box_w + gap)); y1 = row_y[row_index]
            x2 = int(x1 + box_w); y2 = y1 + 152
            d.rounded_rectangle((x1, y1, x2, y2), radius=12, fill="#F2F7FA", outline="#173F5F", width=3)
            d.rectangle((x1, y1, x2, y1 + 48), fill="#173F5F")
            d.text(((x1 + x2) // 2, y1 + 24), title, anchor="mm", font=fit_text(d, title, (x1, y1, x2, y1 + 48), 19, 13, True), fill="white")
            d.multiline_text(((x1 + x2) // 2, y1 + 98), detail, anchor="mm", align="center", font=font(15), fill="#253342", spacing=3)
            centers[title] = ((x1 + x2) // 2, y1, y2)
    def arrow(a, b):
        ax, _, ab = centers[a]; bx, bt, _ = centers[b]
        d.line((ax, ab, bx, bt), fill="#2A77A0", width=3)
        d.polygon([(bx, bt), (bx - 7, bt - 12), (bx + 7, bt - 12)], fill="#2A77A0")
    for a, b in [("USERS", "BILLING STATEMENTS"), ("DEVICES", "BILLING STATEMENTS"), ("WATER READINGS", "NOTIFICATIONS"), ("WATER READINGS", "LEAK ALERTS"), ("BILLING STATEMENTS", "BILLING PAYMENTS"), ("BILLING STATEMENTS", "SERVICE REQUESTS"), ("USERS", "NOTIFICATION PREFS"), ("USERS", "CONSUMPTION GOALS"), ("SERVICE REQUESTS", "REQUEST MESSAGES")]:
        arrow(a, b)
    img.save(path)


def para_text(element) -> str:
    return "".join(node.text or "" for node in element.xpath(".//w:t")).strip()


def find_paragraph(doc, exact: str):
    return next(p for p in doc.paragraphs if p.text.strip() == exact)


def move_before(target, element):
    target._p.addprevious(element)


def insert_paragraph_after(doc, paragraph, text):
    new_paragraph = doc.add_paragraph(text)
    paragraph._p.addnext(new_paragraph._p)
    set_paragraph_text(new_paragraph, text)
    return new_paragraph


def delete_between(doc, start_text: str, end_text: str):
    body = doc._element.body
    children = list(body)
    start = next(i for i, child in enumerate(children) if child.tag == qn("w:p") and para_text(child) == start_text)
    end = next(i for i, child in enumerate(children) if i > start and child.tag == qn("w:p") and para_text(child) == end_text)
    for child in children[start + 1:end]:
        body.remove(child)


def set_cell_margins(cell, top=75, start=90, bottom=75, end=90):
    tc_pr = cell._tc.get_or_add_tcPr()
    tc_mar = tc_pr.first_child_found_in("w:tcMar")
    if tc_mar is None:
        tc_mar = OxmlElement("w:tcMar")
        tc_pr.append(tc_mar)
    for edge, value in (("top", top), ("start", start), ("bottom", bottom), ("end", end)):
        node = tc_mar.find(qn("w:" + edge))
        if node is None:
            node = OxmlElement("w:" + edge)
            tc_mar.append(node)
        node.set(qn("w:w"), str(value)); node.set(qn("w:type"), "dxa")


def set_widths(table, widths):
    table.autofit = False
    grid = table._tbl.tblGrid
    for child in list(grid):
        grid.remove(child)
    for width in widths:
        col = OxmlElement("w:gridCol"); col.set(qn("w:w"), str(int(width * 1440))); grid.append(col)
    for row in table.rows:
        for idx, cell in enumerate(row.cells):
            width = widths[min(idx, len(widths) - 1)]
            cell.width = Inches(width)
            tc_w = cell._tc.get_or_add_tcPr().first_child_found_in("w:tcW")
            if tc_w is None:
                tc_w = OxmlElement("w:tcW"); cell._tc.get_or_add_tcPr().append(tc_w)
            tc_w.set(qn("w:w"), str(int(width * 1440))); tc_w.set(qn("w:type"), "dxa")


def format_cell(cell, size=9.5, bold=False, center=False, vertical_margin=75):
    cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
    set_cell_margins(cell, top=vertical_margin, bottom=vertical_margin)
    for paragraph in cell.paragraphs:
        paragraph.paragraph_format.space_before = Pt(0)
        paragraph.paragraph_format.space_after = Pt(0)
        paragraph.paragraph_format.line_spacing = 1.0
        paragraph.alignment = WD_ALIGN_PARAGRAPH.CENTER if center else WD_ALIGN_PARAGRAPH.LEFT
        for run in paragraph.runs:
            run.font.name = "Arial"
            run._element.get_or_add_rPr().get_or_add_rFonts().set(qn("w:ascii"), "Arial")
            run._element.get_or_add_rPr().get_or_add_rFonts().set(qn("w:hAnsi"), "Arial")
            run.font.size = Pt(size); run.font.bold = bold


def add_page_break_before(doc, target):
    paragraph = doc.add_paragraph()
    break_run = paragraph.add_run(".")
    break_run.font.size = Pt(2)
    break_run.font.color.rgb = RGBColor(255, 255, 255)
    paragraph.paragraph_format.page_break_before = True
    paragraph.paragraph_format.keep_with_next = False
    paragraph.paragraph_format.keep_together = False
    paragraph.paragraph_format.line_spacing = Pt(2)
    paragraph.paragraph_format.space_after = Pt(3)
    move_before(target, paragraph._p)


def add_section_break_before(doc, target):
    paragraph = doc.add_paragraph()
    ppr = paragraph._p.get_or_add_pPr()
    sectpr = copy.deepcopy(doc.sections[-1]._sectPr)
    section_type = sectpr.find(qn("w:type"))
    if section_type is None:
        section_type = OxmlElement("w:type")
        sectpr.insert(0, section_type)
    section_type.set(qn("w:val"), "nextPage")
    ppr.append(sectpr)
    move_before(target, paragraph._p)


def add_caption_before(doc, target, text):
    paragraph = doc.add_paragraph(text, style="Caption")
    paragraph.alignment = WD_ALIGN_PARAGRAPH.CENTER
    paragraph.paragraph_format.space_before = Pt(3)
    paragraph.paragraph_format.space_after = Pt(0)
    paragraph.paragraph_format.keep_with_next = False
    paragraph.paragraph_format.keep_together = True
    for run in paragraph.runs:
        run.font.name = "Arial"; run.font.size = Pt(10); run.font.bold = True; run.font.italic = True
        run.font.color.rgb = RGBColor(0, 0, 0)
    move_before(target, paragraph._p)


def create_use_case_table(doc, uc, index):
    table = doc.add_table(rows=8, cols=3)
    table.style = "Table Grid"; table.alignment = WD_TABLE_ALIGNMENT.CENTER
    set_widths(table, [2.0, 1.85, 1.90])
    table.cell(1, 1).merge(table.cell(1, 2))
    for row_index in range(2, 8):
        table.cell(row_index, 0).merge(table.cell(row_index, 2))
    table.cell(0, 0).text = f"Use Case Name:\n{uc['name']}"
    table.cell(0, 1).text = f"ID:{index}"
    table.cell(0, 2).text = f"Important Level:\n{uc['importance']}"
    table.cell(1, 0).text = f"Primary Actor:\n{uc['actor']}"
    table.cell(1, 1).text = f"Use Case Type: {uc['type']}"
    table.cell(2, 0).text = f"Scenario: {uc['scenario']}"
    table.cell(3, 0).text = f"Brief Description: {uc['brief']}"
    table.cell(4, 0).text = f"Trigger: {uc['trigger']}"
    table.cell(5, 0).text = "Normal Flow of Events:\n" + "\n".join(f"{i}. {item}" for i, item in enumerate(uc["flow"], 1))
    table.cell(6, 0).text = f"Sub Flows:\n{uc['sub']}"
    table.cell(7, 0).text = f"Alternative/Exceptional Flows:\n{uc['alt']}"
    for row_index, row in enumerate(table.rows):
        cant_split = OxmlElement("w:cantSplit"); row._tr.get_or_add_trPr().append(cant_split)
        for cell in row.cells:
            format_cell(cell, 8.6 if row_index == 5 else 8.9, center=row_index < 5, vertical_margin=50)
            for paragraph in cell.paragraphs:
                paragraph.paragraph_format.keep_with_next = row_index < 7
    return table


def create_dictionary_table(doc, fields):
    table = doc.add_table(rows=1, cols=4)
    table.style = "Table Grid"; table.alignment = WD_TABLE_ALIGNMENT.CENTER
    set_widths(table, [1.42, 1.00, 0.82, 2.51])
    for idx, label in enumerate(("Field Name", "Data Type", "Length", "Description")):
        cell = table.rows[0].cells[idx]; cell.text = label
        shade = OxmlElement("w:shd"); shade.set(qn("w:fill"), "D9EAF2"); cell._tc.get_or_add_tcPr().append(shade)
        format_cell(cell, 8.2, bold=True, center=True)
        for paragraph in cell.paragraphs:
            paragraph.paragraph_format.keep_with_next = True
    repeat = OxmlElement("w:tblHeader"); repeat.set(qn("w:val"), "true"); table.rows[0]._tr.get_or_add_trPr().append(repeat)
    for field in fields:
        cells = table.add_row().cells
        for idx, value in enumerate(field):
            cells[idx].text = str(value)
            format_cell(cells[idx], 7.6, bold=idx == 0, center=idx < 3)
        cant_split = OxmlElement("w:cantSplit"); table.rows[-1]._tr.get_or_add_trPr().append(cant_split)
    return table


def set_table_page_break_before(table):
    paragraph = table.cell(0, 0).paragraphs[0]
    ppr = paragraph._p.get_or_add_pPr()
    if not ppr.xpath('./w:pageBreakBefore'):
        ppr.append(OxmlElement("w:pageBreakBefore"))


def set_paragraph_text(paragraph, text, *, justify=True, first_indent=True, bold=False):
    paragraph.clear(); run = paragraph.add_run(text)
    run.font.name = "Arial"; run.font.size = Pt(11); run.font.bold = bold
    run._element.get_or_add_rPr().get_or_add_rFonts().set(qn("w:ascii"), "Arial")
    run._element.get_or_add_rPr().get_or_add_rFonts().set(qn("w:hAnsi"), "Arial")
    paragraph.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY if justify else WD_ALIGN_PARAGRAPH.LEFT
    paragraph.paragraph_format.first_line_indent = Inches(0.5) if first_indent else Inches(0)
    paragraph.paragraph_format.line_spacing = 1.5
    paragraph.paragraph_format.space_after = Pt(6)


def build_dictionary():
    data = load_legacy_dictionary()
    users = data["users"]
    insert_at = next(i for i, row in enumerate(users) if row[0] == "is_active")
    users[insert_at:insert_at] = [
        ("preferred_locale", "VARCHAR", "5", "Preferred interface language: English or Filipino."),
        ("low_bandwidth_mode", "BOOLEAN", "1", "Reduces polling and motion for lower-data use."),
    ]
    billings = data["billings"]
    insert_at = next(i for i, row in enumerate(billings) if row[0] == "consumption_alert_unit")
    billings.insert(insert_at, ("consumption_change_threshold_percent", "DECIMAL", "8,2", "Percentage change from the first-month baseline that triggers an alert."))
    statements = data["billing_statements"]
    statements.insert(1, ("statement_number", "VARCHAR", "255", "Unique municipal statement identifier."))
    insert_at = next(i for i, row in enumerate(statements) if row[0] == "opening_balance")
    statements.insert(insert_at, ("due_date", "DATE", "-", "Payment due date shown on the finalized statement."))
    ordered = {
        "users": data["users"], "devices": data["devices"], "water_readings": data["water_readings"],
        "billings": data["billings"], "billing_statements": data["billing_statements"],
        "billing_payments": NEW_TABLES["billing_payments"], "notifications": data["notifications"],
        "notification_preferences": NEW_TABLES["notification_preferences"], "consumption_goals": NEW_TABLES["consumption_goals"],
        "service_requests": NEW_TABLES["service_requests"], "service_request_messages": NEW_TABLES["service_request_messages"],
        "leak_alerts": data["leak_alerts"], "audit_logs": data["audit_logs"],
    }
    return ordered


def replace_media(docx_path: Path, replacements):
    temp = docx_path.with_suffix(".media-patched.docx")
    with zipfile.ZipFile(docx_path, "r") as source, zipfile.ZipFile(temp, "w") as target:
        for item in source.infolist():
            payload = replacements.get(item.filename, source.read(item.filename))
            target.writestr(item, payload)
    os.replace(temp, docx_path)


def build():
    if sha256(REFERENCE) != EXPECTED_REFERENCE_HASH:
        raise RuntimeError("The Chapter IV reference changed after distillation; inspect it again before editing.")
    WORK.mkdir(parents=True, exist_ok=True)
    use_case_png = WORK / "updated_use_case.png"
    admin_png = WORK / "updated_admin_interface.png"
    household_png = WORK / "updated_household_interface.png"
    erd_png = WORK / "updated_erd.png"
    make_use_case_diagram(use_case_png); make_admin_map(admin_png); make_household_map(household_png); make_erd(erd_png)

    doc = Document(REFERENCE)
    requirement = next(p for p in doc.paragraphs if p.text.startswith("Requirements analysis identified three actors"))
    set_paragraph_text(requirement, "Requirements analysis identified three actors: the municipal administrator, household user, and smart meter. Administrators manage accounts, devices, rates, billing, payments, requests, alerts, reports, notifications, and prioritized actions. Households monitor consumption and bills, set goals, request service, and manage preferences and access. Smart meters activate securely, retain pending telemetry, and accept server-authoritative monthly totals, limits, and leak states after matching acknowledgments.")

    use_heading = find_paragraph(doc, "Use Case Desciption")
    use_heading.text = "Use Case Description"
    use_heading.paragraph_format.page_break_before = True
    for run in use_heading.runs:
        run.font.name = "Arial"; run.font.size = Pt(12); run.font.bold = True
    delete_between(doc, "Use Case Description", "4.2 System Design, Software, and Processes")
    target_42 = find_paragraph(doc, "4.2 System Design, Software, and Processes")
    intro = doc.add_paragraph("The following use-case descriptions document the implemented workflows of the municipal administrator, household user, and commissioned smart meter. Each use case includes the normal sequence, supporting paths, and exceptional conditions.")
    set_paragraph_text(intro, intro.text)
    move_before(target_42, intro._p)
    for index, uc in enumerate(USE_CASES, 1):
        table = create_use_case_table(doc, uc, index)
        move_before(target_42, table._tbl)
        add_caption_before(doc, target_42, f"Table 4.1.{index} {uc['name']}")

    design = target_42
    design.text = "4.2 System Design, Software, and Processes"
    next_body = next(p for p in doc.paragraphs if p.text.startswith("The system uses an Internet of Things client-server design"))
    set_paragraph_text(next_body, "The system uses an Internet of Things client-server design. Firmware version 1.1.1 on the M5Stack CoreS3 measures sensor pulses, maintains a lifetime meter value, and stores unsent interval telemetry for safe retry. The Laravel server remains authoritative for identity, ownership, the monthly billing-period total, configured water limits, leak evaluation, billing, payments, service requests, notifications, goals, and audit records. The browser provides role-specific workspaces, an installable application shell, user-scoped session snapshots, multilingual labels, theme controls, and an optional low-bandwidth mode. Authenticated API responses are not cached by the service worker, and live meter information always requires a current server response.")
    output_heading = find_paragraph(doc, "Output and User Interface Design")
    output_heading.text = "Output and User Interface Design"
    insert_paragraph_after(doc, output_heading, "The household interface presents live readings, history, estimated and finalized billing, statements and receipts, leak information, goals and forecasts, service requests, notifications, preferences, and account controls. The administrator interface centralizes household and meter management, rates, billing and offline payments, service requests, monitoring, leak follow-up, reports, notification delivery, audit information, and the action center. Both workspaces use responsive navigation, accessible controls, theme support, and explicit unavailable states when current data cannot be retrieved.")
    for paragraph in doc.paragraphs:
        if paragraph.text.strip() == "Figure 4.2.3 Admin (Dashboard) Interface":
            paragraph.text = "Figure 4.2.3 Updated Admin (Dashboard) Interface"
        elif paragraph.text.strip() == "Figure 4.2.4 Household (Dashboard) Interface":
            paragraph.text = "Figure 4.2.4 Updated Household (Dashboard) Interface"
        for run in paragraph.runs:
            if paragraph.text.startswith("Figure 4.2."):
                run.font.name = "Arial"; run.font.size = Pt(10); run.font.bold = True; run.font.italic = True

    erd_heading = find_paragraph(doc, "4.3 Entity Relationship Diagram")
    # Keep the existing figure immediately after the heading; add its explanation after the heading instead.
    insert_paragraph_after(doc, erd_heading, "The operational schema links users to devices, readings, billing configurations, monthly statements, recorded payments, notices, preferences, goals, service requests, request messages, leak incidents, and audit records. The diagram summarizes the principal relationships; detailed columns are documented in Section 4.4.")

    delete_between(doc, "4.4 Data Dictionary", "4.5 Development and Testing")
    target_45 = find_paragraph(doc, "4.5 Development and Testing")
    dict_intro = doc.add_paragraph("The following data dictionary describes the operational tables used for monitoring, billing, resident services, preferences, alerts, and administration. Framework-support tables for cache, jobs, sessions, password resets, migrations, and personal access tokens are omitted for clarity.")
    set_paragraph_text(dict_intro, dict_intro.text)
    move_before(target_45, dict_intro._p)
    for index, (table_name, fields) in enumerate(build_dictionary().items(), 1):
        table = create_dictionary_table(doc, fields)
        move_before(target_45, table._tbl)
        add_caption_before(doc, target_45, f"Table 4.4.{index} Database Table of {DISPLAY_NAMES[table_name]}")

    development = next(p for p in doc.paragraphs if p.text.startswith("Continuous development and testing were conducted"))
    set_paragraph_text(development, "Development and verification covered authentication and account restrictions; household and meter management; device activation, credential rotation, reassignment, and health; idempotent telemetry; server-authoritative monthly totals and water limits; live and historical consumption; billing estimates, statements, offline payments, and PDF receipts; leak workflows; service requests; goals and forecasts; notification preferences; reports and exports; PWA and accessibility behavior; and security controls. On September 9, 2026, the complete Laravel test suite passed 166 tests with 1,096 assertions. The Vite production build also completed successfully, producing optimized CSS and JavaScript assets. Physical deployment should additionally record calibrated volume tests and network field observations for the installed sensor and meter assembly.")
    implementation = next(p for p in doc.paragraphs if p.text.startswith("The implementation plan provides a structured process"))
    set_paragraph_text(implementation, "Implementation begins by preparing the Laravel environment, database, application URL, mail configuration, production assets, and municipal administrator account. Staff then configure effective water rates and alert rules, register household accounts, register and assign each meter, and issue a short-lived activation code. Firmware version 1.1.1 is configured with Wi-Fi and server information, uploaded to the M5Stack CoreS3, and verified through hardware binding, last-seen status, acknowledged readings, monthly Period totals, and the lifetime Meter display. After telemetry is confirmed, staff validate dashboards, statements and receipts, service-request routing, notifications and preferences, goals and forecasts, exports, action-center items, PWA installation, and recovery procedures before operational release.")
    challenges = next(p for p in doc.paragraphs if p.text.startswith("Challenges encountered included"))
    set_paragraph_text(challenges, "Implementation challenges included incorrect wiring, defective sensor components, unstable pulse signals, network errors, duplicated or malformed telemetry responses, month-boundary billing behavior, and the risk of presenting missing data as zero. Hardware issues were addressed through component and wiring checks, calibration, pulse-noise filtering, and repeated volume tests. Software issues were addressed through stable reading identifiers, acknowledgement matching, retained pending intervals, server-derived limits and connection state, current-period aggregation, latest-metered-month fallback, explicit unavailable states, validation, audit logging, and automated regression tests.")
    results = next(p for p in doc.paragraphs if p.text.startswith("The Smart Water Monitoring System was successfully implemented"))
    set_paragraph_text(results, "The updated Smart Water Monitoring System implements the complete path from measured water flow to household information and municipal action. The M5Stack CoreS3 records sensor pulses, maintains a lifetime Meter value, and submits retry-safe interval readings. Laravel authenticates the commissioned device, stores each reading once, calculates the authoritative monthly Period total, applies water limits and leak rules, and supports current and historical consumption, estimated and finalized billing, offline payments, statements and receipts, service requests, notifications and preferences, goals and forecasts, reports, exports, and audit records. Household and administrator workspaces expose these functions through responsive, accessible, theme-aware interfaces with PWA and low-bandwidth behavior. The successful 166-test, 1,096-assertion verification run and production asset build confirm the implemented software workflows; calibrated hardware accuracy should remain documented through deployment-specific field measurements.")

    props = doc.core_properties
    props.title = "Smart Water Monitoring Consumption System - Updated Chapter IV"
    props.subject = "Updated methodology, use cases, design, database dictionary, testing, implementation, and results"
    props.keywords = "smart water, monitoring, billing, service requests, payments, PWA, M5Stack, Laravel"
    doc.save(OUTPUT)

    replace_media(OUTPUT, {
        "word/media/image1.png": use_case_png.read_bytes(),
        "word/media/image4.png": admin_png.read_bytes(),
        "word/media/image5.png": household_png.read_bytes(),
        "word/media/image6.png": erd_png.read_bytes(),
    })
    if sha256(REFERENCE) != EXPECTED_REFERENCE_HASH:
        raise RuntimeError("The retained reference was unexpectedly modified.")
    print(OUTPUT)


if __name__ == "__main__":
    build()
