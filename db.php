<?php
// Database connection parameters
$host = "localhost";          // XAMPP server
$username = "root";           // default XAMPP MySQL user
$password = "";               // default XAMPP password is empty
$database = "smart_irrigation_v2";  // your database name

// Create connection
$connection = mysqli_connect($host, $username, $password, $database);

// Check connection
if (!$connection) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Optional: set charset to avoid UTF-8 issues
mysqli_set_charset($connection, "utf8");
?>
