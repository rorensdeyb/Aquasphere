#!/usr/bin/env python3
"""Replace diagram images in existing PDF without regenerating (preserves user-added screenshots)."""
import sys
from pathlib import Path

try:
    import pymupdf
except ImportError:
    import fitz as pymupdf

ROOT = Path(__file__).resolve().parent
FIG = ROOT / "figures"
PDF = ROOT.parent.parent / "AquaSphere_System_Documentation.pdf"

# keyword in caption text -> figure file to swap in
FIGURES = {
    "Entity Relationship Diagram": "diagram-erd.png",
    "ML training and inference pipeline": "diagram-ml-pipeline.png",
    "System flow": "diagram-system-flow.png",
    "Order flow": "diagram-order-flow.png",
}


def find_caption_rect(page, keyword):
    """Return the Rect of the text block whose content matches keyword."""
    blocks = page.get_text("dict", flags=pymupdf.TEXT_PRESERVE_WHITESPACE)["blocks"]
    for b in blocks:
        if b["type"] == 0:
            for line in b["lines"]:
                full = "".join(span["text"] for span in line["spans"])
                if keyword.lower() in full.lower():
                    return pymupdf.Rect(b["bbox"])
    return None


def largest_image_rect(page):
    """Return (xref, Rect) of the largest image on the page."""
    best_xref, best_rect, best_area = None, None, 0
    for img in page.get_images(full=True):
        xref = img[0]
        rects = page.get_image_rects(xref)
        if not rects:
            continue
        r = rects[0]
        area = r.width * r.height
        if area > best_area:
            best_xref, best_rect, best_area = xref, r, area
    return best_xref, best_rect


def replace_images_in_pdf(pdf_path, dry_run=False):
    if not pdf_path.exists():
        print(f"PDF not found: {pdf_path}")
        sys.exit(1)

    doc = pymupdf.open(str(pdf_path))
    replaced = 0

    for page_idx in range(len(doc)):
        page = doc[page_idx]
        page_text = page.get_text()

        for keyword, fig_file in FIGURES.items():
            fig_path = FIG / fig_file
            if not fig_path.exists():
                continue
            if keyword.lower() not in page_text.lower():
                continue

            caption_rect = find_caption_rect(page, keyword)
            xref, img_rect = largest_image_rect(page)

            if img_rect is None:
                print(f"Page {page_idx + 1}: caption found for '{keyword}' but no image on page — skipping")
                continue

            print(f"Page {page_idx + 1}: '{keyword}' image at {img_rect}")

            if dry_run:
                print(f"  -> would replace with {fig_file}")
            else:
                # 1) Cover old image with white rectangle
                shape = page.new_shape()
                shape.draw_rect(img_rect)
                shape.finish(color=(1, 1, 1), fill=(1, 1, 1))
                shape.commit()

                # 2) Insert new image at same position
                page.insert_image(img_rect, filename=str(fig_path), overlay=True)
                print(f"  -> replaced with {fig_file}")

            replaced += 1
            break  # one match per keyword

    if not dry_run and replaced > 0:
        doc.save(str(pdf_path), incremental=True, encryption=pymupdf.PDF_ENCRYPT_KEEP)
        print(f"\nSaved {pdf_path} ({replaced} diagram(s) replaced)")
    elif replaced == 0:
        print("\nNo diagrams found to replace.")
    else:
        print(f"\nDry run: {replaced} diagram(s) would be replaced.")


if __name__ == "__main__":
    dry = "--dry-run" in sys.argv
    replace_images_in_pdf(PDF, dry_run=dry)
