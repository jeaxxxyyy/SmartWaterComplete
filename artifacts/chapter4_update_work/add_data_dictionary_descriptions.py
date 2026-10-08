from copy import deepcopy
from pathlib import Path

from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.shared import Pt


DOCX_PATH = Path(r"C:\Users\Jeangxx\Downloads\Capstone\CHAPTER IV - Updated.docx")
OUTPUT_PATH = Path(r"C:\Users\Jeangxx\Downloads\Capstone\CHAPTER IV - Updated - Data Dictionary Descriptions.docx")

DESCRIPTIONS = [
    ("Users", "Stores administrator, household, and device account records, including identity, contact information, login credentials, access role, account status, language preference, and low-bandwidth setting."),
    ("Devices", "Stores the registered M5Stack or ESP32 smart meter records assigned to households, including hardware identity, activation status, firmware version, maintenance state, installation details, credential status, and latest telemetry timestamps."),
    ("Water Readings", "Stores authenticated meter telemetry submitted by the device, including reading identifiers, flow rate, liters used, cubic-meter conversion, estimated bill values, threshold flags, leak status, connection state, payload details, and recording time."),
    ("Billing Configurations", "Stores the active water-rate rules used for estimates and statements, including rate per cubic meter, fixed monthly charge, subsidy, currency, effective date, threshold settings, leak alert settings, quiet hours, and activation status."),
    ("Billing Statements", "Stores finalized monthly billing snapshots for each household, including statement number, billing month, due date, consumption totals, rate values, charges, payments, remaining balance, currency, and payment status."),
    ("Billing Payments", "Stores offline payment records applied to billing statements, including receipt number, recorded staff member, payment amount, method, reference number, notes, and received date."),
    ("Notifications", "Stores household and administrative notices, including notification type, title, message content, related reading or workflow metadata, read status, delivery status, scheduling details, archive time, and user-side deletion time."),
    ("Notification Preferences", "Stores each household user's notice settings, including enabled categories for announcements, billing updates, consumption alerts, leak alerts, service-request updates, and optional quiet-hour windows."),
    ("Consumption Goals", "Stores household monthly water-use targets, including the owning user, goal month, target liters, and timestamps used for goal tracking and forecast comparison."),
    ("Service Requests", "Stores household support concerns submitted to the municipality, including reference number, category, subject, description, priority, status, assigned staff member, optional billing-statement link, and resolution time."),
    ("Service Request Messages", "Stores conversation messages attached to service requests, including the request, author, message content, internal-note flag, and timestamps."),
    ("Leak Alerts", "Stores detected or reviewed leak incidents, including affected household, first and latest supporting readings, related notification, incident status, investigation notes, detection time, acknowledgement time, and resolution time."),
    ("Audit Logs", "Stores administrative and security activity records, including acting user, action, affected record type and identifier, description, redacted metadata, outcome, request IP address, user agent, and request correlation identifier."),
]


def insert_after(paragraph, new_paragraph):
    paragraph._p.addnext(new_paragraph._p)


def clone_paragraph_format(source, target):
    target.style = source.style
    target.paragraph_format.left_indent = source.paragraph_format.left_indent
    target.paragraph_format.first_line_indent = source.paragraph_format.first_line_indent
    target.paragraph_format.right_indent = source.paragraph_format.right_indent
    target.paragraph_format.space_before = source.paragraph_format.space_before
    target.paragraph_format.space_after = source.paragraph_format.space_after
    target.paragraph_format.line_spacing = source.paragraph_format.line_spacing
    target.paragraph_format.alignment = source.paragraph_format.alignment


def set_run_font(run, size=12, bold=False, italic=False):
    run.font.name = "Arial"
    run.font.size = Pt(size)
    run.bold = bold
    run.italic = italic


def main():
    document = Document(DOCX_PATH)
    paragraphs = document.paragraphs
    heading = next((p for p in paragraphs if p.text.strip() == "4.4 Data Dictionary"), None)
    first_caption = next((p for p in paragraphs if p.text.strip().startswith("Table 4.4.1 ")), None)

    if heading is None or first_caption is None:
        raise RuntimeError("Could not locate Section 4.4 or the first data dictionary table caption.")

    # Remove a previous generated block if this script is run more than once.
    start = next((p for p in document.paragraphs if p.text.strip() == "Brief Database Table Descriptions"), None)
    if start is not None:
        body = document._body._element
        deleting = False
        for element in list(body):
            texts = [node.text for node in element.iter() if node.text]
            joined = "".join(texts).strip()
            if joined == "Brief Database Table Descriptions":
                deleting = True
            if deleting:
                body.remove(element)
                if joined.startswith("Audit Logs - "):
                    break

    document = Document(DOCX_PATH)
    paragraphs = document.paragraphs
    heading_index = next(i for i, p in enumerate(paragraphs) if p.text.strip() == "4.4 Data Dictionary")
    first_caption = next(p for p in paragraphs if p.text.strip().startswith("Table 4.4.1 "))
    intro = paragraphs[heading_index + 1]

    anchor = intro

    spacer = document.add_paragraph("")
    insert_after(anchor, spacer)
    anchor = spacer

    title = document.add_paragraph()
    title.alignment = WD_ALIGN_PARAGRAPH.LEFT
    title.paragraph_format.space_before = Pt(6)
    title.paragraph_format.space_after = Pt(6)
    run = title.add_run("Brief Database Table Descriptions")
    set_run_font(run, 12, bold=True)
    insert_after(anchor, title)
    anchor = title

    for table_name, description in DESCRIPTIONS:
        paragraph = document.add_paragraph()
        clone_paragraph_format(intro, paragraph)
        paragraph.paragraph_format.space_before = Pt(0)
        paragraph.paragraph_format.space_after = Pt(3)
        paragraph.paragraph_format.first_line_indent = None
        name_run = paragraph.add_run(f"{table_name} - ")
        set_run_font(name_run, 11, bold=True)
        desc_run = paragraph.add_run(description)
        set_run_font(desc_run, 11)
        insert_after(anchor, paragraph)
        anchor = paragraph

    spacer_after = document.add_paragraph("")
    insert_after(anchor, spacer_after)

    document.save(OUTPUT_PATH)


if __name__ == "__main__":
    main()
