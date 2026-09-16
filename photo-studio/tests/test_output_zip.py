from __future__ import annotations

import zipfile

from app.gemini_client import GeminiClient
from app.models import ProcessingSettings
from app.output_zip import OUTPUT_ROOT_NAME, build_output_zip
from app.pipeline import process_batch
from app.zip_handler import detect_product_folders, safe_extract_zip


def test_output_zip_has_expected_structure(tmp_path, sample_zip_path, test_settings):
    dest = tmp_path / "extracted"
    safe_extract_zip(sample_zip_path, dest, test_settings)
    products, warnings = detect_product_folders(dest, test_settings)

    settings = ProcessingSettings(use_gemini=False, use_local_enhancement=True)
    gemini_client = GeminiClient(settings=test_settings, cache_dir=tmp_path / "cache")
    enhanced_root = tmp_path / "enhanced"
    batch_report = process_batch(products, settings, test_settings, gemini_client, enhanced_root)

    output_zip_path = tmp_path / "OUTPUT.zip"
    build_output_zip(products, batch_report, settings, enhanced_root, output_zip_path, warnings)

    assert output_zip_path.exists()
    with zipfile.ZipFile(output_zip_path) as zf:
        names = set(zf.namelist())

        assert f"{OUTPUT_ROOT_NAME}/BATCH_PROCESSING_REPORT.txt" in names

        for product in products:
            base = f"{OUTPUT_ROOT_NAME}/{product.name}"
            assert f"{base}/PROCESSING_REPORT.txt" in names
            for image in product.images:
                assert f"{base}/ORIGINAL_PHOTOS/{image.filename}" in names
            enhanced_names = [n for n in names if n.startswith(f"{base}/ENHANCED_PHOTOS/")]
            assert len(enhanced_names) == len(product.images)

        batch_report_text = zf.read(f"{OUTPUT_ROOT_NAME}/BATCH_PROCESSING_REPORT.txt").decode("utf-8")
        assert "BATCH PROCESSING REPORT" in batch_report_text
