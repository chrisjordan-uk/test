"""Google Gemini image-editing integration, with strict product-preservation
instructions, retry/timeout/rate-limit handling, caching and a safety gate.

If the ``google-genai`` package or a network call is unavailable, or the API
key is missing, ``GeminiClient.is_configured`` is False and callers should
use the local Pillow/OpenCV fallback instead — this module never raises on
"not configured", it just reports itself unusable.
"""
from __future__ import annotations

import hashlib
import io
import time
from dataclasses import dataclass
from pathlib import Path

from PIL import Image

from app.config import Settings
from app.image_processing.local_processor import resize_max_dimension
from app.models import ProcessingMode
from app.similarity import structural_similarity

try:
    from google import genai
    from google.genai import types as genai_types
    from google.genai import errors as genai_errors

    GENAI_IMPORT_ERROR: Exception | None = None
except Exception as exc:  # pragma: no cover - exercised only if package missing
    genai = None
    genai_types = None
    genai_errors = None
    GENAI_IMPORT_ERROR = exc


MODE_FOCUS = {
    ProcessingMode.CLEAN_PRODUCT_PHOTO: "Improve lighting, background and framing for a clean catalog look.",
    ProcessingMode.BACKGROUND_CLEANUP: "Focus only on cleaning up and neutralising the background.",
    ProcessingMode.LIGHTING_QUALITY: "Focus only on lighting, exposure, contrast, white balance and sharpness. Do not change the background or framing.",
    ProcessingMode.MARKETPLACE_COVER: "Create a clean, centred cover-image presentation suitable for a marketplace listing.",
    ProcessingMode.BATCH_CONSISTENCY: "Match a consistent, neutral studio presentation style suitable for a batch of similar product photos.",
}

BASE_INSTRUCTION = (
    "Improve the presentation of this clothing product photograph. Preserve the exact garment, "
    "colour, shape, size, texture, logos, labels and all visible defects. You may improve lighting, "
    "framing and the background only. Do not remove or conceal stains, holes, tears, damage or wear. "
    "Do not add or remove product details. Do not change the pattern, print or fabric texture. "
    "The result must remain an honest, accurate representation of the original product photograph."
)


def build_instruction(mode: ProcessingMode, background_style: str) -> str:
    focus = MODE_FOCUS.get(mode, "")
    background_note = ""
    if background_style != "preserve_original":
        background_note = f" Use a simple, neutral {background_style.replace('_', ' ')} background."
    else:
        background_note = " Keep the original background as-is."
    return f"{BASE_INSTRUCTION} {focus}{background_note}"


class GeminiRateLimitError(Exception):
    pass


class GeminiTimeoutError(Exception):
    pass


class GeminiRequestError(Exception):
    pass


@dataclass
class GeminiEditResult:
    image: Image.Image | None
    used_cache: bool
    from_gemini: bool
    ssim: float | None
    is_safe: bool
    error: str | None = None


class GeminiClient:
    def __init__(self, settings: Settings, cache_dir: Path | None = None):
        self.settings = settings
        self.cache_dir = cache_dir
        if self.cache_dir:
            self.cache_dir.mkdir(parents=True, exist_ok=True)
        self._client = None
        if self.is_configured:
            self._client = genai.Client(api_key=settings.gemini_api_key)

    @property
    def is_configured(self) -> bool:
        return bool(self.settings.gemini_api_key) and genai is not None

    def _cache_key(self, image_bytes: bytes, instruction: str) -> str:
        h = hashlib.sha256()
        h.update(image_bytes)
        h.update(instruction.encode("utf-8"))
        h.update(self.settings.gemini_image_model.encode("utf-8"))
        return h.hexdigest()

    def _cache_path(self, key: str) -> Path | None:
        if not self.cache_dir:
            return None
        return self.cache_dir / f"{key}.png"

    def _call_api_once(self, image: Image.Image, instruction: str) -> bytes:
        buf = io.BytesIO()
        image.save(buf, format="PNG")
        image_part = genai_types.Part.from_bytes(data=buf.getvalue(), mime_type="image/png")

        response = self._client.models.generate_content(
            model=self.settings.gemini_image_model,
            contents=[instruction, image_part],
            config=genai_types.GenerateContentConfig(
                http_options=genai_types.HttpOptions(timeout=self.settings.gemini_timeout_seconds * 1000),
            ),
        )

        candidates = getattr(response, "candidates", None) or []
        for candidate in candidates:
            content = getattr(candidate, "content", None)
            if not content:
                continue
            for part in getattr(content, "parts", None) or []:
                inline_data = getattr(part, "inline_data", None)
                if inline_data is not None and inline_data.data:
                    return inline_data.data
        raise GeminiRequestError("Gemini response did not contain an edited image.")

    def _call_with_retries(self, image: Image.Image, instruction: str) -> bytes:
        last_exc: Exception | None = None
        for attempt in range(1, self.settings.gemini_max_retries + 1):
            try:
                return self._call_api_once(image, instruction)
            except Exception as exc:  # noqa: BLE001 - classify below
                last_exc = exc
                message = str(exc).lower()
                is_rate_limit = "rate" in message or "429" in message or "resource_exhausted" in message
                is_timeout = "timeout" in message or "deadline" in message
                if attempt >= self.settings.gemini_max_retries:
                    break
                backoff = self.settings.gemini_retry_backoff_seconds * (2 ** (attempt - 1))
                if is_rate_limit:
                    time.sleep(backoff * 2)
                elif is_timeout:
                    time.sleep(backoff)
                else:
                    time.sleep(backoff)
        assert last_exc is not None
        raise last_exc

    def edit_image(self, image: Image.Image, mode: ProcessingMode, background_style: str) -> GeminiEditResult:
        if not self.is_configured:
            return GeminiEditResult(
                image=None, used_cache=False, from_gemini=False, ssim=None, is_safe=False,
                error="Gemini is not configured (missing API key or package)."
            )

        instruction = build_instruction(mode, background_style)
        upload_image = resize_max_dimension(image, self.settings.gemini_max_upload_dimension)

        buf = io.BytesIO()
        upload_image.save(buf, format="PNG")
        cache_key = self._cache_key(buf.getvalue(), instruction)
        cache_path = self._cache_path(cache_key)

        if cache_path and cache_path.exists():
            edited = Image.open(cache_path).convert("RGB")
            ssim = structural_similarity(upload_image, edited)
            is_safe = ssim >= self.settings.gemini_min_ssim
            return GeminiEditResult(image=edited, used_cache=True, from_gemini=True, ssim=ssim, is_safe=is_safe)

        try:
            raw_bytes = self._call_with_retries(upload_image, instruction)
            edited = Image.open(io.BytesIO(raw_bytes)).convert("RGB")
        except Exception as exc:  # noqa: BLE001
            return GeminiEditResult(
                image=None, used_cache=False, from_gemini=False, ssim=None, is_safe=False, error=str(exc)
            )

        ssim = structural_similarity(upload_image, edited)
        is_safe = ssim >= self.settings.gemini_min_ssim

        if cache_path:
            try:
                edited.save(cache_path, format="PNG")
            except OSError:
                pass  # cache write failures are non-fatal

        return GeminiEditResult(image=edited, used_cache=False, from_gemini=True, ssim=ssim, is_safe=is_safe)


def estimate_api_usage_warning(num_images: int, use_gemini: bool) -> str | None:
    if not use_gemini or num_images == 0:
        return None
    return (
        f"This batch will send up to {num_images} image edit request(s) to the Gemini API "
        "(fewer if some are served from cache). Large batches may take a while and are subject "
        "to your Google AI Studio / Gemini API usage quota and billing."
    )
