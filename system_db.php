<?php

$servername = "localhost";
$db_username = "root";
$db_password = "";
$database = "db_mis";

// Create database connection
$connection = new mysqli($servername, $db_username, $db_password, $database);

// Check connection
if ($connection->connect_error) {
    die("Connection Failed: " . $connection->connect_error);
}


?>