"""Crop/scale raw 2x screenshots (from shoot.mjs) into docs/guide-src/img/*.jpg.
Usage: python crop.py <raw_shots_dir>"""
import sys
from pathlib import Path
from PIL import Image

RAW = Path(sys.argv[1])
OUT = Path(__file__).parent / "img"
OUT.mkdir(exist_ok=True)

# name -> (source shot, crop box in raw pixels or None for full image)
CROPS = {
    "pos-order": ("pos-order", None),
    "login": ("login", (840, 360, 1890, 1175)),
    "dashboard": ("dashboard", (0, 0, 2732, 720)),
    "usermenu": ("usermenu", (2215, 0, 2732, 560)),
    "changepin": ("changepin", (640, 160, 2732, 960)),
    "purchase-form": ("purchase-form", (640, 160, 2732, 1070)),
    "products": ("products", (640, 160, 2732, 1250)),
    "recipe": ("recipe", (640, 920, 2732, 1682)),
    "recipe-edit": ("recipe-edit", (450, 290, 2280, 790)),
    "report-summary": ("report", (690, 505, 2690, 1110)),
    "report-table": ("report", (690, 1150, 2690, 1900)),
}
MAX_W = 1600

for lang in ("fr", "en"):
    for name, (src, box) in CROPS.items():
        im = Image.open(RAW / f"{lang}-{src}.png").convert("RGB")
        if box:
            im = im.crop((box[0], box[1], min(box[2], im.width), min(box[3], im.height)))
        if im.width > MAX_W:
            im = im.resize((MAX_W, round(im.height * MAX_W / im.width)), Image.LANCZOS)
        im.save(OUT / f"{lang}-{name}.jpg", quality=86, optimize=True)
        print(lang, name, im.size)
