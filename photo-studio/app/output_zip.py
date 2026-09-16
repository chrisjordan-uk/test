"""Builds the final downloadable ZIP: originals + enhanced photos + reports."""
from __future__ import annotations

import zipfile
from pathlib import Path

from app.models import BatchReport, ProcessingSettings, ProductFolder
from app.report import build_batch_report, build_product_report

OUTPUT_ROOT_NAME = "JORDYN_PHOTO_STUDIO_OUTPUT"


def build_output_zip(
    products: list[ProductFolder],
    batch_report: BatchReport,
    settings: ProcessingSettings,
    enhanced_root: Path,
    dest_zip_path: Path,
    extraction_warnings: list[str] | None = None,
) -> Path:
    """Assemble the output ZIP at ``dest_zip_path`` and return that path."""
    extraction_warnings = extraction_warnings or []
    report_by_folder = {r.folder_name: r for r in batch_report.product_reports}

    dest_zip_path.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(dest_zip_path, "w", compression=zipfile.ZIP_DEFLATED) as zf:
        for product in products:
            base = f"{OUTPUT_ROOT_NAME}/{product.name}"

            for image in product.images:
                arcname = f"{base}/ORIGINAL_PHOTOS/{image.filename}"
                zf.write(image.original_path, arcname)

            enhanced_dir = enhanced_root / product.name / "ENHANCED_PHOTOS"
            if enhanced_dir.exists():
                for enhanced_file in sorted(enhanced_dir.iterdir()):
                    if enhanced_file.is_file():
                        zf.write(enhanced_file, f"{base}/ENHANCED_PHOTOS/{enhanced_file.name}")

            product_report = report_by_folder.get(product.name)
            if product_report is not None:
                report_text = build_product_report(product_report, settings)
                zf.writestr(f"{base}/PROCESSING_REPORT.txt", report_text)

        batch_text = build_batch_report(batch_report, settings, extraction_warnings)
        zf.writestr(f"{OUTPUT_ROOT_NAME}/BATCH_PROCESSING_REPORT.txt", batch_text)

    return dest_zip_path
