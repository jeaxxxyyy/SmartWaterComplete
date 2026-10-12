from pathlib import Path

from PIL import Image, ImageDraw


render_dir = Path(r"C:\laragon\capstone\SmartWater_Update\_doc_review_render_revised")
pages = sorted(render_dir.glob("page-*.png"))
output = render_dir / "sheets"
output.mkdir(exist_ok=True)

for batch_start in range(0, len(pages), 16):
    images = [Image.open(page).convert("RGB").resize((248, 351)) for page in pages[batch_start : batch_start + 16]]
    sheet = Image.new("RGB", (1080, 1520), "#bfbfbf")
    draw = ImageDraw.Draw(sheet)
    for position, image in enumerate(images):
        x = (position % 4) * 270 + 11
        y = (position // 4) * 380 + 22
        sheet.paste(image, (x, y))
        draw.text((x, y - 18), str(batch_start + position + 1), fill="black")
    sheet.save(output / f"sheet-{batch_start // 16 + 1}.jpg", quality=90)
