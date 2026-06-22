<?php

header('Content-Type: application/json; charset=utf-8');
require_once "db_connection.php";


try {

    /*  MYSQL */

    if (strtolower($db['type']) === 'mysql') {

        $stmt = $pdo->query("SHOW TABLES");

        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    }

    /*  POSTGRESQL */

    else {

        $stmt = $pdo->query("
            SELECT table_name
            FROM information_schema.tables
            WHERE table_type = 'BASE TABLE'
            AND table_schema NOT IN ('pg_catalog', 'information_schema')
        ");

        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    echo json_encode([
        "success" => true,
        "tables" => $tables
    ]);

} catch (Exception $e) {

    error_log("GET_TABLES ERROR: " . $e->getMessage());

    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}