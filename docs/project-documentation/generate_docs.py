#!/usr/bin/env python3
"""Generate AquaSphere comprehensive system documentation as a professional PDF."""
from __future__ import annotations

import os
import sys
from pathlib import Path

from reportlab.lib.colors import HexColor, white
from reportlab.lib.enums import TA_CENTER, TA_JUSTIFY, TA_LEFT, TA_RIGHT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (
    Image,
    KeepTogether,
    ListFlowable,
    ListItem,
    PageBreak,
    Paragraph,
    Preformatted,
    SimpleDocTemplate,
    Spacer,
    Table,
    TableStyle,
)

ROOT = Path(__file__).resolve().parent
FIG = ROOT / "figures"
REPO = ROOT.parent.parent
OUT_PDF = REPO / "AquaSphere_System_Documentation.pdf"

AQUA = HexColor("#2383B5")
DEEP = HexColor("#155A7A")
LIGHT = HexColor("#5BC0EB")
INK = HexColor("#163B53")
MUTED = HexColor("#607D8B")
LINE = HexColor("#D5E5ED")
ROW = HexColor("#EAF7FC")
HEADBG = HexColor("#155A7A")
PLACE = HexColor("#EAF7FC")
WARN = HexColor("#7A5A2E")


def register_fonts():
    candidates = [
        (r"C:\Windows\Fonts\calibri.ttf", r"C:\Windows\Fonts\calibrib.ttf", r"C:\Windows\Fonts\calibrii.ttf"),
        ("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf", "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf", "/usr/share/fonts/truetype/dejavu/DejaVuSans-Oblique.ttf"),
    ]
    for r, b, i in candidates:
        if os.path.isfile(r) and os.path.isfile(b):
            pdfmetrics.registerFont(TTFont("Body", r))
            pdfmetrics.registerFont(TTFont("Body-Bold", b))
            if os.path.isfile(i):
                pdfmetrics.registerFont(TTFont("Body-Italic", i))
            else:
                pdfmetrics.registerFont(TTFont("Body-Italic", r))
            return
    raise SystemExit("No suitable TrueType fonts found (Calibri or DejaVu Sans).")


def styles():
    ss = getSampleStyleSheet()
    ss.add(ParagraphStyle(name="CoverTitle", fontName="Body-Bold", fontSize=28, leading=34, textColor=DEEP, alignment=TA_CENTER, spaceAfter=8))
    ss.add(ParagraphStyle(name="CoverSub", fontName="Body", fontSize=13, leading=18, textColor=MUTED, alignment=TA_CENTER, spaceAfter=4))
    ss.add(ParagraphStyle(name="CoverMeta", fontName="Body", fontSize=10, leading=14, textColor=MUTED, alignment=TA_CENTER, spaceAfter=2))
    ss.add(ParagraphStyle(name="H1", fontName="Body-Bold", fontSize=15, leading=20, textColor=DEEP, spaceBefore=4, spaceAfter=8, keepWithNext=True))
    ss.add(ParagraphStyle(name="H2", fontName="Body-Bold", fontSize=12, leading=16, textColor=AQUA, spaceBefore=10, spaceAfter=5, keepWithNext=True))
    ss.add(ParagraphStyle(name="H3", fontName="Body-Bold", fontSize=10.5, leading=14, textColor=INK, spaceBefore=8, spaceAfter=4, keepWithNext=True))
    ss.add(ParagraphStyle(name="BodyJ", fontName="Body", fontSize=10, leading=14, textColor=INK, alignment=TA_JUSTIFY, spaceAfter=7))
    ss.add(ParagraphStyle(name="BodyL", fontName="Body", fontSize=10, leading=14, textColor=INK, alignment=TA_LEFT, spaceAfter=7))
    ss.add(ParagraphStyle(name="Caption", fontName="Body-Italic", fontSize=8.5, leading=11.5, textColor=MUTED, alignment=TA_CENTER, spaceBefore=3, spaceAfter=10))
    ss.add(ParagraphStyle(name="Note", fontName="Body-Italic", fontSize=9, leading=12.5, textColor=WARN, alignment=TA_LEFT, spaceAfter=8, leftIndent=4, rightIndent=4))
    ss.add(ParagraphStyle(name="CodeMono", fontName="Courier", fontSize=8, leading=11, textColor=INK, backColor=ROW, leftIndent=4, rightIndent=4, spaceAfter=8))
    ss.add(ParagraphStyle(name="TOC1", fontName="Body", fontSize=10.5, leading=16, textColor=INK, spaceAfter=2))
    ss.add(ParagraphStyle(name="Th", fontName="Body-Bold", fontSize=8, leading=11, textColor=white, alignment=TA_LEFT))
    ss.add(ParagraphStyle(name="Td", fontName="Body", fontSize=8, leading=11, textColor=INK, alignment=TA_LEFT))
    ss.add(ParagraphStyle(name="Footer", fontName="Body", fontSize=8, leading=10, textColor=MUTED, alignment=TA_CENTER))
    ss.add(ParagraphStyle(name="PlaceT", fontName="Body-Italic", fontSize=9, leading=12, textColor=MUTED, alignment=TA_CENTER))
    ss.add(ParagraphStyle(name="BulletBody", fontName="Body", fontSize=10, leading=13.5, textColor=INK))
    return ss


def header_footer(canvas, doc):
    canvas.saveState()
    if doc.page > 1:
        canvas.setStrokeColor(LINE)
        canvas.setLineWidth(0.4)
        canvas.line(18 * mm, A4[1] - 12 * mm, A4[0] - 18 * mm, A4[1] - 12 * mm)
        canvas.setFont("Body", 8)
        canvas.setFillColor(MUTED)
        canvas.drawString(18 * mm, A4[1] - 10.5 * mm, "AquaSphere — Comprehensive System Documentation")
        canvas.drawRightString(A4[0] - 18 * mm, A4[1] - 10.5 * mm, f"Page {doc.page}")
        canvas.line(18 * mm, 14 * mm, A4[0] - 18 * mm, 14 * mm)
        canvas.drawCentredString(A4[0] / 2, 9.5 * mm, "Technical record of the current system  |  August 2026")
    canvas.restoreState()


def p(s, style, text):
    return Paragraph(text.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;").replace("\n", "<br/>"), style)


def phtml(s, style, text):
    return Paragraph(text, style)


def bullets(s, items):
    return ListFlowable(
        [ListItem(Paragraph(i.replace("&", "&amp;"), s["BulletBody"]), leftIndent=8, bulletColor=AQUA) for i in items],
        bulletType="bullet",
        start="•",
        leftIndent=14,
        bulletFontName="Body",
        bulletFontSize=10,
        spaceAfter=8,
    )


def table(s, headers, rows, widths):
    head = [Paragraph(h, s["Th"]) for h in headers]
    data = [head]
    for row in rows:
        data.append([Paragraph(str(c).replace("&", "&amp;"), s["Td"]) for c in row])
    t = Table(data, colWidths=widths, repeatRows=1)
    cmds = [
        ("BACKGROUND", (0, 0), (-1, 0), HEADBG),
        ("TEXTCOLOR", (0, 0), (-1, 0), white),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 5),
        ("RIGHTPADDING", (0, 0), (-1, -1), 5),
        ("TOPPADDING", (0, 0), (-1, -1), 4),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
        ("GRID", (0, 0), (-1, -1), 0.25, LINE),
        ("FONTNAME", (0, 0), (-1, 0), "Body-Bold"),
    ]
    for i in range(1, len(data)):
        if i % 2 == 0:
            cmds.append(("BACKGROUND", (0, i), (-1, i), ROW))
    t.setStyle(TableStyle(cmds))
    t.spaceAfter = 10
    return t


def fig(path: Path, caption: str, s, max_w=170 * mm, max_h=95 * mm):
    if not path.is_file():
        return placeholder(s, caption)
    img = Image(str(path))
    iw, ih = img.imageWidth, img.imageHeight
    scale = min(max_w / iw, max_h / ih, 1.0)
    img.drawWidth = iw * scale
    img.drawHeight = ih * scale
    img.hAlign = "CENTER"
    return KeepTogether([img, Paragraph(caption, s["Caption"])])


def placeholder(s, label):
    inner = Table(
        [[Paragraph(f"[Screenshot: {label}]", s["PlaceT"])]],
        colWidths=[170 * mm],
        rowHeights=[28 * mm],
    )
    inner.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, -1), PLACE),
                ("BOX", (0, 0), (-1, -1), 0.6, LIGHT),
                ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
                ("ALIGN", (0, 0), (-1, -1), "CENTER"),
            ]
        )
    )
    return KeepTogether([inner, Paragraph(f"Placeholder pending a captured screenshot of {label}.", s["Caption"])])


def hrule():
    t = Table([[""]], colWidths=[174 * mm], rowHeights=[1])
    t.setStyle(TableStyle([("BACKGROUND", (0, 0), (-1, -1), LIGHT), ("TOPPADDING", (0, 0), (-1, -1), 0), ("BOTTOMPADDING", (0, 0), (-1, -1), 0)]))
    t.spaceAfter = 10
    return t


def build():
    register_fonts()
    s = styles()
    story = []

    # Cover
    story.append(Spacer(1, 42 * mm))
    story.append(Paragraph("AquaSphere", s["CoverTitle"]))
    story.append(Paragraph("Comprehensive System Documentation", s["CoverSub"]))
    story.append(Spacer(1, 4 * mm))
    story.append(hrule())
    story.append(Paragraph("A Web-Based Water Delivery E-Commerce System<br/>with ML-Assisted Delivery Time Prediction", s["CoverSub"]))
    story.append(Spacer(1, 8 * mm))
    story.append(Paragraph("Technical record of the current implemented system", s["CoverMeta"]))
    story.append(Paragraph("August 2026", s["CoverMeta"]))
    story.append(Spacer(1, 14 * mm))
    story.append(Paragraph("Author", s["CoverMeta"]))
    story.append(Paragraph("<b>Tolentino, Lawrence Dave P.</b>", s["CoverMeta"]))
    story.append(Spacer(1, 16 * mm))
    story.append(Paragraph("Live application: https://aquasphere.up.railway.app/", s["CoverMeta"]))
    story.append(Paragraph("GitHub repository: https://github.com/rorensdeyb/Aquasphere.git", s["CoverMeta"]))
    story.append(Spacer(1, 6 * mm))
    story.append(Paragraph("<i>Note: This is a second-year capstone project. Certain features remain incomplete<br/>or scaffolded. Known limitations are documented for transparency.</i>", s["CoverMeta"]))
    story.append(PageBreak())

    # TOC
    story.append(Paragraph("Table of Contents", s["H1"]))
    story.append(hrule())
    toc = [
        "1. Project Overview",
        "2. Statement of the Problem",
        "3. Objectives of the Project",
        "4. Project Scope and Limitation",
        "5. System Features and Functionalities",
        "6. Database Design",
        "7. Machine Learning Methodology",
        "8. API Integration and External Services",
        "9. Deployment Process",
        "10. Technologies Used",
        "11. User Interface and Page Documentation",
        "12. Conclusion and Recommendations",
        "13. Project Links",
    ]
    for item in toc:
        story.append(Paragraph(item, s["TOC1"]))
    story.append(PageBreak())

    # 1
    story.append(Paragraph("1. Project Overview", s["H1"]))
    story.append(hrule())
    story.append(p(s, s["BodyJ"], "AquaSphere is a web-based e-commerce system designed to streamline the ordering and delivery of purified drinking water products. The platform allows users to browse available water products, manage a shopping cart, place delivery orders, and track order status in real time. An integrated machine learning model predicts delivery time and shipping cost based on the customer's location, providing a data-driven logistics experience."))
    story.append(p(s, s["BodyJ"], "The project was developed in response to the growing demand for online water delivery services in local communities. Many water refill stations still rely on phone orders or walk-in transactions, which can lead to scheduling conflicts, manual delivery estimation, and limited order visibility for customers."))
    story.append(p(s, s["BodyJ"], "AquaSphere was developed as a second-year capstone project. While core functionality is implemented and deployed, certain features remain in an early or incomplete state. The system is presented here in its current working form, with known limitations documented for transparency."))
    story.append(phtml(s, s["BodyJ"], "<b>Regional Coverage:</b> The system currently covers the area around Region IV-A CALABARZON and does not cater to all places in the Philippines yet. This showcase demonstrates a potentially helpful system for water delivery businesses operating within this region."))
    story.append(table(
        s,
        ["Item", "Current value"],
        [
            ["Project name", "AquaSphere"],
            ["Project type", "Full-stack web application (e-commerce)"],
            ["Intended users", "Customers ordering water delivery; administrators managing products and orders"],
            ["Primary live host", "Railway (PHP 8.2 + PostgreSQL)"],
            ["ML delivery model", "Python scikit-learn (Random Forest / Linear Regression) via subprocess"],
            ["Current status", "Deployed and operational on free-tier services, with known limitations documented later"],
            ["Project level", "Second-year capstone project (ITST 304)"],
        ],
        [45 * mm, 129 * mm],
    ))

    # 2
    story.append(Paragraph("2. Statement of the Problem", s["H1"]))
    story.append(hrule())
    story.append(p(s, s["BodyJ"], "Water delivery services in local communities face operational challenges related to manual order processing, inconsistent delivery time estimates, and limited customer visibility into order status. Customers often place orders through phone calls or social media messages, which can result in miscommunication, forgotten orders, and difficulty tracking delivery progress."))
    story.append(p(s, s["BodyJ"], "Without a centralized digital platform, water delivery businesses must rely on manual coordination between customers and delivery personnel. This process is time-consuming and prone to errors, particularly when estimating delivery times and calculating shipping fees based on distance."))
    story.append(p(s, s["BodyJ"], "Although e-commerce platforms exist for various product categories, few are specifically designed for the local water delivery niche, where delivery time prediction and location-based logistics are critical. This shows the need for a dedicated system that combines product browsing, order management, and machine learning–assisted delivery estimation in one integrated platform."))

    # 3
    story.append(Paragraph("3. Objectives of the Project", s["H1"]))
    story.append(hrule())
    story.append(Paragraph("3.1 General objective", s["H2"]))
    story.append(p(s, s["BodyJ"], "To develop a web-based water delivery e-commerce system that allows customers to browse products, manage orders, and receive machine-learning–predicted delivery times and shipping fees based on their delivery location."))
    story.append(Paragraph("3.2 Specific objectives", s["H2"]))
    story.append(bullets(s, [
        "Design a responsive web application with user registration, email OTP verification, session login, and profile management.",
        "Develop a product catalog with image display, category filtering, and admin CRUD operations.",
        "Implement a shopping cart and checkout system with delivery address management and ML-based shipping fee calculation.",
        "Integrate Cash on Delivery as the active payment method and scaffold PayMongo digital payment for future activation.",
        "Develop an order management module with status tracking, history, and notification integration.",
        "Implement a machine learning model for delivery time prediction using geographic and order features.",
        "Develop an administrator dashboard for product, order, and user management.",
        "Deploy the system on Railway with PostgreSQL and maintain source code on GitHub.",
    ]))

    # 4
    story.append(Paragraph("4. Project Scope and Limitation", s["H1"]))
    story.append(hrule())
    story.append(p(s, s["BodyJ"], "AquaSphere is a second-year capstone project developed within an academic timeframe. Core features including product browsing, cart management, checkout, order tracking, admin panel, and ML delivery prediction are implemented and functional."))
    story.append(p(s, s["Note"], "The following features are not implemented or are incomplete in the current system:"))
    story.append(bullets(s, [
        "PayMongo digital payment integration is scaffolded but disabled (\"Coming Soon\").",
        "Product search is limited to category filtering; no full-text search.",
        "No product reviews, wishlists, promotional codes, or loyalty programs.",
        "ML model is trained on synthetic data; real-world accuracy not validated at scale.",
        "Notifications are limited to order-status updates only; no push or email notifications for non-auth flows.",
        "No mobile application; designed as a responsive web application.",
        "No automated test suite; testing is manual.",
    ]))

    # 5
    story.append(Paragraph("5. System Features and Functionalities", s["H1"]))
    story.append(hrule())
    story.append(Paragraph("5.1 User Account Management", s["H2"]))
    story.append(p(s, s["BodyJ"], "Registration with email OTP verification via Brevo, session-based login with HTTP-only cookies, password reset with OTP, email change with OTP verification, and profile management. Login lockout protection prevents brute-force attacks."))
    story.append(Paragraph("5.2 Product Catalog", s["H2"]))
    story.append(p(s, s["BodyJ"], "Eight predefined water products seeded on first deployment. Products include image, label, description, price, category, and unit. Category filter bar on the dashboard. Admin CRUD for product management with image upload."))
    story.append(Paragraph("5.3 Shopping Cart and Checkout", s["H2"]))
    story.append(p(s, s["BodyJ"], "Cart synchronized between localStorage and server database. Delivery address selection with province, city, barangay, and street fields. ML-based delivery fee calculation. Order summary with itemized costs."))
    story.append(Paragraph("5.4 Payment Processing", s["H2"]))
    story.append(p(s, s["BodyJ"], "Cash on Delivery (COD) is the active payment method. PayMongo digital payment is scaffolded but currently disabled."))
    story.append(Paragraph("5.5 Order Management", s["H2"]))
    story.append(p(s, s["BodyJ"], "Order status tracking (pending, confirmed, out for delivery, delivered, cancelled). Status history audit trail. Order details with items, delivery address, payment method, and delivery date range. Deep-link support for specific orders."))
    story.append(Paragraph("5.6 Notification System", s["H2"]))
    story.append(p(s, s["BodyJ"], "Order-status notifications with badge count. Notification state persisted in database (notif_seen_at, notif_cleared_at) to survive logout/login. Bell click marks notifications as seen. Notification item click navigates to appropriate orders page."))
    story.append(Paragraph("5.7 ML Delivery Prediction", s["H2"]))
    story.append(p(s, s["BodyJ"], "Random Forest or Linear Regression model predicting delivery time in minutes. Features: geographic coordinates, municipality, barangay, postal code, time of order, day of week, order size. PHP fallback when Python model unavailable. Delivery date range estimation (same-day, next-day, multi-day)."))
    story.append(Paragraph("5.8 Administrator Dashboard", s["H2"]))
    story.append(p(s, s["BodyJ"], "Sidebar-based admin interface with dashboard overview, user management, order management, and product management. Summary statistics (total users, orders, products, revenue). Charts for order and revenue trends."))
    story.append(Paragraph("5.9 Security Features", s["H2"]))
    story.append(bullets(s, [
        "Password hashing with PHP password_hash() (bcrypt).",
        "Session-based authentication with HTTP-only cookies.",
        "OTP verification for registration, password reset, and email changes.",
        "Input validation and sanitization on all API endpoints.",
        "Login lockout protection after repeated failed attempts.",
        "Role-based access control for admin functions.",
        "Dead session detection and automatic redirect to login.",
    ]))

    # 6
    story.append(Paragraph("6. Database Design", s["H1"]))
    story.append(hrule())
    story.append(p(s, s["BodyJ"], "Production uses PostgreSQL via DATABASE_URL (Railway plugin). Local development uses SQLite when DATABASE_URL is not set. api/database.php handles connectivity using pg_* functions for PostgreSQL and SQLite3 class for local testing."))
    story.append(fig(FIG / "diagram-erd.png", "Figure 1. Entity Relationship Diagram showing core tables and their relationships.", s, max_h=110 * mm))
    story.append(table(
        s,
        ["Table", "Role"],
        [
            ["users", "Account credentials, profile fields, cart state, delivery address, notification timestamps, admin flag, suspension fields"],
            ["otp_verification", "Pending registration records with OTP codes and expiry timestamps"],
            ["password_reset", "Password reset OTP records linked to user_id"],
            ["products", "Water product catalog with label, description, price, image, category, unit"],
            ["orders", "Order records with user_id, delivery details, total amount, status, payment method"],
            ["order_items", "Individual line items within an order (product name, price, quantity, subtotal)"],
            ["order_status_history", "Audit trail of order status changes with timestamps"],
            ["system_settings", "Key-value configuration storage for admin-managed settings"],
            ["email_change_otp", "OTP verification for email change requests"],
        ],
        [52 * mm, 122 * mm],
    ))
    story.append(p(s, s["BodyJ"], "A Railway volume is mounted at /data/uploads for persistent image storage. The uploads/ directory in the application root is symlinked to this volume on each request via database.php."))
    story.append(p(s, s["Note"], "The database schema shown in database_schema.sql uses MySQL syntax for ERD documentation purposes. The actual production deployment uses PostgreSQL with equivalent table structures created by database.php."))

    # 7
    story.append(Paragraph("7. Machine Learning Methodology", s["H1"]))
    story.append(hrule())
    story.append(fig(FIG / "diagram-ml-pipeline.png", "Figure 2. ML training and inference pipeline.", s, max_h=80 * mm))
    story.append(Paragraph("7.1 Problem definition", s["H2"]))
    story.append(p(s, s["BodyJ"], "The ML module performs regression to predict delivery time in minutes given geographic and order features. The predicted time is used to calculate shipping fees and estimate delivery date ranges."))
    story.append(Paragraph("7.2 Features", s["H2"]))
    story.append(table(
        s,
        ["Feature", "Type", "Description"],
        [
            ["distance_km", "Numerical", "Haversine distance from delivery hub (San Pablo City) to customer"],
            ["latitude, longitude", "Numerical", "Customer delivery coordinates"],
            ["municipality", "Categorical", "Municipality name (label-encoded)"],
            ["barangay", "Categorical", "Barangay name (label-encoded)"],
            ["postal_code", "Categorical", "Postal code (label-encoded)"],
            ["time_of_order", "Numerical", "Hour of order (0–23)"],
            ["day_of_week", "Numerical", "Day of week (0=Monday, 6=Sunday)"],
            ["order_size", "Numerical", "Number of water bottles"],
        ],
        [38 * mm, 32 * mm, 104 * mm],
    ))
    story.append(Paragraph("7.3 Training process", s["H2"]))
    story.append(p(s, s["BodyJ"], "Two models are trained and compared: Linear Regression and Random Forest Regressor. The model with higher R² on the test set is selected. Training uses synthetic data generated from San Pablo City geographic patterns. The pipeline includes label encoding of categorical features, 80/20 train/test split, and model export as joblib files."))
    story.append(Paragraph("7.4 Fallback behavior", s["H2"]))
    story.append(p(s, s["BodyJ"], "When the Python model is unavailable, a PHP fallback calculates delivery time as: time = 15 + (distance × 2.5) + (order_size × 0.5), with a minimum of 20 minutes. Shipping fee = 50 + (time × 0.5). This ensures checkout remains functional without the ML model."))
    story.append(Paragraph("7.5 Deployment workflow", s["H2"]))
    story.append(p(s, s["BodyJ"], "Training is performed offline: synthetic data generation (generate_synthetic_data.py), model training (train_model.py), model export (joblib). Production uses predict.py invoked via subprocess from PHP predict_delivery.php. The web application does not load the full training pipeline."))

    # 8
    story.append(Paragraph("8. API Integration and External Services", s["H1"]))
    story.append(hrule())
    story.append(Paragraph("8.1 Brevo Transactional Email", s["H2"]))
    story.append(p(s, s["BodyJ"], "Used for registration OTP, password reset OTP, and email change OTP delivery. API key and sender configured in Brevo dashboard and stored as environment variables."))
    story.append(Paragraph("8.2 PayMongo (Scaffolded)", s["H2"]))
    story.append(p(s, s["BodyJ"], "Payment gateway scaffolding exists with webhook handling and source creation endpoints. Currently disabled and displayed as \"Coming Soon\" on the payment page."))
    story.append(Paragraph("8.3 Chart.js", s["H2"]))
    story.append(p(s, s["BodyJ"], "Used for order and revenue charts in the admin dashboard."))
    story.append(Paragraph("8.4 Bootstrap and Font Awesome", s["H2"]))
    story.append(p(s, s["BodyJ"], "Bootstrap 5.3 provides responsive grid and components. Font Awesome 6.4 provides icons. Google Fonts (Inter) provides typography."))

    # 9
    story.append(Paragraph("9. Deployment Process", s["H1"]))
    story.append(hrule())
    story.append(Paragraph("9.1 Railway deployment", s["H2"]))
    story.append(p(s, s["BodyJ"], "GitHub repository connected to Railway. PHP built-in server via Dockerfile: php -S 0.0.0.0:$PORT -t . PostgreSQL plugin provides DATABASE_URL. Volume mounted at /data/uploads for persistent image storage."))
    story.append(Paragraph("9.2 Docker configuration", s["H2"]))
    story.append(p(s, s["BodyJ"], "Dockerfile based on php:8.2-cli with PostgreSQL extensions (pdo_pgsql, pgsql) and Python 3 for ML. Python virtual environment created during Docker build."))
    story.append(Paragraph("9.3 Environment variables", s["H2"]))
    story.append(table(
        s,
        ["Variable", "Purpose"],
        [
            ["DATABASE_URL", "PostgreSQL connection string (injected by Railway)"],
            ["BREVO_API_KEY", "Brevo email API key"],
            ["BREVO_SENDER_EMAIL", "Brevo verified sender address"],
            ["UPLOADS_DIR", "Persistent image directory (/data/uploads)"],
            ["PORT", "HTTP listen port (provided by Railway)"],
        ],
        [55 * mm, 119 * mm],
    ))

    # 10
    story.append(Paragraph("10. Technologies Used", s["H1"]))
    story.append(hrule())
    story.append(table(
        s,
        ["Category", "Technology / Service"],
        [
            ["Frontend", "HTML5, CSS3, JavaScript (ES6+), Bootstrap 5.3, Font Awesome 6.4, Chart.js, Google Fonts (Inter)"],
            ["Backend", "PHP 8.2 (built-in server locally, Docker in production)"],
            ["Database", "PostgreSQL on Railway (pg_* functions); SQLite locally (SQLite3 class)"],
            ["Machine Learning", "Python 3, scikit-learn (Random Forest, Linear Regression), pandas, numpy, joblib"],
            ["Email / OTP", "Brevo Transactional Email API"],
            ["Payment", "Cash on Delivery (active); PayMongo (scaffolded, disabled)"],
            ["Security", "PHP password_hash(), session cookies, OTP verification, input validation"],
            ["Deployment", "Railway (PHP + PostgreSQL + volume), Docker (php:8.2-cli)"],
            ["Version Control", "Git / GitHub"],
        ],
        [42 * mm, 132 * mm],
    ))

    # 11 pages
    story.append(Paragraph("11. User Interface and Page Documentation", s["H1"]))
    story.append(hrule())
    story.append(p(s, s["BodyJ"], "All pages follow the AquaSphere design system with the color palette: Deep Water (#155A7A), Aqua Blue (#2383B5), Water Accent (#5BC0EB), Light Aqua (#EAF7FC). Screenshots of live UI pages were not supplied with this documentation pass; placeholders mark where captures should be inserted."))

    pages = [
        ("11.1 Landing Page (index.html)",
         "Public. First page visitors see.",
         "Provide product overview, branding, and entry points to login/registration.",
         "AquaSphere branding, navbar with logo, Sign In button, Call to Action for products. Hero section with water theme.",
         "Navigate to login, registration, or browse products.",
         "No authentication required.",
         "Landing Page"),
        ("11.2 Registration (registration.html)",
         "Public. New users.",
         "Collect user details and create a pending registration with OTP verification.",
         "First name, last name, username, email, gender, birthday, password with strength requirements.",
         "Submit registration form; receive OTP via email.",
         "POST api/register; Brevo sends OTP.",
         "Registration Page"),
        ("11.3 OTP Verification (verify.html)",
         "Public users who just registered.",
         "Confirm the six-digit email OTP and activate the account.",
         "Six-digit input fields, resend option, target email display.",
         "Verify OTP code; resend code; navigate to login.",
         "POST api/verify_otp; POST api/resend_otp.",
         "OTP Verification Page"),
        ("11.4 Login (login.html)",
         "Public. Registered users.",
         "Authenticate and start a session.",
         "Username/email and password fields, Sign In button, links to Register and Forgot Password.",
         "Submit credentials; request password reset.",
         "POST api/login; POST api/forgot_password; POST api/verify_reset_otp; POST api/reset_password.",
         "Login Page"),
        ("11.5 Dashboard / Products (dashboard.html)",
         "Authenticated users.",
         "Browse water products and add to cart.",
         "Product grid with images, names, prices, Add to Cart buttons. Category filter bar. Navbar with cart/orders/notification badges.",
         "Add items to cart; filter by category; navigate to cart, orders, profile.",
         "GET api/get_products; GET api/get_current_user; GET api/user_state_get.",
         "Dashboard / Product Catalog"),
        ("11.6 Shopping Cart (cart.html)",
         "Authenticated users.",
         "Review and manage cart items before checkout.",
         "Cart items list with quantity controls. Order summary with subtotal, delivery fee, total. Delivery address form with location dropdowns.",
         "Adjust quantities; select items; fill delivery address; proceed to checkout.",
         "GET/POST api/user_state_get/save; POST api/predict_delivery.",
         "Shopping Cart"),
        ("11.7 Payment (payment.html)",
         "Authenticated users at checkout.",
         "Select payment method and confirm order.",
         "Payment method cards: Cash on Delivery (active), PayMongo (Coming Soon). Order summary. Place Order button.",
         "Select COD; confirm order placement.",
         "POST api/create_order.",
         "Payment Page"),
        ("11.8 Order History (orders.html)",
         "Authenticated users.",
         "View current and past orders.",
         "Order cards with ID, date, status badge, total amount, payment method. Active orders displayed prominently.",
         "View order details; click notification to see specific order.",
         "GET api/get_orders; GET api/get_notifications.",
         "Order History"),
        ("11.9 Recent Orders (recent_orders.html)",
         "Authenticated users.",
         "View delivered, completed, and cancelled orders.",
         "Order cards with status badges (green for delivered, red for cancelled). Cancelled On timestamp. Deep-link support.",
         "View past order details.",
         "GET api/get_orders (filtered by status).",
         "Recent Orders"),
        ("11.10 Profile (profile.html)",
         "Authenticated users.",
         "View and manage account information.",
         "User details (username, email, name, gender, DOB). Edit fields with green success styling. Password change with OTP. Email change with OTP modal.",
         "Update profile; change password; change email; logout.",
         "GET/POST api/get_current_user; POST api/update_profile; POST api/send_email_change_otp; POST api/verify_email_change_otp.",
         "User Profile"),
        ("11.11 Admin Dashboard (admin/dashboard.html)",
         "Authenticated admin user.",
         "Overview of system activity and business metrics.",
         "Sidebar with AquaSphere gradient. Summary cards (users, orders, products, revenue). Charts for order trends.",
         "Navigate to orders, users, products management.",
         "GET api/get_orders; GET api/get_products; GET api/get_current_user.",
         "Admin Dashboard"),
        ("11.12 Admin Orders (admin/orders.html)",
         "Authenticated admin user.",
         "View and manage all customer orders.",
         "Order table with filtering by status. Order details modal. Status update controls.",
         "Filter orders; view details; update status.",
         "GET api/get_orders; POST api/update_order_status.",
         "Admin Orders"),
        ("11.13 Admin Users (admin/users.html)",
         "Authenticated admin user.",
         "View all registered user accounts.",
         "User table with account details, registration date, order count. User detail modal.",
         "View user details; monitor account activity.",
         "GET api/get_users.",
         "Admin Users"),
        ("11.14 Admin Products (admin/products.html)",
         "Authenticated admin user.",
         "Manage the water product catalog.",
         "Product table with name, price, category, image. Add/Edit/Delete product modals with image upload.",
         "Add new product; edit product; delete product.",
         "GET/POST/PUT/DELETE api/admin/add_product, update_product, delete_product.",
         "Admin Products"),
    ]

    for title, access, purpose, ui, actions, backend, shot in pages:
        story.append(Paragraph(title, s["H2"]))
        story.append(phtml(s, s["BodyJ"], f"<b>Purpose.</b> {purpose}"))
        story.append(phtml(s, s["BodyJ"], f"<b>Access.</b> {access}"))
        story.append(phtml(s, s["BodyJ"], f"<b>Main UI.</b> {ui}"))
        story.append(phtml(s, s["BodyJ"], f"<b>Actions.</b> {actions}"))
        story.append(phtml(s, s["BodyJ"], f"<b>Server interaction.</b> {backend}"))
        story.append(placeholder(s, shot))

    # 12
    story.append(Paragraph("12. Conclusion and Recommendations", s["H1"]))
    story.append(hrule())
    story.append(Paragraph("12.1 Conclusion", s["H2"]))
    story.append(p(s, s["BodyJ"], "AquaSphere successfully demonstrates a web-based e-commerce system that applies PHP web development, PostgreSQL database integration, machine learning–based delivery time prediction, payment processing scaffolding, and online deployment. The system allows users to create secure accounts, browse and order water products, manage their shopping cart, place delivery orders, and track order status."))
    story.append(p(s, s["BodyJ"], "The project demonstrates practical application of machine learning in an e-commerce context through the delivery time prediction module, which uses geographic features to estimate delivery duration and shipping cost. The admin panel provides operational visibility for managing products, orders, and users."))
    story.append(Paragraph("12.2 Recommendations", s["H2"]))
    story.append(bullets(s, [
        "Activate the PayMongo digital payment integration to provide customers with digital payment options.",
        "Implement product search and filtering by name, product reviews, and promotional codes.",
        "Improve the ML model by training on real delivery data and deploying as a dedicated API service.",
        "Expand the notification system to include email notifications for order updates and push notifications for mobile.",
        "Develop automated tests for authentication, CRUD operations, and payment flows.",
        "Explore mobile application development for improved accessibility.",
    ]))

    # 13
    story.append(Paragraph("13. Project Links", s["H1"]))
    story.append(hrule())
    story.append(table(
        s,
        ["Resource", "URL"],
        [
            ["Live application", "https://aquasphere.up.railway.app/"],
            ["GitHub repository", "https://github.com/rorensdeyb/Aquasphere.git"],
        ],
        [58 * mm, 116 * mm],
    ))
    story.append(Spacer(1, 8 * mm))
    story.append(p(s, s["BodyL"], "This document describes AquaSphere as an integrated system: a Railway-hosted PHP and PostgreSQL web application, a Python scikit-learn delivery time prediction model, Brevo email for OTP flows, and Cash on Delivery payment processing. Features that are scaffolded but incomplete (PayMongo) are listed as such so the record stays accurate for repository readers and future maintainers."))

    os.makedirs(OUT_PDF.parent, exist_ok=True)
    doc = SimpleDocTemplate(
        str(OUT_PDF),
        pagesize=A4,
        leftMargin=18 * mm,
        rightMargin=18 * mm,
        topMargin=18 * mm,
        bottomMargin=18 * mm,
        title="AquaSphere Comprehensive System Documentation",
        author="Tolentino, Lawrence Dave P.",
    )
    doc.build(story, onFirstPage=header_footer, onLaterPages=header_footer)
    print("wrote", OUT_PDF, "pages~", doc.page)


if __name__ == "__main__":
    build()
