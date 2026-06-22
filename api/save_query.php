<?php

header("Content-Type: application/json; charset=utf-8");

require_once "db.php";
require_once "auth_helpers.php";


/* AUTH */
$auth = require_auth();

$user_id = (int)$auth['user_id'];

/* NORMALIZE SQL */
function normalize_sql($sql) {

    // lowercase
    $sql = mb_strtolower($sql);

    // remove final ;
    $sql = rtrim($sql, ";");

    // collapse spaces/newlines/tabs
    $sql = preg_replace('/\s+/', ' ', $sql);

    return trim($sql);
}

/* INPUT */
$data = json_decode(file_get_contents("php://input"), true);

$connection_id = (int)($data['connection_id'] ?? 0);

$question = trim($data['question'] ?? '');
$sql = trim($data['sql'] ?? '');
$mode = trim($data['mode'] ?? 'sql');
$database = trim($data['database'] ?? '');

/* NORMALIZED VERSION */
$normalized_sql = normalize_sql($sql);

/* VALIDATION */
if (!$connection_id) {

    echo json_encode([
        "success" => false,
        "error" => "Missing connection_id"
    ]);

    exit;
}

if (!$sql) {

    echo json_encode([
        "success" => false,
        "error" => "Missing SQL"
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
/* CHECK CONNECTION OWNERSHIP */
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

try {

    /* DATABASE CONNECTION */
    $db = getAppDB();

    /* DUPLICATE CHECK
       SAME SQL + SAME MODE = DUPLICATE
    */
 $check = $db->prepare("
    SELECT id, sql_query
    FROM query_history
    WHERE connection_id = ?
    AND database_name = ?
    AND mode = ?
");

$check->bind_param(
    "iss",
    $connection_id,
    $database,
    $mode
);

    $check->execute();

    $result = $check->get_result();

    $is_duplicate = false;

    while ($row = $result->fetch_assoc()) {

        $existing_sql =
            normalize_sql($row['sql_query']);

        if ($existing_sql === $normalized_sql) {

            $is_duplicate = true;
            break;
        }
    }

    $check->close();

    /* STOP IF DUPLICATE */
    if ($is_duplicate) {

        echo json_encode([
            "success" => true,
            "duplicate" => true
        ]);

        exit;
    }

    /* INSERT QUERY */
    $stmt = $db->prepare("
   INSERT INTO query_history
(
    connection_id,
    database_name,
    question,
    sql_query,
    mode
)
VALUES (?, ?, ?, ?, ?)
    ");

   $stmt->bind_param(
    "issss",
    $connection_id,
    $database,
    $question,
    $sql,
    $mode
);

    $stmt->execute();

    $stmt->close();

    /* KEEP ONLY LAST 5 PER MODE */
    $cleanup = $db->prepare("
    DELETE FROM query_history
    WHERE id NOT IN (

        SELECT id FROM (

            SELECT id
            FROM query_history
            WHERE connection_id = ?
            AND database_name = ?
            AND mode = ?
            ORDER BY id DESC
            LIMIT 5

        ) AS t

    )
    AND connection_id = ?
    AND database_name = ?
    AND mode = ?
");

    $cleanup->bind_param(
        "ississ",
        $connection_id,
        $database,
        $mode,
        $connection_id,
        $database,
        $mode
    );

    $cleanup->execute();

    $cleanup->close();

    $db->close();

    echo json_encode([
        "success" => true
    ]);

}catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
} 

?>