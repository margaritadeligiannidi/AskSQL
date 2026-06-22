<?php
header('Content-Type: application/json; charset=utf-8');

require_once "db_connection.php";

if (!isset($auth)) {
    respond_json([
        "success" => false,
        "error" => "Unauthorized"
    ], 401);
}

// DEBUG 
ini_set('display_errors', 0);
error_reporting(E_ALL);

// GLOBAL ERROR HANDLER 
set_exception_handler(function($e) {
    echo json_encode([
        "success" => false,
        "error" => "Server error"
    ]);
    exit;
});

set_error_handler(function() {
    echo json_encode([
        "success" => false,
        "error" => "Server error"
    ]);
    exit;
});



/* INPUT */
$data = json_decode(file_get_contents("php://input"), true);

$sql = trim($data['sql'] ?? '');
$confirmed = !empty($data['confirmed']);




if (!$sql ) {
    echo json_encode(["success" => false, "error" => "Missing data"]);
    exit;
}


/*  ROLE CHECK */
$role = $auth['role'] ?? 'demo';

// πρώτη λέξη του query
$command = strtoupper(preg_replace('/\s+/', '', strtok($sql, " ")));

if ($role !== 'full' && $role !== 'admin') {

    if ($command !== 'SELECT') {
        echo json_encode([
            "success" => false,
            "error" => "Only SELECT queries are allowed for your account"
        ]);
        exit;
    }
}


/*BASIC SANITIZATION */
$sql = trim($sql);

/* REMOVE TRAILING ; */
$sql = rtrim($sql, ";");

/* BLOCK MULTI STATEMENTS */
if (strpos($sql, ';') !== false) {

    echo json_encode([
        "success" => false,
        "error" => "Multiple queries are not allowed"
    ]);

    exit;
}
function isForbidden($sql) {

    return preg_match(
        '/\b(drop\s+database|create\s+user|grant|revoke|shutdown|file|outfile|load_file)\b/i',
        $sql
    );
}


/* SECURITY FUNCTIONS*/
function isDangerous($sql) {
    return preg_match('/\b(drop|delete|truncate|alter|update|insert)\b/i', $sql);
}

/* SECURITY CHECK */
if (isDangerous($sql) && !$confirmed) {
    echo json_encode([
        "success" => false,
        "error" => "CONFIRM_REQUIRED"
    ]);
    exit;
}
try {

    // AUTO LIMIT PROTECTION
    if (
        preg_match('/^\s*select\b/i', $sql) &&
        !preg_match('/limit\s+\d+/i', $sql)
    ) {
        $sql = rtrim($sql, " ;") . " LIMIT 100";
    }

    $stmt = $pdo->query($sql);

    // SELECT queries
    if ($stmt->columnCount() > 0) {

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "success" => true,
            "type" => "select",
            "data" => $rows
        ]);

    } else {

        echo json_encode([
            "success" => true,
            "type" => "action",
            "data" => [],
            "affected_rows" => $stmt->rowCount()
        ]);
    }

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "error" => $e->getMessage(),
        "sql" => $sql
    ]);
}

exit;

