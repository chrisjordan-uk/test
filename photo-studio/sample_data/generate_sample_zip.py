"""Generates sample_data/STOCK_PHOTOS_SAMPLE.zip — a small demo upload for
trying out Jordyn AI Photo Studio without your own photos.

Run with: python sample_data/generate_sample_zip.py
"""
from __future__ import annotations

import zipfile
from pathlib import Path

import pillow_heif
from PIL import Image, ImageDraw

pillow_heif.register_heif_opener()

OUT_DIR = Path(__file__).resolve().parent
OUT_ZIP = OUT_DIR / "STOCK_PHOTOS_SAMPLE.zip"


def garment_photo(size, bg, garment_color, defect=False, label=False) -> Image.Image:
    img = Image.new("RGB", size, bg)
    draw = ImageDraw.Draw(img)
    mx, my = size[0] // 5, size[1] // 6
    draw.rectangle([mx, my, size[0] - mx, size[1] - my], fill=garment_color)
    # Simple "collar" detail so it reads as a garment silhouette.
    draw.polygon(
        [
            (size[0] // 2 - 40, my),
            (size[0] // 2, my + 50),
            (size[0] // 2 + 40, my),
        ],
        fill=tuple(max(0, c - 30) for c in garment_color),
    )
    if label:
        draw.rectangle([size[0] // 2 - 25, my + 70, size[0] // 2 + 25, my + 110], fill=(255, 255, 255))
        draw.text((size[0] // 2 - 18, my + 82), "SIZE M", fill=(0, 0, 0))
    if defect:
        # A visible "stain" that must remain visible after enhancement.
        draw.ellipse(
            [size[0] // 2 + 30, size[1] // 2, size[0] // 2 + 70, size[1] // 2 + 35],
            fill=(60, 40, 10),
        )
    return img


def main() -> None:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(OUT_ZIP, "w", compression=zipfile.ZIP_DEFLATED) as zf:
        items = [
            ("ITEM_01", [
                ("photo1.jpg", garment_photo((900, 1200), (245, 245, 240), (60, 90, 160))),
                ("photo2.jpg", garment_photo((900, 1200), (238, 238, 232), (60, 90, 160), label=True)),
                ("photo3.jpg", garment_photo((900, 1200), (250, 250, 248), (60, 90, 160), defect=True)),
            ]),
            ("ITEM_02", [
                ("front.jpg", garment_photo((900, 1200), (255, 255, 255), (180, 60, 70))),
                ("back.jpg", garment_photo((900, 1200), (255, 255, 255), (180, 60, 70))),
                ("label.jpg", garment_photo((700, 700), (255, 255, 255), (180, 60, 70), label=True)),
            ]),
            ("ITEM_03", [
                ("image1.png", garment_photo((1000, 1000), (230, 225, 210), (40, 130, 90))),
            ]),
            ("ITEM_04", [
                ("photo.webp", garment_photo((900, 1100), (255, 255, 255), (210, 180, 40), defect=True)),
            ]),
            ("ITEM_05", [
                # An iPhone-style HEIC photo, to demonstrate HEIC support.
                ("IMG_0001.HEIC", garment_photo((900, 1200), (255, 255, 255), (90, 150, 120))),
            ]),
        ]

        for folder, photos in items:
            for filename, img in photos:
                if filename.endswith(".png"):
                    fmt = "PNG"
                elif filename.endswith(".webp"):
                    fmt = "WEBP"
                elif filename.upper().endswith(".HEIC"):
                    fmt = "HEIF"
                else:
                    fmt = "JPEG"
                import io
                buf = io.BytesIO()
                img.save(buf, format=fmt)
                zf.writestr(f"{folder}/{filename}", buf.getvalue())

    print(f"Wrote {OUT_ZIP} ({OUT_ZIP.stat().st_size / 1024:.1f} KB)")


if __name__ == "__main__":
    main()
