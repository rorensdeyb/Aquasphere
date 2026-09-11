<?php
/**
 * Database Module for AquaSphere
 * Supports both PostgreSQL (for hosting) and SQLite (for local development)
 */

// Detect if PostgreSQL is available
$GLOBALS['use_postgres'] = !empty($_ENV['DATABASE_URL']) || 
               (!empty($_ENV['PGHOST']) && !empty($_ENV['PGDATABASE']) && 
                !empty($_ENV['PGUSER']) && !empty($_ENV['PGPASSWORD']));

// If running on Railway (or other deployed env) and Postgres is not configured, fail fast to avoid ephemeral SQLite
$is_prod_env = !empty($_ENV['RAILWAY_ENVIRONMENT']) || !empty($_ENV['RAILWAY_STATIC_URL']) || !empty($_ENV['RAILWAY_PROJECT_ID']);
if ($is_prod_env && !$GLOBALS['use_postgres']) {
    http_response_code(500);
    die("Database configuration error: PostgreSQL is required in production. Please set DATABASE_URL (or PGHOST/PGDATABASE/PGUSER/PGPASSWORD).");
}

/**
 * Get configured admin email(s) from environment variables.
 * Supports AQUASPHERE_ADMIN_EMAIL or ADMIN_EMAIL.
 */
if (!function_exists('getConfiguredAdminEmail')) {
    function getConfiguredAdminEmail() {
        $email = getenv('AQUASPHERE_ADMIN_EMAIL') ?: getenv('ADMIN_EMAIL');
        if (!$email && isset($_ENV['AQUASPHERE_ADMIN_EMAIL'])) $email = $_ENV['AQUASPHERE_ADMIN_EMAIL'];
        if (!$email && isset($_ENV['ADMIN_EMAIL'])) $email = $_ENV['ADMIN_EMAIL'];
        if (!$email && isset($_SERVER['AQUASPHERE_ADMIN_EMAIL'])) $email = $_SERVER['AQUASPHERE_ADMIN_EMAIL'];
        if (!$email && isset($_SERVER['ADMIN_EMAIL'])) $email = $_SERVER['ADMIN_EMAIL'];
        return strtolower(trim((string)$email));
    }
}

/**
 * Check if the given email matches the configured admin email.
 * Supports comma-separated list of emails and is case-insensitive.
 */
if (!function_exists('isConfiguredAdminEmail')) {
    function isConfiguredAdminEmail($email) {
        $adminEmailStr = getConfiguredAdminEmail();
        if (!$adminEmailStr || !$email) {
            return false;
        }
        $target = strtolower(trim((string)$email));
        $configured = array_filter(array_map('trim', explode(',', strtolower($adminEmailStr))));
        return in_array($target, $configured, true);
    }
}

$GLOBALS['db_path'] = $_ENV['DATABASE_PATH'] ?? 'aquasphere.db';

if ($GLOBALS['use_postgres']) {
    // PostgreSQL connection
    function get_db_connection() {
        static $connections = [];
        $conn_key = $_ENV['DATABASE_URL'] ?? ($_ENV['PGHOST'] . $_ENV['PGDATABASE']);
        
        // Always create new connection to avoid stale data
        if (!empty($_ENV['DATABASE_URL'])) {
            $conn = pg_connect($_ENV['DATABASE_URL'], PGSQL_CONNECT_FORCE_NEW);
        } else {
            $conn = pg_connect(
                "host=" . $_ENV['PGHOST'] . 
                " port=" . ($_ENV['PGPORT'] ?? 5432) . 
                " dbname=" . $_ENV['PGDATABASE'] . 
                " user=" . $_ENV['PGUSER'] . 
                " password=" . $_ENV['PGPASSWORD'],
                PGSQL_CONNECT_FORCE_NEW
            );
        }
        if (!$conn) {
            die("PostgreSQL connection failed");
        }
        return $conn;
    }
    
    function execute_sql($conn, $query, $params = null) {
        if ($params) {
            // Replace ? with $1, $2, etc. for PostgreSQL
            $param_count = 1;
            $pg_query = preg_replace_callback('/\?/', function() use (&$param_count) {
                return '$' . $param_count++;
            }, $query);
            $result = pg_query_params($conn, $pg_query, $params);
            if ($result === false) {
                $error = pg_last_error($conn);
                error_log("PostgreSQL query error: $error. Query: $pg_query");
            }
            return $result;
        } else {
            $result = pg_query($conn, $query);
            if ($result === false) {
                $error = pg_last_error($conn);
                error_log("PostgreSQL query error: $error. Query: $query");
            }
            return $result;
        }
    }
    
    function get_id_type() {
        return "SERIAL PRIMARY KEY";
    }
    
    function get_text_type() {
        return "VARCHAR(255)";
    }
    
    function get_integer_type() {
        return "INTEGER";
    }
    
    function fetch_assoc($result) {
        return pg_fetch_assoc($result);
    }
    
    function last_insert_id($conn, $table) {
        $result = pg_query($conn, "SELECT lastval()");
        $row = pg_fetch_row($result);
        return $row[0];
    }
    
    function close_connection($conn) {
        pg_close($conn);
    }
} else {
    // SQLite connection
    function get_db_connection() {
        global $db_path;
        $conn = new SQLite3($GLOBALS['db_path']);
        if (!$conn) {
            die("SQLite connection failed: " . $conn->lastErrorMsg());
        }
        return $conn;
    }
    
    function execute_sql($conn, $query, $params = null) {
        $stmt = $conn->prepare($query);
        if ($params) {
            foreach ($params as $index => $param) {
                $stmt->bindValue($index + 1, $param);
            }
        }
        return $stmt->execute();
    }
    
    function get_id_type() {
        return "INTEGER PRIMARY KEY AUTOINCREMENT";
    }
    
    function get_text_type() {
        return "TEXT";
    }
    
    function get_integer_type() {
        return "INTEGER";
    }
    
    function fetch_assoc($result) {
        return $result->fetchArray(SQLITE3_ASSOC);
    }
    
    function last_insert_id($conn, $table) {
        return $conn->lastInsertRowID();
    }
    
    function close_connection($conn) {
        $conn->close();
    }
}

/**
 * Initialize the database with necessary tables
 */
function init_db() {
    $conn = get_db_connection();
    
    // Create users table
    $query = "
    CREATE TABLE IF NOT EXISTS users (
        id " . get_id_type() . ",
        username " . get_text_type() . " UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        email " . get_text_type() . " UNIQUE NOT NULL,
        first_name " . get_text_type() . ",
        last_name " . get_text_type() . ",
        gender " . get_text_type() . ",
        date_of_birth DATE,
        is_admin " . get_integer_type() . " DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP
    )
    ";
    
    $result = execute_sql($conn, $query);
    if ($result === false) {
        error_log("Failed to create users table: " . ($GLOBALS['use_postgres'] ? pg_last_error($conn) : "SQLite error"));
    }
    
    // Ensure persistence columns exist for all user state (carts, addresses, checkout, payment, etc.)
    if ($GLOBALS['use_postgres']) {
        execute_sql($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS saved_cart JSONB");
        execute_sql($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS delivery_address JSONB");
        execute_sql($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS selected_cart_items JSONB");
        execute_sql($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS checkout_items JSONB");
        execute_sql($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS pending_order_id TEXT");
        execute_sql($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS pending_checkout_items JSONB");
        execute_sql($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS payment_redirect_time TEXT");
        execute_sql($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS paymongo_checkout_url TEXT");
        execute_sql($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS payment_page_url TEXT");
        execute_sql($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS pending_cancellation_orders JSONB");
        execute_sql($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        execute_sql($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS suspended INTEGER DEFAULT 0");
        execute_sql($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS suspension_reason TEXT");
        execute_sql($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS suspended_at TIMESTAMP");
        execute_sql($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS suspension_lifted_at TIMESTAMP");
    } else {
        // SQLite: add columns if missing (ignore errors if they exist)
        @execute_sql($conn, "ALTER TABLE users ADD COLUMN saved_cart TEXT");
        @execute_sql($conn, "ALTER TABLE users ADD COLUMN delivery_address TEXT");
        @execute_sql($conn, "ALTER TABLE users ADD COLUMN selected_cart_items TEXT");
        @execute_sql($conn, "ALTER TABLE users ADD COLUMN checkout_items TEXT");
        @execute_sql($conn, "ALTER TABLE users ADD COLUMN pending_order_id TEXT");
        @execute_sql($conn, "ALTER TABLE users ADD COLUMN pending_checkout_items TEXT");
        @execute_sql($conn, "ALTER TABLE users ADD COLUMN payment_redirect_time TEXT");
        @execute_sql($conn, "ALTER TABLE users ADD COLUMN paymongo_checkout_url TEXT");
        @execute_sql($conn, "ALTER TABLE users ADD COLUMN payment_page_url TEXT");
        @execute_sql($conn, "ALTER TABLE users ADD COLUMN pending_cancellation_orders TEXT");
        @execute_sql($conn, "ALTER TABLE users ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        @execute_sql($conn, "ALTER TABLE users ADD COLUMN suspended INTEGER DEFAULT 0");
        @execute_sql($conn, "ALTER TABLE users ADD COLUMN suspension_reason TEXT");
        @execute_sql($conn, "ALTER TABLE users ADD COLUMN suspended_at TEXT");
        @execute_sql($conn, "ALTER TABLE users ADD COLUMN suspension_lifted_at TEXT");
    }
    
    // Create system_settings table
    $query = "
    CREATE TABLE IF NOT EXISTS system_settings (
        id " . get_id_type() . ",
        setting_key " . get_text_type() . " UNIQUE NOT NULL,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_by " . get_integer_type() . " DEFAULT NULL
    )
    ";
    
    $result = execute_sql($conn, $query);
    if ($result === false) {
        error_log("Failed to create system_settings table: " . ($GLOBALS['use_postgres'] ? pg_last_error($conn) : "SQLite error"));
    }
    
    // Create otp_verification table
    $query = "
    CREATE TABLE IF NOT EXISTS otp_verification (
        id " . get_id_type() . ",
        email " . get_text_type() . " NOT NULL,
        otp_code " . get_text_type() . " NOT NULL,
        username " . get_text_type() . " NOT NULL,
        password_hash TEXT NOT NULL,
        first_name " . get_text_type() . ",
        last_name " . get_text_type() . ",
        gender " . get_text_type() . ",
        date_of_birth DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NOT NULL,
        is_verified " . get_integer_type() . " DEFAULT 0
    )
    ";
    
    $result = execute_sql($conn, $query);
    if ($result === false) {
        error_log("Failed to create otp_verification table: " . ($GLOBALS['use_postgres'] ? pg_last_error($conn) : "SQLite error"));
    }
    
    // Create password_reset table
    $query = "
    CREATE TABLE IF NOT EXISTS password_reset (
        id " . get_id_type() . ",
        email " . get_text_type() . " NOT NULL,
        otp_code " . get_text_type() . " NOT NULL,
        user_id " . get_integer_type() . " NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NOT NULL,
        is_verified " . get_integer_type() . " DEFAULT 0
    )
    ";
    
    $result = execute_sql($conn, $query);
    if ($result === false) {
        error_log("Failed to create password_reset table: " . ($GLOBALS['use_postgres'] ? pg_last_error($conn) : "SQLite error"));
    }
    
    // Create orders table for water delivery system
    // Note: FOREIGN KEY constraints are handled differently for PostgreSQL vs SQLite
    if ($GLOBALS['use_postgres']) {
        $query = "
        CREATE TABLE IF NOT EXISTS orders (
            id " . get_id_type() . ",
            user_id " . get_integer_type() . " NOT NULL,
            order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            delivery_date DATE,
            delivery_time TIME,
            delivery_address TEXT,
            total_amount DECIMAL(10,2) NOT NULL,
            payment_method " . get_text_type() . ",
            status " . get_text_type() . " DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
        ";
        $result = execute_sql($conn, $query);
        if ($result === false) {
            error_log("Failed to create orders table: " . pg_last_error($conn));
        }
        
        // Add foreign key constraint separately for PostgreSQL (if it doesn't exist)
        $check_query = "SELECT 1 FROM pg_constraint WHERE conname = 'fk_orders_user_id'";
        $check_result = execute_sql($conn, $check_query);
        if ($check_result !== false) {
            $exists = pg_fetch_assoc($check_result);
            if (!$exists) {
                $query = "ALTER TABLE orders ADD CONSTRAINT fk_orders_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE";
                $result = execute_sql($conn, $query);
                if ($result === false) {
                    error_log("Failed to add foreign key constraint: " . pg_last_error($conn));
                }
            }
        }
    } else {
        $query = "
        CREATE TABLE IF NOT EXISTS orders (
            id " . get_id_type() . ",
            user_id " . get_integer_type() . " NOT NULL,
            order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            delivery_date DATE,
            delivery_time TIME,
            delivery_address TEXT,
            total_amount DECIMAL(10,2) NOT NULL,
            payment_method " . get_text_type() . ",
            status " . get_text_type() . " DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
        ";
        $result = execute_sql($conn, $query);
        if ($result === false) {
            error_log("Failed to create orders table: SQLite error");
        }
    }

    // Create order_status_history table to track status changes
    if ($GLOBALS['use_postgres']) {
        $query = "
        CREATE TABLE IF NOT EXISTS order_status_history (
            id " . get_id_type() . ",
            order_id " . get_integer_type() . " NOT NULL,
            user_id " . get_integer_type() . " NOT NULL,
            status " . get_text_type() . " NOT NULL,
            payment_method " . get_text_type() . ",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
        ";
        $result = execute_sql($conn, $query);
        if ($result === false) {
            error_log("Failed to create order_status_history table: " . pg_last_error($conn));
        }
        // Add FK constraints if missing
        $check_query = "SELECT 1 FROM pg_constraint WHERE conname = 'fk_order_status_history_order_id'";
        $check_result = execute_sql($conn, $check_query);
        if ($check_result !== false) {
            $exists = pg_fetch_assoc($check_result);
            if (!$exists) {
                execute_sql($conn, "ALTER TABLE order_status_history ADD CONSTRAINT fk_order_status_history_order_id FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE");
            }
        }
        $check_query = "SELECT 1 FROM pg_constraint WHERE conname = 'fk_order_status_history_user_id'";
        $check_result = execute_sql($conn, $check_query);
        if ($check_result !== false) {
            $exists = pg_fetch_assoc($check_result);
            if (!$exists) {
                execute_sql($conn, "ALTER TABLE order_status_history ADD CONSTRAINT fk_order_status_history_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
            }
        }
    } else {
        $query = "
        CREATE TABLE IF NOT EXISTS order_status_history (
            id " . get_id_type() . ",
            order_id " . get_integer_type() . " NOT NULL,
            user_id " . get_integer_type() . " NOT NULL,
            status " . get_text_type() . " NOT NULL,
            payment_method " . get_text_type() . ",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id),
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
        ";
        $result = execute_sql($conn, $query);
        if ($result === false) {
            error_log("Failed to create order_status_history table: SQLite error");
        }
    }
    
    // Create order_items table
    if ($GLOBALS['use_postgres']) {
        $query = "
        CREATE TABLE IF NOT EXISTS order_items (
            id " . get_id_type() . ",
            order_id " . get_integer_type() . " NOT NULL,
            product_name " . get_text_type() . " NOT NULL,
            product_price DECIMAL(10,2) NOT NULL,
            quantity " . get_integer_type() . " NOT NULL,
            subtotal DECIMAL(10,2) NOT NULL
        )
        ";
        $result = execute_sql($conn, $query);
        if ($result === false) {
            error_log("Failed to create order_items table: " . pg_last_error($conn));
        }
        
        // Add foreign key constraint separately for PostgreSQL (if it doesn't exist)
        $check_query = "SELECT 1 FROM pg_constraint WHERE conname = 'fk_order_items_order_id'";
        $check_result = execute_sql($conn, $check_query);
        if ($check_result !== false) {
            $exists = pg_fetch_assoc($check_result);
            if (!$exists) {
                $query = "ALTER TABLE order_items ADD CONSTRAINT fk_order_items_order_id FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE";
                $result = execute_sql($conn, $query);
                if ($result === false) {
                    error_log("Failed to add foreign key constraint: " . pg_last_error($conn));
                }
            }
        }
    } else {
        $query = "
        CREATE TABLE IF NOT EXISTS order_items (
            id " . get_id_type() . ",
            order_id " . get_integer_type() . " NOT NULL,
            product_name " . get_text_type() . " NOT NULL,
            product_price DECIMAL(10,2) NOT NULL,
            quantity " . get_integer_type() . " NOT NULL,
            subtotal DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (order_id) REFERENCES orders(id)
        )
        ";
        $result = execute_sql($conn, $query);
        if ($result === false) {
            error_log("Failed to create order_items table: SQLite error");
        }
    }
    
    // Create products table
    if ($GLOBALS['use_postgres']) {
        $query = "
        CREATE TABLE IF NOT EXISTS products (
            id " . get_id_type() . ",
            label " . get_text_type() . " NOT NULL,
            description TEXT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            image_url TEXT NOT NULL,
            category " . get_text_type() . " NOT NULL,
            unit " . get_text_type() . " NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
        ";
        $result = execute_sql($conn, $query);
        if ($result === false) {
            error_log("Failed to create products table: " . pg_last_error($conn));
        }
        
        // Migration: Remove icon_class column if it exists, make image_url NOT NULL if it isn't
        @execute_sql($conn, "ALTER TABLE products DROP COLUMN IF EXISTS icon_class");
        @execute_sql($conn, "ALTER TABLE products ALTER COLUMN image_url SET NOT NULL");
    } else {
        $query = "
        CREATE TABLE IF NOT EXISTS products (
            id " . get_id_type() . ",
            label " . get_text_type() . " NOT NULL,
            description TEXT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            image_url TEXT NOT NULL,
            category " . get_text_type() . " NOT NULL,
            unit " . get_text_type() . " NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
        ";
        $result = execute_sql($conn, $query);
        if ($result === false) {
            error_log("Failed to create products table: SQLite error");
        }
        
        // SQLite doesn't support DROP COLUMN easily, so we'll just ignore it
        // The icon_class field will remain but won't be used
        // For SQLite, we need to ensure image_url exists and is not null
        @execute_sql($conn, "ALTER TABLE products ADD COLUMN image_url TEXT");
    }
    
    close_connection($conn);
    error_log("Database initialization completed. Using: " . ($GLOBALS['use_postgres'] ? "PostgreSQL" : "SQLite"));
}

/**
 * Get user by username
 */
function get_user_by_username($username) {
    // Initialize database first to ensure tables exist
    init_db();
    
    $conn = get_db_connection();
    $query = "SELECT * FROM users WHERE username = ?";
    $result = execute_sql($conn, $query, [$username]);
    
    if ($result === false) {
        error_log("Failed to execute query in get_user_by_username");
        close_connection($conn);
        return null;
    }
    
    if ($GLOBALS['use_postgres']) {
        $user = pg_fetch_assoc($result);
    } else {
        $user = $result->fetchArray(SQLITE3_ASSOC);
    }
    
    close_connection($conn);
    return $user ? $user : null;
}

/**
 * Get user by email
 */
function get_user_by_email($email) {
    // Initialize database first to ensure tables exist
    init_db();
    
    $conn = get_db_connection();
    $query = "SELECT * FROM users WHERE email = ?";
    $result = execute_sql($conn, $query, [$email]);
    
    if ($result === false) {
        error_log("Failed to execute query in get_user_by_email");
        close_connection($conn);
        return null;
    }
    
    if ($GLOBALS['use_postgres']) {
        $user = pg_fetch_assoc($result);
    } else {
        $user = $result->fetchArray(SQLITE3_ASSOC);
    }
    
    close_connection($conn);
    return $user ? $user : null;
}

/**
 * Create a new user
 */
function create_user($username, $password, $email, $first_name, $last_name, $gender, $date_of_birth) {
    $conn = get_db_connection();
    
    // Check if username or email already exists
    $existing_user = get_user_by_username($username);
    if ($existing_user) {
        close_connection($conn);
        return ['success' => false, 'message' => 'Username already exists.'];
    }
    
    $existing_email = get_user_by_email($email);
    if ($existing_email) {
        close_connection($conn);
        return ['success' => false, 'message' => 'Email already exists.'];
    }
    
    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user
    $query = "
        INSERT INTO users 
        (username, password_hash, email, first_name, last_name, gender, date_of_birth, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ";
    
    $result = execute_sql($conn, $query, [
        $username, $password_hash, $email, $first_name, $last_name, $gender, $date_of_birth
    ]);
    
    if ($result) {
        $user_id = last_insert_id($conn, 'users');
        close_connection($conn);
        return ['success' => true, 'user_id' => $user_id];
    } else {
        close_connection($conn);
        return ['success' => false, 'message' => 'Failed to create user.'];
    }
}

/**
 * Get system setting value
 */
function get_system_setting($key, $default = null) {
    // Initialize database first to ensure tables exist
    init_db();
    
    // Force new connection to avoid stale data
    $conn = get_db_connection();
    
    // Use LIMIT 1 to ensure we only get one result
    $query = "SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1";
    $result = execute_sql($conn, $query, [$key]);
    
    if ($result === false) {
        error_log("Failed to execute query in get_system_setting for key: $key");
        close_connection($conn);
        return $default;
    }
    
    $value = null;
    if ($GLOBALS['use_postgres']) {
        $row = pg_fetch_assoc($result);
        $value = $row ? $row['setting_value'] : null;
    } else {
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $value = $row ? $row['setting_value'] : null;
    }
    
    // Debug logging
    if (in_array($key, ['brevo_api_key', 'brevo_sender_email', 'enable_email_notifications'])) {
        $log_value = in_array($key, ['brevo_api_key']) ? '***HIDDEN***' : $value;
        error_log("get_system_setting('$key') - found: " . ($value !== null ? "YES (length: " . strlen($value) . ", value: $log_value)" : "NO") . ", returning: " . ($value !== null ? $log_value : ($default !== null ? "default: $default" : "null")));
        
        // Also check all settings in database for debugging
        $all_query = "SELECT setting_key, LENGTH(setting_value) as val_len FROM system_settings WHERE setting_key IN ('brevo_api_key', 'brevo_sender_email', 'enable_email_notifications')";
        $all_result = execute_sql($conn, $all_query);
        $all_settings = [];
        if ($GLOBALS['use_postgres']) {
            while ($row = pg_fetch_assoc($all_result)) {
                $all_settings[] = $row['setting_key'] . " (len: " . $row['val_len'] . ")";
            }
        } else {
            while ($row = $all_result->fetchArray(SQLITE3_ASSOC)) {
                $all_settings[] = $row['setting_key'] . " (len: " . $row['val_len'] . ")";
            }
        }
        error_log("All Brevo settings in DB: " . (empty($all_settings) ? "NONE" : implode(", ", $all_settings)));
    }
    
    close_connection($conn);
    return $value !== null ? $value : $default;
}

/**
 * Update or insert system setting
 */
function update_system_setting($key, $value, $user_id = null) {
    $conn = get_db_connection();
    
    // Ensure table exists
    init_db();
    
    // Check if setting exists
    $query = "SELECT id FROM system_settings WHERE setting_key = ?";
    $result = execute_sql($conn, $query, [$key]);
    
    if ($GLOBALS['use_postgres']) {
        $exists = pg_fetch_assoc($result);
    } else {
        $exists = $result->fetchArray(SQLITE3_ASSOC);
    }
    
    if ($exists) {
        // Update existing setting
        $query = "UPDATE system_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP, updated_by = ? WHERE setting_key = ?";
        $result = execute_sql($conn, $query, [$value, $user_id, $key]);
        
        if ($GLOBALS['use_postgres']) {
            $success = $result !== false && pg_affected_rows($result) > 0;
            // Explicitly sync to ensure data is written
            pg_query($conn, "SELECT 1");
        } else {
            $success = $result !== false && $conn->changes() > 0;
        }
    } else {
        // Insert new setting
        $query = "INSERT INTO system_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?)";
        $result = execute_sql($conn, $query, [$key, $value, $user_id]);
        $success = $result !== false;
        if ($GLOBALS['use_postgres'] && $success) {
            // Explicitly sync to ensure data is written
            pg_query($conn, "SELECT 1");
        }
    }
    
    // Verify the save was successful - use same connection
    if ($success) {
        $verify_query = "SELECT setting_value FROM system_settings WHERE setting_key = ?";
        $verify_result = execute_sql($conn, $verify_query, [$key]);
        
        if ($GLOBALS['use_postgres']) {
            $verify_row = pg_fetch_assoc($verify_result);
            $saved_value = $verify_row ? $verify_row['setting_value'] : null;
        } else {
            $verify_row = $verify_result->fetchArray(SQLITE3_ASSOC);
            $saved_value = $verify_row ? $verify_row['setting_value'] : null;
        }
        
        if ($saved_value !== $value) {
            error_log("Warning: Setting $key was saved but verification failed. Expected: " . (in_array($key, ['brevo_api_key']) ? '***HIDDEN***' : $value) . ", Got: " . ($saved_value ?? 'null'));
            $success = false;
        }
    }
    
    close_connection($conn);
    return $success;
}

/**
 * Get all system settings
 */
function get_all_system_settings() {
    $conn = get_db_connection();
    $query = "SELECT setting_key, setting_value FROM system_settings";
    $result = execute_sql($conn, $query);
    
    $settings = [];
    if ($GLOBALS['use_postgres']) {
        while ($row = pg_fetch_assoc($result)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } else {
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    close_connection($conn);
    return $settings;
}

/**
 * Store OTP verification data
 */
function store_otp_verification($email, $otp_code, $username, $password_hash, $first_name, $last_name, $gender, $date_of_birth, $expires_at) {
    $conn = get_db_connection();
    
    // Delete any existing OTP for this email
    $deleteQuery = "DELETE FROM otp_verification WHERE email = ?";
    execute_sql($conn, $deleteQuery, [$email]);
    
    // Insert new OTP
    $query = "
        INSERT INTO otp_verification 
        (email, otp_code, username, password_hash, first_name, last_name, gender, date_of_birth, expires_at, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ";
    
    $result = execute_sql($conn, $query, [
        $email, $otp_code, $username, $password_hash, $first_name, $last_name, $gender, $date_of_birth, $expires_at
    ]);
    
    close_connection($conn);
    return $result ? true : false;
}

/**
 * Verify OTP code and get user data
 */
function verify_otp_code($email, $otp_code) {
    // Initialize database first to ensure tables exist
    init_db();
    
    $conn = get_db_connection();
    
    $query = "
        SELECT * FROM otp_verification 
        WHERE email = ? AND otp_code = ? AND is_verified = 0 AND expires_at > CURRENT_TIMESTAMP
        ORDER BY created_at DESC
        LIMIT 1
    ";
    
    $result = execute_sql($conn, $query, [$email, $otp_code]);
    
    if ($result === false) {
        error_log("Failed to execute query in verify_otp_code: " . ($GLOBALS['use_postgres'] ? pg_last_error($conn) : "SQLite error"));
        close_connection($conn);
        return null;
    }
    
    if ($GLOBALS['use_postgres']) {
        $otp_data = pg_fetch_assoc($result);
    } else {
        $otp_data = $result->fetchArray(SQLITE3_ASSOC);
    }
    
    if ($otp_data) {
        // Mark as verified
        $updateQuery = "UPDATE otp_verification SET is_verified = 1 WHERE id = ?";
        $updateResult = execute_sql($conn, $updateQuery, [$otp_data['id']]);
        
        if ($updateResult === false) {
            error_log("Failed to mark OTP as verified");
        }
        
        // Return user data
        $user_data = [
            'username' => $otp_data['username'],
            'password_hash' => $otp_data['password_hash'],
            'first_name' => $otp_data['first_name'],
            'last_name' => $otp_data['last_name'],
            'gender' => $otp_data['gender'],
            'date_of_birth' => $otp_data['date_of_birth']
        ];
        
        close_connection($conn);
        return $user_data;
    } else {
        // Log for debugging
        error_log("OTP verification failed for email: $email, code: $otp_code");
        // Check if OTP exists but is expired or already verified
        $check_query = "SELECT * FROM otp_verification WHERE email = ? AND otp_code = ? ORDER BY created_at DESC LIMIT 1";
        $check_result = execute_sql($conn, $check_query, [$email, $otp_code]);
        if ($check_result !== false) {
            if ($GLOBALS['use_postgres']) {
                $check_data = pg_fetch_assoc($check_result);
            } else {
                $check_data = $check_result->fetchArray(SQLITE3_ASSOC);
            }
            if ($check_data) {
                error_log("OTP found but: is_verified=" . ($check_data['is_verified'] ?? 'N/A') . ", expires_at=" . ($check_data['expires_at'] ?? 'N/A'));
            } else {
                error_log("No OTP record found for email: $email, code: $otp_code");
            }
        }
    }
    
    close_connection($conn);
    return null;
}

/**
 * Get pending OTP data by email
 */
function get_pending_otp($email) {
    // Initialize database first to ensure tables exist
    init_db();
    
    $conn = get_db_connection();
    
    $query = "
        SELECT * FROM otp_verification 
        WHERE email = ? AND is_verified = 0 AND expires_at > CURRENT_TIMESTAMP
        ORDER BY created_at DESC
        LIMIT 1
    ";
    
    $result = execute_sql($conn, $query, [$email]);
    
    if ($result === false) {
        error_log("Failed to execute query in get_pending_otp: " . ($GLOBALS['use_postgres'] ? pg_last_error($conn) : "SQLite error"));
        close_connection($conn);
        return null;
    }
    
    if ($GLOBALS['use_postgres']) {
        $otp_data = pg_fetch_assoc($result);
    } else {
        $otp_data = $result->fetchArray(SQLITE3_ASSOC);
    }
    
    close_connection($conn);
    return $otp_data ? $otp_data : null;
}

/**
 * Update OTP code (for resend)
 */
function update_otp_code($email, $otp_code, $expires_at) {
    // Initialize database first to ensure tables exist
    init_db();
    
    $conn = get_db_connection();
    
    $query = "
        UPDATE otp_verification 
        SET otp_code = ?, expires_at = ?, created_at = CURRENT_TIMESTAMP
        WHERE email = ? AND is_verified = 0
    ";
    
    $result = execute_sql($conn, $query, [$otp_code, $expires_at, $email]);
    
    if ($result === false) {
        error_log("Failed to execute query in update_otp_code: " . ($GLOBALS['use_postgres'] ? pg_last_error($conn) : "SQLite error"));
        close_connection($conn);
        return false;
    }
    
    if ($GLOBALS['use_postgres']) {
        $affected = pg_affected_rows($result);
    } else {
        $affected = $conn->changes();
    }
    
    close_connection($conn);
    return $affected > 0;
}

/**
 * Clean up expired OTP records
 */
function cleanup_expired_otp() {
    $conn = get_db_connection();
    
    $query = "DELETE FROM otp_verification WHERE expires_at < CURRENT_TIMESTAMP OR is_verified = 1";
    execute_sql($conn, $query);
    
    close_connection($conn);
}

/**
 * Store password reset OTP
 */
function store_password_reset_otp($email, $otp_code, $user_id, $expires_at) {
    init_db();
    $conn = get_db_connection();
    
    // Delete any existing reset OTP for this email
    $deleteQuery = "DELETE FROM password_reset WHERE email = ?";
    execute_sql($conn, $deleteQuery, [$email]);
    
    // Insert new reset OTP
    $query = "
        INSERT INTO password_reset 
        (email, otp_code, user_id, expires_at, created_at)
        VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
    ";
    
    $result = execute_sql($conn, $query, [$email, $otp_code, $user_id, $expires_at]);
    
    close_connection($conn);
    return $result ? true : false;
}

/**
 * Verify password reset OTP
 */
function verify_password_reset_otp($email, $otp_code) {
    init_db();
    $conn = get_db_connection();
    
    $query = "
        SELECT * FROM password_reset 
        WHERE email = ? AND otp_code = ? AND is_verified = 0 AND expires_at > CURRENT_TIMESTAMP
        ORDER BY created_at DESC
        LIMIT 1
    ";
    
    $result = execute_sql($conn, $query, [$email, $otp_code]);
    
    if ($result === false) {
        error_log("Failed to execute query in verify_password_reset_otp: " . ($GLOBALS['use_postgres'] ? pg_last_error($conn) : "SQLite error"));
        close_connection($conn);
        return null;
    }
    
    if ($GLOBALS['use_postgres']) {
        $reset_data = pg_fetch_assoc($result);
    } else {
        $reset_data = $result->fetchArray(SQLITE3_ASSOC);
    }
    
    if ($reset_data) {
        // Mark as verified
        $updateQuery = "UPDATE password_reset SET is_verified = 1 WHERE id = ?";
        execute_sql($conn, $updateQuery, [$reset_data['id']]);
        
        close_connection($conn);
        return $reset_data;
    }
    
    close_connection($conn);
    return null;
}

/**
 * Update password reset OTP (for resend)
 */
function update_password_reset_otp($email, $otp_code, $expires_at) {
    init_db();
    $conn = get_db_connection();
    
    $query = "
        UPDATE password_reset 
        SET otp_code = ?, expires_at = ?, created_at = CURRENT_TIMESTAMP
        WHERE email = ? AND is_verified = 0
    ";
    
    $result = execute_sql($conn, $query, [$otp_code, $expires_at, $email]);
    
    if ($result === false) {
        error_log("Failed to execute query in update_password_reset_otp: " . ($GLOBALS['use_postgres'] ? pg_last_error($conn) : "SQLite error"));
        close_connection($conn);
        return false;
    }
    
    if ($GLOBALS['use_postgres']) {
        $affected = pg_affected_rows($result);
    } else {
        $affected = $conn->changes();
    }
    
    close_connection($conn);
    return $affected > 0;
}

/**
 * Clean up expired password reset records
 */
function cleanup_expired_password_reset() {
    $conn = get_db_connection();
    
    $query = "DELETE FROM password_reset WHERE expires_at < CURRENT_TIMESTAMP OR is_verified = 1";
    execute_sql($conn, $query);
    
    close_connection($conn);
}

// Initialize database if it doesn't exist (SQLite only)
if (!$GLOBALS['use_postgres'] && !file_exists($GLOBALS['db_path'])) {
    init_db();
}

// Create symlink for uploads if using a different upload directory (Railway volume)
$upload_base_dir = '';
if (!empty($_ENV['UPLOADS_DIR'])) {
    $upload_base_dir = rtrim($_ENV['UPLOADS_DIR'], DIRECTORY_SEPARATOR);
} elseif (!empty($_ENV['RAILWAY_VOLUME_PATH'])) {
    $upload_base_dir = rtrim($_ENV['RAILWAY_VOLUME_PATH'], DIRECTORY_SEPARATOR);
}

if ($upload_base_dir) {
    $local_uploads = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
    
    if (!is_link($local_uploads)) {
        // If it's a real directory, move it into the volume first
        if (is_dir($local_uploads)) {
            $volume_products = $upload_base_dir . DIRECTORY_SEPARATOR . 'products';
            if (!is_dir($volume_products)) {
                @mkdir($volume_products, 0777, true);
            }
            
            // Move any existing files from local uploads to volume
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($local_uploads, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $item) {
                $rel_path = substr($item->getPathname(), strlen($local_uploads) + 1);
                $dest = $upload_base_dir . DIRECTORY_SEPARATOR . $rel_path;
                if ($item->isDir() && !is_dir($dest)) {
                    @mkdir($dest, 0777, true);
                } elseif ($item->isFile()) {
                    @copy($item->getPathname(), $dest);
                }
            }
            
            // Remove the real directory (now that files are in volume)
            $this_dir = $local_uploads;
            $iterator2 = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator2 as $item) {
                if ($item->isDir()) {
                    @rmdir($item->getPathname());
                } else {
                    @unlink($item->getPathname());
                }
            }
            @rmdir($this_dir);
            error_log("Moved existing uploads to volume and removed local directory");
        }
        
        // Create symlink
        if (!is_dir($local_uploads) && !is_link($local_uploads)) {
            $result = @symlink($upload_base_dir, $local_uploads);
            if ($result) {
                error_log("Created symlink: " . $local_uploads . " -> " . $upload_base_dir);
            } else {
                error_log("Failed to create symlink: " . $local_uploads . " -> " . $upload_base_dir);
            }
        }
    } elseif (is_link($local_uploads)) {
        // Symlink exists, verify it points to the right place
        $target = readlink($local_uploads);
        if ($target !== $upload_base_dir) {
            @unlink($local_uploads);
            @symlink($upload_base_dir, $local_uploads);
            error_log("Recreated symlink: " . $local_uploads . " -> " . $upload_base_dir);
        }
    }
}

/**
 * Seed default products exactly once (guarded by a system_settings flag).
 * Copies bundled images to uploads directory on every run (idempotent),
 * but only inserts product rows a single time so that admin renames and
 * deletes of predefined products are never resurrected.
 */
function seed_default_products() {
    global $use_postgres;
    
    $conn = get_db_connection();
    
    error_log("Checking default products...");
    
    // Always use local uploads directory (committed to repo with .htaccess)
    // The volume symlink approach doesn't work when uploads/ already exists in the repo
    $root_dir = dirname(__DIR__);
    $products_dir = $root_dir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products';
    if (!is_dir($products_dir)) {
        @mkdir($products_dir, 0777, true);
    }
    
    // Source images from the bundled products/ folder
    $source_images_dir = $root_dir . DIRECTORY_SEPARATOR . 'products';
    
    $default_products = [
        [
            'label' => '1L Pure Water',
            'description' => 'Your perfect everyday hydration companion. Lightweight, ergonomic, and easy to carry, it provides all-day refreshment on the go.',
            'price' => 20.00,
            'category' => 'Bottle',
            'unit' => '/unit',
            'image_file' => 'single-1L.png'
        ],
        [
            'label' => '500ml Water (Pack of 24)',
            'description' => 'Grab-and-go hydration for the whole family. Perfect for school lunches, road trips, and daily errands, this 24-pack ensures you stay refreshed wherever the day takes you.',
            'price' => 240.00,
            'category' => 'Bottled',
            'unit' => '/pack',
            'image_file' => '500ml-pack-of-24.png'
        ],
        [
            'label' => '500ml Water (Pack of 48)',
            'description' => 'Bulk hydration built for events, offices, and large households. Keep everyone refreshed with a generous supply of pure, clean drinking water.',
            'price' => 460.00,
            'category' => 'Bottle',
            'unit' => '/pack',
            'image_file' => '500ml-pack-of-48.png'
        ],
        [
            'label' => '1L Water (Pack of 12)',
            'description' => 'Double the hydration in every bottle. Great for workouts, long workdays, or stocking up your pantry, this 12-pack delivers maximum refreshment with fewer refills.',
            'price' => 210.00,
            'category' => 'Bottle',
            'unit' => '/pack',
            'image_file' => '1L-pack-of-12.png'
        ],
        [
            'label' => '5-Gallon Pure Water Refill',
            'description' => 'The ultimate high-capacity solution for homes and offices. Designed for standard water coolers, it offers a continuous supply of crisp, clean drinking water.',
            'price' => 50.00,
            'category' => 'Refill Gallon',
            'unit' => '/refill',
            'image_file' => '5-gallon.png'
        ],
        [
            'label' => '5-Gallon Container with Tap (New Set)',
            'description' => 'Hydration made effortless. Featuring a built-in faucet and easy-carry handle, this portable jug is ideal for countertops, outdoor BBQs, and camping trips—no cooler required.',
            'price' => 350.00,
            'category' => 'Gallon',
            'unit' => '/unit',
            'image_file' => '5-gallon-faucet.png'
        ],
        [
            'label' => '5-Gallon Round Jug (New Set)',
            'description' => 'Includes a brand new heavy-duty 5-gallon jug filled with fresh, purified drinking water. Perfect for new customers or adding extra capacity to your home or office setup.',
            'price' => 300.00,
            'category' => 'Gallon',
            'unit' => '/unit',
            'image_file' => '5-gallon.png'
        ],
        [
            'label' => '5-Gallon Tap Container Refill',
            'description' => 'Refill service for your existing 5-gallon tap container. Enjoy the same clean, pure drinking water refilled directly into your portable dispensing jug.',
            'price' => 50.00,
            'category' => 'Refill Gallon',
            'unit' => '/refill',
            'image_file' => '5-gallon-faucet.png'
        ]
    ];
    
    $seeded = 0;
    
    // Step 1: Always ensure seed images exist in uploads directory
    // (images may be lost on redeploy if not on volume).
    // Overwrites the destination when the bundled image changed so that
    // replacing a file in products/ actually refreshes it on deploy.
    $copy_count = 0;
    foreach ($default_products as $product) {
        $source_file = $source_images_dir . DIRECTORY_SEPARATOR . $product['image_file'];
        $dest_file = $products_dir . DIRECTORY_SEPARATOR . $product['image_file'];
        
        if (!file_exists($source_file)) {
            continue;
        }
        $needs_copy = !file_exists($dest_file)
            || @filesize($source_file) !== @filesize($dest_file)
            || @md5_file($source_file) !== @md5_file($dest_file);
        if ($needs_copy) {
            @copy($source_file, $dest_file);
            $copy_count++;
            error_log("Copied seed image: " . $product['image_file']);
        }
    }
    if ($copy_count > 0) {
        error_log("Copied $copy_count seed images to uploads directory");
    }
    
    // Step 2: Insert missing products, but only once ever.
    // A persistent flag ensures admin renames/deletes of predefined
    // products are respected and never resurrected on later requests.
    $seed_flag = get_system_setting('default_products_seeded', null);
    if ($seed_flag === '1') {
        close_connection($conn);
        return;
    }

    foreach ($default_products as $product) {
        // Check if this product already exists by label
        $check = execute_sql($conn, "SELECT id FROM products WHERE label = ?", [$product['label']]);
        $exists = false;
        if ($check !== false) {
            if ($use_postgres) {
                $exists = pg_fetch_assoc($check) !== false;
            } else {
                $exists = $check->fetchArray(SQLITE3_ASSOC) !== false;
            }
        }
        
        if ($exists) {
            continue; // Skip, already exists
        }
        
        $image_url = 'uploads/products/' . $product['image_file'];
        
        // Insert product
        $query = "INSERT INTO products (label, description, price, image_url, category, unit, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
        $result = execute_sql($conn, $query, [
            $product['label'],
            $product['description'],
            $product['price'],
            $image_url,
            $product['category'],
            $product['unit']
        ]);
        
        if ($result !== false) {
            $seeded++;
            error_log("Seeded product: " . $product['label']);
        } else {
            error_log("Failed to seed product: " . $product['label']);
        }
    }
    
    close_connection($conn);
    if ($seeded > 0) {
        error_log("Seeded $seeded new default products");
    }
    // Mark seeding complete so renames/deletes are never resurrected
    update_system_setting('default_products_seeded', '1');
}

// Run seed after database init and symlink setup
seed_default_products();
?>

