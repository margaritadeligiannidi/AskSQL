<?php

header('Content-Type: application/json; charset=utf-8');

require_once "db.php";

/* INPUT */
$data = json_decode(file_get_contents("php://input"), true);

$token = trim($data['token'] ?? '');
$password = $data['password'] ?? '';

/* VALIDATION */
if (!$token || !$password) {

    echo json_encode([
        "success" => false,
        "error" => "Missing data"
    ]);

    exit;
}

if (strlen($password) < 8) {

    echo json_encode([
        "success" => false,
        "error" => "Password must be at least 8 characters"
    ]);

    exit;
}

try {

    $db = getAppDB();

    /* FIND VALID TOKEN */
    $stmt = $db->prepare("
        SELECT user_id
        FROM password_resets
        WHERE token = ?
        AND expires_at > NOW()
        LIMIT 1
    ");

    $stmt->bind_param("s", $token);
    $stmt->execute();

    $res = $stmt->get_result();

    if ($res->num_rows === 0) {

        echo json_encode([
            "success" => false,
            "error" => "Invalid or expired token"
        ]);

        exit;
    }

    $row = $res->fetch_assoc();

    $user_id = (int)$row['user_id'];

    /* HASH PASSWORD */
    $hash = password_hash($password, PASSWORD_DEFAULT);

    /* UPDATE PASSWORD */
    $stmt = $db->prepare("
        UPDATE users
        SET password = ?
        WHERE id = ?
    ");

    $stmt->bind_param("si", $hash, $user_id);

    $stmt->execute();

    /* DELETE USED TOKENS */
    $stmt = $db->prepare("
        DELETE FROM password_resets
        WHERE user_id = ?
    ");

    $stmt->bind_param("i", $user_id);

    $stmt->execute();

    echo json_encode([
        "success" => true,
        "message" => "Password updated successfully"
    ]);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "error" => "Server error"
    ]);
}

?>