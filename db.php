<?php
session_start();

$conn = mysqli_connect("localhost", "root", "", "railway1");
if (!$conn) {
    die(json_encode(['error' => 'DB connection failed: ' . mysqli_connect_error()]));
}
mysqli_set_charset($conn, "utf8mb4");

function clean($conn, $val) {
    return mysqli_real_escape_string($conn, trim($val));
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: index.php");
        exit;
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        header("Location: dashboard.php");
        exit;
    }
}

// Create trains table if not exists
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS trains (
    train_id INT AUTO_INCREMENT PRIMARY KEY,
    train_name VARCHAR(100),
    train_number VARCHAR(20),
    from_station VARCHAR(100),
    to_station VARCHAR(100),
    departure_time TIME,
    arrival_time TIME,
    total_seats INT DEFAULT 72,
    fare DECIMAL(10,2) DEFAULT 500.00,
    status VARCHAR(20) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT current_timestamp()
)");

// Add missing columns to trains table if not exists
$train_cols = ['status' => "VARCHAR(20) DEFAULT 'Active'", 'fare' => "DECIMAL(10,2) DEFAULT 500.00", 'total_seats' => "INT DEFAULT 72", 'departure_time' => "TIME", 'arrival_time' => "TIME", 'train_number' => "VARCHAR(20)", 'from_station' => "VARCHAR(100)", 'to_station' => "VARCHAR(100)"];
foreach ($train_cols as $col => $def) {
    $chk = mysqli_query($conn, "SHOW COLUMNS FROM trains LIKE '$col'");
    if (mysqli_num_rows($chk) === 0) {
        mysqli_query($conn, "ALTER TABLE trains ADD COLUMN $col $def");
    }
}

// Add role column to users if not exists
$cols = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'role'");
if (mysqli_num_rows($cols) === 0) {
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'user'");
}

// Add train_number to bookings if not exists
$cols2 = mysqli_query($conn, "SHOW COLUMNS FROM bookings LIKE 'fare'");
if (mysqli_num_rows($cols2) === 0) {
    mysqli_query($conn, "ALTER TABLE bookings ADD COLUMN fare DECIMAL(10,2) DEFAULT 0.00");
}


// Create payments table if not exists
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(30) DEFAULT 'Card',
    payment_status VARCHAR(20) DEFAULT 'Pending',
    transaction_id VARCHAR(60),
    card_last4 VARCHAR(4),
    paid_at TIMESTAMP DEFAULT current_timestamp()
)");
// Ensure admin account exists
$adminCheck = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id FROM users WHERE email='admin@railsys.com'"));
if (!$adminCheck) {
    $hashed = password_hash('admin123', PASSWORD_DEFAULT);
    mysqli_query($conn, "INSERT INTO users (name, email, password, phone, role) VALUES ('System Admin', 'admin@railsys.com', '$hashed', '9999999999', 'admin')");
}
?>