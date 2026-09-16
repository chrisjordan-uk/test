"""Human-readable processing reports (per product and whole-batch)."""
from __future__ import annotations

from datetime import datetime, timezone

from app.models import BatchReport, ProcessingSettings, ProductProcessingReport

PRIVACY_NOTICE = (
    "Privacy notice: when Gemini processing is enabled, photographs are sent to Google's "
    "Gemini API for editing. Disable Gemini in the settings to keep all processing local."
)


def _timestamp() -> str:
    return datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S UTC")


def build_product_report(report: ProductProcessingReport, settings: ProcessingSettings) -> str:
    lines: list[str] = []
    lines.append(f"PROCESSING REPORT — {report.folder_name}")
    lines.append("=" * (len(lines[0])))
    lines.append(f"Generated: {_timestamp()}")
    lines.append(f"Mode: {settings.mode.value}")
    lines.append(f"Background style: {settings.background_style.value}")
    lines.append(f"Aspect ratio: {settings.aspect_ratio.value}")
    lines.append(
        f"Images: {len(report.results)} total | {report.success_count} enhanced | "
        f"{report.unsafe_count} kept original (safety) | {report.failure_count} failed"
    )
    lines.append("")

    for result in report.results:
        lines.append(f"- {result.filename}")
        status = "OK" if result.success else ("UNSAFE / ORIGINAL KEPT" if result.marked_unsafe else "FAILED")
        lines.append(f"    Status: {status}")
        if result.output_filename:
            lines.append(f"    Output: ENHANCED_PHOTOS/{result.output_filename}")
        if result.used_gemini:
            lines.append("    Enhanced by: Gemini AI")
        elif result.used_local_fallback:
            lines.append("    Enhanced by: Local processing (Pillow/OpenCV)")
        elif result.success:
            lines.append("    Enhanced by: none (resized only)")
        if result.similarity_score is not None:
            lines.append(f"    Preservation similarity score: {result.similarity_score:.2f}")
        for op in result.operations:
            lines.append(f"    - {op}")
        for warning in result.warnings:
            lines.append(f"    WARNING: {warning}")
        if result.error:
            lines.append(f"    ERROR: {result.error}")
        lines.append("")

    lines.append(PRIVACY_NOTICE if settings.use_gemini else "Gemini was disabled for this batch; all processing was local.")
    lines.append("")
    lines.append(
        "This report does not certify the garment's condition. Any defects visible in the "
        "original photographs (stains, holes, tears, wear, etc.) remain visible in the enhanced "
        "photographs by design."
    )
    return "\n".join(lines)


def build_batch_report(batch: BatchReport, settings: ProcessingSettings, extraction_warnings: list[str]) -> str:
    lines: list[str] = []
    title = "BATCH PROCESSING REPORT — Jordyn AI Photo Studio"
    lines.append(title)
    lines.append("=" * len(title))
    lines.append(f"Generated: {_timestamp()}")
    lines.append(f"Mode: {settings.mode.value}")
    lines.append(f"Gemini enabled: {settings.use_gemini}")
    lines.append(f"Local enhancement enabled: {settings.use_local_enhancement}")
    lines.append(f"Background style: {settings.background_style.value}")
    lines.append(f"Aspect ratio: {settings.aspect_ratio.value}")
    lines.append(f"Products processed: {len(batch.product_reports)}")
    lines.append(f"Total images: {batch.total_images}")
    lines.append(f"  Successfully enhanced: {batch.total_success}")
    lines.append(f"  Kept original (AI safety check failed): {batch.total_unsafe}")
    lines.append(f"  Failed: {batch.total_failure}")
    lines.append(f"Gemini API calls made: {batch.gemini_calls_made}")
    lines.append("")

    lines.append("Per-product summary:")
    for product_report in batch.product_reports:
        lines.append(
            f"  - {product_report.folder_name}: {len(product_report.results)} images, "
            f"{product_report.success_count} enhanced, {product_report.unsafe_count} kept original, "
            f"{product_report.failure_count} failed"
        )
    lines.append("")

    if extraction_warnings:
        lines.append("ZIP extraction warnings:")
        for w in extraction_warnings:
            lines.append(f"  - {w}")
        lines.append("")

    lines.append(PRIVACY_NOTICE if settings.use_gemini else "Gemini was disabled for this batch; all processing was local.")
    lines.append("")
    lines.append(
        "Strict preservation policy: this application never removes stains, holes, tears or other "
        "damage, never changes garment colour/shape/size, and never alters logos, labels or care "
        "tags. Any photo the AI could not confidently edit within these rules was left as the "
        "original image and flagged above."
    )
    return "\n".join(lines)
