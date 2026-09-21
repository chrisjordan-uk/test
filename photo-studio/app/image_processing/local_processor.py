"""Local, non-destructive image enhancement using Pillow and OpenCV.

This is the always-available fallback (and, for many photos, the only step
needed). Everything here is deliberately conservative:

- Lighting/contrast/white-balance/sharpening/denoising operate on the whole
  frame and never touch the garment's shape, colour identity or detail.
- Background replacement / centring only runs when the background is
  confidently uniform (i.e. we can tell foreground from background without
  guessing); otherwise the original background is preserved untouched
  rather than risking a misleading result.
- Aspect-ratio conversion pads onto a canvas; it never crops into the
  product.
"""
from __future__ import annotations

from dataclasses import dataclass

import cv2
import numpy as np
import pillow_heif
from PIL import Image, ImageOps

from app.models import ASPECT_RATIO_VALUES, BACKGROUND_RGB, AspectRatio, BackgroundStyle, ProcessingMode, ProcessingSettings

# Registers a Pillow plugin so Image.open() transparently reads iPhone
# HEIC/HEIF photos — no separate code path needed anywhere else.
pillow_heif.register_heif_opener()

CORNER_SAMPLE_FRACTION = 0.06
CORNER_UNIFORMITY_STD_THRESHOLD = 18.0
FLOOD_FILL_TOLERANCE = 22


def load_image(path) -> Image.Image:
    """Load an image (JPEG/PNG/WEBP/HEIC/HEIF), applying EXIF orientation, as RGB."""
    img = Image.open(path)
    img = ImageOps.exif_transpose(img)
    if img.mode != "RGB":
        img = img.convert("RGB")
    return img


def pil_to_cv(image: Image.Image) -> np.ndarray:
    return cv2.cvtColor(np.array(image), cv2.COLOR_RGB2BGR)


def cv_to_pil(mat: np.ndarray) -> Image.Image:
    return Image.fromarray(cv2.cvtColor(mat, cv2.COLOR_BGR2RGB))


@dataclass
class BackgroundAnalysis:
    is_uniform: bool
    corner_color_bgr: tuple[int, int, int]
    std_dev: float


def analyze_background(mat: np.ndarray) -> BackgroundAnalysis:
    """Conservatively decide whether the image has a near-uniform background.

    Samples the four corners; only if they agree with each other AND have
    low internal variance do we call the background "uniform" and safe to
    touch. Anything else (busy backgrounds, gradients, clutter) is left
    alone.
    """
    h, w = mat.shape[:2]
    cs_h = max(4, int(h * CORNER_SAMPLE_FRACTION))
    cs_w = max(4, int(w * CORNER_SAMPLE_FRACTION))
    corners = [
        mat[0:cs_h, 0:cs_w],
        mat[0:cs_h, w - cs_w:w],
        mat[h - cs_h:h, 0:cs_w],
        mat[h - cs_h:h, w - cs_w:w],
    ]
    corner_means = np.array([c.reshape(-1, 3).mean(axis=0) for c in corners])
    corner_stds = np.array([c.reshape(-1, 3).std() for c in corners])

    between_corner_std = corner_means.std(axis=0).mean()
    within_corner_std = corner_stds.mean()
    combined_std = max(between_corner_std, within_corner_std)

    is_uniform = bool(combined_std < CORNER_UNIFORMITY_STD_THRESHOLD)
    avg_color = tuple(int(v) for v in corner_means.mean(axis=0))
    return BackgroundAnalysis(is_uniform=is_uniform, corner_color_bgr=avg_color, std_dev=float(combined_std))


def segment_foreground_bbox(mat: np.ndarray, analysis: BackgroundAnalysis) -> tuple[int, int, int, int] | None:
    """Return a conservative (x, y, w, h) bounding box of the product, or None.

    Uses flood fill seeded from the four corners against the detected
    background colour. Only trusted when the resulting foreground region is
    a sane size (not almost-empty, not almost-everything), which avoids
    false segmentation on textured-but-uniform-ish backgrounds.
    """
    if not analysis.is_uniform:
        return None

    h, w = mat.shape[:2]
    mask = np.zeros((h + 2, w + 2), np.uint8)
    flood_mask = np.zeros((h, w), np.uint8)
    tol = (FLOOD_FILL_TOLERANCE,) * 3

    work = mat.copy()
    seeds = [(0, 0), (w - 1, 0), (0, h - 1), (w - 1, h - 1)]
    for seed in seeds:
        fill_mask = np.zeros((h + 2, w + 2), np.uint8)
        cv2.floodFill(work, fill_mask, seed, (0, 0, 0), loDiff=tol, upDiff=tol, flags=4)
        flood_mask = cv2.bitwise_or(flood_mask, fill_mask[1:-1, 1:-1])

    foreground_mask = cv2.bitwise_not(flood_mask * 255)
    fg_ratio = float(np.count_nonzero(foreground_mask)) / (h * w)

    if fg_ratio < 0.02 or fg_ratio > 0.92:
        # Segmentation is either empty or barely removed anything useful —
        # don't trust it rather than risk cropping into the product.
        return None

    ys, xs = np.where(foreground_mask > 0)
    if len(xs) == 0:
        return None
    x0, x1 = int(xs.min()), int(xs.max())
    y0, y1 = int(ys.min()), int(ys.max())
    return x0, y0, x1 - x0 + 1, y1 - y0 + 1


def apply_white_balance(mat: np.ndarray) -> np.ndarray:
    """Gray-world white balance — corrects colour casts without shifting hue identity much."""
    result = mat.astype(np.float32)
    avg_b, avg_g, avg_r = [result[:, :, i].mean() for i in range(3)]
    avg_gray = (avg_b + avg_g + avg_r) / 3.0
    for i, avg in enumerate((avg_b, avg_g, avg_r)):
        if avg > 1e-3:
            result[:, :, i] *= avg_gray / avg
    return np.clip(result, 0, 255).astype(np.uint8)


def apply_auto_contrast_brightness(mat: np.ndarray) -> np.ndarray:
    """CLAHE on the L channel of LAB — improves local contrast/brightness gently."""
    lab = cv2.cvtColor(mat, cv2.COLOR_BGR2LAB)
    l, a, b = cv2.split(lab)
    clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
    l2 = clahe.apply(l)
    lab2 = cv2.merge((l2, a, b))
    return cv2.cvtColor(lab2, cv2.COLOR_LAB2BGR)


def apply_sharpen(mat: np.ndarray) -> np.ndarray:
    blurred = cv2.GaussianBlur(mat, (0, 0), sigmaX=2.0)
    return cv2.addWeighted(mat, 1.4, blurred, -0.4, 0)


def apply_denoise(mat: np.ndarray) -> np.ndarray:
    return cv2.fastNlMeansDenoisingColored(mat, None, h=4, hColor=4, templateWindowSize=7, searchWindowSize=21)


def pad_to_canvas(image: Image.Image, bg_rgb: tuple[int, int, int], margin_fraction: float = 0.06) -> Image.Image:
    """Place the image, shrunk slightly, centred on a plain canvas of its own size + margin."""
    w, h = image.size
    margin = int(round(max(w, h) * margin_fraction))
    canvas = Image.new("RGB", (w + 2 * margin, h + 2 * margin), bg_rgb)
    canvas.paste(image, (margin, margin))
    return canvas


def crop_to_bbox_with_margin(
    image: Image.Image, bbox: tuple[int, int, int, int], margin_fraction: float = 0.08
) -> Image.Image:
    x, y, w, h = bbox
    mx = int(round(w * margin_fraction))
    my = int(round(h * margin_fraction))
    img_w, img_h = image.size
    left = max(0, x - mx)
    top = max(0, y - my)
    right = min(img_w, x + w + mx)
    bottom = min(img_h, y + h + my)
    return image.crop((left, top, right, bottom))


def convert_aspect_ratio(image: Image.Image, ratio: AspectRatio, bg_rgb: tuple[int, int, int]) -> Image.Image:
    """Letterbox/pillarbox the image onto ``ratio`` — never crops the product."""
    if ratio == AspectRatio.ORIGINAL:
        return image
    target_w, target_h = ASPECT_RATIO_VALUES[ratio]
    target_aspect = target_w / target_h
    w, h = image.size
    current_aspect = w / h

    if abs(current_aspect - target_aspect) < 1e-3:
        return image

    if current_aspect > target_aspect:
        new_w = w
        new_h = int(round(w / target_aspect))
    else:
        new_h = h
        new_w = int(round(h * target_aspect))

    canvas = Image.new("RGB", (new_w, new_h), bg_rgb)
    offset = ((new_w - w) // 2, (new_h - h) // 2)
    canvas.paste(image, offset)
    return canvas


def resize_max_dimension(image: Image.Image, max_dimension: int) -> Image.Image:
    w, h = image.size
    if max(w, h) <= max_dimension:
        return image
    scale = max_dimension / max(w, h)
    new_size = (max(1, int(w * scale)), max(1, int(h * scale)))
    return image.resize(new_size, Image.LANCZOS)


def enhance_locally(
    image: Image.Image, settings: ProcessingSettings
) -> tuple[Image.Image, list[str], list[str]]:
    """Run the full local enhancement pipeline. Returns (image, operations, warnings)."""
    operations: list[str] = []
    warnings: list[str] = []

    result = image
    mat = pil_to_cv(result)

    # --- Lighting / quality (safe on every mode; never touches product identity) ---
    mat = apply_white_balance(mat)
    operations.append("Corrected white balance (gray-world).")

    mat = apply_auto_contrast_brightness(mat)
    operations.append("Improved local contrast/brightness (CLAHE).")

    mat = apply_denoise(mat)
    operations.append("Reduced minor image noise.")

    mat = apply_sharpen(mat)
    operations.append("Applied gentle sharpening for clarity.")

    result = cv_to_pil(mat)

    # --- Background / framing (only for modes that call for it, only when confident) ---
    wants_background_work = settings.mode in {
        ProcessingMode.CLEAN_PRODUCT_PHOTO,
        ProcessingMode.BACKGROUND_CLEANUP,
        ProcessingMode.MARKETPLACE_COVER,
        ProcessingMode.BATCH_CONSISTENCY,
    }
    if wants_background_work and settings.background_style != BackgroundStyle.PRESERVE_ORIGINAL:
        analysis = analyze_background(pil_to_cv(result))
        if analysis.is_uniform:
            bbox = segment_foreground_bbox(pil_to_cv(result), analysis)
            bg_rgb = BACKGROUND_RGB[settings.background_style]
            if bbox is not None:
                result = crop_to_bbox_with_margin(result, bbox)
                result = pad_to_canvas(result, bg_rgb)
                operations.append(
                    f"Centred product and replaced background with '{settings.background_style.value}' "
                    "(uniform background detected)."
                )
            else:
                warnings.append(
                    "Background looked uniform but the product could not be confidently isolated; "
                    "left background untouched to avoid a misleading crop."
                )
        else:
            warnings.append(
                "Background is not uniform enough to safely replace; kept the original background."
            )
    elif settings.background_style == BackgroundStyle.PRESERVE_ORIGINAL:
        operations.append("Preserved original background (per settings).")

    # --- Aspect ratio ---
    target_ratio = settings.aspect_ratio
    if settings.mode == ProcessingMode.MARKETPLACE_COVER and target_ratio == AspectRatio.ORIGINAL:
        target_ratio = AspectRatio.RATIO_4_5
    if target_ratio != AspectRatio.ORIGINAL:
        bg_rgb = BACKGROUND_RGB.get(settings.background_style, (245, 245, 242))
        result = convert_aspect_ratio(result, target_ratio, bg_rgb)
        operations.append(f"Converted to {target_ratio.value} aspect ratio (padded, not cropped).")

    # --- Final resize ---
    result = resize_max_dimension(result, settings.max_dimension)
    operations.append(f"Resized to a maximum dimension of {settings.max_dimension}px.")

    return result, operations, warnings


def finish_image(image: Image.Image, settings: ProcessingSettings) -> tuple[Image.Image, list[str]]:
    """Lightweight finishing pass applied on top of a Gemini-edited image.

    Only handles aspect ratio and final resizing — Gemini already handled
    lighting/background, so we don't re-run those local corrections on its
    output.
    """
    operations: list[str] = []
    result = image

    target_ratio = settings.aspect_ratio
    if settings.mode == ProcessingMode.MARKETPLACE_COVER and target_ratio == AspectRatio.ORIGINAL:
        target_ratio = AspectRatio.RATIO_4_5
    if target_ratio != AspectRatio.ORIGINAL:
        bg_rgb = BACKGROUND_RGB.get(settings.background_style, (245, 245, 242))
        result = convert_aspect_ratio(result, target_ratio, bg_rgb)
        operations.append(f"Converted to {target_ratio.value} aspect ratio (padded, not cropped).")

    result = resize_max_dimension(result, settings.max_dimension)
    operations.append(f"Resized to a maximum dimension of {settings.max_dimension}px.")
    return result, operations
