<?php

header('Content-Type: application/json');

require_once "db.php";
require_once "auth_helpers.php";

/* AUTH */
$auth = require_auth();

$user_id = (int)$auth['user_id'];

/* INPUT */
$data = json_decode(
    file_get_contents("php://input"),
    true
);

$connection_id =(int)($data['connection_id'] ?? 0);
$database = trim($data['database'] ?? '');
if (!$connection_id) {

    echo json_encode([
        "success" => false,
        "error" => "Missing data"
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



if (!$database) {

    echo json_encode([
        "success" => false,
        "error" => "Missing database"
    ]);

    exit;
}

/* DELETE HISTORY */
try {

    $db = getAppDB();

   $stmt = $db->prepare("
    DELETE FROM query_history
    WHERE connection_id = ?
    AND database_name = ?
");

$stmt->bind_param(
    "is",
    $connection_id,
    $database
);

    $stmt->execute();

    $deleted = $stmt->affected_rows;

    $stmt->close();
    $db->close();

    echo json_encode([
        "success" => true,
        "deleted_rows" => $deleted
    ]);

} catch (Exception $e) {

    error_log($e->getMessage());

    echo json_encode([
        "success" => false,
        "error" => "Failed to delete history"
    ]);
}