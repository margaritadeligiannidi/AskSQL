<?php

header('Content-Type: application/json; charset=utf-8');

require_once "db.php";
require_once "auth_helpers.php";

/* AUTH */
$auth = require_auth();

$user_id = (int)$auth['user_id'];

/* DATABASE CONNECTION */
$db = getAppDB();

/* PROFILE INFOS */
$stmt = $db->prepare("
    SELECT email, created_at, username, role
    FROM users 
    WHERE id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$res = $stmt->get_result();

$user = $res->fetch_assoc();

if (!$user) {

    echo json_encode([
        "success" => false,
        "error" => "User not found"
    ]);

    exit;
}

echo json_encode([
    "success" => true,
    "username" => $user['username'],
    "email" => $user['email'],
    "created_at" => $user['created_at'],
    "role" => $user['role']
]);

$stmt->close();
$db->close();

?>