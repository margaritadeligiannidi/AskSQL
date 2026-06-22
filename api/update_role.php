<?php

header('Content-Type: application/json; charset=utf-8');

require_once "db.php";
require_once "auth_helpers.php";

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

/* INPUT */
$data = json_decode(file_get_contents("php://input"), true);

$userId = (int)($data['user_id'] ?? 0);
$role   = trim($data['role'] ?? '');

/* VALIDATION */
if (!$userId || !$role) {

    echo json_encode([
        "success" => false,
        "error" => "Missing data"
    ]);

    exit;
}

$allowedRoles = ['demo', 'full', 'admin'];

if (!in_array($role, $allowedRoles, true)) {

    echo json_encode([
        "success" => false,
        "error" => "Invalid role"
    ]);

    exit;
}

try {

    /* DATABASE CONNECTION */
    $db = getAppDB();

    /* UPDATE ROLE */
    $stmt = $db->prepare("
        UPDATE users
        SET role = ?
        WHERE id = ?
    ");

    $stmt->bind_param("si", $role, $userId);

    $stmt->execute();

    if ($stmt->affected_rows > 0) {

        echo json_encode([
            "success" => true
        ]);

    } else {

        echo json_encode([
            "success" => false,
            "error" => "User not found or role unchanged"
        ]);
    }

    $stmt->close();
    $db->close();

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "error" => "Update failed"
    ]);
}

?>