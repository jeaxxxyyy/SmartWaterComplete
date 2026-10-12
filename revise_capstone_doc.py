import re
from pathlib import Path

from docx import Document
from docx.enum.style import WD_STYLE_TYPE
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Inches, Pt


SOURCE = Path(r"C:\laragon\capstone\SmartWater_Update\Smart-Water-Consumption-Monitoring-Revised.docx")


def remove_paragraph(paragraph):
    paragraph._element.getparent().remove(paragraph._element)
    paragraph._p = paragraph._element = None


def replace_in_runs(paragraph, replacements):
    for run in paragraph.runs:
        for old, new in replacements.items():
            if old in run.text:
                run.text = run.text.replace(old, new)


def set_header_row(row):
    tr_pr = row._tr.get_or_add_trPr()
    if tr_pr.find(qn("w:tblHeader")) is None:
        header = OxmlElement("w:tblHeader")
        header.set(qn("w:val"), "true")
        tr_pr.append(header)


doc = Document(SOURCE)
if "Chapter Label" not in [style.name for style in doc.styles]:
    chapter_label = doc.styles.add_style("Chapter Label", WD_STYLE_TYPE.PARAGRAPH)
    chapter_label.base_style = doc.styles["Normal"]
    chapter_label.font.bold = True

# System facts confirmed by the project team.
replacements = {
    "M5Stack Core2 ESP32 IoT Development Kit": "M5Stack CoreS3 ESP32 IoT Development Kit",
    "M5Stack Core2 ESP32": "M5Stack CoreS3 ESP32",
    "M5Stack Core2ESP32": "M5Stack CoreS3 ESP32",
    "M5Stack Core2": "M5Stack CoreS3",
    "M5Core2": "M5Stack CoreS3",
    "M5Stack CoreS3 (ESP32-)": "M5Stack CoreS3 (ESP32-S3)",
    "ZJ-S201": "YF-S201",
    "Smart Water Monitoring Consumption System": "Smart Water Consumption Monitoring System",
    "Intelligent Water Consumption Monitoring System": "Smart Water Consumption Monitoring System",
    "SmartWater Monitoring Consumption": "Smart Water Consumption Monitoring",
    "wi-fi": "Wi-Fi",
    "withe": "with",
    "regidtration": "registration",
    "Use Case Desciption": "Use Case Description",
    "man-agement": "management",
}
for paragraph in doc.paragraphs:
    replace_in_runs(paragraph, replacements)

# Remove a decorative underline from the cover page.
for paragraph in doc.paragraphs:
    if paragraph.text.strip("_") == "" and "_" in paragraph.text:
        remove_paragraph(paragraph)
        break

main_content_start = next(
    (index for index, paragraph in enumerate(doc.paragraphs) if paragraph.text.strip() == "CHAPTER I"),
    0,
)

# Remove the duplicated list of limitations (the preceding list remains).
duplicate_limitations = {
    "The system's precision is subject to the condition of the YF-S201 water flow sensors' calibration and performance.",
    "A steady Wi-Fi connection is needed for this system to transmit information instantaneously to a distant location or device.",
    "This paper does not cover anything else except a prototype-level investigation.",
    "This monitoring system is made for a family's use; big business or industry should look elsewhere for similar solutions.",
    "The amount of the bill calculated by the system is a guess only, and there may always be a difference between it and a real bill from the local water provider.",
    "A feature for turning the water off automatically is not a part of the system. It only does monitoring and notification functions.",
    "It's just a matter of how well the customer has been keeping track of his water consumption without controlling it at all.",
    "There might be a possibility of getting a delay in getting the information sent through the internet especially during times when the internet is either slow or completely blocked.",
    "This monitoring system is only an addition or an assist tool and cannot replace the local water utility company's official water metering and billing process.",
}
for paragraph in list(doc.paragraphs):
    if paragraph.text.strip() in duplicate_limitations:
        remove_paragraph(paragraph)

# Replace a few high-visibility, ungrammatical sentences without changing claims.
sentence_revisions = {
    "Water is an indispensable household resource because cooking, keeping clean and drinking liquids all require it.":
        "Water is an essential household resource used for drinking, cooking, hygiene, and cleaning.",
    "The main contribution of this paper is the IoT-based Smart Water Consumption Monitoring System's design and a detailed explanation of the system.":
        "This paper presents the design and implementation of an IoT-based Smart Water Consumption Monitoring System.",
    "Development of Smart Water Consumption Monitoring System by an IoT that will monitor water usage in homes, transmit data from the device to the web dashboard, and give users a view on real-time data plus an estimated bill.":
        "To develop an IoT-based Smart Water Consumption Monitoring System that monitors household water use, transmits data to a web dashboard, and provides real-time consumption data and an estimated bill.",
    "It is expected that a web platform providing data to local water utility offices regarding the consumption records and predicted monthly bills.":
        "To provide a web platform through which authorized local water utility personnel can review consumption records and estimated monthly bills.",
    "The M5Stack CoreS3 device detects sensor pulses, keeps Meter lifetime value and periodically, securely sends the readings even when retry required.":
        "The M5Stack CoreS3 device detects sensor pulses, maintains a lifetime meter total, and periodically transmits readings with retry support when needed.",
}
for paragraph in doc.paragraphs:
    cleaned = " ".join(paragraph.text.split())
    if cleaned in sentence_revisions:
        paragraph.text = sentence_revisions[cleaned]

# Correct chapter and section hierarchy to make navigation and a generated TOC reliable.
title_texts = {"CHAPTER I", "Chapter II", "Chapter III", "CHAPTER IV", "CHAPTER V"}
heading_1 = {
    "Introduction", "Review of Related Literature/Systems", "Technical Background",
    "Methodology", "Results and Discussions", "References",
}
heading_2_starts = (
    "Project Context", "1.2 Purpose and Description", "1.3 Objectives", "1.4 Scope and Limitations",
    "3.1 Architecture Design", "3.2 Details of the Technology Used", "3.3 Feature List",
    "4.1 Requirements Analysis and Requirements Documentation", "4.2 System Design, Software, and Processes",
    "4.3 Entity Relationship Diagram", "4.4 Data Dictionary", "4.5 Development and Testing",
    "4.6 Implementation Plan", "4.7 System Implementation and Results",
    "User Guide (Web Application)", "Household User Guide", "Administrator User Guide", "User Guide (IoT Device)",
)
heading_3 = {
    "General Objective", "Specific Objectives:", "Scope", "Limitations", "System Flow", "Use Case Diagram",
    "Use Case Description", "Output and User Interface Design", "Authentication", "Dashboard", "Live Monitoring",
    "Consumption History", "Estimated Billing", "Leak Detection", "Notifications", "Account Settings", "Overview",
    "Customer Management", "Device Management", "Water Rate Management", "Billing Management", "Consumption Monitoring",
    "Leak Monitoring", "Reports", "Notification Management",
}
for index, paragraph in enumerate(doc.paragraphs):
    if index < main_content_start:
        continue
    text = " ".join(paragraph.text.split())
    if text == "Project Context":
        paragraph.text = "Project Context"
        text = paragraph.text
    if text in title_texts:
        paragraph.style = doc.styles["Chapter Label"]
        paragraph.paragraph_format.page_break_before = True
    elif text in heading_1:
        paragraph.style = doc.styles["Heading 1"]
    elif text.startswith(heading_2_starts):
        paragraph.style = doc.styles["Heading 2"]
    elif text in heading_3:
        paragraph.style = doc.styles["Heading 3"]

# Bring manual TOC wording into agreement with the body text, without assigning it heading styles.
toc_replacements = {
    "4.6 Implementations Plan": "4.6 Implementation Plan",
    "4.7 System Implementation Plan": "4.7 System Implementation and Results",
    "V. Results and Discussion": "V. Results and Discussions",
    "Project Context": "1.1 Project Context",
}
for index, paragraph in enumerate(doc.paragraphs):
    if index >= main_content_start:
        continue
    replace_in_runs(paragraph, toc_replacements)
    if paragraph.text.strip() == "1.1 Project Context":
        paragraph.text = "Project Context"
    if paragraph.text.strip() and paragraph.text.strip() != "TABLE OF CONTENTS":
        for run in paragraph.runs:
            run.font.size = Pt(10)

for paragraph in doc.paragraphs:
    if "Project Context" in paragraph.text:
        if paragraph.style.name == "Heading 2":
            paragraph.text = "Project Context"
        else:
            paragraph.text = re.sub(r"(?:1\.1\s+)+Project Context", "1.1 Project Context", paragraph.text)

# Correct duplicate data-dictionary captions.
for paragraph in doc.paragraphs:
    if paragraph.text.startswith("Table 4.4.7 Database Table of Leak Alerts"):
        paragraph.text = paragraph.text.replace("Table 4.4.7", "Table 4.4.12", 1)
    elif paragraph.text.startswith("Table 4.4.8 Database Table of Audit Logs"):
        paragraph.text = paragraph.text.replace("Table 4.4.8", "Table 4.4.13", 1)

# Remove unsupported numerical test claims; they require evidence from the project team.
for paragraph in doc.paragraphs:
    if "166-test" in paragraph.text or "1, 096-assertion" in paragraph.text:
        paragraph.text = (
            "The implemented system supports the workflow from measured water flow to household and administrative actions. "
            "Before final submission, this section must be supported by documented functional-test results, sensor-calibration results, "
            "data-transmission results, billing-validation results, and leak-detection results from the actual prototype."
        )

# Standardize and complete the four verified references.
reference_locations = [
    index for index, paragraph in enumerate(doc.paragraphs)
    if paragraph.text.strip() == "References"
]
reference_start = reference_locations[-1] if reference_locations else None
if reference_start is not None:
    references = [
        "Clemente, A. E., Samaniego, R. M. G., & Cruz, F. R. G. (2023). IoT based water consumption monitoring system for water management. 2023 15th International Conference on Computer and Automation Engineering, 114-119.",
        "Rapelli, N., Myakal, A., Kota, V., & Rajarapollu, P. R. (2019). IoT based smart water management, monitoring and distribution system for an apartment. 2019 International Conference on Intelligent Computing and Control Systems, 440-443. https://doi.org/10.1109/ICCS45141.2019.9065369",
        "Roslan, M. Y. H., Rahman, F. Y. A., Mohamad, R., & Shahbudin, S. (2025). Development and evaluation of an IoT-based home water usage monitoring system using LoRa and ESP32. 2025 IEEE 16th Control and System Graduate Research Colloquium, 111-114. https://ieeexplore.ieee.org/document/11159838",
        "Varghese, L. J., Devanath, V., Kishore, V., Poornima, R., & Jacob, S. S. (2025). IoT based smart water meter for domestic utility. 2025 International Conference on Inventive Computation Technologies. https://doi.org/10.1109/ICICT64420.2025.11005058",
    ]
    existing = []
    for paragraph in doc.paragraphs[reference_start + 1:]:
        if paragraph.text.strip() == "CURRICULUM VITAE":
            break
        if paragraph.text.strip():
            existing.append(paragraph)
    for position, reference in enumerate(references):
        if position < len(existing):
            paragraph = existing[position]
            paragraph.text = reference
        else:
            paragraph = doc.add_paragraph(reference)
        paragraph.style = doc.styles["Normal"]
        paragraph.paragraph_format.left_indent = Inches(0.25)
        paragraph.paragraph_format.first_line_indent = Inches(-0.25)
    for paragraph in existing[len(references):]:
        remove_paragraph(paragraph)

# Mark the first row of all tables as a table header for accessibility and repeated display.
for table in doc.tables:
    if table.rows:
        set_header_row(table.rows[0])

doc.save(SOURCE)
