from __future__ import annotations

from pathlib import Path

import pytest

from app.zip_handler import (
    ZipValidationError,
    detect_product_folders,
    safe_extract_zip,
    validate_zip_bytes,
)


def test_safe_extract_extracts_only_valid_images(tmp_path, sample_zip_path, test_settings):
    dest = tmp_path / "extracted"
    report = safe_extract_zip(sample_zip_path, dest, test_settings)

    # 2 + 3 + 1 + 1 = 7 valid images
    assert report.extracted_files == 7
    assert (dest / "ITEM_01" / "photo1.jpg").exists()
    assert (dest / "ITEM_02" / "label.png").exists()
    assert (dest / "ITEM_04" / "photo.webp").exists()


def test_path_traversal_is_neutralised(tmp_path, sample_zip_path, test_settings):
    dest = tmp_path / "extracted"
    report = safe_extract_zip(sample_zip_path, dest, test_settings)

    # The "../../evil.jpg" entry must never escape the destination directory.
    escaped_path = tmp_path / "evil.jpg"
    assert not escaped_path.exists()
    assert not (tmp_path.parent / "evil.jpg").exists()
    assert any("evil.jpg" in w for w in report.warnings)

    # Every file that *was* written must be inside dest.
    for path in dest.rglob("*"):
        if path.is_file():
            assert dest.resolve() in path.resolve().parents


def test_ignores_macosx_and_dsstore_and_non_images(tmp_path, sample_zip_path, test_settings):
    dest = tmp_path / "extracted"
    safe_extract_zip(sample_zip_path, dest, test_settings)

    assert not (dest / "__MACOSX").exists()
    assert not (dest / ".DS_Store").exists()
    assert not (dest / "ITEM_01" / "notes.txt").exists()


def test_detect_product_folders_groups_correctly(tmp_path, sample_zip_path, test_settings):
    dest = tmp_path / "extracted"
    safe_extract_zip(sample_zip_path, dest, test_settings)
    products, warnings = detect_product_folders(dest, test_settings)

    names = sorted(p.name for p in products)
    assert names == ["ITEM_01", "ITEM_02", "ITEM_03", "ITEM_04"]

    by_name = {p.name: p for p in products}
    assert len(by_name["ITEM_01"].images) == 2
    assert len(by_name["ITEM_02"].images) == 3
    assert len(by_name["ITEM_03"].images) == 1
    assert len(by_name["ITEM_04"].images) == 1


def test_validate_zip_bytes_rejects_oversized(test_settings):
    too_big = (test_settings.max_zip_size_mb + 1) * 1024 * 1024
    with pytest.raises(ZipValidationError):
        validate_zip_bytes(too_big, test_settings)


def test_validate_zip_bytes_rejects_empty(test_settings):
    with pytest.raises(ZipValidationError):
        validate_zip_bytes(0, test_settings)


def test_max_product_folders_limit_enforced(tmp_path, sample_zip_path, test_settings):
    dest = tmp_path / "extracted"
    safe_extract_zip(sample_zip_path, dest, test_settings)

    from dataclasses import replace

    strict_settings = replace(test_settings, max_product_folders=2)
    with pytest.raises(ZipValidationError):
        detect_product_folders(dest, strict_settings)


def test_corrupt_zip_raises(tmp_path, test_settings):
    bad_zip = tmp_path / "bad.zip"
    bad_zip.write_bytes(b"this is not a zip file at all")
    with pytest.raises(ZipValidationError):
        safe_extract_zip(bad_zip, tmp_path / "out", test_settings)


def test_no_images_in_zip_raises(tmp_path, test_settings):
    import zipfile

    empty_zip = tmp_path / "empty.zip"
    with zipfile.ZipFile(empty_zip, "w") as zf:
        zf.writestr("ITEM_01/notes.txt", b"no images here")
    with pytest.raises(ZipValidationError):
        safe_extract_zip(empty_zip, tmp_path / "out", test_settings)
