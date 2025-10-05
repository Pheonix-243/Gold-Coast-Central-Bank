<?php
// db.php
$con = mysqli_connect("localhost", "root", "", "web_programming");
if (!$con) {
    error_log("Database connection failed: " . mysqli_connect_error());
    die("System temporarily unavailable. Please try again later.");
}

// Set charset to ensure proper encoding
mysqli_set_charset($con, "utf8mb4");
?>