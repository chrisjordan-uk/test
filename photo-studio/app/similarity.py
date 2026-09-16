"""A small, dependency-free structural-similarity (SSIM) check.

Used to sanity-check Gemini's edited output against the original photo: if
the edit is structurally very different from the source, we don't trust
that the garment was preserved, and fall back to the original/local
pipeline instead of shipping a possibly-misleading image.

This is a lightweight single-scale grayscale SSIM (no scikit-image
dependency), good enough as a coarse safety gate, not a scientific metric.
"""
from __future__ import annotations

import cv2
import numpy as np
from PIL import Image


def _to_gray(image: Image.Image, size: tuple[int, int]) -> np.ndarray:
    resized = image.convert("L").resize(size, Image.BILINEAR)
    return np.asarray(resized, dtype=np.float64)


def structural_similarity(image_a: Image.Image, image_b: Image.Image, compare_size: int = 256) -> float:
    """Return an SSIM-like score in [-1, 1] between two images (1 = identical)."""
    w, h = image_a.size
    aspect = h / w if w else 1.0
    target_size = (compare_size, max(1, int(compare_size * aspect)))

    a = _to_gray(image_a, target_size)
    b = _to_gray(image_b, target_size)

    c1 = (0.01 * 255) ** 2
    c2 = (0.03 * 255) ** 2

    kernel = (7, 7)
    mu_a = cv2.GaussianBlur(a, kernel, 1.5)
    mu_b = cv2.GaussianBlur(b, kernel, 1.5)

    mu_a_sq = mu_a ** 2
    mu_b_sq = mu_b ** 2
    mu_ab = mu_a * mu_b

    sigma_a_sq = cv2.GaussianBlur(a * a, kernel, 1.5) - mu_a_sq
    sigma_b_sq = cv2.GaussianBlur(b * b, kernel, 1.5) - mu_b_sq
    sigma_ab = cv2.GaussianBlur(a * b, kernel, 1.5) - mu_ab

    numerator = (2 * mu_ab + c1) * (2 * sigma_ab + c2)
    denominator = (mu_a_sq + mu_b_sq + c1) * (sigma_a_sq + sigma_b_sq + c2)
    ssim_map = numerator / denominator
    return float(np.clip(ssim_map.mean(), -1.0, 1.0))
