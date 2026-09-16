from __future__ import annotations

from PIL import Image

from app.image_processing.local_processor import (
    analyze_background,
    convert_aspect_ratio,
    enhance_locally,
    pil_to_cv,
    resize_max_dimension,
)
from app.models import AspectRatio, BackgroundStyle, ProcessingMode, ProcessingSettings


def test_resize_max_dimension_shrinks_large_image():
    img = Image.new("RGB", (4000, 2000), (255, 255, 255))
    resized = resize_max_dimension(img, 1000)
    assert max(resized.size) == 1000
    assert resized.size[0] / resized.size[1] == 4000 / 2000


def test_resize_max_dimension_leaves_small_image_untouched():
    img = Image.new("RGB", (300, 200), (255, 255, 255))
    resized = resize_max_dimension(img, 1000)
    assert resized.size == (300, 200)


def test_convert_aspect_ratio_pads_without_cropping():
    img = Image.new("RGB", (400, 600), (255, 255, 255))
    result = convert_aspect_ratio(img, AspectRatio.RATIO_1_1, (255, 255, 255))
    w, h = result.size
    assert w == h
    # Padding only grows the canvas; it must never shrink below the original.
    assert w >= 400 and h >= 600


def test_convert_aspect_ratio_original_is_noop():
    img = Image.new("RGB", (400, 600), (255, 255, 255))
    result = convert_aspect_ratio(img, AspectRatio.ORIGINAL, (255, 255, 255))
    assert result.size == (400, 600)


def test_analyze_background_detects_uniform_background():
    img = Image.new("RGB", (500, 500), (250, 250, 250))
    analysis = analyze_background(pil_to_cv(img))
    assert analysis.is_uniform is True


def test_analyze_background_detects_noisy_background():
    import numpy as np

    rng = np.random.default_rng(42)
    noisy = (rng.integers(0, 255, size=(500, 500, 3))).astype("uint8")
    img = Image.fromarray(noisy)
    analysis = analyze_background(pil_to_cv(img))
    assert analysis.is_uniform is False


def test_enhance_locally_runs_and_reports_operations():
    img = Image.new("RGB", (800, 1000), (255, 255, 255))
    settings = ProcessingSettings(
        mode=ProcessingMode.CLEAN_PRODUCT_PHOTO,
        background_style=BackgroundStyle.NEUTRAL_STUDIO,
        aspect_ratio=AspectRatio.RATIO_4_5,
        max_dimension=1200,
    )
    result_img, operations, warnings = enhance_locally(img, settings)
    assert isinstance(result_img, Image.Image)
    assert len(operations) > 0
    # 4:5 target
    assert abs(result_img.size[0] / result_img.size[1] - 4 / 5) < 0.02


def test_enhance_locally_preserves_original_background_when_requested():
    img = Image.new("RGB", (800, 1000), (255, 255, 255))
    settings = ProcessingSettings(
        mode=ProcessingMode.CLEAN_PRODUCT_PHOTO,
        background_style=BackgroundStyle.PRESERVE_ORIGINAL,
        aspect_ratio=AspectRatio.ORIGINAL,
        max_dimension=1200,
    )
    _, operations, _ = enhance_locally(img, settings)
    assert any("Preserved original background" in op for op in operations)


def test_lighting_mode_does_not_touch_background_or_aspect():
    img = Image.new("RGB", (800, 1000), (255, 255, 255))
    settings = ProcessingSettings(
        mode=ProcessingMode.LIGHTING_QUALITY,
        background_style=BackgroundStyle.NEUTRAL_STUDIO,
        aspect_ratio=AspectRatio.ORIGINAL,
        max_dimension=1200,
    )
    result_img, operations, _ = enhance_locally(img, settings)
    assert result_img.size == (800, 1000)
    assert not any("background" in op.lower() for op in operations)
