from docx import Document

path = r"C:\laragon\capstone\SmartWater_Update\artifacts\Smart_Water_Monitoring_Consumption_System_Chapters_4_and_5.docx"
doc = Document(path)
for i in [0, 1, 2, 3, 5, 6, 7, 8, 10, 37, 90, 91, 93, 95, 96]:
    paragraph = doc.paragraphs[i]
    fmt = paragraph.paragraph_format
    runs = []
    for run in paragraph.runs:
        size = run.font.size.pt if run.font.size else None
        runs.append((run.text, run.font.name, size, run.bold, run.italic))
    print({
        "index": i,
        "style": paragraph.style.name,
        "alignment": paragraph.alignment,
        "space_before": fmt.space_before.pt if fmt.space_before else None,
        "space_after": fmt.space_after.pt if fmt.space_after else None,
        "line_spacing": fmt.line_spacing,
        "first_indent": fmt.first_line_indent.inches if fmt.first_line_indent else None,
        "left_indent": fmt.left_indent.inches if fmt.left_indent else None,
        "page_break_before": fmt.page_break_before,
        "keep_with_next": fmt.keep_with_next,
        "runs": runs,
    })
