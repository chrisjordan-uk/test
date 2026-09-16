"""Plain data structures shared across the pipeline."""
from __future__ import annotations

from dataclasses import dataclass, field
from enum import Enum
from pathlib import Path


class ProcessingMode(str, Enum):
    CLEAN_PRODUCT_PHOTO = "clean_product_photo"
    BACKGROUND_CLEANUP = "background_cleanup"
    LIGHTING_QUALITY = "lighting_quality"
    MARKETPLACE_COVER = "marketplace_cover"
    BATCH_CONSISTENCY = "batch_consistency"


MODE_LABELS = {
    ProcessingMode.CLEAN_PRODUCT_PHOTO: "A — Clean Product Photo",
    ProcessingMode.BACKGROUND_CLEANUP: "B — Background Cleanup",
    ProcessingMode.LIGHTING_QUALITY: "C — Lighting & Quality Enhancement",
    ProcessingMode.MARKETPLACE_COVER: "D — Marketplace Cover Image",
    ProcessingMode.BATCH_CONSISTENCY: "E — Batch Consistency",
}


class BackgroundStyle(str, Enum):
    WHITE = "white"
    LIGHT_GREY = "light_grey"
    BEIGE = "beige"
    NEUTRAL_STUDIO = "neutral_studio"
    PRESERVE_ORIGINAL = "preserve_original"


BACKGROUND_RGB = {
    BackgroundStyle.WHITE: (255, 255, 255),
    BackgroundStyle.LIGHT_GREY: (235, 235, 233),
    BackgroundStyle.BEIGE: (240, 232, 218),
    BackgroundStyle.NEUTRAL_STUDIO: (245, 245, 242),
}


class AspectRatio(str, Enum):
    ORIGINAL = "original"
    RATIO_4_5 = "4:5"
    RATIO_1_1 = "1:1"
    RATIO_3_4 = "3:4"


ASPECT_RATIO_VALUES = {
    AspectRatio.RATIO_4_5: (4, 5),
    AspectRatio.RATIO_1_1: (1, 1),
    AspectRatio.RATIO_3_4: (3, 4),
}


@dataclass
class ProcessingSettings:
    mode: ProcessingMode = ProcessingMode.CLEAN_PRODUCT_PHOTO
    use_gemini: bool = True
    use_local_enhancement: bool = True
    background_style: BackgroundStyle = BackgroundStyle.NEUTRAL_STUDIO
    aspect_ratio: AspectRatio = AspectRatio.ORIGINAL
    max_dimension: int = 1600
    jpeg_quality: int = 90
    selected_folders: list[str] | None = None  # None = all folders


@dataclass
class ImageRecord:
    """One original photograph belonging to a product folder."""
    filename: str
    original_path: Path
    size_bytes: int


@dataclass
class ProductFolder:
    """One detected product (one folder in the uploaded ZIP)."""
    name: str
    images: list[ImageRecord] = field(default_factory=list)


@dataclass
class ImageProcessingResult:
    filename: str
    success: bool
    used_gemini: bool = False
    used_local_fallback: bool = False
    marked_unsafe: bool = False
    operations: list[str] = field(default_factory=list)
    warnings: list[str] = field(default_factory=list)
    error: str | None = None
    output_filename: str | None = None
    similarity_score: float | None = None


@dataclass
class ProductProcessingReport:
    folder_name: str
    results: list[ImageProcessingResult] = field(default_factory=list)

    @property
    def success_count(self) -> int:
        return sum(1 for r in self.results if r.success and not r.marked_unsafe)

    @property
    def unsafe_count(self) -> int:
        return sum(1 for r in self.results if r.marked_unsafe)

    @property
    def failure_count(self) -> int:
        return sum(1 for r in self.results if not r.success)


@dataclass
class BatchReport:
    product_reports: list[ProductProcessingReport] = field(default_factory=list)
    total_images: int = 0
    gemini_calls_made: int = 0
    gemini_calls_cached: int = 0

    @property
    def total_success(self) -> int:
        return sum(r.success_count for r in self.product_reports)

    @property
    def total_unsafe(self) -> int:
        return sum(r.unsafe_count for r in self.product_reports)

    @property
    def total_failure(self) -> int:
        return sum(r.failure_count for r in self.product_reports)
