# -*- coding: utf-8 -*-
"""
Option 1 — Stage 2: High-Precision Vector/Canvas Post-Processor (Pillow + Supersampling)
Reads clean raw screenshots (`raw/*.png`) and Playwright bounding-box manifests (`manifests/*.json`),
and composites publication-grade anti-aliased bounding boxes and numbered badges.
"""

import json
import os
import sys
from pathlib import Path
from PIL import Image, ImageDraw, ImageFilter, ImageFont

BASE_DIR = Path(__file__).resolve().parent.parent
OPT1_DIR = BASE_DIR / "docs" / "system details" / "capstone" / "User and Secuiryt Manual" / "screenshots" / "option1_login"
RAW_DIR = OPT1_DIR / "raw"
MANIFEST_DIR = OPT1_DIR / "manifests"
RENDERED_DIR = OPT1_DIR / "rendered"
MAIN_SCREENSHOTS_DIR = BASE_DIR / "docs" / "system details" / "capstone" / "User and Secuiryt Manual" / "screenshots"

RENDERED_DIR.mkdir(parents=True, exist_ok=True)

# Supersampling factor for ultra-crisp anti-aliased circles and rounded rectangles
SS = 2

# Brand Palette (Sky-600 #0284C7 + White contrast halo)
ACCENT_RGB = (2, 132, 199)
WHITE_RGB = (255, 255, 255)
SHADOW_RGB = (15, 23, 42)


def load_bold_font(size_px: int) -> ImageFont.FreeTypeFont:
    candidates = [
        "C:/Windows/Fonts/segoeuib.ttf",
        "C:/Windows/Fonts/arialbd.ttf",
        "C:/Windows/Fonts/calibrib.ttf",
    ]
    for path in candidates:
        if os.path.exists(path):
            return ImageFont.truetype(path, size_px)
    return ImageFont.load_default()


def compute_badge_center(px_box: dict, badge_pos: str, radius: int, img_w: int, img_h: int) -> tuple[int, int]:
    x = px_box["x"]
    y = px_box["y"]
    w = px_box["width"]
    h = px_box["height"]

    if badge_pos == "left":
        cx = x - radius - 2
        cy = y + h // 2
    elif badge_pos == "right":
        cx = x + w + radius + 2
        cy = y + h // 2
    elif badge_pos == "bottom":
        cx = x + w // 2
        cy = y + h + radius + 2
    elif badge_pos == "top":
        cx = x + w // 2
        cy = y - radius - 2
    elif badge_pos == "top-right":
        cx = x + w
        cy = y - radius // 2
    elif badge_pos == "bottom-left":
        cx = x - radius - 2
        cy = y + h - 4
    else:  # default: top-left
        cx = x - radius // 2
        cy = y - radius // 2

    margin = radius + 6
    cx = max(margin, min(img_w - margin, cx))
    cy = max(margin, min(img_h - margin, cy))
    return cx, cy


def render_annotated_image(manifest_path: Path, raw_dir: Path, rendered_dir: Path) -> Path:
    with open(manifest_path, "r", encoding="utf-8") as f:
        manifest = json.load(f)

    filename = manifest["filename"]
    raw_path = raw_dir / filename
    if not raw_path.exists():
        raise FileNotFoundError(f"Raw screenshot missing: {raw_path}")

    base_img = Image.open(raw_path).convert("RGBA")
    img_w, img_h = base_img.size

    # 1. Shadow layer at 1x resolution (blurred with Gaussian filter)
    shadow_layer = Image.new("RGBA", (img_w, img_h), (0, 0, 0, 0))
    shadow_draw = ImageDraw.Draw(shadow_layer)

    # 2. High-resolution supersampled vector layer (SSx)
    ss_w, ss_h = img_w * SS, img_h * SS
    vec_layer = Image.new("RGBA", (ss_w, ss_h), (0, 0, 0, 0))
    vec_draw = ImageDraw.Draw(vec_layer)

    badge_radius = 22  # in 1.5x image pixels (~14.5px CSS radius)
    font = load_bold_font(23 * SS)

    callouts = manifest.get("callouts", [])

    # First pass: draw all bounding boxes
    for c in callouts:
        pb = c["pixelBox"]
        x0, y0 = pb["x"], pb["y"]
        x1, y1 = x0 + pb["width"], y0 + pb["height"]
        corner_r = 12

        # Soft outer shadow around bounding box
        shadow_draw.rounded_rectangle(
            [x0 - 2, y0 + 2, x1 + 2, y1 + 6],
            radius=corner_r + 2,
            outline=(*ACCENT_RGB, 95),
            width=6,
        )

        # Supersampled coordinates
        sx0, sy0, sx1, sy1 = x0 * SS, y0 * SS, x1 * SS, y1 * SS
        sr = corner_r * SS

        # Subtle interior highlight tint (5% opacity so text remains 100% sharp)
        vec_draw.rounded_rectangle(
            [sx0, sy0, sx1, sy1],
            radius=sr,
            fill=(*ACCENT_RGB, 13),
        )

        # Outer white contrast halo (ensures visibility on dark headers and green buttons)
        halo_pad = 3 * SS
        vec_draw.rounded_rectangle(
            [sx0 - halo_pad, sy0 - halo_pad, sx1 + halo_pad, sy1 + halo_pad],
            radius=sr + halo_pad,
            outline=(*WHITE_RGB, 245),
            width=3 * SS,
        )

        # Primary Sky-600 border
        vec_draw.rounded_rectangle(
            [sx0, sy0, sx1, sy1],
            radius=sr,
            outline=(*ACCENT_RGB, 255),
            width=int(4.5 * SS),
        )

    # Second pass: draw all numbered circular badges on top of bounding boxes
    for c in callouts:
        pb = c["pixelBox"]
        badge_pos = c.get("badgePos", "left")
        cx, cy = compute_badge_center(pb, badge_pos, badge_radius, img_w, img_h)

        # Drop shadow under badge
        shadow_draw.ellipse(
            [
                cx - badge_radius - 2,
                cy - badge_radius + 3,
                cx + badge_radius + 2,
                cy + badge_radius + 7,
            ],
            fill=(*SHADOW_RGB, 115),
        )

        # Supersampled badge coordinates
        scx, scy = cx * SS, cy * SS
        srad = badge_radius * SS
        ring_w = int(3.5 * SS)

        # Outer white ring
        vec_draw.ellipse(
            [scx - srad - ring_w, scy - srad - ring_w, scx + srad + ring_w, scy + srad + ring_w],
            fill=(*WHITE_RGB, 255),
        )

        # Inner Sky-600 circle
        vec_draw.ellipse(
            [scx - srad, scy - srad, scx + srad, scy + srad],
            fill=(*ACCENT_RGB, 255),
        )

        # Number text centered in badge
        num_str = str(c["num"])
        vec_draw.text(
            (scx, scy - int(1 * SS)),
            num_str,
            font=font,
            fill=(*WHITE_RGB, 255),
            anchor="mm",
        )

    # Blur shadow layer and composite everything onto the clean screenshot
    shadow_blurred = shadow_layer.filter(ImageFilter.GaussianBlur(radius=6))
    vec_downsampled = vec_layer.resize((img_w, img_h), resample=Image.Resampling.LANCZOS)

    out_img = Image.alpha_composite(base_img, shadow_blurred)
    out_img = Image.alpha_composite(out_img, vec_downsampled)

    rendered_dir.mkdir(parents=True, exist_ok=True)
    out_path = rendered_dir / filename
    out_img.convert("RGB").save(out_path, "PNG", optimize=True)

    # Also copy to main screenshots folder so manual compiler picks it up
    main_out_path = MAIN_SCREENSHOTS_DIR / filename
    out_img.convert("RGB").save(main_out_path, "PNG", optimize=True)

    return out_path


def process_directory(target_dir: Path):
    manifest_dir = target_dir / "manifests"
    raw_dir = target_dir / "raw"
    rendered_dir = target_dir / "rendered"

    if not manifest_dir.exists():
        print(f"Skipping {target_dir} (no manifests/ directory)")
        return

    manifests = sorted(manifest_dir.glob("*.json"))
    print(f"\nProcessing {len(manifests)} manifests in: {target_dir.name}")
    for mp in manifests:
        out_path = render_annotated_image(mp, raw_dir, rendered_dir)
        print(f"  -> Rendered: {out_path.name}")


def main():
    print("=== Stage 2: Python Pillow Supersampled Vector Post-Processor ===")
    
    if len(sys.argv) > 1:
        target_name = sys.argv[1]
        target_dir = MAIN_SCREENSHOTS_DIR / target_name
        if not target_dir.exists():
            target_dir = Path(target_name)
        process_directory(target_dir)
    else:
        # Process all subdirectories under screenshots that have manifests
        subdirs = [d for d in MAIN_SCREENSHOTS_DIR.iterdir() if d.is_dir() and (d / "manifests").exists()]
        if not subdirs:
            print(f"No manifest directories found under {MAIN_SCREENSHOTS_DIR}")
            sys.exit(1)
        for sd in subdirs:
            process_directory(sd)

    print("\nStage 2 complete!")


if __name__ == "__main__":
    main()
