<?php

header('Content-Type: application/json; charset=utf-8');

require_once "db_connection.php";

/* CONFIG*/

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

/* MYSQL */

if ($type === 'mysql') {

    /* SCHEMA DUMP */

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
 

    $schemaOutput .= trim($dumpOutput);

    /* SAMPLE DATA */

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

    $tablesRes = $mysqli->query("SHOW TABLES");

    if ($tablesRes) {

        $schemaOutput .= "\n\n";
        $schemaOutput .= "SAMPLE DATA\n";
  

        while ($tableRow = $tablesRes->fetch_array()) {

            $table = $tableRow[0];

            $schemaOutput .= "\n";
            $schemaOutput .= "TABLE: $table\n";

            $sampleRes = $mysqli->query(
                "SELECT * FROM `$table` LIMIT 1"
            );

            if ($sampleRes && $sampleRes->num_rows > 0) {

                $sample = $sampleRes->fetch_assoc();

                foreach ($sample as $column => $value) {

                    if ($value === null) {
                        $value = "NULL";
                    }

                    $value = str_replace(
                        "\n",
                        " ",
                        (string)$value
                    );

                    $schemaOutput .=
                        $column . " = " . $value . "\n";
                }

            } else {

                $schemaOutput .=
                    "No sample data available\n";
            }
        }
    }

    $mysqli->close();
}

/*POSTGRESQL*/

elseif (
    $type === 'postgresql' ||
    $type === 'pgsql' ||
    $type === 'postgres'
) {

    /*  SCHEMA DUMP */

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

    $schemaOutput .= trim($dumpOutput);

    /*  CONNECT */

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

    /* TABLES */

   $tables = pg_query(
    $conn,
    "
    SELECT schemaname, tablename
    FROM pg_tables
    WHERE schemaname NOT IN (
        'pg_catalog',
        'information_schema'
    )
    ORDER BY schemaname, tablename
    "
);

    if (!$tables) {

        echo json_encode([
            "error" => "Failed to fetch PostgreSQL tables"
        ]);

        exit;
    }

    $schemaOutput .= "\n\n";
    $schemaOutput .= "SAMPLE DATA\n";


    while ($t = pg_fetch_assoc($tables)) {

        $schemaName = $t['schemaname'];
$table = $t['tablename'];

        $schemaOutput .= "\n";
        $schemaOutput .= "TABLE: $table\n";

        $escapedTable = str_replace('"', '""', $table);

       $escapedSchema = str_replace('"', '""', $schemaName);

$sampleQuery =
    'SELECT * FROM "' .
    $escapedSchema .
    '"."' .
    $escapedTable .
    '" LIMIT 1';
        $sample = pg_query(
            $conn,
            $sampleQuery
        );

        if (!$sample) {

            $schemaOutput .=
                "Failed to fetch sample row\n";

            continue;
        }

        if (pg_num_rows($sample) > 0) {

            $row = pg_fetch_assoc($sample);

            foreach ($row as $column => $value) {

                if ($value === null) {
                    $value = "NULL";
                }

                $value = str_replace(
                    "\n",
                    " ",
                    (string)$value
                );

                $schemaOutput .=
                    $column . " = " . $value . "\n";
            }

        } else {

            $schemaOutput .=
                "No sample data available\n";
        }
    }

    pg_close($conn);
}

/* UNSUPPORTED */

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

    "schema" => $schemaOutput

]);

exit;