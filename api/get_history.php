<?php

require_once "db.php";
require_once "auth_helpers.php";

header("Content-Type: application/json; charset=utf-8");

/* AUTH */
$auth = require_auth();

$user_id = (int)$auth['user_id'];

/* INPUT */
$data = json_decode(
    file_get_contents("php://input"),
    true
);

$connection_id = (int)($data['connection_id'] ?? 0);
$database = trim($data['database'] ?? '');    

if (!$connection_id) {

    echo json_encode([
        "success" => false,
        "error" => "Missing connection_id"
    ]);

    exit;
}

if (!$database) {

    echo json_encode([
        "success" => false,
        "error" => "Missing database"
    ]);

    exit;
}

/* CONNECTION OWNERSHIP CHECK */
$connection = get_user_connection(
    $connection_id,
    $user_id
);

if (!$connection) {

    echo json_encode([
        "success" => false,
        "error" => "Connection not found"
    ]);

    exit;
}

/* APP DATABASE */
$db = getAppDB();

/* GET HISTORY */
$stmt = $db->prepare("
    SELECT question, sql_query, mode
    FROM query_history
    WHERE connection_id = ?
    AND database_name = ?
    ORDER BY id DESC
    LIMIT 20
");

$stmt->bind_param(
    "is",
    $connection_id,
      $database
);

$stmt->execute();

$res = $stmt->get_result();

$history = [];

while ($row = $res->fetch_assoc()) {
    $history[] = $row;
}

$stmt->close();
$db->close();

/* RESPONSE */
echo json_encode([
    "success" => true,
    "history" => $history
]);