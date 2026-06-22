<?php

header('Content-Type: application/json; charset=utf-8');

require_once "auth_helpers.php";
require_once "db.php";

/* AUTH */
$auth = require_auth();

/* ADMIN ONLY */
if (($auth['role'] ?? '') !== 'admin') {

    echo json_encode([
        "success" => false,
        "error" => "Unauthorized"
    ]);

    exit;
}

/* DATABASE */
$db = getAppDB();

/* GET USERS */
$result = $db->query("
    SELECT id, username, role
    FROM users
    ORDER BY id ASC
");

$users = [];

while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

/* RESPONSE */
echo json_encode([
    "success" => true,
    "users" => $users
]);

exit;