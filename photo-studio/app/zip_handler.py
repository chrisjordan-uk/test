"""Upload ZIP validation, safe extraction and product-folder detection.

Security notes
---------------
- ``safe_extract_zip`` rejects any member whose resolved path would land
  outside the extraction directory (the classic "zip slip" / path-traversal
  attack), and rejects absolute paths and members containing ``..``.
- File size and file count are bounded by ``Settings`` before any bytes are
  written to disk.
- Only recognised image extensions are treated as photographs; everything
  else is ignored (and reported).
"""
from __future__ import annotations

import os
import zipfile
from dataclasses import dataclass, field
from pathlib import Path

from app.config import IGNORED_NAMES, SUPPORTED_IMAGE_EXTENSIONS, Settings
from app.models import ImageRecord, ProductFolder


class ZipValidationError(Exception):
    """Raised when the uploaded ZIP fails validation or is unsafe to extract."""


@dataclass
class ExtractionReport:
    extracted_files: int = 0
    skipped_entries: list[str] = field(default_factory=list)
    warnings: list[str] = field(default_factory=list)


def _is_ignored(name: str) -> bool:
    parts = Path(name).parts
    return any(p in IGNORED_NAMES or p.startswith("._") for p in parts)


def _is_safe_member(member_name: str, dest_dir: Path) -> bool:
    """True only if extracting ``member_name`` under ``dest_dir`` cannot escape it."""
    if not member_name or member_name.strip() in {"", "."}:
        return False
    normalized = member_name.replace("\\", "/")
    if normalized.startswith("/") or (len(normalized) > 1 and normalized[1] == ":"):
        return False  # absolute POSIX or Windows drive path
    if any(part == ".." for part in normalized.split("/")):
        return False
    target = (dest_dir / normalized).resolve()
    try:
        target.relative_to(dest_dir.resolve())
    except ValueError:
        return False
    return True


def validate_zip_bytes(size_bytes: int, settings: Settings) -> None:
    max_bytes = settings.max_zip_size_mb * 1024 * 1024
    if size_bytes <= 0:
        raise ZipValidationError("The uploaded file is empty.")
    if size_bytes > max_bytes:
        raise ZipValidationError(
            f"The uploaded ZIP is {size_bytes / (1024 * 1024):.1f} MB, which exceeds the "
            f"configured limit of {settings.max_zip_size_mb} MB (MAX_ZIP_SIZE_MB)."
        )


def open_zip(path: Path) -> zipfile.ZipFile:
    try:
        zf = zipfile.ZipFile(path)
    except zipfile.BadZipFile as exc:
        raise ZipValidationError("The uploaded file is not a valid ZIP archive.") from exc
    bad_member = zf.testzip()
    if bad_member is not None:
        zf.close()
        raise ZipValidationError(f"The ZIP archive is corrupt (bad member: {bad_member}).")
    return zf


def safe_extract_zip(zip_path: Path, dest_dir: Path, settings: Settings) -> ExtractionReport:
    """Extract ``zip_path`` into ``dest_dir``, refusing anything unsafe.

    Unsafe or oversized individual members are skipped (not fatal) and noted
    in the returned report, so one bad entry doesn't sink the whole batch.
    """
    dest_dir.mkdir(parents=True, exist_ok=True)
    report = ExtractionReport()
    max_image_bytes = settings.max_image_file_size_mb * 1024 * 1024

    with open_zip(zip_path) as zf:
        infos = zf.infolist()
        for info in infos:
            name = info.filename
            if info.is_dir():
                continue
            if _is_ignored(name):
                continue
            if not _is_safe_member(name, dest_dir):
                report.skipped_entries.append(name)
                report.warnings.append(f"Skipped unsafe path in ZIP: {name!r}")
                continue

            ext = Path(name).suffix.lower()
            if ext not in SUPPORTED_IMAGE_EXTENSIONS:
                report.skipped_entries.append(name)
                report.warnings.append(f"Skipped unsupported file type: {name!r}")
                continue

            if info.file_size > max_image_bytes:
                report.skipped_entries.append(name)
                report.warnings.append(
                    f"Skipped {name!r}: {info.file_size / (1024 * 1024):.1f} MB exceeds "
                    f"the per-image limit of {settings.max_image_file_size_mb} MB."
                )
                continue

            target_path = (dest_dir / name.replace("\\", "/")).resolve()
            target_path.parent.mkdir(parents=True, exist_ok=True)
            with zf.open(info) as src, open(target_path, "wb") as dst:
                dst.write(src.read())
            report.extracted_files += 1

    if report.extracted_files == 0:
        raise ZipValidationError(
            "No supported image files were found in the ZIP (expected .jpg, .jpeg, .png or .webp "
            "files inside product folders)."
        )
    return report


def detect_product_folders(dest_dir: Path, settings: Settings) -> tuple[list[ProductFolder], list[str]]:
    """Walk the extracted directory and group images by their immediate parent folder.

    Each top-level directory (or, if images sit directly under nested paths,
    each directory that directly contains images) becomes one product. Images
    dropped straight in the ZIP root (no folder) are grouped under "UNSORTED".
    """
    warnings: list[str] = []
    folders: dict[str, list[Path]] = {}

    for root, dirnames, filenames in os.walk(dest_dir):
        dirnames[:] = [d for d in dirnames if d not in IGNORED_NAMES and not d.startswith(".")]
        root_path = Path(root)
        image_files = [
            f for f in filenames
            if Path(f).suffix.lower() in SUPPORTED_IMAGE_EXTENSIONS and not f.startswith("._")
        ]
        if not image_files:
            continue

        if root_path == dest_dir:
            folder_key = "UNSORTED"
        else:
            folder_key = root_path.relative_to(dest_dir).as_posix()

        for fname in sorted(image_files):
            folders.setdefault(folder_key, []).append(root_path / fname)

    if not folders:
        raise ZipValidationError("No product folders containing images were detected in the ZIP.")

    if len(folders) > settings.max_product_folders:
        raise ZipValidationError(
            f"The ZIP contains {len(folders)} product folders, which exceeds the configured "
            f"limit of {settings.max_product_folders} (MAX_PRODUCT_FOLDERS)."
        )

    total_images = sum(len(v) for v in folders.values())
    if total_images > settings.max_total_images:
        raise ZipValidationError(
            f"The ZIP contains {total_images} images, which exceeds the configured total limit "
            f"of {settings.max_total_images} (MAX_TOTAL_IMAGES)."
        )

    products: list[ProductFolder] = []
    for folder_name in sorted(folders.keys()):
        paths = folders[folder_name]
        if len(paths) > settings.max_images_per_folder:
            warnings.append(
                f"{folder_name!r} has {len(paths)} images; only the first "
                f"{settings.max_images_per_folder} (MAX_IMAGES_PER_FOLDER) will be processed."
            )
            paths = paths[: settings.max_images_per_folder]

        images = [
            ImageRecord(filename=p.name, original_path=p, size_bytes=p.stat().st_size)
            for p in paths
        ]
        products.append(ProductFolder(name=folder_name, images=images))

    return products, warnings
