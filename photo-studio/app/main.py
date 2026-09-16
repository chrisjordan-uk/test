"""Jordyn AI Photo Studio — Streamlit UI.

Batch-processes clothing product photographs: upload a ZIP of per-product
folders, preview the originals, choose enhancement settings, process the
whole batch, review results, and download a ZIP of originals + enhanced
photos + processing reports.
"""
from __future__ import annotations

import shutil
import tempfile
import uuid
from pathlib import Path

import streamlit as st
from PIL import Image

from app.config import get_settings
from app.gemini_client import GeminiClient, estimate_api_usage_warning
from app.models import (
    MODE_LABELS,
    AspectRatio,
    BackgroundStyle,
    ProcessingMode,
    ProcessingSettings,
)
from app.output_zip import build_output_zip
from app.pipeline import process_batch
from app.zip_handler import ZipValidationError, detect_product_folders, safe_extract_zip, validate_zip_bytes

APP_SETTINGS = get_settings()

st.set_page_config(page_title=APP_SETTINGS.app_name, page_icon="📸", layout="wide")


def _session_work_dir() -> Path:
    if "work_dir" not in st.session_state:
        base = Path(tempfile.gettempdir()) / f"{APP_SETTINGS.temp_dir_prefix}{uuid.uuid4().hex[:10]}"
        base.mkdir(parents=True, exist_ok=True)
        st.session_state.work_dir = str(base)
    return Path(st.session_state.work_dir)


def _reset_batch_state(clear_upload: bool = False) -> None:
    work_dir = Path(st.session_state.get("work_dir", ""))
    if work_dir.exists():
        shutil.rmtree(work_dir, ignore_errors=True)
    for key in ["work_dir", "products", "extraction_warnings", "batch_report", "output_zip_path", "zip_bytes_hash"]:
        st.session_state.pop(key, None)
    if clear_upload:
        st.session_state.pop("uploaded_name", None)


def _init_state() -> None:
    st.session_state.setdefault("products", None)
    st.session_state.setdefault("extraction_warnings", [])
    st.session_state.setdefault("batch_report", None)
    st.session_state.setdefault("output_zip_path", None)


def render_header() -> None:
    st.title(f"📸 {APP_SETTINGS.app_name}")
    st.caption(
        "Batch photo enhancement for clothing resale listings — upload a ZIP of product "
        "folders, enhance them consistently, and download the results."
    )
    with st.expander("Privacy & how this works", expanded=False):
        st.markdown(
            "- This tool only processes photographs. It does not manage inventory, pricing, "
            "sales, invoicing, or listings.\n"
            "- Original photographs are never modified or overwritten — enhanced copies are "
            "created alongside them.\n"
            "- Defects visible in your original photos (stains, holes, tears, wear) are always "
            "preserved in the enhanced copies.\n"
            "- **Privacy:** when Gemini AI editing is enabled, photographs are sent to Google's "
            "Gemini API for processing. Turn Gemini off in Processing Settings to keep everything "
            "fully local."
        )


def render_upload_section() -> None:
    st.header("1. Upload")
    uploaded = st.file_uploader(
        "Upload a ZIP file containing one folder per product (each folder holding that "
        "product's photos).",
        type=["zip"],
    )

    if uploaded is None:
        return

    if st.session_state.get("uploaded_name") != uploaded.name or st.session_state.get("products") is None:
        _reset_batch_state()
        st.session_state.uploaded_name = uploaded.name
        work_dir = _session_work_dir()
        zip_path = work_dir / "upload.zip"
        extract_dir = work_dir / "extracted"

        data = uploaded.getvalue()
        try:
            validate_zip_bytes(len(data), APP_SETTINGS)
            zip_path.write_bytes(data)
            extraction_report = safe_extract_zip(zip_path, extract_dir, APP_SETTINGS)
            products, folder_warnings = detect_product_folders(extract_dir, APP_SETTINGS)
        except ZipValidationError as exc:
            st.error(f"Could not process this ZIP: {exc}")
            _reset_batch_state(clear_upload=True)
            return

        st.session_state.products = products
        st.session_state.extraction_warnings = extraction_report.warnings + folder_warnings

    products = st.session_state.get("products")
    if not products:
        return

    total_images = sum(len(p.images) for p in products)
    st.success(f"Detected **{len(products)}** product folder(s) with **{total_images}** image(s) total.")

    if st.session_state.extraction_warnings:
        with st.expander(f"⚠️ {len(st.session_state.extraction_warnings)} warning(s) during extraction"):
            for w in st.session_state.extraction_warnings:
                st.write(f"- {w}")


def render_preview_section() -> list[str]:
    products = st.session_state.get("products")
    if not products:
        return []

    st.header("2. Preview original photographs")
    selected_folders: list[str] = []
    for product in products:
        with st.expander(f"{product.name}  ·  {len(product.images)} image(s)", expanded=False):
            include = st.checkbox("Include in processing", value=True, key=f"include_{product.name}")
            if include:
                selected_folders.append(product.name)
            cols = st.columns(min(4, max(1, len(product.images))))
            for i, image_record in enumerate(product.images):
                with cols[i % len(cols)]:
                    try:
                        img = Image.open(image_record.original_path)
                        st.image(img, caption=image_record.filename, use_container_width=True)
                    except Exception:
                        st.write(f"(preview unavailable: {image_record.filename})")
    return selected_folders


def render_settings_section() -> ProcessingSettings:
    st.header("3. Processing settings")
    col1, col2 = st.columns(2)

    with col1:
        mode_choice = st.selectbox(
            "Processing mode",
            options=list(ProcessingMode),
            format_func=lambda m: MODE_LABELS[m],
            index=0,
        )
        background_choice = st.selectbox(
            "Background style",
            options=list(BackgroundStyle),
            format_func=lambda b: b.value.replace("_", " ").title(),
            index=list(BackgroundStyle).index(BackgroundStyle.NEUTRAL_STUDIO),
        )
        aspect_choice = st.selectbox(
            "Output aspect ratio",
            options=list(AspectRatio),
            format_func=lambda a: a.value,
            index=0,
        )

    with col2:
        use_gemini = st.checkbox(
            "Use Gemini AI editing",
            value=APP_SETTINGS.gemini_enabled_by_default,
            help="Requires GEMINI_API_KEY to be configured. Falls back to local processing if unavailable.",
        )
        if use_gemini and not APP_SETTINGS.gemini_api_key:
            st.warning("GEMINI_API_KEY is not configured — Gemini editing will be skipped and local processing used instead.")
        use_local = st.checkbox("Use local enhancement fallback", value=APP_SETTINGS.default_use_local_enhancement)
        max_dimension = st.slider("Maximum output dimension (px)", 600, 4000, APP_SETTINGS.default_max_dimension, step=100)
        jpeg_quality = st.slider("JPEG quality", 60, 100, APP_SETTINGS.default_jpeg_quality, step=1)

    return ProcessingSettings(
        mode=mode_choice,
        use_gemini=use_gemini,
        use_local_enhancement=use_local,
        background_style=background_choice,
        aspect_ratio=aspect_choice,
        max_dimension=max_dimension,
        jpeg_quality=jpeg_quality,
    )


def render_processing_section(settings: ProcessingSettings, selected_folders: list[str]) -> None:
    products = st.session_state.get("products")
    if not products:
        return

    st.header("4. Process")
    settings.selected_folders = selected_folders or None
    active_products = [p for p in products if not selected_folders or p.name in selected_folders]
    total_images = sum(len(p.images) for p in active_products)

    usage_warning = estimate_api_usage_warning(total_images, settings.use_gemini)
    if usage_warning:
        st.info(usage_warning)

    if st.button("🚀 Process All Images", type="primary", disabled=total_images == 0):
        work_dir = _session_work_dir()
        enhanced_root = work_dir / "enhanced"
        gemini_client = GeminiClient(settings=APP_SETTINGS, cache_dir=work_dir / "gemini_cache")

        progress_bar = st.progress(0)
        status_text = st.empty()
        log_box = st.container(height=240)
        log_lines: list[str] = []

        def on_progress(folder: str, filename: str, done: int, total: int) -> None:
            if total > 0:
                progress_bar.progress(min(done / total, 1.0))
            if folder and filename:
                line = f"Processing [{folder}] {filename} ({done + 1}/{total})"
                status_text.write(line)
                log_lines.append(line)
                log_box.write(line)

        with st.spinner("Processing batch..."):
            batch_report = process_batch(
                products=active_products,
                settings=settings,
                app_settings=APP_SETTINGS,
                gemini_client=gemini_client,
                output_root=enhanced_root,
                progress_callback=on_progress,
            )

        progress_bar.progress(1.0)
        status_text.write(
            f"Done: {batch_report.total_success} enhanced, {batch_report.total_unsafe} kept original "
            f"(safety), {batch_report.total_failure} failed."
        )

        output_zip_path = work_dir / "JORDYN_PHOTO_STUDIO_OUTPUT.zip"
        build_output_zip(
            products=active_products,
            batch_report=batch_report,
            settings=settings,
            enhanced_root=enhanced_root,
            dest_zip_path=output_zip_path,
            extraction_warnings=st.session_state.get("extraction_warnings", []),
        )

        st.session_state.batch_report = batch_report
        st.session_state.output_zip_path = str(output_zip_path)
        st.session_state.enhanced_root = str(enhanced_root)
        st.rerun()


def render_results_section() -> None:
    batch_report = st.session_state.get("batch_report")
    if not batch_report:
        return

    st.header("5. Results")
    c1, c2, c3, c4 = st.columns(4)
    c1.metric("Total images", batch_report.total_images)
    c2.metric("Enhanced", batch_report.total_success)
    c3.metric("Kept original (safety)", batch_report.total_unsafe)
    c4.metric("Failed", batch_report.total_failure)

    products = st.session_state.get("products") or []
    products_by_name = {p.name: p for p in products}
    enhanced_root = Path(st.session_state.get("enhanced_root", ""))

    for product_report in batch_report.product_reports:
        product = products_by_name.get(product_report.folder_name)
        with st.expander(
            f"{product_report.folder_name} — {product_report.success_count} enhanced, "
            f"{product_report.unsafe_count} kept original, {product_report.failure_count} failed",
            expanded=False,
        ):
            for result in product_report.results:
                cols = st.columns(2)
                original_record = None
                if product:
                    original_record = next((i for i in product.images if i.filename == result.filename), None)
                with cols[0]:
                    st.caption(f"Original — {result.filename}")
                    if original_record:
                        try:
                            st.image(Image.open(original_record.original_path), use_container_width=True)
                        except Exception:
                            st.write("(preview unavailable)")
                with cols[1]:
                    status = "✅ Enhanced" if (result.success and not result.marked_unsafe) else (
                        "⚠️ Kept original (safety)" if result.marked_unsafe else "❌ Failed"
                    )
                    st.caption(f"{status} — {result.output_filename or ''}")
                    if result.output_filename:
                        out_path = enhanced_root / product_report.folder_name / "ENHANCED_PHOTOS" / result.output_filename
                        if out_path.exists():
                            st.image(Image.open(out_path), use_container_width=True)
                            with open(out_path, "rb") as f:
                                st.download_button(
                                    "Download this photo",
                                    data=f.read(),
                                    file_name=result.output_filename,
                                    key=f"dl_{product_report.folder_name}_{result.filename}",
                                )
                    for w in result.warnings:
                        st.warning(w)
                    if result.error:
                        st.error(result.error)
                st.divider()


def render_export_section() -> None:
    output_zip_path = st.session_state.get("output_zip_path")
    if not output_zip_path:
        return
    path = Path(output_zip_path)
    if not path.exists():
        return

    st.header("6. Download")
    with open(path, "rb") as f:
        st.download_button(
            "⬇️ Download JORDYN_PHOTO_STUDIO_OUTPUT.zip",
            data=f.read(),
            file_name="JORDYN_PHOTO_STUDIO_OUTPUT.zip",
            mime="application/zip",
            type="primary",
        )


def main() -> None:
    _init_state()
    render_header()
    render_upload_section()
    selected_folders = render_preview_section()
    if st.session_state.get("products"):
        settings = render_settings_section()
        render_processing_section(settings, selected_folders)
    render_results_section()
    render_export_section()


if __name__ == "__main__":
    main()
