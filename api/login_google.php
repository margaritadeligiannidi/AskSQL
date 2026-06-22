<?php

require_once "config.php";

// φόρτωση .env
loadEnv(__DIR__ . "/../.env");

$clientId = $_ENV["GOOGLE_CLIENT_ID"];
$redirectUri = $_ENV["GOOGLE_REDIRECT_URI"];

$scope = urlencode("openid email profile");

$url =
    "https://accounts.google.com/o/oauth2/v2/auth"
    . "?client_id=" . urlencode($clientId)
    . "&redirect_uri=" . urlencode($redirectUri)
    . "&response_type=code"
    . "&scope=" . $scope
    . "&access_type=online"
    . "&prompt=select_account";

header("Location: " . $url);
exit;