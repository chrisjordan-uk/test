from __future__ import annotations

from dataclasses import replace

from PIL import Image

from app.gemini_client import GeminiClient, build_instruction
from app.models import ProcessingMode
from app.similarity import structural_similarity


def test_not_configured_without_api_key(test_settings):
    client = GeminiClient(settings=test_settings)
    assert client.is_configured is False

    img = Image.new("RGB", (100, 100), (255, 255, 255))
    result = client.edit_image(img, ProcessingMode.CLEAN_PRODUCT_PHOTO, "neutral_studio")
    assert result.image is None
    assert result.is_safe is False
    assert result.error is not None


def test_configured_when_api_key_present(test_settings):
    configured_settings = replace(test_settings, gemini_api_key="fake-test-key-not-real")
    client = GeminiClient(settings=configured_settings)
    assert client.is_configured is True


def test_build_instruction_preserves_defect_language():
    instruction = build_instruction(ProcessingMode.CLEAN_PRODUCT_PHOTO, "white")
    assert "stains" in instruction.lower()
    assert "holes" in instruction.lower()
    assert "preserve" in instruction.lower()
    assert "logos" in instruction.lower()


def test_build_instruction_respects_preserve_original_background():
    instruction = build_instruction(ProcessingMode.CLEAN_PRODUCT_PHOTO, "preserve_original")
    assert "keep the original background" in instruction.lower()


def test_ssim_identical_images_is_near_one():
    img = Image.new("RGB", (200, 200), (120, 80, 40))
    score = structural_similarity(img, img)
    assert score > 0.99


def test_ssim_very_different_images_is_low():
    img_a = Image.new("RGB", (200, 200), (255, 255, 255))
    img_b = Image.new("RGB", (200, 200), (0, 0, 0))
    score = structural_similarity(img_a, img_b)
    assert score < 0.5
