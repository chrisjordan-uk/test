"""End-to-end smoke test: sample ZIP -> extraction -> processing -> output ZIP.

Not part of the pytest suite (it prints a human-readable summary); run with:
    python sample_data/run_e2e_smoke_test.py
"""
from __future__ import annotations

import shutil
import tempfile
import zipfile
from pathlib import Path

from app.config import get_settings
from app.gemini_client import GeminiClient
from app.models import AspectRatio, BackgroundStyle, ProcessingMode, ProcessingSettings
from app.output_zip import build_output_zip
from app.pipeline import process_batch
from app.zip_handler import detect_product_folders, safe_extract_zip

SAMPLE_ZIP = Path(__file__).resolve().parent / "STOCK_PHOTOS_SAMPLE.zip"


def main() -> None:
    app_settings = get_settings()
    work_dir = Path(tempfile.mkdtemp(prefix="jordyn_smoke_"))
    try:
        extract_dir = work_dir / "extracted"
        report = safe_extract_zip(SAMPLE_ZIP, extract_dir, app_settings)
        print(f"Extracted {report.extracted_files} image(s); {len(report.warnings)} warning(s).")

        products, folder_warnings = detect_product_folders(extract_dir, app_settings)
        print(f"Detected {len(products)} product folder(s): {[p.name for p in products]}")

        settings = ProcessingSettings(
            mode=ProcessingMode.MARKETPLACE_COVER,
            use_gemini=False,  # no live API key in this environment
            use_local_enhancement=True,
            background_style=BackgroundStyle.NEUTRAL_STUDIO,
            aspect_ratio=AspectRatio.RATIO_4_5,
        )
        gemini_client = GeminiClient(settings=app_settings, cache_dir=work_dir / "cache")
        enhanced_root = work_dir / "enhanced"

        def progress(folder, filename, done, total):
            if folder:
                print(f"  [{done + 1}/{total}] {folder}/{filename}")

        batch_report = process_batch(products, settings, app_settings, gemini_client, enhanced_root, progress)

        print(
            f"Processed: {batch_report.total_success} enhanced, "
            f"{batch_report.total_unsafe} kept original, {batch_report.total_failure} failed."
        )

        output_zip_path = work_dir / "JORDYN_PHOTO_STUDIO_OUTPUT.zip"
        build_output_zip(products, batch_report, settings, enhanced_root, output_zip_path, folder_warnings)

        with zipfile.ZipFile(output_zip_path) as zf:
            names = zf.namelist()
        print(f"Output ZIP created: {output_zip_path} ({len(names)} entries)")

        final_dest = SAMPLE_ZIP.parent / "SAMPLE_OUTPUT_PREVIEW.zip"
        shutil.copy(output_zip_path, final_dest)
        print(f"Copied output ZIP to {final_dest} for inspection.")

        assert batch_report.total_failure == 0, "Smoke test expected zero failures on clean sample data."
        assert batch_report.total_images == sum(len(p.images) for p in products)
        print("SMOKE TEST PASSED.")
    finally:
        shutil.rmtree(work_dir, ignore_errors=True)


if __name__ == "__main__":
    main()
