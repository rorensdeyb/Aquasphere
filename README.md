# AquaSphere

**AquaSphere** is a web-based water delivery e-commerce system that allows customers to browse purified water products, manage shopping carts, place delivery orders, and receive machine-learning–predicted delivery times. An administrator panel supports product, order, and user management.

**Author:** Tolentino, Lawrence Dave P.

> **Project status:** This is a second-year capstone project (ITST 304). Core features are implemented and deployed. PayMongo digital payment is scaffolded but not active. Known limitations are documented in the system documentation.

---

## Key Features

- **Product catalog:** Browse predefined water products with images, prices, and category filters. Admin CRUD for product management with image upload.
- **Shopping cart:** Add/remove items, adjust quantities, select items for checkout. Cart synchronized between localStorage and server database.
- **Checkout and payment:** Delivery address selection with ML-based shipping fee calculation. Cash on Delivery (COD) active; PayMongo scaffolded ("Coming Soon").
- **Order management:** Order status tracking (pending → confirmed → out for delivery → delivered/cancelled). Status history audit trail. Deep-link support.
- **Notifications:** Order-status notifications with badge count. State persisted in database across logout/login cycles.
- **ML delivery prediction:** Random Forest / Linear Regression model predicting delivery time and shipping fee based on geographic coordinates, municipality, barangay, order size, and time of day.
- **Admin dashboard:** Sidebar-based interface for managing products, orders, and users. Summary statistics and order/revenue charts.
- **Secure authentication:** Registration with email OTP (Brevo), session-based login, password reset with OTP, email change with OTP, login lockout protection.

---

## Tech Stack

- **Frontend:** HTML5, CSS3, JavaScript (ES6+), Bootstrap 5.3, Font Awesome 6.4, Chart.js, Google Fonts (Inter)
- **Backend:** PHP 8.2 (built-in server locally, Docker on Railway)
- **Database:** PostgreSQL on Railway (production); SQLite locally when `DATABASE_URL` is unset
- **Machine Learning:** Python 3, scikit-learn (RandomForestRegressor, LinearRegression), pandas, numpy, joblib
- **Email / OTP:** Brevo Transactional Email API
- **Payment:** Cash on Delivery (active); PayMongo (scaffolded)
- **Deployment:** [Railway](https://aquasphere.up.railway.app/) (PHP + PostgreSQL + volume)
- **Version control:** [GitHub](https://github.com/rorensdeyb/Aquasphere.git)

---

## How It Works

1. **Browse and add to cart:** The user browses the product dashboard, filters by category, and adds water products to the cart.
2. **Checkout:** The user selects items, fills in a delivery address, and proceeds to payment. The ML model predicts delivery time and calculates shipping fee based on location.
3. **Place order:** With COD selected, the order is created with "pending" status. The user is redirected to the order confirmation page.
4. **Track order:** The user can view order status in the Orders page. Notifications appear when the status changes (confirmed, out for delivery, delivered, cancelled).
5. **Admin manages:** The administrator can update order status, manage products (add/edit/delete), and view all users and orders.

### Why ML for delivery prediction?

Local water delivery businesses typically estimate delivery time manually based on distance and experience. AquaSphere automates this by training a regression model on synthetic delivery data from the San Pablo City area, then using it at checkout to provide data-driven delivery estimates and shipping fees.

The workflow is:

- **Generate** synthetic delivery data (`ml/generate_synthetic_data.py`)
- **Train** Random Forest and Linear Regression models (`ml/train_model.py`)
- **Export** model artifacts as joblib files (`ml/models/`)
- **Predict** at checkout via `ml/predict.py` invoked from PHP

---

## Installation Instructions

### Prerequisites

- PHP 8.2+
- Python 3.8+
- Git

### 1. Clone the repository

```bash
git clone https://github.com/rorensdeyb/Aquasphere.git
cd Aquasphere
```

### 2. Set up PHP

Ensure PHP 8.2+ is installed with the SQLite extension (usually included by default).

### 3. Set up Python (for ML delivery prediction)

```bash
python -m venv .venv
# Windows
.\.venv\Scripts\Activate.ps1
# Linux/macOS
source .venv/bin/activate

pip install -r requirements.txt
```

### 4. Train the ML model (optional, pre-trained models may be included)

```bash
cd ml
python generate_synthetic_data.py
python train_model.py
```

This creates `ml/models/delivery_time_model.joblib` and related files.

### 5. Start the development server

```bash
php -S localhost:8080
```

Open **http://localhost:8080** in your browser. The database auto-initializes on first use (SQLite locally, PostgreSQL when `DATABASE_URL` is set).

### Production deploy (Railway)

1. Connect [https://github.com/rorensdeyb/Aquasphere.git](https://github.com/rorensdeyb/Aquasphere.git) to a Railway project.
2. Add the PostgreSQL plugin (`DATABASE_URL` is injected).
3. Mount a volume at `/data/uploads` and set `UPLOADS_DIR=/data/uploads`.
4. Set environment variables: `BREVO_API_KEY`, `BREVO_SENDER_EMAIL`.
5. Railway builds from the Dockerfile: `php -S 0.0.0.0:$PORT -t .`

Live app: **https://aquasphere.up.railway.app/**

---

## Environment Variables

| Variable | Purpose |
|----------|---------|
| `DATABASE_URL` | PostgreSQL connection string (injected by Railway) |
| `BREVO_API_KEY` | Brevo email API key for OTP delivery |
| `BREVO_SENDER_EMAIL` | Brevo verified sender email address |
| `UPLOADS_DIR` | Persistent image directory (set to `/data/uploads` on Railway) |
| `PORT` | HTTP listen port (provided by Railway) |

---

## Project Structure

| Path | Description |
|------|-------------|
| `api/` | PHP backend API endpoints (auth, products, orders, payments, etc.) |
| `api/database.php` | Database schema, connectivity, and seed function |
| `api/email_service.php` | Brevo email senders for OTP flows |
| `api/predict_delivery.php` | ML delivery prediction endpoint (calls Python) |
| `admin/` | Admin panel pages (dashboard, orders, users, products) |
| `js/` | Client-side JavaScript modules (user_state.js) |
| `navbar.js`, `navbar.html`, `navbar.css` | Shared navbar logic and styling |
| `ml/` | Machine learning training and prediction scripts |
| `ml/train_model.py` | Model training (Random Forest, Linear Regression) |
| `ml/predict.py` | Production prediction script (invoked by PHP) |
| `products/` | Bundled product images (copied to uploads on seed) |
| `docs/` | System documentation source and PDF generator |
| `dashboard.html` | User dashboard / product catalog |
| `cart.html` | Shopping cart page |
| `payment.html` | Payment method selection page |
| `orders.html` | Order history page |
| `recent_orders.html` | Delivered/completed/cancelled orders |
| `profile.html` | User profile management |
| `login.html`, `registration.html`, `verify.html` | Auth pages |
| `Dockerfile` | Docker configuration for Railway deployment |

---

## Author

Tolentino, Lawrence Dave P.

---

## Project Links

- **Live Deployment (Railway):** https://aquasphere.up.railway.app/
- **GitHub Repository:** https://github.com/rorensdeyb/Aquasphere.git
- **System Documentation (PDF):** `AquaSphere_System_Documentation.pdf`

---

*Created to help users order water products conveniently and to demonstrate practical application of web development, machine learning, and e-commerce concepts.*
