from __future__ import annotations

import hashlib
from pathlib import Path

from app.gemini_client import GeminiClient
from app.models import AspectRatio, BackgroundStyle, ProcessingMode, ProcessingSettings
from app.pipeline import process_batch
from app.zip_handler import detect_product_folders, safe_extract_zip


def _sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def _extract_sample(tmp_path, sample_zip_path, test_settings):
    dest = tmp_path / "extracted"
    safe_extract_zip(sample_zip_path, dest, test_settings)
    products, _ = detect_product_folders(dest, test_settings)
    return products


def test_original_files_are_never_modified(tmp_path, sample_zip_path, test_settings):
    products = _extract_sample(tmp_path, sample_zip_path, test_settings)
    hashes_before = {
        img.original_path: _sha256(img.original_path) for p in products for img in p.images
    }

    settings = ProcessingSettings(
        mode=ProcessingMode.CLEAN_PRODUCT_PHOTO,
        use_gemini=False,
        use_local_enhancement=True,
        background_style=BackgroundStyle.NEUTRAL_STUDIO,
        aspect_ratio=AspectRatio.RATIO_4_5,
    )
    gemini_client = GeminiClient(settings=test_settings, cache_dir=tmp_path / "cache")
    process_batch(products, settings, test_settings, gemini_client, tmp_path / "enhanced")

    for path, digest in hashes_before.items():
        assert path.exists()
        assert _sha256(path) == digest


def test_process_batch_produces_enhanced_output_for_every_image(tmp_path, sample_zip_path, test_settings):
    products = _extract_sample(tmp_path, sample_zip_path, test_settings)
    settings = ProcessingSettings(use_gemini=False, use_local_enhancement=True)
    gemini_client = GeminiClient(settings=test_settings, cache_dir=tmp_path / "cache")

    batch_report = process_batch(products, settings, test_settings, gemini_client, tmp_path / "enhanced")

    total_images = sum(len(p.images) for p in products)
    assert batch_report.total_images == total_images
    assert batch_report.total_success == total_images
    assert batch_report.total_failure == 0

    for product in products:
        enhanced_dir = tmp_path / "enhanced" / product.name / "ENHANCED_PHOTOS"
        produced = list(enhanced_dir.glob("*_enhanced.jpg"))
        assert len(produced) == len(product.images)


def test_error_recovery_continues_after_corrupt_image(tmp_path, test_settings):
    from tests.conftest import make_image_bytes
    import zipfile

    zip_path = tmp_path / "batch.zip"
    with zipfile.ZipFile(zip_path, "w") as zf:
        zf.writestr("ITEM_01/good1.jpg", make_image_bytes())
        zf.writestr("ITEM_01/corrupt.jpg", b"this is not actually a jpeg")
        zf.writestr("ITEM_01/good2.jpg", make_image_bytes())

    dest = tmp_path / "extracted"
    safe_extract_zip(zip_path, dest, test_settings)
    products, _ = detect_product_folders(dest, test_settings)

    settings = ProcessingSettings(use_gemini=False, use_local_enhancement=True)
    gemini_client = GeminiClient(settings=test_settings, cache_dir=tmp_path / "cache")
    batch_report = process_batch(products, settings, test_settings, gemini_client, tmp_path / "enhanced")

    assert batch_report.total_images == 3
    assert batch_report.total_failure == 1
    assert batch_report.total_success == 2

    report = batch_report.product_reports[0]
    corrupt_result = next(r for r in report.results if r.filename == "corrupt.jpg")
    assert corrupt_result.success is False
    assert corrupt_result.error is not None


def test_selected_folders_filters_processing(tmp_path, sample_zip_path, test_settings):
    products = _extract_sample(tmp_path, sample_zip_path, test_settings)
    settings = ProcessingSettings(use_gemini=False, use_local_enhancement=True, selected_folders=["ITEM_01"])
    gemini_client = GeminiClient(settings=test_settings, cache_dir=tmp_path / "cache")

    batch_report = process_batch(products, settings, test_settings, gemini_client, tmp_path / "enhanced")

    assert len(batch_report.product_reports) == 1
    assert batch_report.product_reports[0].folder_name == "ITEM_01"


def test_heic_photo_is_decoded_and_processed(tmp_path, sample_zip_path, test_settings):
    products = _extract_sample(tmp_path, sample_zip_path, test_settings)
    settings = ProcessingSettings(use_gemini=False, use_local_enhancement=True, selected_folders=["ITEM_05"])
    gemini_client = GeminiClient(settings=test_settings, cache_dir=tmp_path / "cache")

    batch_report = process_batch(products, settings, test_settings, gemini_client, tmp_path / "enhanced")

    assert batch_report.total_images == 1
    assert batch_report.total_success == 1
    assert batch_report.total_failure == 0

    enhanced_dir = tmp_path / "enhanced" / "ITEM_05" / "ENHANCED_PHOTOS"
    produced = list(enhanced_dir.glob("*_enhanced.jpg"))
    assert len(produced) == 1
