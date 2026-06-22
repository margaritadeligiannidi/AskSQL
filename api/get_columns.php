<?php

header('Content-Type: application/json; charset=utf-8');

require_once "db_connection.php";


$table = trim($_GET['table'] ?? '');

if (!$table) {
    echo json_encode(["error" => "Missing params"]);
    exit;
}

try {

    /*  GET COLUMNS */
    if ($config['type'] === 'mysql') {

$stmt = $pdo->prepare("
    SELECT
        column_name   AS COLUMN_NAME,
        column_type   AS COLUMN_TYPE,
        is_nullable   AS IS_NULLABLE,
        column_key    AS COLUMN_KEY,
        column_default AS COLUMN_DEFAULT,
        extra         AS EXTRA
    FROM information_schema.columns
    WHERE table_schema = :db
      AND table_name = :table
");

        $stmt->execute([
            "db" => $dbName,
            "table" => $table
        ]);

        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {

      $stmt = $pdo->prepare("
    SELECT
        column_name,
        data_type,
        is_nullable,
        column_default
    FROM information_schema.columns
    WHERE table_name = :table
");
        $stmt->execute([
            "table" => $table
        ]);

        $rawColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Normalize to MySQL format
        $columns = [];

        foreach ($rawColumns as $col) {
            $columns[] = [
                "COLUMN_NAME" => $col['column_name'],
                "COLUMN_TYPE" => $col['data_type'],
                "IS_NULLABLE" => $col['is_nullable'],
                "COLUMN_KEY" => "",
                "COLUMN_DEFAULT" => $col['column_default'],
                "EXTRA" => ""
            ];
        }
    }

    echo json_encode([
        "success" => true,
        "columns" => $columns
    ]);

} catch (Exception $e) {

    error_log("GET COLUMNS ERROR: " . $e->getMessage());

    echo json_encode([
        "error" => "Failed to fetch columns"
    ]);
}