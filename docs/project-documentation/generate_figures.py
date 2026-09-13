#!/usr/bin/env python3
"""Generate AquaSphere documentation figures (ERD, flow diagrams)."""
from __future__ import annotations

import os
from pathlib import Path

import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
import matplotlib.patches as mpatches
from matplotlib.patches import FancyBboxPatch
from matplotlib.path import Path as MPath

ROOT = Path(__file__).resolve().parent
FIG = ROOT / "figures"

AQUA = "#2383B5"
DEEP = "#155A7A"
LIGHT = "#5BC0EB"
INK = "#163B53"
MUTED = "#607D8B"
WHITE = "#FFFFFF"
GREEN = "#2E8B70"
RED = "#b84252"


def ensure_dir():
    FIG.mkdir(parents=True, exist_ok=True)


# ---------------------------------------------------------------------------
# ERD
# ---------------------------------------------------------------------------
def draw_erd():
    fig, ax = plt.subplots(1, 1, figsize=(18, 15))
    ax.set_xlim(0, 18)
    ax.set_ylim(-0.3, 15)
    ax.axis("off")
    fig.patch.set_facecolor(WHITE)

    LINE_H = 0.30
    HEADER_H = 0.46
    PAD_TOP = 0.14
    PAD_X = 0.20

    def calc_h(n_cols):
        return HEADER_H + PAD_TOP + n_cols * LINE_H + 0.14

    def table_box(x, y, w, name, cols, pk=None):
        h = calc_h(len(cols))
        rect = FancyBboxPatch((x, y), w, h, boxstyle="round,pad=0.06",
                              facecolor=WHITE, edgecolor=AQUA, linewidth=1.8)
        ax.add_patch(rect)
        hdr = FancyBboxPatch((x, y + h - HEADER_H), w, HEADER_H,
                             boxstyle="round,pad=0.03",
                             facecolor=DEEP, edgecolor=DEEP, linewidth=1.2)
        ax.add_patch(hdr)
        ax.text(x + w / 2, y + h - HEADER_H / 2, name,
                ha="center", va="center", fontsize=10,
                fontweight="bold", color=WHITE, fontfamily="sans-serif")
        ty = y + h - HEADER_H - PAD_TOP - LINE_H / 2
        for col in cols:
            prefix = "\u2611  " if col == pk else "     "
            ax.text(x + PAD_X, ty, prefix + col, ha="left", va="center",
                    fontsize=8, color=INK, fontfamily="monospace")
            ty -= LINE_H
        return h

    # Layered layout: users on top, related tables in rows below
    ux, uw, uy = 7.2, 3.6, 9.0              # users box (top layer)
    W1 = 2.8                                 # middle-layer box width
    c_otp, c_prod = 1.0, 4.3                 # otp_verification / products
    c_ord, c_sys, c_eml = 7.6, 10.9, 14.2    # orders / system_settings / email_change_otp
    y_mid, y_ord = 5.2, 4.2                  # middle-layer bottoms (orders staggered)
    W2 = 2.8                                 # bottom-layer box width
    b_prst, b_oitem, b_osh = 1.0, 6.7, 9.8   # password_reset / order_items / order_status_history
    y_bot = 0.6                              # bottom-layer bottom

    # --- Top layer ---
    h_users = table_box(ux, uy, uw, "users", [
        "id  (PK)", "username", "password_hash", "email",
        "first_name", "last_name", "gender", "date_of_birth",
        "is_admin", "saved_cart  (JSON)", "delivery_address  (JSON)",
        "notif_seen_at", "notif_cleared_at",
    ], "id  (PK)")

    # --- Middle layer ---
    h_otp = table_box(c_otp, y_mid, W1, "otp_verification", [
        "id  (PK)", "email", "otp_code", "username",
        "password_hash", "expires_at", "is_verified",
    ], "id  (PK)")

    h_prod = table_box(c_prod, y_mid, W1, "products", [
        "id  (PK)", "label", "description", "price",
        "image_url", "category", "unit",
    ], "id  (PK)")

    h_ord = table_box(c_ord, y_ord, W1, "orders", [
        "id  (PK)", "user_id  (FK)", "order_date",
        "delivery_date", "delivery_time", "delivery_address",
        "total_amount", "status", "payment_method",
        "paymongo_source_id",
    ], "id  (PK)")

    h_sys = table_box(c_sys, y_mid, W1, "system_settings", [
        "id  (PK)", "setting_key", "setting_value",
        "updated_by  (FK)",
    ], "id  (PK)")

    h_eml = table_box(c_eml, y_mid, W1, "email_change_otp", [
        "id  (PK)", "user_id  (FK)", "otp_code",
        "new_email", "expires_at",
    ], "id  (PK)")

    # --- Bottom layer ---
    h_prst = table_box(b_prst, y_bot, W2, "password_reset", [
        "id  (PK)", "email", "otp_code",
        "user_id  (FK)", "expires_at", "is_verified",
    ], "id  (PK)")

    h_oitem = table_box(b_oitem, y_bot, W2, "order_items", [
        "id  (PK)", "order_id  (FK)", "product_name",
        "product_price", "quantity", "subtotal",
    ], "id  (PK)")

    h_osh = table_box(b_osh, y_bot, W2, "order_status_history", [
        "id  (PK)", "order_id  (FK)", "user_id  (FK)",
        "status", "payment_method", "created_at",
    ], "id  (PK)")

    # ====== ARROWS: short fan-out from users, straight drops elsewhere ======
    AW = 1.6
    AS = 16

    def elbow(pts, label=None, lx=None, ly=None):
        """Orthogonal connector; arrowhead lands on the target box."""
        verts = list(pts)
        codes = [MPath.MOVETO] + [MPath.LINETO] * (len(verts) - 1)
        ax.add_patch(mpatches.FancyArrowPatch(path=MPath(verts, codes),
                                              arrowstyle="-|>", color=MUTED,
                                              lw=AW, mutation_scale=AS))
        if label:
            ax.text(lx, ly, label, ha="center", fontsize=7.5,
                    color=MUTED, fontstyle="italic")

    def drop(x, y_from, y_to, label=None, lx=None, ly=None):
        """Straight vertical arrow."""
        ax.annotate("", xy=(x, y_to), xytext=(x, y_from),
                    arrowprops=dict(arrowstyle="-|>", color=MUTED, lw=AW,
                                    shrinkA=3, shrinkB=3))
        if label:
            ax.text(lx, ly, label, ha="center", fontsize=7.5,
                    color=MUTED, fontstyle="italic")

    u_cx = ux + uw / 2          # users horizontal center
    u_by = uy                   # users bottom edge

    # users → otp_verification (left elbow, clear of all boxes)
    elbow([(7.6, u_by), (7.6, 8.75), (2.4, 8.75), (2.4, y_mid + h_otp)],
          label="1:N", lx=5.0, ly=8.93)
    # users → orders (straight drop)
    drop(u_cx, u_by, y_ord + h_ord, label="1:N", lx=u_cx + 0.28, ly=8.45)
    # users → system_settings + email_change_otp (shared bus below users)
    for tx, ty_top, lx, ly in [(c_sys + W1 / 2, y_mid + h_sys, c_sys + W1 / 2 + 0.28, 7.8),
                               (c_eml + W1 / 2, y_mid + h_eml, c_eml + W1 / 2 + 0.28, 7.9)]:
        elbow([(10.4, u_by), (10.4, 8.5), (tx, 8.5), (tx, ty_top)],
              label="1:N", lx=lx, ly=ly)
    # otp_verification → password_reset (straight drop)
    drop(c_otp + W1 / 2, y_mid, y_bot + h_prst, label="1:N", lx=2.68, ly=4.2)
    # products → order_items (elbow into shared top edge)
    elbow([(c_prod + W1 / 2, y_mid), (c_prod + W1 / 2, 3.8),
           (b_oitem + 0.9, 3.8), (b_oitem + 0.9, y_bot + h_oitem)],
          label="1:N", lx=6.5, ly=3.55)
    # orders → order_items (straight drop)
    drop(8.6, y_ord, y_bot + h_oitem, label="1:N", lx=8.88, ly=3.7)
    # orders → order_status_history (elbow, lands on its top edge)
    elbow([(10.2, y_ord), (10.2, 3.6), (b_osh + W2 / 2, 3.6),
           (b_osh + W2 / 2, y_bot + h_osh)],
          label="1:N", lx=10.7, ly=3.82)

    ax.set_title("AquaSphere — Entity Relationship Diagram", fontsize=16, fontweight="bold",
                 color=DEEP, pad=16, fontfamily="sans-serif")

    plt.tight_layout(pad=0.5)
    fig.savefig(FIG / "diagram-erd.png", dpi=300, bbox_inches="tight", facecolor=WHITE)
    plt.close()
    print("Saved diagram-erd.png")


# ---------------------------------------------------------------------------
# System Flow
# ---------------------------------------------------------------------------
def draw_system_flow():
    fig, ax = plt.subplots(1, 1, figsize=(13, 8))
    ax.set_xlim(0, 13)
    ax.set_ylim(0, 8)
    ax.axis("off")
    fig.patch.set_facecolor(WHITE)

    bw, bh = 2.0, 0.85
    gap = 0.55

    def box(x, y, text, color=AQUA):
        r = FancyBboxPatch((x, y), bw, bh, boxstyle="round,pad=0.08",
                           facecolor=color, edgecolor=DEEP, linewidth=1.2)
        ax.add_patch(r)
        ax.text(x + bw / 2, y + bh / 2, text, ha="center", va="center",
                fontsize=7.5, color=WHITE, fontweight="bold", fontfamily="sans-serif")

    def arr(x1, y1, x2, y2, label=None, color=INK, rad=0, ls="-"):
        ax.annotate("", xy=(x2, y2), xytext=(x1, y1),
                    arrowprops=dict(arrowstyle="-|>", color=color, lw=1.3,
                                    connectionstyle=f"arc3,rad={rad}", linestyle=ls))
        if label:
            mx, my = (x1 + x2) / 2, (y1 + y2) / 2
            ax.text(mx, my + 0.15, label, ha="center", fontsize=5.5,
                    color=MUTED, fontstyle="italic")

    ax.text(0.3, 7.4, "Customer Flow", fontsize=9, fontweight="bold",
            color=DEEP, fontfamily="sans-serif")
    ax.text(0.3, 4.5, "Admin Flow", fontsize=9, fontweight="bold",
            color=DEEP, fontfamily="sans-serif")

    row1_y = 6.2
    row2_y = 5.0
    row3_y = 2.2
    x0 = 0.4

    # Row 1
    box(x0, row1_y, "User\n(Customer)", DEEP)
    box(x0 + bw + gap, row1_y, "Register /\nLogin", AQUA)
    box(x0 + 2*(bw + gap), row1_y, "OTP\nVerification", LIGHT)
    box(x0 + 3*(bw + gap), row1_y, "Browse\nProducts", AQUA)
    box(x0 + 4*(bw + gap), row1_y, "Add to\nCart", AQUA)

    arr(x0 + bw, row1_y + bh/2, x0 + bw + gap, row1_y + bh/2)
    arr(x0 + 2*bw + gap, row1_y + bh/2, x0 + 2*(bw + gap), row1_y + bh/2, "credentials")
    arr(x0 + 3*bw + 2*gap, row1_y + bh/2, x0 + 3*(bw + gap), row1_y + bh/2, "verified")
    arr(x0 + 4*bw + 3*gap, row1_y + bh/2, x0 + 4*(bw + gap), row1_y + bh/2)

    # Row 2
    box(x0, row2_y, "Checkout", AQUA)
    box(x0 + bw + gap, row2_y, "Payment\n(COD)", GREEN)
    box(x0 + 2*(bw + gap), row2_y, "Order\nCreated", AQUA)
    box(x0 + 3*(bw + gap), row2_y, "ML Delivery\nPredict", "#D4952A")
    box(x0 + 4*(bw + gap), row2_y, "Notifications", LIGHT)

    arr(x0 + bw, row2_y + bh/2, x0 + bw + gap, row2_y + bh/2, "confirm")
    arr(x0 + 2*bw + gap, row2_y + bh/2, x0 + 2*(bw + gap), row2_y + bh/2)
    arr(x0 + 3*bw + 2*gap, row2_y + bh/2, x0 + 3*(bw + gap), row2_y + bh/2, "location", color=MUTED)
    arr(x0 + 4*bw + 3*gap, row2_y + bh/2, x0 + 4*(bw + gap), row2_y + bh/2, "status update", color=MUTED)

    arr(x0 + 3*(bw + gap) + bw/2, row1_y, x0 + 3*(bw + gap) + bw/2, row2_y + bh, rad=0)
    arr(x0 + 4*(bw + gap) + bw/2, row1_y, x0 + 4*(bw + gap) + bw/2, row2_y + bh, rad=0)

    # Row 3
    box(x0, row3_y, "Admin", DEEP)
    box(x0 + bw + gap, row3_y, "Manage\nProducts", AQUA)
    box(x0 + 2*(bw + gap), row3_y, "Manage\nOrders", AQUA)
    box(x0 + 3*(bw + gap), row3_y, "Manage\nUsers", AQUA)

    arr(x0 + bw, row3_y + bh/2, x0 + bw + gap, row3_y + bh/2)
    arr(x0 + 2*bw + gap, row3_y + bh/2, x0 + 2*(bw + gap), row3_y + bh/2)
    arr(x0 + 3*bw + 2*gap, row3_y + bh/2, x0 + 3*(bw + gap), row3_y + bh/2)

    db = FancyBboxPatch((11.2, 1.6), 1.6, 1.6, boxstyle="round,pad=0.08",
                        facecolor="#f0f0f0", edgecolor=MUTED, linewidth=1, linestyle="--")
    ax.add_patch(db)
    ax.text(12.0, 2.4, "PostgreSQL\nDatabase", ha="center", va="center",
            fontsize=7, color=INK, fontfamily="sans-serif")

    arr(x0 + 4*(bw + gap), row3_y + bh/2, 11.2, 2.4, color=MUTED, rad=-0.15, ls="--")

    ax.set_title("AquaSphere — System Flow Diagram", fontsize=14, fontweight="bold",
                 color=DEEP, pad=14, fontfamily="sans-serif")

    plt.tight_layout(pad=0.5)
    fig.savefig(FIG / "diagram-system-flow.png", dpi=180, bbox_inches="tight", facecolor=WHITE)
    plt.close()
    print("Saved diagram-system-flow.png")


# ---------------------------------------------------------------------------
# Order Status Flow
# ---------------------------------------------------------------------------
def draw_order_flow():
    fig, ax = plt.subplots(1, 1, figsize=(13, 4.5))
    ax.set_xlim(0, 13)
    ax.set_ylim(0, 4.5)
    ax.axis("off")
    fig.patch.set_facecolor(WHITE)

    bw, bh = 1.8, 0.9
    gap = 0.5

    def box(x, y, text, color=AQUA):
        r = FancyBboxPatch((x, y), bw, bh, boxstyle="round,pad=0.06",
                           facecolor=color, edgecolor=DEEP, linewidth=1.2)
        ax.add_patch(r)
        ax.text(x + bw / 2, y + bh / 2, text, ha="center", va="center",
                fontsize=7.5, color=WHITE, fontweight="bold", fontfamily="sans-serif")

    xs = [0.4 + i * (bw + gap) for i in range(6)]
    labels = ["Order\nPlaced", "Pending", "Confirmed", "Out for\nDelivery", "Delivered", "Completed"]
    colors = [DEEP, AQUA, AQUA, LIGHT, GREEN, GREEN]

    for i, (x, lab, col) in enumerate(zip(xs, labels, colors)):
        box(x, 1.8, lab, col)
        if i < len(xs) - 1:
            ax.annotate("", xy=(xs[i + 1], 2.25), xytext=(x + bw, 2.25),
                        arrowprops=dict(arrowstyle="-|>", color=INK, lw=1.3))

    cx = xs[2] + bw / 2
    box(xs[2], 0.3, "Cancelled", RED)
    ax.annotate("", xy=(cx, 0.3 + bh), xytext=(cx, 1.8),
                arrowprops=dict(arrowstyle="-|>", color=RED, lw=1.3))
    ax.text(cx + 0.25, 1.05, "cancel", fontsize=6, color=RED, fontstyle="italic")

    ax.set_title("AquaSphere — Order Status Flow", fontsize=13, fontweight="bold",
                 color=DEEP, pad=12, fontfamily="sans-serif")

    plt.tight_layout(pad=0.5)
    fig.savefig(FIG / "diagram-order-flow.png", dpi=180, bbox_inches="tight", facecolor=WHITE)
    plt.close()
    print("Saved diagram-order-flow.png")


# ---------------------------------------------------------------------------
# ML Pipeline
# ---------------------------------------------------------------------------
def draw_ml_pipeline():
    fig, ax = plt.subplots(1, 1, figsize=(13, 6.5))
    ax.set_xlim(0, 13)
    ax.set_ylim(0, 6.5)
    ax.axis("off")
    fig.patch.set_facecolor(WHITE)

    bw, bh = 2.0, 0.85
    gap = 0.55

    def box(x, y, text, color=AQUA):
        r = FancyBboxPatch((x, y), bw, bh, boxstyle="round,pad=0.06",
                           facecolor=color, edgecolor=DEEP, linewidth=1.2)
        ax.add_patch(r)
        ax.text(x + bw / 2, y + bh / 2, text, ha="center", va="center",
                fontsize=7.5, color=WHITE, fontweight="bold", fontfamily="sans-serif")

    def arr(x1, y1, x2, y2, label=None, color=INK, rad=0, ls="-"):
        ax.annotate("", xy=(x2, y2), xytext=(x1, y1),
                    arrowprops=dict(arrowstyle="-|>", color=color, lw=1.3,
                                    connectionstyle=f"arc3,rad={rad}", linestyle=ls))
        if label:
            mx, my = (x1 + x2) / 2, (y1 + y2) / 2
            ax.text(mx, my + 0.15, label, ha="center", fontsize=6,
                    color=MUTED, fontstyle="italic")

    # Training row
    ax.text(0.3, 5.8, "Training Pipeline (Offline)", fontsize=9, fontweight="bold",
            color=DEEP, fontfamily="sans-serif")

    ty = 4.8  # y for training boxes
    tx = [0.4 + i * (bw + gap) for i in range(4)]
    t_labs = ["Synthetic\nData", "Feature\nEngineering", "Model\nTraining", "Model\nExport"]
    t_cols = [MUTED, AQUA, AQUA, GREEN]
    for i, (lab, col) in enumerate(zip(t_labs, t_cols)):
        box(tx[i], ty, lab, col)
        if i < 3:
            arr(tx[i] + bw, ty + bh/2, tx[i+1], ty + bh/2)

    # Inference row
    ax.text(0.3, 3.2, "Inference Pipeline (Production)", fontsize=9, fontweight="bold",
            color=DEEP, fontfamily="sans-serif")

    iy = 1.8  # y for inference boxes
    ix = [0.4 + i * (bw + gap) for i in range(5)]
    i_labs = ["User\nCheckout", "PHP\nBackend", "Python\npredict.py", "Delivery\nTime", "Shipping\nFee"]
    i_cols = [DEEP, AQUA, AQUA, GREEN, GREEN]
    for i, (lab, col) in enumerate(zip(i_labs, i_cols)):
        box(ix[i], iy, lab, col)
        if i < 4:
            arr(ix[i] + bw, iy + bh/2, ix[i+1], iy + bh/2)

    # joblib arrow: Model Export → Python predict.py (dashed, from export bottom to predict.py top)
    arr(tx[3] + bw/2, ty, ix[2] + bw/2, iy + bh,
        label="joblib", color=MUTED, rad=-0.2, ls="--")

    # Fallback arrow: PHP Backend → Delivery Time (bypasses predict.py)
    # Draw it as a simple curved arrow going BELOW the predict.py box
    fb_sx = ix[1] + bw        # right edge of PHP Backend
    fb_sy = iy + bh * 0.5     # mid-height of PHP Backend
    fb_ex = ix[3]              # left edge of Delivery Time
    fb_ey = iy + bh * 0.5     # mid-height of Delivery Time

    # Use a simple arc3 with negative radius to curve below
    ax.annotate("", xy=(fb_ex, fb_ey), xytext=(fb_sx, fb_sy),
                arrowprops=dict(arrowstyle="-|>", color=RED, lw=1.2, linestyle="--",
                                connectionstyle="arc3,rad=-0.5"))
    ax.text((fb_sx + fb_ex) / 2, iy - 0.25, "fallback (when Python unavailable)",
            ha="center", fontsize=6, color=RED, fontstyle="italic")

    ax.set_title("AquaSphere — ML Training and Inference Pipeline", fontsize=13, fontweight="bold",
                 color=DEEP, pad=14, fontfamily="sans-serif")

    plt.tight_layout(pad=0.5)
    fig.savefig(FIG / "diagram-ml-pipeline.png", dpi=300, bbox_inches="tight", facecolor=WHITE)
    plt.close()
    print("Saved diagram-ml-pipeline.png")


if __name__ == "__main__":
    ensure_dir()
    draw_erd()
    draw_system_flow()
    draw_order_flow()
    draw_ml_pipeline()
    print("All figures generated.")
