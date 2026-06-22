<?php

require_once "crypto.php";
require_once "db.php";
require_once "auth_helpers.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

/* AUTH */
$auth = require_auth();

$user_id = (int)$auth['user_id'];
$appDB = getAppDB();


/* JSON INPUT */
$data = $_POST;

if (empty($data)) {
    $data = json_decode(
        file_get_contents("php://input"),
        true
    ) ?? [];
}

/* RECONNECT MODE */
$connection_id = $data['connection_id'] ?? null;

if ($connection_id) {

    $stmt = $appDB->prepare("
        SELECT *
        FROM connections
        WHERE id = ? AND user_id = ?
        LIMIT 1
    ");

    if (!$stmt) {

        echo json_encode([
            "error" => "Database prepare failed"
        ]);

        exit;
    }

    $stmt->bind_param("ii", $connection_id, $user_id);

    $stmt->execute();

    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {

        $type     = $row['db_type'];
        $host     = $row['host'];
        $port     = $row['port'];
        $username = $row['db_username'];
        $database = $row['db_name'];

        $password = !empty($row['db_password'])
            ? decryptData($row['db_password'])
            : null;

        $useSSH = (int)$row['ssh'] === 1;

        $ssh_host_input = $row['ssh_host'];

        $ssh_user_input = $row['ssh_user'];

        $ssh_pass_input = !empty($row['ssh_password'])
            ? decryptData($row['ssh_password'])
            : null;

        $ssh_port_input = $row['ssh_port'];


    } else {

        echo json_encode([
            "error" => "Connection not found"
        ]);

        exit;
    }

    $stmt->close();

} else {

    /* NORMAL CONNECT */

   $type     = $data['type'] ?? null;
$host     = $data['host'] ?? null;
$port     = $data['port'] ?? null;
$username = $data['username'] ?? null;
$password = $data['password'] ?? null;
$database = $data['database'] ?? null;
$con_name = $data['con_name'] ?? null;

$ssh_host_input = $data['ssh_host'] ?? null;
$ssh_user_input = $data['ssh_user'] ?? null;
$ssh_pass_input = $data['ssh_password'] ?? null;
$ssh_port_input = $data['ssh_port'] ?? null;

    if (!$type || !$host || !$username) {

        echo json_encode([
            "error" => "Missing required fields"
        ]);

        exit;
    }

    $useSSH = strpos($type, '_ssh') !== false;

    $type = str_replace('_ssh', '', $type);
}

/* DEFAULT PORT */

if (empty($port)) {

    $port = ($type === 'mysql')
        ? 3306
        : 5432;
}

/* SAVE ORIGINAL */

$original_host = $host;
$original_port = $port;

/* SSH HANDLING */
if ($useSSH) {

    if (
        empty($ssh_host_input) ||
        empty($ssh_user_input) ||
        empty($ssh_port_input)
    ) {

        echo json_encode([
            "error" => "Missing SSH fields"
        ]);

        exit;
    }
}


/* SSH TUNNEL */

if ((int)$useSSH === 1) {

    $localPort = rand(20000, 65000);

    $remoteDbPort =
        ($type === 'mysql')
        ? 3306
        : 5432;

    $ssh_host = escapeshellarg($ssh_host_input);
    $ssh_user = escapeshellarg($ssh_user_input);

    $escaped_host = escapeshellarg($host);

    if (!empty($ssh_pass_input)) {

        $ssh_pass = escapeshellarg($ssh_pass_input);

        $cmd = "sshpass -p $ssh_pass ssh "
             . "-o UserKnownHostsFile=/dev/null "
             . "-o StrictHostKeyChecking=no "
             . "-o ExitOnForwardFailure=yes "
             . "-o LogLevel=ERROR "
             . "-f -N "
             . "-L {$localPort}:{$escaped_host}:{$remoteDbPort} "
             . "-p {$ssh_port_input} {$ssh_user}@{$ssh_host}";

    } else {

        $cmd = "ssh "
             . "-o UserKnownHostsFile=/dev/null "
             . "-o StrictHostKeyChecking=no "
             . "-o ExitOnForwardFailure=yes "
             . "-o LogLevel=ERROR "
             . "-f -N "
             . "-L {$localPort}:{$escaped_host}:{$remoteDbPort} "
             . "-p {$ssh_port_input} {$ssh_user}@{$ssh_host}";
    }

    $homeDir = sys_get_temp_dir();

    exec(
        "HOME=" . escapeshellarg($homeDir) . " " . $cmd . " 2>&1",
        $output,
        $result
    );

    if ($result !== 0) {

        echo json_encode([
            "error" => "SSH tunnel failed",
            "debug" => implode("\n", $output)
        ]);

        exit;
    }

    usleep(500000);

    $host = "127.0.0.1";
    $port = $localPort;
}


/* CONNECT PDO */

try {


    if ($type === 'mysql') {

        if (!empty($database)) {

            $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";

        } else {

            $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
        }

    } else {

        if (empty($database)) {

            echo json_encode([
                "error" => "Database name is required for PostgreSQL"
            ]);

            exit;
        }

        $dsn = "pgsql:host=$host;port=$port;dbname=$database;options='--client_encoding=UTF8'";
    }

    $pdo = new PDO(
        $dsn,
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );

} catch (Exception $e) {

    echo json_encode([
        "error" => "Connection failed",
        "debug" => $e->getMessage()
    ]);

    exit;
}

/* SAVE CONNECTION (ONLY NEW) */

if (!$connection_id) {

    $encrypted_db_pass = encryptData($password);

    $encrypted_ssh_pass = !empty($ssh_pass_input)
        ? encryptData($ssh_pass_input)
        : null;

    $stmt = $appDB->prepare("
        INSERT INTO connections
        (
            user_id,
            db_type,
            host,
            port,
            db_name,
            db_username,
            db_password,
            ssh,
            name,
            ssh_host,
            ssh_port,
            ssh_user,
            ssh_password
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {

        echo json_encode([
            "error" => "Insert prepare failed"
        ]);

        exit;
    }

    $stmt->bind_param(
        "ississsississ",
        $user_id,
        $type,
        $original_host,
        $original_port,
        $database,
        $username,
        $encrypted_db_pass,
        $useSSH,
        $con_name,
        $ssh_host_input,
        $ssh_port_input,
        $ssh_user_input,
        $encrypted_ssh_pass
    );

    $stmt->execute();

    $new_connection_id = $stmt->insert_id;

    $stmt->close();
}

/* FINAL CONNECTION ID */

$active_connection_id = $connection_id
    ? (int)$connection_id
    : (int)$new_connection_id;

/* CLOSE DB */

$appDB->close();

/* RESPONSE */

echo json_encode([
    "success" => true,
    "connection_id" => $active_connection_id
]);