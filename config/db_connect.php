<?php
// Database connection
$host = "127.0.0.1";
$user = "root";
$password = "";
$database = "tiffinly";
//$port = 3307;

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


// Set charset to utf8
$conn->set_charset("utf8");
?>