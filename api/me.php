<?php

header('Content-Type: application/json; charset=utf-8');

require_once "auth_helpers.php";

$auth = require_auth();

echo json_encode([
    "authenticated" => true,
    "user_id" => $auth['user_id'],
    "role" => $auth['role']
]);