<?php

require_once "db.php";
require_once "auth_helpers.php";

header('Content-Type: application/json; charset=utf-8');

$auth = require_auth();

$user_id = (int)$auth['user_id'];

$data = json_decode(file_get_contents("php://input"), true);

$new = $data['password'] ?? '';

if (!$new) {
    echo json_encode(["error" => "Missing password"]);
    exit;
}

if (strlen($new) < 8) {
    echo json_encode([
        "error" => "Password must be at least 8 characters"
    ]);
    exit;
}

/* DATABASE connection */
$db = getAppDB();

/* HASH */
$newHash = password_hash($new, PASSWORD_DEFAULT);

/* UPDATE */
$stmt = $db->prepare("
    UPDATE users
    SET password = ?
    WHERE id = ?
");

$stmt->bind_param("si", $newHash, $user_id);

if ($stmt->execute()) {

    echo json_encode([
        "success" => true
    ]);

} else {

    echo json_encode([
        "error" => "Database error"
    ]);
}