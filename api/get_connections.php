<?php
require_once "auth_helpers.php";
require_once "db.php";

header('Content-Type: application/json; charset=utf-8');

$auth = require_auth();
$user_id = (int)$auth['user_id'];

/*  DB CONNECTION */
$conn = getAppDB();

/* GET CONNECTIONS */
$stmt = $conn->prepare("
 SELECT
    id,
    user_id,
    db_type,
    host,
    port,
    db_name,
    db_username,
    ssh,
    name,
    ssh_host,
    ssh_port,
    ssh_user,
    created_at
FROM connections
WHERE user_id = ?
ORDER BY id DESC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

$connections = [];

while ($row = $result->fetch_assoc()) {

    $connections[] = $row;
}

echo json_encode([
    "success" => true,
    "user_id" => $user_id,
    "connections" => $connections
]);

$stmt->close();
$conn->close();