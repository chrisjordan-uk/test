from __future__ import annotations

import io
import zipfile
from pathlib import Path

import pytest
from PIL import Image, ImageDraw

from app.config import Settings


def make_image_bytes(size=(400, 500), bg=(255, 255, 255), fmt="JPEG", draw_shape=True) -> bytes:
    img = Image.new("RGB", size, bg)
    if draw_shape:
        draw = ImageDraw.Draw(img)
        # A centred "garment" rectangle with a distinct colour and a small
        # "defect" mark, so tests can reason about preserved content.
        margin_x, margin_y = size[0] // 4, size[1] // 4
        draw.rectangle(
            [margin_x, margin_y, size[0] - margin_x, size[1] - margin_y],
            fill=(120, 60, 200),
        )
        draw.ellipse(
            [size[0] // 2 - 10, size[1] // 2 - 10, size[0] // 2 + 10, size[1] // 2 + 10],
            fill=(20, 20, 20),
        )
    buf = io.BytesIO()
    img.save(buf, format=fmt)
    return buf.getvalue()


@pytest.fixture()
def test_settings(tmp_path) -> Settings:
    return Settings(
        gemini_api_key="",
        max_zip_size_mb=50,
        max_product_folders=10,
        max_images_per_folder=10,
        max_image_file_size_mb=10,
        max_total_images=100,
    )


@pytest.fixture()
def sample_zip_bytes() -> bytes:
    """A realistic small upload ZIP: 4 products, mixed formats, junk files, and one
    path-traversal attempt that must be neutralised on extraction."""
    buf = io.BytesIO()
    with zipfile.ZipFile(buf, "w") as zf:
        zf.writestr("ITEM_01/photo1.jpg", make_image_bytes(fmt="JPEG", bg=(250, 250, 250)))
        zf.writestr("ITEM_01/photo2.jpg", make_image_bytes(fmt="JPEG", bg=(248, 248, 248)))

        zf.writestr("ITEM_02/front.jpg", make_image_bytes(fmt="JPEG", bg=(255, 255, 255)))
        zf.writestr("ITEM_02/back.jpg", make_image_bytes(fmt="JPEG", bg=(255, 255, 255)))
        zf.writestr("ITEM_02/label.png", make_image_bytes(fmt="PNG", bg=(255, 255, 255)))

        zf.writestr("ITEM_03/image1.png", make_image_bytes(fmt="PNG", bg=(240, 240, 240)))

        zf.writestr("ITEM_04/photo.webp", make_image_bytes(fmt="WEBP", bg=(255, 255, 255)))

        # Junk that must be ignored.
        zf.writestr("__MACOSX/._photo1.jpg", b"not a real image")
        zf.writestr(".DS_Store", b"junk")
        zf.writestr("ITEM_01/notes.txt", b"not an image, should be skipped")

        # Path traversal attempt — must never land outside the extraction dir.
        zf.writestr("../../evil.jpg", make_image_bytes())
    return buf.getvalue()


@pytest.fixture()
def sample_zip_path(tmp_path, sample_zip_bytes) -> Path:
    path = tmp_path / "STOCK_PHOTOS.zip"
    path.write_bytes(sample_zip_bytes)
    return path
