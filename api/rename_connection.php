<?php

header('Content-Type: application/json; charset=utf-8');

require_once "db.php";
require_once "auth_helpers.php";

/* AUTH */
$auth = require_auth();

$user_id = (int)$auth['user_id'];

/* INPUT */
$data = json_decode(file_get_contents("php://input"), true);

$id   = (int)($data['id'] ?? 0);
$name = trim($data['name'] ?? '');

/* VALIDATION */
if (!$id || !$name) {

    echo json_encode([
        "success" => false,
        "error" => "Missing fields"
    ]);

    exit;
}

if (strlen($name) > 50) {

    echo json_encode([
        "success" => false,
        "error" => "Name too long"
    ]);

    exit;
}

/* DATABASE CONNECTION */
$conn = getAppDB();

/* UPDATE */
$stmt = $conn->prepare("
    UPDATE connections 
    SET name = ? 
    WHERE id = ? 
    AND user_id = ?
");

$stmt->bind_param("sii", $name, $id, $user_id);

$stmt->execute();

if ($stmt->affected_rows > 0) {

    echo json_encode([
        "success" => true
    ]);

} else {

    echo json_encode([
        "success" => false,
        "error" => "Not found or not allowed"
    ]);
}

$stmt->close();
$conn->close();

?>