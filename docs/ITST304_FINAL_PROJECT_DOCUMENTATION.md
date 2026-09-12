# AQUASPHERE: A WEB-BASED WATER DELIVERY E-COMMERCE SYSTEM WITH ML-ASSISTED DELIVERY TIME PREDICTION

A Final Project presented to the  
Faculty of College of Computer Studies  
Laguna State Polytechnic University — San Pablo City Campus  

In Partial Fulfillment of the Requirements for the Degree  
**BACHELOR OF SCIENCE IN INFORMATION TECHNOLOGY**

Tolentino, Lawrence Dave P.

May 2026

---

## Project Overview

AquaSphere is a web-based e-commerce system designed to streamline the ordering and delivery of purified drinking water products. The platform allows users to browse available water products, manage a shopping cart, place delivery orders, and track order status in real time. An integrated machine learning model predicts delivery time and shipping cost based on the customer's location, providing a data-driven logistics experience.

The project was developed in response to the growing demand for online water delivery services in local communities. Many water refill stations still rely on phone orders or walk-in transactions, which can lead to scheduling conflicts, manual delivery estimation, and limited order visibility for customers. AquaSphere addresses these challenges by providing an online platform where customers can place orders, choose a payment method, and receive estimated delivery times powered by machine learning.

The system includes an administrator panel for managing products, viewing orders, and monitoring user accounts. The admin dashboard provides operational visibility over the business, including order status tracking and user management. The payment system currently supports Cash on Delivery (COD) as the primary method, with PayMongo digital payment integration scaffolded but temporarily disabled pending further configuration.

AquaSphere was developed as a second-year capstone project, and while core functionality is implemented and deployed, certain features remain in an early or incomplete state. The system is presented here in its current working form, with known limitations documented for transparency.

---

## Statement of the Problem

Water delivery services in local communities face operational challenges related to manual order processing, inconsistent delivery time estimates, and limited customer visibility into order status. Customers often place orders through phone calls or social media messages, which can result in miscommunication, forgotten orders, and difficulty tracking delivery progress.

Without a centralized digital platform, water delivery businesses must rely on manual coordination between customers and delivery personnel. This process is time-consuming and prone to errors, particularly when estimating delivery times and calculating shipping fees based on distance. Customers have no reliable way to know when their order will arrive, and delivery staff must estimate travel time manually for each order.

Students and consumers in the community also lack a convenient way to browse available water products, compare prices, and place orders outside of business hours. A web-based system can address these issues by providing an accessible ordering platform with real-time product availability, automated cart management, and data-driven delivery predictions that improve both customer experience and operational efficiency.

Although e-commerce platforms exist for various product categories, few are specifically designed for the local water delivery niche, where delivery time prediction and location-based logistics are critical. This shows the need for a dedicated system that combines product browsing, order management, and machine learning–assisted delivery estimation in one integrated platform.

---

## Objectives of the Project

### General Objective

The general objective of this project is to develop a web-based water delivery e-commerce system that allows customers to browse products, manage orders, and receive machine-learning–predicted delivery times and shipping fees based on their delivery location. The system aims to demonstrate practical application of web development, database integration, payment processing, and machine learning concepts in a real-world e-commerce context.

### Specific Objectives

The project specifically aims to:

1. Design and develop a responsive web-based application that allows users to create an account, verify their email through OTP, log in securely, and manage their profile.

2. Develop a product catalog module that displays available water products with images, descriptions, prices, and category filtering.

3. Implement a shopping cart and checkout system that supports item selection, quantity adjustment, delivery address management, and order placement.

4. Integrate a payment system that supports Cash on Delivery as the primary method and scaffolds PayMongo digital payment integration for future activation.

5. Develop an order management module that allows users to view order history, track order status, and receive notifications for order updates.

6. Implement a machine learning model that predicts delivery time and shipping cost based on the customer's geographic location, municipality, barangay, order size, and time of order.

7. Develop an administrator dashboard for managing products (CRUD), viewing orders, monitoring users, and tracking system activity.

8. Implement a notification system that alerts users to order status changes and provides persistent notification state across sessions.

9. Apply session-based authentication, password hashing, CSRF protection, and input validation to protect user accounts and data.

10. Deploy the system online using Railway with PostgreSQL for persistent storage, and maintain the source code in a GitHub repository.

11. Evaluate the system based on functionality, usability, database integration, and deployment accessibility.

---

## Project Scope and Limitation

This project focuses on the development of AquaSphere, a web-based water delivery e-commerce system. The system is designed for customers who want to order purified drinking water products online and receive delivery to their specified address. It also includes an administrator panel for managing products, orders, and users.

The features of the system include user account management with email OTP verification, product browsing with category filters, shopping cart management, checkout with delivery address selection, payment processing (Cash on Delivery active, PayMongo scaffolded), order history and status tracking, notifications, and an admin dashboard for business operations.

Product management features consist of product listing, product creation, product editing, and product deletion, all accessible through the admin panel. Products are seeded with predefined water items on first deployment, and administrators can modify or remove them as needed.

The system uses a machine learning model to predict delivery time and shipping fees based on the customer's delivery location. The prediction considers geographic coordinates, municipality, barangay, postal code, time of order, day of week, and order size. A PHP fallback calculation is used when the Python model is unavailable, ensuring the system remains functional.

AquaSphere provides an order tracking module that allows customers to view their order history and monitor the status of current and past orders. Notifications inform users when their order status changes, and notification state is persisted in the database to survive logout and re-login cycles.

However, the proposed system has several limitations. AquaSphere is a second-year capstone project developed within an academic timeframe, and several features are not fully implemented or are intentionally deferred.

The PayMongo digital payment integration is scaffolded in the codebase but is currently disabled and set to "Coming Soon." Cash on Delivery is the only active payment method. Activating PayMongo requires additional API configuration and testing that was not completed within the project timeline.

The system does not include features such as product reviews, wishlists, promotional codes, or loyalty programs. Product search is limited to category-based filtering and does not include full-text search or advanced product comparison tools.

The machine learning delivery prediction model is trained on synthetic data generated from geographic patterns in the San Pablo City area. While functional, the model's accuracy on real-world delivery data has not been validated with production-scale testing. The model is invoked via a Python subprocess call from PHP, which introduces latency compared to a dedicated API service.

The notification system currently supports order-status notifications only. Push notifications, email notifications for non-authentication flows, and real-time WebSocket updates are not implemented.

The system does not include mobile applications and is designed as a responsive web application accessible through browsers on desktop and mobile devices.

The project is designed to be completed within the given development period by focusing on the core features of product management, order processing, delivery prediction, and admin operations rather than enterprise-level features, formal usability studies, or large-scale performance benchmarking.

---

## 5. System Features and Functionalities

### 5.1 User Account Management

AquaSphere includes features that allow users to create an account, verify their email, log in or out, reset their password, and manage their profile information. The registration process collects basic profile details and validates password strength. A one-time password (OTP) is sent to the user's email via the Brevo transactional email API, and the account is activated only after successful OTP verification.

The system uses session-based authentication with HTTP-only cookies. Passwords are securely hashed using PHP's `password_hash()` function. Login lockout protection prevents brute-force attacks by temporarily locking accounts after repeated failed login attempts. Password reset also uses OTP-based verification to ensure only the account owner can set a new password.

Email change requests require OTP verification through a dedicated modal interface, ensuring that users cannot change their email address without confirming access to the new address.

Admin and user access is separated through role-based verification. Users with the configured admin email can access the administrative panel for system management.

### 5.2 Product Catalog and Management

The product catalog module displays available water products to customers. Each product includes a label, description, price, image, category, and unit of measurement. Products are categorized by type (e.g., "gallon", "bottle") and can be filtered by category on the dashboard.

On first deployment, the system seeds eight predefined water products with corresponding images. These seed products are created once and guarded against duplicate insertion. Administrators can edit, delete, or add new products through the admin panel.

The admin product management interface supports full CRUD operations: creating new products with image upload, editing existing product details, and deleting products. Image uploads are stored in a persistent uploads directory on the server, with automatic filename generation to prevent conflicts.

### 5.3 Shopping Cart and Checkout

The shopping cart allows authenticated users to add products, adjust quantities, and select items for checkout. Cart state is synchronized between localStorage and the server database, ensuring cart persistence across sessions and devices.

The checkout process collects delivery address information including province, city/municipality, barangay, street address, and optional landmark. The system supports location-based delivery fee calculation using a machine learning model that predicts delivery time based on the customer's geographic coordinates.

The delivery address can be saved to the user's profile for future orders. The checkout page displays an order summary with itemized costs, delivery fee, and total amount before order placement.

### 5.4 Payment Processing

The system currently supports Cash on Delivery (COD) as the primary payment method. When a user places an order with COD, the order is immediately created with a "pending" status and payment is collected upon delivery.

PayMongo digital payment integration is scaffolded in the codebase with webhook handling, source creation, and payment verification endpoints. However, this integration is currently disabled and displayed as "Coming Soon" on the payment page. Activating PayMongo requires configuring the PayMongo API keys and completing additional integration testing.

### 5.5 Order Management and Tracking

The order management module allows users to view their order history and track the status of current orders. Orders progress through statuses: pending, confirmed, out for delivery, delivered, and cancelled. Each status change is recorded in an order status history table for audit purposes.

Users can view detailed order information including items ordered, delivery address, payment method, total amount, and delivery date range. Delivered, completed, and cancelled orders are displayed in a dedicated "Recent Orders" section with deep-link support for direct access to specific orders.

The admin panel provides a comprehensive view of all orders across all users, with filtering and status update capabilities. Administrators can update order status and view order details for operational management.

### 5.6 Notification System

The notification system alerts users to order status changes. When an order status is updated (e.g., confirmed, out for delivery, delivered, cancelled), a notification is created for the associated user. The notification badge on the navbar displays the count of unseen notifications.

Notification state is persisted in the database with `notif_seen_at` and `notif_cleared_at` timestamps, ensuring that badge state survives logout and login cycles. When a user opens the notification bell, the system marks notifications as seen. Clicking a notification item navigates to the appropriate orders page based on the order status.

### 5.7 Machine Learning Delivery Prediction

AquaSphere includes a machine learning module that predicts delivery time and shipping fee based on the customer's delivery location. The model uses features including geographic coordinates (latitude/longitude), municipality, barangay, postal code, time of order, day of week, and order size.

The prediction is performed by a Python script (`ml/predict.py`) that is invoked from the PHP backend via subprocess. The model uses a trained Random Forest or Linear Regression estimator, with a Haversine distance calculation from the delivery hub in San Pablo City as a key feature.

When the Python model is unavailable, a PHP fallback calculation provides a basic delivery time estimate based on distance and order size. The predicted delivery time is used to calculate shipping fees and estimate delivery date ranges (same-day, next-day, or multi-day delivery).

### 5.8 Administrator Dashboard

The admin dashboard provides a sidebar-based interface with sections for dashboard overview, user management, order management, and product management. The dashboard displays summary statistics including total users, total orders, total products, and total revenue.

User management allows administrators to view all registered users with their account details and order history. Order management provides a comprehensive view of all orders with filtering and status update capabilities. Product management supports full CRUD operations for the product catalog.

### 5.9 Security Features

AquaSphere includes several security measures for protecting user accounts and data:

- Password hashing using PHP's `password_hash()` with bcrypt
- Session-based authentication with HTTP-only cookies
- OTP verification for registration, password reset, and email changes
- Input validation and sanitization on all API endpoints
- CSRF protection on state-changing requests
- Login lockout protection after repeated failed attempts
- Role-based access control for admin functions
- Dead session detection and automatic redirect to login

---

## 6. Database Design

AquaSphere uses a dual-database approach depending on the deployment environment. The system uses PostgreSQL in production through the `DATABASE_URL` environment variable on Railway. For local development, the system falls back to SQLite when PostgreSQL is not configured. Database connectivity is handled in `api/database.php` using `pg_*` functions for PostgreSQL and the built-in `SQLite3` class for local testing.

### 6.1 PostgreSQL Production Mode

When PostgreSQL is enabled, the system stores records in relational tables connected through foreign keys. The identified core tables are:

- users
- otp_verification
- password_reset
- products
- orders
- order_items
- order_status_history
- system_settings
- email_change_otp

The `users` table stores account credentials, profile fields, cart state, delivery address, and notification timestamps. The `products` table stores the water product catalog. The `orders` table stores order records with delivery and payment information, connected to `order_items` for individual line items. The `order_status_history` table provides an audit trail of status changes.

### 6.2 SQLite Development Mode

When PostgreSQL is not configured, SQLite is used for local development and testing. The same table structure is created through `api/database.php`, allowing developers to run the application locally without requiring a separate database server. This mode is useful for development and debugging but is not used as the primary production storage strategy.

### 6.3 Entity Relationship

The following relationships exist between tables:

- A user can have many orders (one-to-many: users → orders)
- An order can have many order items (one-to-many: orders → order_items)
- An order can have many status history records (one-to-many: orders → order_status_history)
- A user can have many OTP verification records (one-to-many: users → otp_verification)
- A user can have many password reset records (one-to-many: users → password_reset)
- System settings are stored as key-value pairs (single table)

### 6.4 Data Protection in the Database

Sensitive account data such as password hashes are stored using secure hashing rather than plain text. Cart state, delivery addresses, and notification timestamps are stored as JSON or text fields on the user record. Order and product data are stored in relational tables with foreign key constraints to ensure referential integrity.

The application uses parameterized SQL queries to reduce the risk of SQL injection. Session-based authentication limits access to user-owned data, and admin-only routes are restricted to configured administrator accounts.

### 6.5 Sample Database Queries

```sql
SELECT id, username, email, created_at FROM users ORDER BY id DESC LIMIT 10;

SELECT id, user_id, status, total_amount, payment_method, created_at
FROM orders ORDER BY id DESC LIMIT 5;

SELECT label, price, category FROM products ORDER BY category, label;

SELECT o.id, u.username, o.status, o.total_amount, o.created_at
FROM orders o
JOIN users u ON o.user_id = u.id
ORDER BY o.id DESC LIMIT 10;
```

---

## 7. Machine Learning Methodology

AquaSphere uses a regression-based machine learning model to predict delivery time and shipping fees based on geographic and order-related features. The production web application invokes the trained model through a Python subprocess call from the PHP backend.

### 7.1 Problem Definition and Model Output

The ML module performs regression to predict delivery time in minutes given a set of input features. The predicted delivery time is then used to calculate:

- Shipping fee (base fee + rate per minute × predicted time)
- Delivery date range (same-day, next-day, or multi-day based on predicted hours)

The model outputs a single continuous value representing estimated delivery time in minutes.

### 7.2 Feature Preparation

The model uses the following features:

| Feature | Type | Description |
|---------|------|-------------|
| distance_km | Numerical | Haversine distance from delivery hub to customer |
| latitude | Numerical | Customer delivery latitude |
| longitude | Numerical | Customer delivery longitude |
| municipality | Categorical | Municipality name (label-encoded) |
| barangay | Categorical | Barangay name (label-encoded) |
| postal_code | Categorical | Postal code (label-encoded) |
| time_of_order | Numerical | Hour of order (0-23) |
| day_of_week | Numerical | Day of week (0=Monday, 6=Sunday) |
| order_size | Numerical | Number of water bottles |

The Haversine distance is calculated from the delivery hub located at San Pablo City (latitude 14.0703, longitude 121.3253). Categorical features (municipality, barangay, postal_code) are encoded using LabelEncoder fitted on the training data.

### 7.3 Training Process

Two models are trained and compared: Linear Regression and Random Forest Regressor. The model with the higher R² score on the test set is selected for production use. Training is performed offline using a synthetic dataset generated from geographic patterns in the San Pablo City area.

The training pipeline includes:
1. Loading the synthetic dataset
2. Encoding categorical features with LabelEncoder
3. Splitting data into training (80%) and test (20%) sets
4. Training both Linear Regression and Random Forest models
5. Evaluating models using MAE, RMSE, and R² metrics
6. Selecting the best model based on R² score
7. Saving the model, label encoders, and metadata to disk

### 7.4 Fallback Behavior

If the Python model is unavailable or fails to execute, the PHP backend uses a simplified distance-based calculation:

```
delivery_time = 15 + (distance_km × 2.5) + (order_size × 0.5)
delivery_time = max(20, delivery_time)
shipping_fee = 50 + (delivery_time × 0.5)
```

This fallback ensures the checkout process remains functional even when the ML model is not accessible, though with reduced accuracy compared to the trained model.

### 7.5 Training and Deployment Workflow

The model training workflow is performed offline:

1. **Data Generation:** Synthetic delivery data is generated using `ml/generate_synthetic_data.py`, which creates realistic delivery scenarios based on San Pablo City geography.
2. **Model Training:** `ml/train_model.py` trains both Linear Regression and Random Forest models on the synthetic data.
3. **Model Export:** The trained model and encoders are saved as joblib files in `ml/models/`.
4. **Production Use:** The PHP backend (`api/predict_delivery.php`) invokes `ml/predict.py` via subprocess to get delivery time predictions.

The production web application does not load the full training pipeline. Instead, it calls the pre-trained model through a subprocess, keeping the PHP process lightweight.

---

## 8. API Integration and External Services

AquaSphere uses several external libraries and services that support its main functions in payment processing, communication, visualization, and deployment.

### 8.1 Brevo Transactional Email API

The system uses Brevo transactional email services for different email-based functions. Based on the application implementation, these include:

- Registration verification OTP emails
- Password reset OTP emails
- Email change verification OTP emails

This integration supports account security and verification workflows required for responsible user onboarding.

### 8.2 PayMongo Payment Gateway (Scaffolded)

The system includes scaffolding for PayMongo digital payment integration. Endpoints for creating payment sources, handling webhooks, and verifying payments exist in the codebase. However, this integration is currently disabled and displayed as "Coming Soon" on the payment page. Cash on Delivery is the active payment method.

### 8.3 Chart.js for Data Visualization

Chart.js is used to display graphical outputs in the admin dashboard. Order and revenue charts are prepared by the backend and rendered in the browser to help administrators understand business metrics visually.

### 8.4 Bootstrap and Font Awesome

Bootstrap 5 provides the responsive grid system, component library, and styling framework for both the user-facing pages and the admin panel. Font Awesome provides the icon library used throughout the interface.

### 8.5 PHP and Python Integration

The backend uses PHP 8.2 for API routing, database connectivity, session management, and email sending. Python is used for the machine learning delivery prediction module, invoked via subprocess from PHP. This separation keeps the PHP web service lightweight while leveraging Python's machine learning ecosystem.

### 8.6 Railway Hosting and PostgreSQL

The system is deployed on Railway, which provides managed hosting with PostgreSQL database, HTTPS, environment variable management, and persistent volume storage for uploaded files.

---

## 9. Deployment Process

### 9.1 Railway Deployment

The system is deployed online using Railway as the primary managed hosting environment. The GitHub repository is connected to a Railway web service, and the application is started using the PHP built-in server via the Dockerfile: `php -S 0.0.0.0:$PORT -t .`

Important production environment variables include:

- `DATABASE_URL` — PostgreSQL connection string (injected by Railway PostgreSQL plugin)
- `BREVO_API_KEY` — Brevo email API key
- `BREVO_SENDER_EMAIL` — Brevo verified sender address
- `UPLOADS_DIR` — Persistent image directory (mapped to Railway volume)

A volume is mounted at `/data/uploads` so that product images and uploaded files survive redeploys. The `uploads/` directory in the application root is symlinked to this volume on each request.

### 9.2 Docker Configuration

The application uses a Dockerfile based on `php:8.2-cli` with PostgreSQL extensions (`pdo_pgsql`, `pgsql`) and Python 3 installed for the ML module. The Python virtual environment is created during the Docker build process, and dependencies from `requirements.txt` are installed.

### 9.3 Local Development

For local development, the system uses PHP's built-in development server on port 8080. When PostgreSQL is not configured, SQLite is used as the local database. Product images are copied from the `products/` directory to the uploads folder on first run.

### 9.4 Version Control

The source code is maintained in a GitHub repository. All changes are committed and pushed to the `main` branch. The repository includes the complete application source, documentation, ML training scripts, and product images.

---

## 10. Technologies Used

### Backend and Web Development

- PHP 8.2
- PHP built-in development server (local) and Docker (production)
- JSON API endpoints

### Database and Storage

- PostgreSQL on Railway (production)
- SQLite (local development fallback)
- pg_* functions (PostgreSQL) and SQLite3 class (SQLite)

### Machine Learning

- Python 3
- scikit-learn (RandomForestRegressor, LinearRegression, LabelEncoder)
- pandas, numpy
- joblib (model serialization)
- Haversine distance calculation

### Frontend and Visualization

- HTML5, CSS3, JavaScript (ES6+)
- Bootstrap 5.3
- Font Awesome 6.4
- Chart.js
- Google Fonts (Inter)

### Email and External APIs

- Brevo Transactional Email API
- PayMongo (scaffolded, not active)

### Authentication and Security

- PHP session management
- password_hash() / password_verify() for password hashing
- OTP verification for registration, password reset, email change
- Input validation and sanitization
- Session-based role verification

### Deployment and Version Control

- Git / GitHub
- Railway (PHP + PostgreSQL + volume)
- Docker (php:8.2-cli with PostgreSQL and Python extensions)

---

## 11. User Interface and Page Documentation

### Figure 1. Landing Page (index.html)

**[Insert Image 1 here]**

Figure 1 shows the Landing Page of AquaSphere, which serves as the public entry point for the system. The page displays the AquaSphere branding with the water delivery theme and provides navigation options including a Sign In button and a Call to Action button for browsing products. The navbar shows the system logo and navigation links.

This page is the first impression for new visitors and provides access to the product catalog, user login, and registration. It sets the visual tone with the AquaSphere color palette (deep water blue and aqua blue accents).

---

### Figure 2. Registration Page (registration.html)

**[Insert Image 2 here]**

Figure 2 shows the Registration Page of AquaSphere, where new users can create an account by entering their personal information. The form collects details such as first name, last name, username, email address, gender, birthday, and password. Password strength requirements are displayed to guide the user in creating a secure credential.

This page serves as the entry point for new users who want to begin using the ordering system. After the user completes the form, the system sends a verification OTP to the registered email address before the account is fully activated.

---

### Figure 3. OTP Verification Page (verify.html)

**[Insert Image 3 here]**

Figure 3 shows the Account Verification page of AquaSphere, where the user is asked to enter the six-digit one-time password sent to the email address used during registration. The page displays the target email and includes options to resend the code.

This page serves as the final step of account activation. The system compares the entered OTP with the value stored in the `otp_verification` table and creates the full user record in the `users` table only when the code is valid and not expired.

---

### Figure 4. Login Page (login.html)

**[Insert Image 4 here]**

Figure 4 shows the Login Page of AquaSphere, where registered users can access their accounts by entering their username or email address and password. The page displays the AquaSphere branding and provides navigation options for users who do not yet have an account or need to reset their password.

This page serves as the system's main authentication interface, ensuring that only verified users can access the product catalog, shopping cart, orders, and profile features. When login is successful, the system establishes a session and redirects the user to the dashboard.

---

### Figure 5. Dashboard / Product Catalog (dashboard.html)

**[Insert Image 5 here]**

Figure 5 shows the Dashboard of AquaSphere, which serves as the main product browsing interface for authenticated users. The page displays a grid of water products with images, names, prices, and "Add to Cart" buttons. A category filter bar allows users to filter products by type (e.g., "gallon", "bottle").

This page is the user's home screen after login. It helps users browse available products, view pricing, and add items to their shopping cart. The product cards use a consistent layout with the AquaSphere design system colors.

---

### Figure 6. Shopping Cart (cart.html)

**[Insert Image 6 here]**

Figure 6 shows the Shopping Cart page of AquaSphere, where users can review and manage their selected items. The page displays a list of cart items with product images, names, prices, and quantity adjustment controls. A summary section shows subtotal, delivery fee, and total amount.

The cart includes delivery address selection with province, city/municipality, barangay, and street address fields. The system calculates delivery fees based on the selected location using the ML prediction model. Users can proceed to checkout or continue shopping.

---

### Figure 7. Payment Page (payment.html)

**[Insert Image 7 here]**

Figure 7 shows the Payment Page of AquaSphere, where users select their preferred payment method before confirming their order. The page displays available payment options including Cash on Delivery (active) and PayMongo digital payment (shown as "Coming Soon").

The page also shows an order summary with itemized costs and the total amount. When the user confirms the order with Cash on Delivery, the system creates the order and redirects to the order confirmation page.

---

### Figure 8. Order History (orders.html)

**[Insert Image 8 here]**

Figure 8 shows the Order History page of AquaSphere, where users can view their current and past orders. Each order is displayed as a card showing the order ID, date, status badge, total amount, and payment method. Active orders (pending, confirmed, out for delivery) are displayed prominently.

The notification badge on the navbar reflects unseen order updates. Clicking on an order card navigates to detailed order information.

---

### Figure 9. Recent Orders (recent_orders.html)

**[Insert Image 9 here]**

Figure 9 shows the Recent Orders page of AquaSphere, which displays delivered, completed, and cancelled orders. Each order card includes the order status with a color-coded badge (green for delivered/completed, red for cancelled) and a "Cancelled On" timestamp for cancelled orders.

This page provides users with a history of their completed transactions and serves as an archive for past orders. Deep-link support allows direct access to specific orders via URL parameters.

---

### Figure 10. User Profile (profile.html)

**[Insert Image 10 here]**

Figure 10 shows the Profile page of AquaSphere, where users can view and manage their account information. The page displays user details including username, email, first name, last name, gender, and date of birth. Users can update their profile information, change their password (with OTP verification), and change their email address (with OTP verification).

The profile page also shows the user's order statistics and provides access to account settings. Green success styling highlights fields that have been modified during the current session.

---

### Figure 11. Admin Dashboard (admin/dashboard.html)

**[Insert Image 11 here]**

Figure 11 shows the Admin Dashboard of AquaSphere, which provides administrators with an overview of system activity. The page displays summary statistics including total users, total orders, total products, and total revenue. Charts show order trends and revenue data.

The admin interface includes a sidebar navigation with links to dashboard, orders, users, and products management. The sidebar uses the AquaSphere gradient color scheme.

---

### Figure 12. Admin Order Management (admin/orders.html)

**[Insert Image 12 here]**

Figure 12 shows the Admin Order Management page, where administrators can view and manage all orders across all users. The page displays a table of orders with filtering options by status. Administrators can view order details and update order status.

This module supports the operational management of the delivery business by providing visibility into all customer orders and their current status.

---

### Figure 13. Admin User Management (admin/users.html)

**[Insert Image 13 here]**

Figure 13 shows the Admin User Management page, where administrators can view all registered users. The page displays a table of users with their account details, registration date, and order count. Administrators can view user details and monitor account activity.

This module provides the administrator with visibility into the user base and supports user-related operational decisions.

---

### Figure 14. Admin Product Management (admin/products.html)

**[Insert Image 14 here]**

Figure 14 shows the Admin Product Management page, where administrators can manage the water product catalog. The page displays a table of products with their name, price, category, and image. Administrators can add new products, edit existing products, and delete products.

This module provides full CRUD operations for the product catalog, allowing the administrator to maintain an up-to-date product listing.

---

## 12. Conclusion and Recommendations

### Conclusion

AquaSphere successfully demonstrates a web-based e-commerce system that applies PHP web development, PostgreSQL database integration, machine learning–based delivery time prediction, payment processing scaffolding, and online deployment. The system allows users to create secure accounts, browse and order water products, manage their shopping cart, place delivery orders, and track order status.

The project also demonstrates practical application of machine learning in an e-commerce context through the delivery time prediction module, which uses geographic features to estimate delivery duration and shipping cost. The admin panel provides operational visibility for managing products, orders, and users.

Overall, AquaSphere fulfills the intended outcomes of the course by combining web development, database design, machine learning integration, and deployment into one working platform focused on water delivery e-commerce.

### Recommendations

For future improvement, the team recommends activating the PayMongo digital payment integration to provide customers with digital payment options beyond Cash on Delivery. This would require completing the API configuration and testing the full payment flow.

Additional recommendations include implementing product search and filtering by name, adding product reviews and ratings, implementing a promotional code system, and expanding the notification system to include email notifications for order updates.

The machine learning delivery prediction model could be improved by training on real delivery data rather than synthetic data, and by deploying the model as a dedicated API service rather than a subprocess call.

The system could benefit from implementing push notifications for mobile users, adding a real-time chat feature for customer-delivery communication, and developing a mobile application for improved accessibility.

Future versions may also explore automated testing, performance optimization, accessibility compliance, and integration with third-party logistics providers.

---

## 13. Project Links

| Resource | URL |
|----------|-----|
| Live application | https://aquasphere.up.railway.app/ |
| GitHub repository | https://github.com/rorensdeyb/Aquasphere.git |

---

*Created to help users order water products conveniently and to demonstrate practical application of web development, machine learning, and e-commerce concepts.*
