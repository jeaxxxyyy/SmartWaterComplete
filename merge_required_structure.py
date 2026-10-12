from copy import deepcopy
from pathlib import Path

from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml.ns import qn


SOURCE = Path(r"C:\Users\Jeangxx\Downloads\Capstone\2-Capstone\Smart-Water-Consumption-Monitoring(SL) - Copy.docx")
CHAPTERS = Path(r"C:\laragon\capstone\SmartWater_Update\Smart-Water-Consumption-Monitoring-Updated-Chapters-I-III.docx")
OUTPUT = Path(r"C:\laragon\capstone\SmartWater_Update\Smart-Water-Consumption-Monitoring-Updated-Full.docx")


def p_text(paragraph):
    return "".join(paragraph._p.itertext()).strip()


def find_paragraph(doc, exact):
    for p in doc.paragraphs:
        if p.text.strip() == exact:
            return p
    raise ValueError(f"Paragraph not found: {exact}")


def remove_between(start_p, end_p, include_start=True):
    """Remove all body siblings from start up to, but not including, end."""
    node = start_p._p if include_start else start_p._p.getnext()
    end = end_p._p
    while node is not None and node is not end:
        nxt = node.getnext()
        node.getparent().remove(node)
        node = nxt


doc = Document(SOURCE)

# Remove previous Chapters I–III while retaining all preliminary pages and Chapter IV onward.
chapter_one = find_paragraph(doc, "CHAPTER I")
chapter_four = find_paragraph(doc, "CHAPTER IV")
remove_between(chapter_one, chapter_four)

# Retain the complete requirements documentation, including the use-case
# descriptions and the database/data-dictionary tables. Each use case is
# supported by a current Admin, Household, or commissioned-device feature.
# Only its label is aligned with the current screen and workflow terminology.
use_case_labels = {
    "Manage Households": "Manage Household Accounts",
    "Manage Meters and Rates": "Manage Devices and Water Rates",
    "Generate Billing": "Generate and Finalize Billing Statements",
    "Review History and Bills": "Review Consumption History and Bills",
    "Install and Use Resilient Website": "Use Low-Bandwidth and Installable Website",
}
for table in doc.tables[:15]:
    for row in table.rows:
        for cell in row.cells:
            for old, new in use_case_labels.items():
                if old in cell.text:
                    for paragraph in cell.paragraphs:
                        if old in paragraph.text:
                            paragraph.text = paragraph.text.replace(old, new)

for paragraph in doc.paragraphs:
    for old, new in use_case_labels.items():
        if old in paragraph.text:
            paragraph.text = paragraph.text.replace(old, new)

# Make the retained data dictionary usable: the source tables had blank
# Description cells.  These entries describe the implemented database fields
# without exposing any credentials or secrets.
field_descriptions = {
    "id": "Primary key.", "created_at": "Record creation timestamp.", "updated_at": "Last update timestamp.",
    "user_id": "Related household account.", "device_id": "Related registered meter.",
    "billing_statement_id": "Related billing statement.", "water_reading_id": "Related telemetry reading.",
    "name": "Display name.", "email": "Account email address.", "role": "Access role (administrator or household).",
    "account_number": "Municipal household account number.", "address": "Household service address.", "phone": "Household contact number.",
    "preferred_locale": "Selected interface language.", "low_bandwidth_mode": "Reduces polling and interface motion.",
    "is_active": "Whether the record is active.", "email_verified_at": "Email-verification timestamp.",
    "password": "Hashed password.", "remember_token": "Remember-me session token.",
    "created_by_admin": "Whether an administrator created the account.", "assigned_user_id": "Related account assignment.",
    "created_by": "Administrator that created the record.", "approved_at": "Approval timestamp.",
    "account_status": "Current account status.", "uuid": "Public device identifier.",
    "asset_number": "Municipal asset identifier.", "serial_number": "Manufacturer or internal serial number.",
    "household_id": "Assigned household account.", "auth_user_id": "Account authorized to the device.",
    "hardware_id": "Bound physical hardware identity.", "firmware_version": "Reported firmware version.",
    "status": "Current lifecycle or workflow status.", "maintenance_status": "Maintenance state.",
    "installation_address": "Meter installation location.", "installed_at": "Installation timestamp.",
    "last_seen_at": "Most recent device-contact time.", "last_telemetry_at": "Most recent telemetry time.",
    "calibrated_at": "Latest calibration time.", "calibration_due_at": "Next calibration due time.",
    "token_rotated_at": "Device-credential rotation time.", "revoked_at": "Credential revocation time.",
    "activation_code_hash": "Hashed one-time activation code.", "activation_expires_at": "Activation-code expiry time.",
    "activated_at": "Successful activation time.", "notes": "Operational notes.", "reading_id": "Stable device reading identifier.",
    "flow_rate": "Reported flow rate.", "liters_used": "Consumption for the recorded interval.",
    "cubic_meter": "Consumption in cubic metres.", "estimated_bill": "Estimated charge at reading time.",
    "rate_per_cubic_meter": "Applied rate per cubic metre.", "subsidy_deduction": "Applied subsidy or deduction.",
    "threshold_liters": "Configured consumption-alert threshold.", "is_threshold_exceeded": "Whether the threshold was exceeded.",
    "is_possible_leak": "Whether server rules flagged a possible leak.", "leak_detection_status": "Possible-leak workflow status.",
    "water_limit_liters": "Configured household consumption limit.", "monitoring_status": "Current monitoring state.",
    "wifi_signal_strength": "Reported Wi-Fi signal strength.", "device_connection_status": "Meter connection state.",
    "server_connection_status": "Server connection state.", "device_name": "Reported device label.",
    "device_payload": "Sanitized telemetry payload.", "recorded_at": "Reading collection time.",
    "water_rate": "Water rate used for billing.", "fixed_monthly_charge": "Fixed monthly charge.",
    "currency": "Billing currency code.", "effective_date": "Date configuration takes effect.",
    "consumption_alert_enabled": "Enables consumption alerts.", "consumption_alert_unit": "Unit used for the consumption alert.",
    "consumption_alert_behavior": "Configured consumption-alert action.", "leak_alert_enabled": "Enables possible-leak alerts.",
    "leak_flow_rate_threshold": "Flow-rate threshold for possible leaks.", "leak_continuous_duration_minutes": "Required continuous duration for a possible leak.",
    "leak_alert_behavior": "Configured possible-leak alert action.", "quiet_hours_start": "Notification quiet-hours start.",
    "quiet_hours_end": "Notification quiet-hours end.", "billing_month": "Statement billing month.",
    "opening_balance": "Balance carried into the statement.", "total_liters": "Total billed consumption in litres.",
    "total_cubic_meter": "Total billed consumption in cubic metres.", "current_charge": "Current-period charge.",
    "amount_paid": "Recorded offline payments.", "remaining_balance": "Outstanding balance.", "generated_at": "Statement generation time.",
    "paid_at": "Full-payment time.", "recorded_by": "Administrator who recorded payment.",
    "receipt_number": "Official receipt number.", "amount": "Payment amount.", "payment_method": "Offline payment method.",
    "reference_number": "Payment or service-request reference.", "received_at": "Payment receipt time.",
    "type": "Notification category.", "title": "Notification title.", "message": "Notification message.",
    "metadata": "Supplementary structured metadata.", "is_read": "Whether the notification was read.",
    "batch_id": "Notification delivery batch.", "delivery_status": "Notification delivery state.",
    "delivery_error": "Delivery error details, if any.", "scheduled_at": "Planned notification time.",
    "sent_at": "Notification send time.", "archived_at": "Archive time.", "user_deleted_at": "Household deletion time.",
    "announcements": "Allows municipal announcements.", "billing_updates": "Allows billing updates.",
    "consumption_alerts": "Allows consumption alerts.", "leak_alerts": "Allows possible-leak alerts.",
    "service_request_updates": "Allows service-request updates.", "month": "Goal month.",
    "target_liters": "Household monthly consumption target.", "assigned_to": "Assigned administrator.",
    "category": "Service-request category.", "subject": "Service-request subject.",
    "description": "Detailed request or audit description.", "priority": "Request priority.",
    "resolved_at": "Resolution time.", "service_request_id": "Related service request.",
    "message": "Message content.", "is_internal": "Visible only to administrators when true.",
    "first_water_reading_id": "First reading in the possible-leak event.", "latest_water_reading_id": "Latest reading in the possible-leak event.",
    "notification_id": "Related notification.", "detected_at": "Initial detection time.",
    "last_detected_at": "Most recent detection time.", "notified_at": "Notification time.",
    "acknowledged_at": "Administrator acknowledgement time.", "action": "Recorded administrative or security action.",
    "auditable_type": "Type of affected record.", "auditable_id": "Identifier of affected record.",
    "outcome": "Result of the recorded action.", "ip_address": "Request IP address.",
    "user_agent": "Request user-agent value.", "request_id": "Request correlation identifier.",
}
for table in doc.tables[15:]:
    if not table.rows or len(table.rows[0].cells) < 4:
        continue
    for row in table.rows[1:]:
        field = row.cells[0].text.strip()
        if field and not row.cells[3].text.strip():
            row.cells[3].text = field_descriptions.get(field, field.replace("_", " ").capitalize() + ".")

# Correct outdated test language. Automated tests validate software behaviour;
# they are not evidence of sensor calibration or field accuracy.
paragraph_replacements = {
    "Continuous development and testing were carried out in order to make the Smart Water Monitoring System reliable.":
        "The web application was developed iteratively and its implemented software behaviour was checked through automated tests.",
    "Laravel app, database, M5Stack CoreS3, and YF-S201 water-flow sensor were tested, to confirm correct functioning of the following features: pulse detection, data transmission, billing calculation, alerts and user man-agement.":
        "The automated test suite covers application behaviour such as authentication, role-scoped access, telemetry validation, duplicate-reading protection, billing, notifications, service requests, and offline-payment records. Sensor calibration, installation quality, and field accuracy require separate measured-volume and deployment validation.",
    "Once spotted, errors were fixed before the system rolled out.":
        "Observed software defects are addressed through regression testing before a release; hardware and field issues remain subject to installation and calibration checks.",
}
for paragraph in doc.paragraphs:
    clean = " ".join(paragraph.text.split())
    if clean in paragraph_replacements:
        paragraph.text = paragraph_replacements[clean]
    if "successful 166-test and 1, 096-assertion verification run" in clean:
        paragraph.text = (
            "The completed automated test run recorded 181 passing tests and 1,178 assertions. This is evidence for the "
            "tested software behaviour only. The M5Stack CoreS3 and YF-S201 sensor still require measured-volume "
            "calibration and field validation before any accuracy claim is made."
        )

# Copy only the required I–III body from the corrected standalone chapters file.
chapters = Document(CHAPTERS)
start = next((p._p for p in chapters.paragraphs if p.text.strip() == "I. Introduction"), None)
seen_start = False
end = None
for p in chapters.paragraphs:
    if p._p is start:
        seen_start = True
    elif seen_start and p.text.strip() == "References":
        end = p._p
        break
if start is None or end is None:
    raise RuntimeError("Required chapter structure was not found in the standalone chapter document.")

child = start
while child is not None and child is not end:
    nxt = child.getnext()
    chapter_four._p.addprevious(deepcopy(child))
    child = nxt

# The required TOC headings in the attached manuscript are manual text.
# Replace its Chapter I–III labels with the approved wording; page values are
# refreshed after the merged document is rendered.
toc_updates = {
    "I. Introduction": "I.  Introduction",
    "1.1 Project Context": "     1.1  Project Context",
    "1.2 Purpose and Description": "     1.2  Purpose and Description",
    "1.3 Objectives": "     1.3  Objectives",
    "1.4 Scope and Limitations": "     1.4  Scope and Limitations",
    "II. Review of Related Literature": "II.  Review of Related Literature",
    "III. Technical Background": "III.  Technical Background",
    "3.1 Architecture Design": "     3.1  Architecture Design",
    "3.1.1 Smart Water Consumption Monitoring\nSystem Architecture Design": "          3.1.1 Smart Water Consumption Monitoring\n                System Architecture Design",
    "3.2 Details of Technology Used": "     3.2  Details of Technology Used",
}
for p in doc.paragraphs[:50]:
    clean = p.text.strip().replace("\r", "")
    for needle, replacement in toc_updates.items():
        if needle.replace("\n", " ") in clean.replace("\n", " "):
            p.text = replacement
            break

# The table of contents is a manually formatted list in the supplied document.
# Its page values are refreshed for this revised layout (the first chapter page
# is counted as page 1, after the cover and contents pages).
toc_page_updates = {
    28: ("Introduction", 1), 29: ("Project Context", None),
    30: ("Purpose and Description", 1), 31: ("Objectives", 1),
    32: ("Scope and Limitations", 2), 33: ("Review of Related Literature", 3),
    34: ("Technical Background", 4), 35: ("Architecture Design", 4),
    36: ("3.1.1 Smart Water Consumption Monitoring", None),
    37: ("System Architecture Design", 4), 38: ("Details of Technology Used", 4),
    39: ("Methodology", 5), 40: ("4.1 Requirements Analysis and Requirements", None),
    41: ("Documentation", 5), 42: ("4.2 System Design, Software, and Processes", 21),
    43: ("4.3 Entity Relationship Diagram", 24), 44: ("4.4 Data Dictionary", 26),
    45: ("4.5 Development and Testing", 40), 46: ("4.6 Implementations Plan", 40),
    47: ("4.7 System Implementation Plan", 41), 48: ("Results and Discussion", 42),
    49: ("References", 64),
}
for index, (label, page) in toc_page_updates.items():
    if index < len(doc.paragraphs):
        doc.paragraphs[index].text = label if page is None else f"{label}\t\t\t{page}"

# Match the exact Chapter IV titles in the required structure supplied by the
# user while retaining the existing implementation narrative underneath them.
for paragraph in doc.paragraphs:
    if paragraph.text.strip() == "4.6 Implementation Plan":
        paragraph.text = "4.6 Implementations Plan"
    elif paragraph.text.strip() == "4.7 System Implementation and Results":
        paragraph.text = "4.7 System Implementation Plan"

# The supplied use-case overview is built from Word drawing text rather than
# ordinary paragraphs. Update those visual labels as well as the detailed
# tables, so the diagram reflects the current interface terminology.
diagram_label_updates = {
    "Manage households": "Manage household accounts",
    "Manage meters and rates": "Manage devices and water rates",
    "Generate billing": "Finalize billing statements",
    "Alerts reports and action center": "Review alerts and action center",
    "History bills and receipts": "Review history and bills",
    "Goals and forecast": "Set goals and forecast",
    "Install resilient website": "Use low-bandwidth website",
}
for element in doc.part._element.iter():
    if not element.tag.endswith("}t") or not element.text:
        continue
    for old, new in diagram_label_updates.items():
        if element.text.lower() == old.lower():
            element.text = new
            break

for section in doc.sections:
    for p in section.header.paragraphs:
        p.text = "SMART WATER CONSUMPTION MONITORING SYSTEM"
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER

doc.save(OUTPUT)
print(OUTPUT)
