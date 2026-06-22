<?php

header('Content-Type: application/json; charset=utf-8');

require_once "db_connection.php";

/* CONFIG */

$dbName = $db['database'];

if (!$dbName) {

    echo json_encode([
        "error" => "Missing database parameter"
    ]);

    exit;
}

$type = strtolower($db['type']);

$host = $db['host'];
$port = $db['port'];
$user = $db['username'];
$pass = $db['password'];

$schemaOutput = "";

/*MYSQL */

if ($type === 'mysql') {

    $mysqli = new mysqli(
        $host,
        $user,
        $pass,
        $dbName,
        (int)$port
    );

    if ($mysqli->connect_error) {

        echo json_encode([
            "error" => $mysqli->connect_error
        ]);

        exit;
    }

    $mysqli->set_charset("utf8mb4");

    $tables = $mysqli->query("SHOW TABLES");

    while ($tableRow = $tables->fetch_array()) {

        $table = $tableRow[0];

        $columns = [];

        $cols = $mysqli->query(
            "SHOW COLUMNS FROM `$table`"
        );

        while ($col = $cols->fetch_assoc()) {

            $columns[] = $col['Field'];
        }

        $schemaOutput .=
            $table .
            "(" .
            implode(",", $columns) .
            ")\n";
    }

    $mysqli->close();
}

/* POSTGRESQL */

elseif (
    $type === 'postgresql' ||
    $type === 'pgsql' ||
    $type === 'postgres'
) {

    $connString =
        "host=$host " .
        "port=$port " .
        "dbname=$dbName " .
        "user=$user " .
        "password=$pass";

    $conn = pg_connect($connString);

    if (!$conn) {

        echo json_encode([
            "error" => "PostgreSQL connection failed"
        ]);

        exit;
    }

    $tables = pg_query(
        $conn,
        "
        SELECT schemaname, tablename
        FROM pg_tables
        WHERE schemaname NOT IN
        ('pg_catalog','information_schema')
        ORDER BY tablename
        "
    );

    while ($tableRow = pg_fetch_assoc($tables)) {

        $schema = $tableRow['schemaname'];
        $table  = $tableRow['tablename'];

        $cols = pg_query(
            $conn,
            "
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = '$schema'
            AND table_name = '$table'
            ORDER BY ordinal_position
            "
        );

        $columns = [];

        while ($col = pg_fetch_assoc($cols)) {

            $columns[] = $col['column_name'];
        }

        $schemaOutput .=
            $table .
            "(" .
            implode(",", $columns) .
            ")\n";
    }

    pg_close($conn);
}

/*UNSUPPORTED */

else {

    echo json_encode([
        "error" => "Unsupported database type",
        "type" => $type
    ]);

    exit;
}

/* RESPONSE*/

echo json_encode([

    "success" => true,
    "schema" => trim($schemaOutput)

]);

exit;
?>