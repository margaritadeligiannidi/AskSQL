<?php

require_once __DIR__ . "/config.php";

loadEnv(__DIR__ . "/../.env");

function getAppDB() {

    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $user = $_ENV['DB_USER'] ?? '';
    $pass = $_ENV['DB_PASS'] ?? '';
    $name = $_ENV['DB_NAME'] ?? '';

    $conn = new mysqli($host, $user, $pass, $name);
    $conn->set_charset("utf8mb4");

    if ($conn->connect_error) {
        die("Database connection failed");
    }

    return $conn;
}