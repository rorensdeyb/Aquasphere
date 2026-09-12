#!/usr/bin/env python3
"""Generate AquaSphere documentation figures (ERD, flow diagrams)."""
from __future__ import annotations

import os
from pathlib import Path

import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
import matplotlib.patches as mpatches
from matplotlib.patches import FancyBboxPatch, FancyArrowPatch

ROOT = Path(__file__).resolve().parent
FIG = ROOT / "figures"

AQUA = "#2383B5"
DEEP = "#155A7A"
LIGHT = "#5BC0EB"
BG = "#EAF7FC"
INK = "#163B53"
MUTED = "#607D8B"
WHITE = "#FFFFFF"
GREEN = "#2E8B70"
RED = "#b84252"
BORDER = "#D5E5ED"


def ensure_dir():
    FIG.mkdir(parents=True, exist_ok=True)


def draw_erd():
    fig, ax = plt.subplots(1, 1, figsize=(14, 9))
    ax.set_xlim(0, 14)
    ax.set_ylim(0, 9)
    ax.axis("off")
    fig.patch.set_facecolor(WHITE)

    def table_box(x, y, w, h, name, cols, pk=None):
        rect = FancyBboxPatch((x, y), w, h, boxstyle="round,pad=0.05",
                              facecolor=WHITE, edgecolor=AQUA, linewidth=1.5)
        ax.add_patch(rect)
        header = FancyBboxPatch((x, y + h - 0.35), w, 0.35, boxstyle="round,pad=0.02",
                                facecolor=DEEP, edgecolor=DEEP, linewidth=1)
        ax.add_patch(header)
        ax.text(x + w / 2, y + h - 0.17, name, ha="center", va="center",
                fontsize=8, fontweight="bold", color=WHITE, fontfamily="sans-serif")
        ty = y + h - 0.5
        for col in cols:
            marker = "PK " if col == pk else "    "
            ax.text(x + 0.1, ty, f"{marker}{col}", ha="left", va="center",
                    fontsize=6.5, color=INK, fontfamily="monospace")
            ty -= 0.22

    # Tables
    table_box(0.3, 6.5, 2.8, 2.2, "users", [
        "id (PK)", "username", "password_hash", "email",
        "first_name, last_name", "gender, date_of_birth",
        "is_admin", "saved_cart (JSON)", "delivery_address (JSON)",
        "notif_seen_at, notif_cleared_at"
    ], "id (PK)")

    table_box(4.0, 7.0, 2.5, 1.5, "otp_verification", [
        "id (PK)", "email", "otp_code", "username",
        "password_hash", "expires_at", "is_verified"
    ], "id (PK)")

    table_box(4.0, 5.2, 2.5, 1.3, "password_reset", [
        "id (PK)", "email", "otp_code", "user_id (FK)",
        "expires_at", "is_verified"
    ], "id (PK)")

    table_box(7.5, 7.0, 2.5, 1.3, "products", [
        "id (PK)", "label", "description", "price",
        "image_url", "category", "unit"
    ], "id (PK)")

    table_box(11.0, 6.5, 2.7, 2.2, "orders", [
        "id (PK)", "user_id (FK)", "order_date",
        "delivery_date, delivery_time", "delivery_address",
        "total_amount, status", "payment_method",
        "paymongo_source_id"
    ], "id (PK)")

    table_box(7.5, 4.5, 2.5, 1.3, "order_items", [
        "id (PK)", "order_id (FK)", "product_name",
        "product_price", "quantity", "subtotal"
    ], "id (PK)")

    table_box(11.0, 4.2, 2.7, 1.3, "order_status_history", [
        "id (PK)", "order_id (FK)", "user_id (FK)",
        "status", "payment_method", "created_at"
    ], "id (PK)")

    table_box(0.3, 3.5, 2.8, 1.0, "system_settings", [
        "id (PK)", "setting_key", "setting_value",
        "updated_by (FK)"
    ], "id (PK)")

    table_box(4.0, 3.5, 2.5, 1.0, "email_change_otp", [
        "id (PK)", "user_id (FK)", "otp_code",
        "new_email", "expires_at"
    ], "id (PK)")

    # Relationships (arrows)
    arrow_kw = dict(arrowstyle="-|>", color=MUTED, lw=1.2, connectionstyle="arc3,rad=0")
    ax.annotate("", xy=(4.0, 7.75), xytext=(3.15, 7.75), arrowprops=arrow_kw)
    ax.annotate("", xy=(4.0, 5.85), xytext=(3.15, 6.2), arrowprops=arrow_kw)
    ax.annotate("", xy=(7.5, 7.65), xytext=(6.5, 7.65), arrowprops=arrow_kw)
    ax.annotate("", xy=(11.0, 7.6), xytext=(10.0, 7.6), arrowprops=arrow_kw)
    ax.annotate("", xy=(7.5, 5.15), xytext=(11.0, 5.15),
                arrowprops=dict(arrowstyle="-|>", color=MUTED, lw=1.2, connectionstyle="arc3,rad=-0.2"))
    ax.annotate("", xy=(11.0, 4.85), xytext=(10.0, 5.5),
                arrowprops=dict(arrowstyle="-|>", color=MUTED, lw=1.2, connectionstyle="arc3,rad=0.15"))
    ax.annotate("", xy=(4.0, 4.0), xytext=(3.15, 4.2),
                arrowprops=dict(arrowstyle="-|>", color=MUTED, lw=1.2, connectionstyle="arc3,rad=-0.15"))

    # Relationship labels
    ax.text(3.5, 7.9, "1:N", ha="center", fontsize=6, color=MUTED, fontstyle="italic")
    ax.text(3.5, 5.95, "1:N", ha="center", fontsize=6, color=MUTED, fontstyle="italic")
    ax.text(7.0, 7.85, "1:N", ha="center", fontsize=6, color=MUTED, fontstyle="italic")
    ax.text(10.5, 7.85, "1:N", ha="center", fontsize=6, color=MUTED, fontstyle="italic")

    ax.set_title("AquaSphere — Entity Relationship Diagram", fontsize=12, fontweight="bold",
                 color=DEEP, pad=10, fontfamily="sans-serif")

    plt.tight_layout()
    fig.savefig(FIG / "diagram-erd.png", dpi=180, bbox_inches="tight", facecolor=WHITE)
    plt.close()
    print("Saved diagram-erd.png")


def draw_system_flow():
    fig, ax = plt.subplots(1, 1, figsize=(12, 7))
    ax.set_xlim(0, 12)
    ax.set_ylim(0, 7)
    ax.axis("off")
    fig.patch.set_facecolor(WHITE)

    def box(x, y, w, h, text, color=AQUA):
        rect = FancyBboxPatch((x, y), w, h, boxstyle="round,pad=0.08",
                              facecolor=color, edgecolor=DEEP, linewidth=1.2)
        ax.add_patch(rect)
        ax.text(x + w / 2, y + h / 2, text, ha="center", va="center",
                fontsize=7.5, color=WHITE, fontweight="bold", fontfamily="sans-serif")

    def arrow(x1, y1, x2, y2, label=None):
        ax.annotate("", xy=(x2, y2), xytext=(x1, y1),
                    arrowprops=dict(arrowstyle="-|>", color=INK, lw=1.3))
        if label:
            mx, my = (x1 + x2) / 2, (y1 + y2) / 2
            ax.text(mx, my + 0.12, label, ha="center", fontsize=5.5, color=MUTED, fontstyle="italic")

    # User
    box(0.3, 5.5, 1.8, 0.9, "User\n(Customer)", DEEP)

    # Auth flow
    box(3.0, 5.8, 1.8, 0.7, "Register /\nLogin", AQUA)
    arrow(2.15, 5.95, 3.0, 6.15)
    arrow(2.15, 5.95, 3.0, 5.95)

    box(5.5, 5.8, 1.8, 0.7, "OTP\nVerification", LIGHT)
    arrow(4.85, 6.15, 5.5, 6.15, "email")

    # Main flow
    box(3.0, 4.2, 1.8, 0.7, "Browse\nProducts", AQUA)
    arrow(4.85, 5.95, 3.9, 4.55)

    box(5.5, 4.2, 1.8, 0.7, "Add to\nCart", AQUA)
    arrow(4.85, 4.55, 5.5, 4.55)

    box(8.0, 4.2, 1.8, 0.7, "Checkout", AQUA)
    arrow(7.35, 4.55, 8.0, 4.55)

    # Payment
    box(8.0, 2.8, 1.8, 0.7, "Payment\n(COD)", GREEN)
    arrow(8.9, 4.2, 8.9, 3.5)

    # ML
    box(10.2, 4.2, 1.5, 0.7, "ML\nPredict", "#E8A838")
    arrow(8.0, 4.55, 10.2, 4.55, "location")

    # Order
    box(5.5, 2.8, 1.8, 0.7, "Order\nCreated", AQUA)
    arrow(8.9, 2.8, 7.35, 3.15)

    # Notifications
    box(3.0, 2.8, 1.8, 0.7, "Notifications", LIGHT)
    arrow(5.5, 3.15, 4.85, 3.15, "status update")

    # Admin
    box(0.3, 1.0, 1.8, 0.9, "Admin", DEEP)
    box(3.0, 1.0, 1.8, 0.7, "Manage\nProducts", AQUA)
    arrow(2.15, 1.45, 3.0, 1.35)

    box(5.5, 1.0, 1.8, 0.7, "Manage\nOrders", AQUA)
    arrow(4.85, 1.35, 5.5, 1.35)

    box(8.0, 1.0, 1.8, 0.7, "Manage\nUsers", AQUA)
    arrow(7.35, 1.35, 8.0, 1.35)

    # DB
    rect = FancyBboxPatch((9.5, 0.5), 2.2, 1.5, boxstyle="round,pad=0.08",
                          facecolor="#f0f0f0", edgecolor=MUTED, linewidth=1, linestyle="--")
    ax.add_patch(rect)
    ax.text(10.6, 1.25, "PostgreSQL\nDatabase", ha="center", va="center",
            fontsize=7, color=INK, fontfamily="sans-serif")

    ax.set_title("AquaSphere — System Flow Diagram", fontsize=12, fontweight="bold",
                 color=DEEP, pad=10, fontfamily="sans-serif")

    plt.tight_layout()
    fig.savefig(FIG / "diagram-system-flow.png", dpi=180, bbox_inches="tight", facecolor=WHITE)
    plt.close()
    print("Saved diagram-system-flow.png")


def draw_order_flow():
    fig, ax = plt.subplots(1, 1, figsize=(12, 4))
    ax.set_xlim(0, 12)
    ax.set_ylim(0, 4)
    ax.axis("off")
    fig.patch.set_facecolor(WHITE)

    def box(x, y, w, h, text, color=AQUA):
        rect = FancyBboxPatch((x, y), w, h, boxstyle="round,pad=0.06",
                              facecolor=color, edgecolor=DEEP, linewidth=1.2)
        ax.add_patch(rect)
        ax.text(x + w / 2, y + h / 2, text, ha="center", va="center",
                fontsize=7, color=WHITE, fontweight="bold", fontfamily="sans-serif")

    def arrow(x1, y1, x2, y2, label=None):
        ax.annotate("", xy=(x2, y2), xytext=(x1, y1),
                    arrowprops=dict(arrowstyle="-|>", color=INK, lw=1.3))
        if label:
            mx, my = (x1 + x2) / 2, (y1 + y2) / 2
            ax.text(mx, my + 0.15, label, ha="center", fontsize=5.5, color=MUTED, fontstyle="italic")

    statuses = [
        (0.3, 1.5, "Order\nPlaced", DEEP),
        (2.3, 1.5, "Pending", AQUA),
        (4.3, 1.5, "Confirmed", AQUA),
        (6.3, 1.5, "Out for\nDelivery", LIGHT),
        (8.3, 1.5, "Delivered", GREEN),
        (10.3, 1.5, "Completed", GREEN),
    ]

    for i, (x, y, text, color) in enumerate(statuses):
        box(x, y, 1.6, 0.9, text, color)
        if i < len(statuses) - 1:
            arrow(x + 1.6, 1.95, x + 2.3, 1.95)

    # Cancelled branch
    box(4.3, 0.2, 1.6, 0.7, "Cancelled", RED)
    ax.annotate("", xy=(5.1, 0.9), xytext=(5.1, 1.5),
                arrowprops=dict(arrowstyle="-|>", color=RED, lw=1.3))
    ax.text(5.4, 1.15, "cancel", fontsize=5.5, color=RED, fontstyle="italic")

    ax.set_title("AquaSphere — Order Status Flow", fontsize=11, fontweight="bold",
                 color=DEEP, pad=10, fontfamily="sans-serif")

    plt.tight_layout()
    fig.savefig(FIG / "diagram-order-flow.png", dpi=180, bbox_inches="tight", facecolor=WHITE)
    plt.close()
    print("Saved diagram-order-flow.png")


def draw_ml_pipeline():
    fig, ax = plt.subplots(1, 1, figsize=(11, 4.5))
    ax.set_xlim(0, 11)
    ax.set_ylim(0, 4.5)
    ax.axis("off")
    fig.patch.set_facecolor(WHITE)

    def box(x, y, w, h, text, color=AQUA):
        rect = FancyBboxPatch((x, y), w, h, boxstyle="round,pad=0.06",
                              facecolor=color, edgecolor=DEEP, linewidth=1.2)
        ax.add_patch(rect)
        ax.text(x + w / 2, y + h / 2, text, ha="center", va="center",
                fontsize=7, color=WHITE, fontweight="bold", fontfamily="sans-serif")

    def arrow(x1, y1, x2, y2, label=None):
        ax.annotate("", xy=(x2, y2), xytext=(x1, y1),
                    arrowprops=dict(arrowstyle="-|>", color=INK, lw=1.3))
        if label:
            mx, my = (x1 + x2) / 2, (y1 + y2) / 2
            ax.text(mx, my + 0.15, label, ha="center", fontsize=5.5, color=MUTED, fontstyle="italic")

    # Training pipeline (top row)
    ax.text(0.5, 4.0, "Training Pipeline (Offline)", fontsize=8, fontweight="bold",
            color=DEEP, fontfamily="sans-serif")
    box(0.3, 3.0, 1.8, 0.7, "Synthetic\nData", MUTED)
    box(2.6, 3.0, 1.8, 0.7, "Feature\nEngineering", AQUA)
    box(4.9, 3.0, 1.8, 0.7, "Model\nTraining", AQUA)
    box(7.2, 3.0, 1.8, 0.7, "Model\nExport", GREEN)
    arrow(2.15, 3.35, 2.6, 3.35)
    arrow(4.45, 3.35, 4.9, 3.35)
    arrow(6.75, 3.35, 7.2, 3.35)

    # Inference pipeline (bottom row)
    ax.text(0.5, 2.2, "Inference Pipeline (Production)", fontsize=8, fontweight="bold",
            color=DEEP, fontfamily="sans-serif")
    box(0.3, 1.2, 1.8, 0.7, "User\nCheckout", DEEP)
    box(2.6, 1.2, 1.8, 0.7, "PHP\nBackend", AQUA)
    box(4.9, 1.2, 1.8, 0.7, "Python\npredict.py", AQUA)
    box(7.2, 1.2, 1.8, 0.7, "Delivery\nPrediction", GREEN)
    box(9.3, 1.2, 1.5, 0.7, "Ship\nFee", GREEN)
    arrow(2.15, 1.55, 2.6, 1.55)
    arrow(4.45, 1.55, 4.9, 1.55)
    arrow(6.75, 1.55, 7.2, 1.55)
    arrow(9.05, 1.55, 9.3, 1.55)

    # Arrow from export to predict.py
    ax.annotate("", xy=(5.8, 1.9), xytext=(8.1, 3.0),
                arrowprops=dict(arrowstyle="-|>", color=MUTED, lw=1, linestyle="dashed",
                                connectionstyle="arc3,rad=0.3"))
    ax.text(7.5, 2.5, "joblib", fontsize=5.5, color=MUTED, fontstyle="italic")

    # Fallback arrow
    ax.annotate("", xy=(7.2, 1.2), xytext=(4.9, 1.2),
                arrowprops=dict(arrowstyle="-|>", color=RED, lw=1, linestyle="dotted"))
    ax.text(6.0, 1.0, "fallback", fontsize=5, color=RED, fontstyle="italic")

    ax.set_title("AquaSphere — ML Training and Inference Pipeline", fontsize=11, fontweight="bold",
                 color=DEEP, pad=10, fontfamily="sans-serif")

    plt.tight_layout()
    fig.savefig(FIG / "diagram-ml-pipeline.png", dpi=180, bbox_inches="tight", facecolor=WHITE)
    plt.close()
    print("Saved diagram-ml-pipeline.png")


if __name__ == "__main__":
    ensure_dir()
    draw_erd()
    draw_system_flow()
    draw_order_flow()
    draw_ml_pipeline()
    print("All figures generated.")
