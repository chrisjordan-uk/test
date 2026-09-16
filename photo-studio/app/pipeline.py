"""Orchestrates processing a whole batch of product folders end-to-end."""
from __future__ import annotations

from pathlib import Path
from typing import Callable

from PIL import Image, UnidentifiedImageError

from app.config import Settings
from app.gemini_client import GeminiClient
from app.image_processing.local_processor import enhance_locally, finish_image, load_image, resize_max_dimension
from app.models import (
    BatchReport,
    ImageProcessingResult,
    ProcessingSettings,
    ProductFolder,
    ProductProcessingReport,
)

ProgressCallback = Callable[[str, str, int, int], None]


def _save_enhanced(image: Image.Image, dest_path: Path, jpeg_quality: int) -> None:
    dest_path.parent.mkdir(parents=True, exist_ok=True)
    rgb = image.convert("RGB") if image.mode != "RGB" else image
    rgb.save(dest_path, format="JPEG", quality=jpeg_quality, optimize=True)


def process_single_image(
    image_record,
    settings: ProcessingSettings,
    gemini_client: GeminiClient,
    enhanced_dir: Path,
) -> ImageProcessingResult:
    stem = Path(image_record.filename).stem
    output_filename = f"{stem}_enhanced.jpg"
    dest_path = enhanced_dir / output_filename

    try:
        original = load_image(image_record.original_path)
    except (UnidentifiedImageError, OSError) as exc:
        # Last-resort fallback: copy the raw original bytes through unchanged
        # so the product still has *something* in ENHANCED_PHOTOS.
        try:
            dest_path.parent.mkdir(parents=True, exist_ok=True)
            dest_path.write_bytes(Path(image_record.original_path).read_bytes())
            output_filename_used = Path(image_record.filename).name
        except OSError:
            output_filename_used = None
        return ImageProcessingResult(
            filename=image_record.filename,
            success=False,
            error=f"Could not read image ({exc}); original file copied unchanged where possible.",
            output_filename=output_filename_used,
        )

    operations: list[str] = []
    warnings: list[str] = []
    used_gemini = False
    used_local_fallback = False
    marked_unsafe = False
    similarity_score: float | None = None
    result_image: Image.Image | None = None
    success = True

    gemini_attempted_unsafe = False

    if settings.use_gemini and gemini_client.is_configured:
        gemini_result = gemini_client.edit_image(original, settings.mode, settings.background_style.value)
        if gemini_result.image is not None and gemini_result.is_safe:
            result_image = gemini_result.image
            used_gemini = True
            similarity_score = gemini_result.ssim
            cache_note = " (served from cache)" if gemini_result.used_cache else ""
            operations.append(
                f"Enhanced with Gemini ({gemini_client.settings.gemini_image_model}){cache_note}; "
                f"preservation check passed (similarity={gemini_result.ssim:.2f})."
            )
        else:
            gemini_attempted_unsafe = True
            if gemini_result.error:
                warnings.append(f"Gemini request failed: {gemini_result.error}")
            else:
                warnings.append(
                    f"Gemini edit failed the product-preservation safety check "
                    f"(similarity={gemini_result.ssim:.2f} below threshold); AI edit discarded."
                )
    elif settings.use_gemini and not gemini_client.is_configured:
        warnings.append("Gemini requested but not configured (missing GEMINI_API_KEY); used local processing instead.")

    if gemini_attempted_unsafe:
        # Strict safety rule: if AI editing cannot be verified safe, keep the
        # ORIGINAL image rather than risk any misleading alteration.
        result_image = resize_max_dimension(original.copy(), settings.max_dimension)
        marked_unsafe = True
        success = False
        operations.append("Kept the original, unmodified photograph because the AI edit was rejected.")
    elif result_image is not None:
        result_image, finish_ops = finish_image(result_image, settings)
        operations.extend(finish_ops)
    else:
        if settings.use_local_enhancement:
            try:
                result_image, local_ops, local_warnings = enhance_locally(original, settings)
                operations.extend(local_ops)
                warnings.extend(local_warnings)
                used_local_fallback = True
            except Exception as exc:  # noqa: BLE001
                warnings.append(f"Local enhancement failed ({exc}); kept original image unchanged.")
                result_image = resize_max_dimension(original.copy(), settings.max_dimension)
        else:
            result_image = resize_max_dimension(original.copy(), settings.max_dimension)
            operations.append("Local enhancement disabled; original kept as-is (resized only).")

    try:
        _save_enhanced(result_image, dest_path, settings.jpeg_quality)
    except OSError as exc:
        return ImageProcessingResult(
            filename=image_record.filename,
            success=False,
            error=f"Failed to save processed image: {exc}",
        )

    return ImageProcessingResult(
        filename=image_record.filename,
        success=success,
        used_gemini=used_gemini,
        used_local_fallback=used_local_fallback,
        marked_unsafe=marked_unsafe,
        operations=operations,
        warnings=warnings,
        output_filename=output_filename,
        similarity_score=similarity_score,
    )


def process_batch(
    products: list[ProductFolder],
    settings: ProcessingSettings,
    app_settings: Settings,
    gemini_client: GeminiClient,
    output_root: Path,
    progress_callback: ProgressCallback | None = None,
) -> BatchReport:
    """Process every selected folder/image, writing enhanced copies under ``output_root``.

    ``output_root`` will end up containing one directory per product, each
    with an ``ENHANCED_PHOTOS`` subfolder — the caller is responsible for
    also placing ``ORIGINAL_PHOTOS`` and building the final ZIP.
    """
    selected = set(settings.selected_folders) if settings.selected_folders else None
    batch_report = BatchReport()

    active_products = [p for p in products if selected is None or p.name in selected]
    total_images = sum(len(p.images) for p in active_products)
    done = 0

    for product in active_products:
        product_report = ProductProcessingReport(folder_name=product.name)
        enhanced_dir = output_root / product.name / "ENHANCED_PHOTOS"

        for image_record in product.images:
            if progress_callback:
                progress_callback(product.name, image_record.filename, done, total_images)

            result = process_single_image(image_record, settings, gemini_client, enhanced_dir)
            product_report.results.append(result)
            if result.used_gemini:
                batch_report.gemini_calls_made += 1

            done += 1

        batch_report.product_reports.append(product_report)

    batch_report.total_images = total_images
    if progress_callback:
        progress_callback("", "", done, total_images)

    return batch_report
