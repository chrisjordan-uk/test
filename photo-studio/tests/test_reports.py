from __future__ import annotations

from app.models import (
    BatchReport,
    ImageProcessingResult,
    ProcessingMode,
    ProcessingSettings,
    ProductProcessingReport,
)
from app.report import build_batch_report, build_product_report


def _sample_product_report() -> ProductProcessingReport:
    report = ProductProcessingReport(folder_name="ITEM_01")
    report.results.append(
        ImageProcessingResult(
            filename="photo1.jpg",
            success=True,
            used_local_fallback=True,
            operations=["Resized to a maximum dimension of 1600px."],
            output_filename="photo1_enhanced.jpg",
        )
    )
    report.results.append(
        ImageProcessingResult(
            filename="photo2.jpg",
            success=False,
            marked_unsafe=True,
            warnings=["Gemini edit failed the product-preservation safety check."],
            output_filename="photo2_enhanced.jpg",
            similarity_score=0.2,
        )
    )
    return report


def test_product_report_contains_key_fields():
    settings = ProcessingSettings(mode=ProcessingMode.CLEAN_PRODUCT_PHOTO)
    text = build_product_report(_sample_product_report(), settings)
    assert "ITEM_01" in text
    assert "photo1.jpg" in text
    assert "photo2.jpg" in text
    assert "UNSAFE / ORIGINAL KEPT" in text
    assert "similarity" in text.lower()


def test_batch_report_contains_counts_and_privacy_notice():
    settings = ProcessingSettings(mode=ProcessingMode.CLEAN_PRODUCT_PHOTO, use_gemini=True)
    batch = BatchReport(product_reports=[_sample_product_report()], total_images=2, gemini_calls_made=1)
    text = build_batch_report(batch, settings, extraction_warnings=["Skipped foo.txt"])

    assert "BATCH PROCESSING REPORT" in text
    assert "Total images: 2" in text
    assert "Gemini" in text
    assert "Privacy notice" in text
    assert "Skipped foo.txt" in text


def test_batch_report_without_gemini_states_local_only():
    settings = ProcessingSettings(use_gemini=False)
    batch = BatchReport(product_reports=[_sample_product_report()], total_images=2)
    text = build_batch_report(batch, settings, extraction_warnings=[])
    assert "Gemini was disabled" in text
