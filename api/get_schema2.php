<?php

header('Content-Type: application/json; charset=utf-8');

require_once "db_connection.php";

/*  CONFIG */

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

/*  MYSQL */

if ($type === 'mysql') {

    putenv("MYSQL_PWD=" . $pass);

    $escapedHost = escapeshellarg($host);
    $escapedPort = escapeshellarg($port);
    $escapedUser = escapeshellarg($user);
    $escapedDb   = escapeshellarg($dbName);

    $dumpCmd =
        "mysqldump " .
        "-h $escapedHost " .
        "-P $escapedPort " .
        "-u $escapedUser " .
        "$escapedDb " .
        "--no-data " .
        "--single-transaction " .
        "--skip-lock-tables " .
        "--force " .
        "--no-tablespaces " .
        "--skip-comments " .
        "2>&1";

    $dumpOutput = shell_exec($dumpCmd);

    if (!$dumpOutput) {

        echo json_encode([
            "error" => "Failed to generate MySQL schema dump"
        ]);

        exit;
    }

    $schemaOutput = trim($dumpOutput);
}

/*POSTGRESQL */

elseif (
    $type === 'postgresql' ||
    $type === 'pgsql' ||
    $type === 'postgres'
) {

    putenv("PGPASSWORD=" . $pass);

    $escapedHost = escapeshellarg($host);
    $escapedPort = escapeshellarg($port);
    $escapedUser = escapeshellarg($user);
    $escapedDb   = escapeshellarg($dbName);

    $dumpCmd =
        "pg_dump " .
        "-h $escapedHost " .
        "-p $escapedPort " .
        "-U $escapedUser " .
        "-d $escapedDb " .
        "--schema-only " .
        "--no-owner " .
        "--no-privileges " .
        "--no-comments " .
        "2>&1";

    $dumpOutput = shell_exec($dumpCmd);

    if (!$dumpOutput) {

        echo json_encode([
            "error" => "Failed to generate PostgreSQL schema dump"
        ]);

        exit;
    }

    $schemaOutput = trim($dumpOutput);
}

/* UNSUPPORTED */

else {

    echo json_encode([
        "error" => "Unsupported database type",
        "type" => $type
    ]);

    exit;
}

/*  RESPONSE */

echo json_encode([

    "success" => true,
    "schema" => $schemaOutput

]);

exit;