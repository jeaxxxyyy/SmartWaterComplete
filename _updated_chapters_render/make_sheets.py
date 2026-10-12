from pathlib import Path

from PIL import Image, ImageDraw

root = Path(r"C:\laragon\capstone\SmartWater_Update\_updated_chapters_render")
pages = sorted(root.glob("page-*.png"))
out = root / "sheets"
out.mkdir(exist_ok=True)
for start in range(0, len(pages), 12):
    sheet = Image.new("RGB", (1000, 1530), "#bfbfbf")
    draw = ImageDraw.Draw(sheet)
    for n, page in enumerate(pages[start:start + 12]):
        image = Image.open(page).convert("RGB").resize((235, 332))
        x = (n % 4) * 250 + 8
        y = (n // 4) * 505 + 30
        sheet.paste(image, (x, y))
        draw.text((x, y - 18), str(start + n + 1), fill="black")
    sheet.save(out / f"sheet-{start // 12 + 1}.jpg", quality=90)
