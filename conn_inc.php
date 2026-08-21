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
    // Live server configuration (InfinityFree)
    $servername = "sql207.infinityfree.com";  // InfinityFree MySQL hostname
    $username = "if0_42714877";               // Your InfinityFree MySQL username
    $password = "Rockstar9530";               // Your InfinityFree MySQL password (replace with actual)
    $dbname = "if0_42714877_XXX";             // Your InfinityFree database name (replace XXX with actual)
    // Optional: $port = 3306;                // Default port, no need to specify
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