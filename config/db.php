<?php
// --- Database Credentials ---
// Define constants for the database connection details.
// This makes it easy to manage and use them for both connection types.
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ashram_management'); // Changed from 'ashram_management' to match the SQL setup

// --- Connection Method 1: MySQLi (Procedural/Object-Oriented) ---
// This creates the $conn variable used by your donation management page.
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check the MySQLi connection for errors.
if ($conn->connect_error) {
    // If there's an error, stop the script and show the error message.
    die("MySQLi Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4 to support a wide range of characters
$conn->set_charset("utf8mb4");


// --- Connection Method 2: PDO (PHP Data Objects) ---
// This creates the $pdo variable for use in other parts of your application.
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // If there's an error, stop the script and show the error message.
    die("PDO Error: Could not connect to the database. <br>" . $e->getMessage());
}

?>


