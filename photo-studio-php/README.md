# Jordyn AI Photo Studio — PHP edition

Batch photo enhancement for clothing-reselling product photography — **plain
PHP, no framework, no Composer, no SSH required.** Built to be uploaded
directly to shared hosting (Hostinger, cPanel, etc.) and just work.

This is a sibling of the Python/Streamlit edition in `../photo-studio/`.
Same rules, same output structure, ported to PHP + GD so it runs on hosting
that only offers PHP (no persistent Node/Python process). Only use one of
the two editions — pick PHP for shared hosting, Python for a VPS/Node-capable
host.

**This tool only does photo processing.** No inventory, pricing, sales,
invoicing, listings, or Vinted integration.

## Requirements

Any PHP host with:
- **PHP 8.1+**
- Extensions: **GD** (with WebP support), **cURL**, **Zip**, **mbstring**, **fileinfo**

All of these are enabled by default on Hostinger's shared/business plans and
virtually every other PHP host. No Composer, no database, no SSH needed.

## 1. Upload to Hostinger (or any shared host)

1. In **hPanel → Files → File Manager** (or via FTP), upload the entire
   `photo-studio-php/` folder's contents into a subdomain or subfolder, e.g.
   `public_html/photo-studio/`.
2. Copy `.env.example` to `.env` in that same folder (File Manager → select
   `.env.example` → Copy → rename to `.env`), then edit it:
   - Get a key from [Google AI Studio](https://aistudio.google.com/apikey)
     and set `GEMINI_API_KEY=your-key-here`.
   - Leave everything else at its default unless you need to change limits.
3. Make sure `storage/` is writable (File Manager → right-click → Permissions
   → `755` or `775`; on most Hostinger plans it's writable by default since
   your account owns it).
4. Visit `https://yourdomain.com/photo-studio/index.php` in your browser.
   That's it — no build step, no install command.

### Increasing the upload size (optional)

Hostinger's default PHP `upload_max_filesize`/`post_max_size` may be lower
than `MAX_ZIP_SIZE_MB` in `.env`. To raise it, either:
- **hPanel → Advanced → PHP Configuration** → raise `upload_max_filesize` and
  `post_max_size` to match, or
- add a `php.ini` (or `.user.ini`) file in this folder with:
  ```ini
  upload_max_filesize = 100M
  post_max_size = 110M
  max_execution_time = 300
  memory_limit = 256M
  ```

### Deploying to cPanel hosts

Same as above: upload the folder (e.g. into `public_html/photo-studio/`),
copy `.env.example` to `.env` and edit it, visit the URL. No Node.js App /
Setup Node.js App step needed — this is why the PHP edition exists.

## 2. Using the app

1. **Upload** — pick a ZIP structured like:
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
2. **Preview & settings** — expand each folder to see its photos, untick any
   folder you don't want processed, pick a mode (A–E), background style,
   aspect ratio, resolution and quality, and whether to use Gemini/local
   enhancement.
3. **Process All Images** — a log streams as each photo is processed (best
   effort — some hosts buffer output, in which case you'll just see the
   final result once it's done; processing still completes normally either
   way).
4. **Results** — original vs. enhanced photos side by side, per-product
   counts, warnings and errors.
5. **Download** — `JORDYN_PHOTO_STUDIO_OUTPUT.zip`:
   ```
   JORDYN_PHOTO_STUDIO_OUTPUT/
   ├── ITEM_01/
   │   ├── ORIGINAL_PHOTOS/
   │   ├── ENHANCED_PHOTOS/
   │   └── PROCESSING_REPORT.txt
   ├── ITEM_02/...
   └── BATCH_PROCESSING_REPORT.txt
   ```

There's a demo ZIP at `sample_data/STOCK_PHOTOS_SAMPLE.zip` — regenerate any
time with `php sample_data/generate_sample_zip.php` (run locally; not needed
on the live host).

## 3. Processing modes

Same five modes as the Python edition: **A** Clean Product Photo, **B**
Background Cleanup, **C** Lighting & Quality Enhancement, **D** Marketplace
Cover Image (4:5), **E** Batch Consistency.

## 3a. iPhone HEIC/HEIF photos

HEIC files are accepted at upload (they're recognised by their file
signature, not just their extension), but **decoding one into a normal
image depends entirely on your host's PHP having the `Imagick` extension
built with HEIF/libheif support.** GD (the library this edition otherwise
relies on for everything) has no HEIC support at all, and most shared
hosting — including typical Hostinger shared/business plans — does **not**
ship Imagick with the HEIF delegate enabled.

What happens on a host without HEIC decoding: the app doesn't crash or
stop the batch — it copies the original HEIC file through unchanged into
`ENHANCED_PHOTOS`, marks that photo "failed", and the processing report
explains exactly why plus what to do about it.

Two reliable ways around this:
1. **Set your iPhone to shoot JPEG instead of HEIC** — Settings → Camera →
   Formats → "Most Compatible". New photos will already be JPEG, no
   conversion needed.
2. **Convert existing HEIC photos to JPEG before zipping them** — on
   iPhone/Mac, "Share" → "Duplicate as JPEG" (Photos app), or any free
   bulk HEIC→JPEG converter, before building your upload ZIP.

If your host *does* have Imagick with HEIF support (check with
`php -r "var_dump(class_exists('Imagick'));"` and ask your host if the
HEIF delegate is enabled), HEIC photos are decoded automatically — no
settings to change.

Prefer not to worry about this at all? The **Python edition**
(`../photo-studio/`) has full, reliable HEIC support out of the box via
`pillow-heif`, independent of hosting — use that if HEIC support matters
and you have somewhere to run it (a VPS or Node/Python-capable host).

## 4. Product-preservation policy

Identical rule to the Python edition: **the AI must never make a garment
look different from how it actually is.** No hiding stains/holes/tears/wear,
no changing colour/shape/size/pattern, no altering logos/labels/care tags.

Every Gemini request carries an explicit preservation instruction. Every
Gemini-edited photo is compared against the original with a coarse
perceptual-similarity check (`GEMINI_MIN_SIMILARITY` in `.env`, default
`0.55`) — GD has no OpenCV/SSIM available, so this is a lighter-weight
32×32 grayscale comparison rather than true SSIM, but serves the same
purpose: if the edit differs too much from the source, it's discarded and
the **original photo** is used instead, flagged "kept original (safety
check)". Always spot-check output before using it commercially.

Background replacement/centring only runs when the background is
confidently near-uniform (checked by sampling corners + a colour-distance
foreground scan); otherwise the original background is left alone.

## 5. Gemini usage, limits and privacy

- An estimated Gemini request count is shown before processing.
- Requests use configurable timeouts/retries with backoff and handle
  rate-limit (429) and server errors gracefully — one failed image never
  stops the batch.
- Photos are downsized before upload (`GEMINI_MAX_UPLOAD_DIMENSION`).
- Identical edits (same image + same settings) are served from an on-disk
  cache instead of re-calling the API.
- **Privacy:** when Gemini is enabled, your photographs are sent to
  Google's Gemini API. Untick "Use Gemini AI editing" to keep everything
  fully local (GD only) — no photos leave your server.

## 6. How results are stored

Each visit gets its own working folder under `storage/jobs/<session-id>/`
(never web-accessible — blocked by `storage/.htaccess`). Folders older than
6 hours are cleaned up automatically on the next request. Nothing is stored
in a database; there's nothing to migrate or back up beyond your `.env`.

## 7. Running tests (before you deploy, or after changing code)

No PHPUnit/Composer needed — a small dependency-free runner:

```bash
cp .env.example .env
php tests/run_all.php
```

Covers ZIP extraction and path-traversal protection, product-folder
detection, supported/invalid formats, resizing and aspect-ratio conversion,
original-image preservation, output ZIP structure, error recovery,
processing reports, and the local (GD) fallback pipeline.

You can also try the whole flow locally before uploading anywhere:

```bash
php -S localhost:8811
# then open http://localhost:8811/index.php
```

## 8. Troubleshooting

| Problem | Fix |
|---|---|
| Blank page / 500 error | Check your host's PHP error log; usually a missing extension (GD/cURL/Zip) or a permissions issue on `storage/`. |
| "GEMINI_API_KEY is not configured" warning | Expected without a key — Gemini is skipped, local (GD) processing is used. Add a key to `.env` to enable it. |
| Upload rejected as too large | Raise `MAX_ZIP_SIZE_MB` in `.env` **and** your host's `upload_max_filesize`/`post_max_size` (see above). |
| Processing seems to "hang" then finishes all at once | Normal on hosts that buffer output (e.g. some LiteSpeed configs) — the batch still completes, you just don't see the live log. |
| A photo shows "kept original (safety)" | Intentional — the AI edit didn't pass the preservation check, so the original was used instead. |
| WebP photos won't open | Confirm your host's GD build has WebP support: `php -r "print_r(gd_info());"` should show `WebP Support => 1`. |
| HEIC/HEIF photo shows "could not be decoded" | Expected without Imagick+HEIF on your host — see section 3a. Convert to JPEG before uploading, or set your iPhone to Settings → Camera → Formats → "Most Compatible". |
| 504/timeout on a large batch | Split into smaller ZIPs, or raise `max_execution_time` via `.user.ini` (see above). |

## Project layout

```
photo-studio-php/
├── index.php              # Upload + preview + settings
├── process.php             # Runs the batch, streams a log
├── results.php             # Results + per-photo download
├── download.php            # Streams the final output ZIP
├── serve_image.php         # Session-scoped image proxy for previews
├── config.php               # Loads .env, defines constants, wires includes
├── includes/
│   ├── env.php              # Dependency-free .env parser
│   ├── functions.php        # Session/work-dir helpers, CSRF, flashes
│   ├── zip_handler.php       # Safe extraction, validation, folder detection
│   ├── image_processor.php   # GD-based local enhancement pipeline
│   ├── gemini_client.php     # Gemini REST client (cURL, retries, cache, safety check)
│   ├── pipeline.php          # Per-image / per-batch orchestration
│   ├── report.php            # PROCESSING_REPORT.txt / BATCH_PROCESSING_REPORT.txt
│   └── output_zip.php        # Final output ZIP assembly
├── assets/style.css
├── storage/                 # Runtime working directories (not web-readable)
├── tests/                   # Dependency-free test suite (php tests/run_all.php)
├── sample_data/              # Demo ZIP + generator
├── .env.example
└── .htaccess
```
