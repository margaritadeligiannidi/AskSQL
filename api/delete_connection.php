<?php

header('Content-Type: application/json; charset=utf-8');

require_once "db.php";
require_once "auth_helpers.php";

$auth = require_auth();
$user_id = (int)$auth['user_id'];

/*  INPUT */
$data = json_decode(file_get_contents("php://input"), true);
$id = (int)($data['id'] ?? 0);

if (!$id) {
    echo json_encode(["error" => "Missing ID"]);
    exit;
}

/*  DB CONNECTION*/
$conn = getAppDB();

/*  DELETE */
$stmt = $conn->prepare("DELETE FROM connections WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $id, $user_id);

if ($stmt->execute()) {

    if ($stmt->affected_rows > 0) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["error" => "Not found or not allowed"]);
    }

} else {
    echo json_encode(["error" => "Delete failed"]);
}

$stmt->close();
$conn->close();