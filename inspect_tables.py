from docx import Document

document = Document(r"Smart-Water-Consumption-Monitoring-Updated-Chapters-IV-V.docx")
for table_index, table in enumerate(document.tables):
    for row in table.rows:
        text = " | ".join(cell.text.replace("\n", " ") for cell in row.cells)
        if "failing" in text.lower() or "137" in text or "test" in text.lower():
            print(table_index, text)
