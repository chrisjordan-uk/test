"""Central configuration, loaded from environment variables / .env.

Nothing in this module ever hardcodes a secret. All values have sensible
defaults suitable for a small clothing-reselling batch job.
"""
from __future__ import annotations

import os
from dataclasses import dataclass, field
from pathlib import Path

from dotenv import load_dotenv

# Load .env once, on import, without overriding variables already set in
# the real environment (e.g. by the hosting platform).
load_dotenv(override=False)


def _env_bool(name: str, default: bool) -> bool:
    val = os.getenv(name)
    if val is None:
        return default
    return val.strip().lower() in {"1", "true", "yes", "on"}


def _env_int(name: str, default: int) -> int:
    val = os.getenv(name)
    if val is None or not val.strip():
        return default
    try:
        return int(val)
    except ValueError:
        return default


def _env_float(name: str, default: float) -> float:
    val = os.getenv(name)
    if val is None or not val.strip():
        return default
    try:
        return float(val)
    except ValueError:
        return default


SUPPORTED_IMAGE_EXTENSIONS = {".jpg", ".jpeg", ".png", ".webp"}

IGNORED_NAMES = {"__MACOSX", ".DS_Store", "Thumbs.db", "desktop.ini"}


@dataclass(frozen=True)
class Settings:
    # --- Gemini -------------------------------------------------------
    gemini_api_key: str = field(default_factory=lambda: os.getenv("GEMINI_API_KEY", "").strip())
    gemini_image_model: str = field(
        default_factory=lambda: os.getenv("GEMINI_IMAGE_MODEL", "gemini-2.5-flash-image").strip()
    )
    gemini_timeout_seconds: int = field(default_factory=lambda: _env_int("GEMINI_TIMEOUT_SECONDS", 60))
    gemini_max_retries: int = field(default_factory=lambda: _env_int("GEMINI_MAX_RETRIES", 3))
    gemini_retry_backoff_seconds: float = field(
        default_factory=lambda: _env_float("GEMINI_RETRY_BACKOFF_SECONDS", 2.0)
    )
    gemini_max_upload_dimension: int = field(
        default_factory=lambda: _env_int("GEMINI_MAX_UPLOAD_DIMENSION", 1536)
    )
    gemini_min_ssim: float = field(default_factory=lambda: _env_float("GEMINI_MIN_SSIM", 0.45))

    # --- Limits ---------------------------------------------------------
    max_zip_size_mb: int = field(default_factory=lambda: _env_int("MAX_ZIP_SIZE_MB", 300))
    max_product_folders: int = field(default_factory=lambda: _env_int("MAX_PRODUCT_FOLDERS", 60))
    max_images_per_folder: int = field(default_factory=lambda: _env_int("MAX_IMAGES_PER_FOLDER", 20))
    max_image_file_size_mb: int = field(default_factory=lambda: _env_int("MAX_IMAGE_FILE_SIZE_MB", 25))
    max_total_images: int = field(default_factory=lambda: _env_int("MAX_TOTAL_IMAGES", 400))

    # --- Defaults for image settings ------------------------------------
    default_use_gemini: bool = field(default_factory=lambda: _env_bool("DEFAULT_USE_GEMINI", True))
    default_use_local_enhancement: bool = field(
        default_factory=lambda: _env_bool("DEFAULT_USE_LOCAL_ENHANCEMENT", True)
    )
    default_jpeg_quality: int = field(default_factory=lambda: _env_int("DEFAULT_JPEG_QUALITY", 90))
    default_max_dimension: int = field(default_factory=lambda: _env_int("DEFAULT_MAX_DIMENSION", 1600))

    # --- App --------------------------------------------------------
    app_name: str = "Jordyn AI Photo Studio"
    temp_dir_prefix: str = "jordyn_photo_studio_"

    @property
    def gemini_enabled_by_default(self) -> bool:
        return self.default_use_gemini and bool(self.gemini_api_key)


def get_settings() -> Settings:
    """Return a fresh Settings snapshot (cheap; env vars rarely change at runtime)."""
    return Settings()


def project_root() -> Path:
    return Path(__file__).resolve().parent.parent
