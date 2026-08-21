<?php
//echo "<pre>";

// Detect environment
if ($_SERVER['SERVER_NAME'] == 'localhost') {
    // Localhost configuration
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "madrasa_new";
   
    // Report all PHP errors
error_reporting(E_ALL);

// Display errors on the screen
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
} else {
    // Live server configuration
    $servername = "localhost";
    $username = "softray1_alfarooqia"; // <-- Confirm this is correct
    $password = "alfaaroqia123!@#$%^&*()";
    $dbname = "softray1_alfarooqia";
   // echo "Environment: Live Server\n";
}

// echo "Server: $servername\n";
// echo "Username: $username\n";
// echo "Database: $dbname\n";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    echo "Connection failed:\n";
    // echo "Error Number: " . $conn->connect_errno . "\n";
    // echo "Error Message: " . $conn->connect_error . "\n";
    die("Database connection failed.");
} else {
    // echo "Connection successful!\n";
}

//echo "</pre>";
?>
