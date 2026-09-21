# Jordyn AI Photo Studio

Batch photo enhancement for clothing-reselling product photography.

Upload a ZIP of product folders (one folder per item, containing that
item's photos), choose a processing mode, and get back a ZIP with clean,
consistent, professional-looking photos alongside your untouched originals
and a processing report for every image.

**This tool only does photo processing.** It does not manage inventory,
pricing, sales, invoicing, listings, or Vinted integrations — pair it with
whatever system you already use for that.

> **Deploying to shared hosting (Hostinger, cPanel, etc.) instead of a
> VPS/Node-capable host?** Use the plain-PHP edition in
> [`../photo-studio-php/`](../photo-studio-php/README.md) — no Python, no
> build step, just upload the folder.

## What it does

- Detects each product folder inside your uploaded ZIP automatically.
- Lets you preview the original photos before processing.
- Enhances every photo in the batch: lighting, contrast, white balance,
  sharpening, background cleanup/replacement, framing/centring, and
  aspect-ratio conversion for marketplace listings.
- Uses the Google Gemini API for AI-assisted editing when configured, with
  a fully local Pillow/OpenCV fallback when it isn't (or when an AI edit
  can't be verified safe).
- **Never** alters the actual product: stains, holes, tears, damage, wear,
  colour, shape, size, logos, labels and care tags are always preserved
  exactly as photographed. If an AI edit can't be confidently verified to
  preserve the product, the app keeps the original photo instead and flags
  it in the report — it never ships a misleading image.
- Keeps your original photographs completely untouched; enhanced photos
  are separate copies.
- Produces a per-product `PROCESSING_REPORT.txt` and a batch-wide
  `BATCH_PROCESSING_REPORT.txt` explaining exactly what was done to every
  image (and why, if something was skipped or rejected).

## 1. Install Python

You need **Python 3.11 or newer**. Check with:

```bash
python3 --version
```

If you don't have it, download it from [python.org/downloads](https://www.python.org/downloads/)
(Windows/Mac) or install via your package manager on Linux.

## 2. Quick start

**Windows:** double-click `run_windows.bat` (or run it from a terminal).

**Mac/Linux:**

```bash
./run_mac_linux.sh
```

Either script will: create a virtual environment (`.venv`), install
dependencies, copy `.env.example` to `.env` on first run, and launch the
app at `http://localhost:8501`.

### Manual setup

```bash
python3 -m venv .venv
source .venv/bin/activate        # Windows: .venv\Scripts\activate
pip install -r requirements.txt
cp .env.example .env             # Windows: copy .env.example .env
python -m streamlit run app/main.py
```

or simply `python run_app.py` once dependencies are installed.

## 3. Configure the Gemini API key (optional but recommended)

1. Get a key from [Google AI Studio](https://aistudio.google.com/apikey).
2. Open `.env` and set:
   ```
   GEMINI_API_KEY=your-key-here
   GEMINI_IMAGE_MODEL=gemini-2.5-flash-image
   ```
3. Restart the app.

The API key is **only** read from the environment/`.env` file — it's never
hardcoded, never shown in the UI, and never logged. If you leave it blank
or turn Gemini off in the UI, the app runs entirely on local Pillow/OpenCV
processing.

You can tune request limits, timeouts, retries and safety thresholds — see
every option in `.env.example`.

## 4. Using the app

1. **Upload** — drop a ZIP file structured like:
   ```
   STOCK_PHOTOS.zip
   ├── ITEM_01/
   │   ├── photo1.jpg
   │   ├── photo2.jpg
   │   └── photo3.jpg
   ├── ITEM_02/
   │   ├── front.jpg
   │   ├── back.jpg
   │   └── label.jpg
   └── ITEM_03/
       └── image1.png
   ```
   JPG, JPEG, PNG and WEBP are all supported; filenames can be anything.
   `__MACOSX`, `.DS_Store` and other system junk are ignored automatically.
2. **Preview** — expand each detected product folder to see its original
   photos, and uncheck any folder you don't want processed this run.
3. **Choose settings** — processing mode (A–E, see below), background
   style, output aspect ratio, resolution and JPEG quality, and whether to
   use Gemini and/or local enhancement.
4. **Process All Images** — watch the progress bar and live log as every
   photo is processed. Errors on individual photos don't stop the batch.
5. **Review results** — original vs. enhanced photos side by side, grouped
   by product, with status (enhanced / kept original for safety / failed)
   and any warnings.
6. **Download** — grab `JORDYN_PHOTO_STUDIO_OUTPUT.zip`, structured as:
   ```
   JORDYN_PHOTO_STUDIO_OUTPUT/
   ├── ITEM_01/
   │   ├── ORIGINAL_PHOTOS/
   │   ├── ENHANCED_PHOTOS/
   │   └── PROCESSING_REPORT.txt
   ├── ITEM_02/
   │   └── ...
   └── BATCH_PROCESSING_REPORT.txt
   ```

There's a ready-made demo ZIP at `sample_data/STOCK_PHOTOS_SAMPLE.zip` if
you want to try the app without your own photos (regenerate it any time
with `python sample_data/generate_sample_zip.py`).

## 5. Processing modes

| Mode | What it focuses on |
|---|---|
| **A — Clean Product Photo** | Overall professional look: lighting, contrast, white balance, background, centring, framing. |
| **B — Background Cleanup** | Background only — neutralises/replaces it, leaves the product itself untouched. |
| **C — Lighting & Quality Enhancement** | Brightness, exposure, contrast, white balance, sharpness, noise — no background or framing changes. |
| **D — Marketplace Cover Image** | A clean, centred, portrait 4:5 cover shot for a listing. |
| **E — Batch Consistency** | Applies a consistent background/framing/lighting style across the whole batch. |

Background replacement and product-centring only run when the app can
**confidently** tell the background apart from the product (a uniform-ish
backdrop). If it can't, the original background is left alone rather than
risk cropping or masking part of the garment.

## 6. Product-preservation policy

This is the core safety rule of the app: **the AI must never make a
garment look different from how it actually is.**

- No removing/hiding stains, holes, tears, damage or wear.
- No changing colour, shape, size, fit or pattern.
- No altering logos, labels or care tags.
- No adding details that weren't in the original photo.

Every Gemini request includes an explicit instruction to this effect. On
top of that, every Gemini-edited photo is compared against the original
with a structural-similarity check (`GEMINI_MIN_SSIM` in `.env`, default
`0.45`); if the edit differs too much from the source, it's discarded and
the **original photo** is used instead, flagged as "kept original (safety
check)" in the results and reports. The app never claims an edited photo
is an exact representation beyond what it can verify this way.

## 7. Gemini usage, limits and privacy

- Before processing a batch, the app shows an estimated number of Gemini
  requests it will make (fewer if some images are served from an on-disk
  cache of identical prior edits).
- Requests use configurable timeouts, retries with backoff, and handle
  rate-limit errors gracefully — one failed image never stops the batch.
- Photos are downsized before upload (`GEMINI_MAX_UPLOAD_DIMENSION`) to
  keep requests fast and cheap.
- **Privacy:** when Gemini is enabled, your photographs are sent to
  Google's Gemini API for processing. Turn "Use Gemini AI editing" off in
  the Processing Settings to keep everything fully local (Pillow/OpenCV
  only) — no photos leave your machine.
- Gemini limitations: results depend on the model and can vary between
  runs; very cluttered or complex backgrounds may not be handled well; the
  safety check is a heuristic (SSIM), not a certification — always spot
  check the output before using it commercially.

## 8. Running tests

```bash
source .venv/bin/activate
pytest
```

The suite covers ZIP extraction and path-traversal protection, product
folder detection, supported/invalid image formats, resizing and
aspect-ratio conversion, original-image preservation, output ZIP
structure, error recovery, processing reports, and the local fallback
pipeline. A full end-to-end run against the sample ZIP is also available:

```bash
python sample_data/run_e2e_smoke_test.py
```

## 9. Troubleshooting

| Problem | Fix |
|---|---|
| `ModuleNotFoundError` on startup | Activate the virtual environment (`source .venv/bin/activate`) and re-run `pip install -r requirements.txt`. |
| "GEMINI_API_KEY is not configured" warning | Expected if you haven't set a key — Gemini editing is skipped and local processing is used. Add a key to `.env` to enable it. |
| ZIP upload rejected as too large / too many folders | Raise `MAX_ZIP_SIZE_MB` / `MAX_PRODUCT_FOLDERS` / `MAX_TOTAL_IMAGES` in `.env`, or split the batch. |
| A photo shows "kept original (safety)" | The AI edit didn't pass the preservation check — this is intentional, not a bug. The original photo is used instead. |
| WEBP photos won't open | Make sure Pillow was installed with WEBP support (the pinned version in `requirements.txt` includes it by default). |
| App won't start / port already in use | Another Streamlit app may be running on port 8501. Stop it, or run `streamlit run app/main.py --server.port 8502`. |

## Project layout

```
photo-studio/
├── app/
│   ├── main.py                 # Streamlit UI
│   ├── config.py                # Environment-driven settings
│   ├── models.py                 # Shared data structures
│   ├── zip_handler.py            # Upload validation, safe extraction, folder detection
│   ├── pipeline.py               # Per-image / per-batch orchestration
│   ├── gemini_client.py          # Gemini API integration (retries, cache, safety check)
│   ├── similarity.py             # Lightweight SSIM preservation check
│   ├── report.py                 # PROCESSING_REPORT.txt / BATCH_PROCESSING_REPORT.txt
│   ├── output_zip.py             # Final output ZIP assembly
│   └── image_processing/
│       └── local_processor.py    # Pillow/OpenCV local fallback pipeline
├── tests/                        # pytest suite
├── sample_data/                  # Demo ZIP + generator + e2e smoke test
├── requirements.txt
├── .env.example
├── run_windows.bat
├── run_mac_linux.sh
└── run_app.py
```
