<?php

require_once "db.php";
require_once "auth_helpers.php";

header("Content-Type: application/json; charset=utf-8");

/* AUTH */
$auth = require_auth();

$user_id = (int)$auth['user_id'];

/* INPUT */
$data = json_decode(file_get_contents("php://input"), true);

$connection_id = (int)($data['connection_id'] ?? 0);

$question = trim($data['question'] ?? "");
$sql = trim($data['sql'] ?? "");
$mode = trim($data['mode'] ?? "sql");

/* VALIDATION */
if (!$connection_id) {

    echo json_encode([
        "found" => false,
        "error" => "Missing connection_id"
    ]);

    exit;
}

/* CHECK OWNERSHIP */
$connection = get_user_connection(
    $connection_id,
    $user_id
);

if (!$connection) {

    echo json_encode([
        "found" => false,
        "error" => "Connection not found"
    ]);

    exit;
}

/* NORMALIZE SQL */
function normalize_sql($sql) {

    $sql = mb_strtolower($sql);

    $sql = rtrim($sql, ";");

    $sql = preg_replace('/\s+/', ' ', $sql);

    return trim($sql);
}

try {

    $db = getAppDB();

    /* SQL MODE */
    if ($mode === "sql") {

        $stmt = $db->prepare("
            SELECT sql_query
            FROM query_history
            WHERE connection_id = ?
            AND mode = 'sql'
            ORDER BY id DESC
        ");

        $stmt->bind_param(
            "i",
            $connection_id
        );

        $stmt->execute();

        $res = $stmt->get_result();

        $normalized_input_sql =
            normalize_sql($sql);

        while ($row = $res->fetch_assoc()) {

            if (
                normalize_sql($row['sql_query'])
                === $normalized_input_sql
            ) {

                echo json_encode([
                    "found" => true,
                    "sql" => $row['sql_query']
                ]);

                exit;
            }
        }

        echo json_encode([
            "found" => false
        ]);

        exit;
    }

    /* NL MODE */
    $stmt = $db->prepare("
        SELECT question, sql_query
        FROM query_history
        WHERE connection_id = ?
        AND question = ?
        AND mode = 'nl'
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->bind_param(
        "is",
        $connection_id,
        $question
    );

    $stmt->execute();

    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {

        echo json_encode([
            "found" => true,
            "question" => $row['question'],
            "sql" => $row['sql_query']
        ]);

    } else {

        echo json_encode([
            "found" => false
        ]);
    }

    $stmt->close();
    $db->close();

} catch (Throwable $e) {

    echo json_encode([
        "found" => false,
        "error" => "Database error"
    ]);
}
?>