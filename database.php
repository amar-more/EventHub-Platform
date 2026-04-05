<?php
$host = "localhost";
$user = "root";
$password = "root@54321";
$dbname = "eventhub";

// Create connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
