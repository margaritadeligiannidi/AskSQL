<?php

require_once __DIR__ . "/auth_helpers.php";
require_once __DIR__ . "/db.php";

header('Content-Type: application/json; charset=utf-8');

/*AUTH*/
$auth = require_auth();

$user_id = (int)$auth['user_id'];

/*  GET CONNECTION ID*/
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

$connection_id =
    $data['connection_id']
    ?? $_POST['connection_id']
    ?? $_GET['connection_id']
    ?? null;


/* VALIDATE CONNECTION ID*/
if (!$connection_id) {

    respond_json([
        "error" => "Missing connection_id"
    ], 400);
}

/*  GET USER CONNECTION*/
$connection = get_user_connection(
    (int)$connection_id,
    $user_id
);


/* DB CONFIG*/
$db = get_db_config_from_connection(
    $connection,
    $data['database'] ?? $_POST['database'] ?? $_GET['database'] ?? null
);

/* NORMALIZE DB TYPE */
if ($db['type'] === 'postgresql') {
    $db['type'] = 'pgsql';
}

/* VALIDATE DB TYPE */
if (!in_array($db['type'], ['mysql', 'pgsql'])) {

    respond_json([
        "error" => "Invalid DB type"
    ], 400);
}
/* SSH TUNNEL */

if ((int)$db['ssh'] === 1) {

    $localPort = rand(20000, 65000);

    $remoteDbPort =
        ($db['type'] === 'mysql')
        ? 3306
        : 5432;

    $ssh_host = escapeshellarg($db['ssh_host']);
    $ssh_user = escapeshellarg($db['ssh_user']);

    $escaped_host = escapeshellarg($db['host']);

    if (!empty($db['ssh_password'])) {

        $ssh_pass = escapeshellarg($db['ssh_password']);

        $cmd = "sshpass -p $ssh_pass ssh "
             . "-o UserKnownHostsFile=/dev/null "
             . "-o StrictHostKeyChecking=no "
             . "-o ExitOnForwardFailure=yes "
             . "-o LogLevel=ERROR "
             . "-f -N "
             . "-L {$localPort}:{$escaped_host}:{$remoteDbPort} "
             . "-p {$db['ssh_port']} {$ssh_user}@{$ssh_host}";

    } else {

        $cmd = "ssh "
             . "-o UserKnownHostsFile=/dev/null "
             . "-o StrictHostKeyChecking=no "
             . "-o ExitOnForwardFailure=yes "
             . "-o LogLevel=ERROR "
             . "-f -N "
             . "-L {$localPort}:{$escaped_host}:{$remoteDbPort} "
             . "-p {$db['ssh_port']} {$ssh_user}@{$ssh_host}";
    }

    $homeDir = sys_get_temp_dir();

    exec(
        "HOME=" . escapeshellarg($homeDir) . " " . $cmd . " 2>&1",
        $output,
        $result
    );

    if ($result !== 0) {

        respond_json([
            "error" => "SSH tunnel failed",
            "debug" => implode("\n", $output)
        ], 500);
    }

    usleep(500000);

    $db['host'] = "127.0.0.1";
    $db['port'] = $localPort;
}

/*  CREATE PDO */
try {

    if ($db['type'] === 'mysql') {

        $dsn =
            "mysql:host={$db['host']};" .
            "port={$db['port']};" .
            "dbname={$db['database']};" .
            "charset=utf8mb4";

    } else {

        $dsn =
            "pgsql:host={$db['host']};" .
            "port={$db['port']};" .
            "dbname={$db['database']};" .
            "options='--client_encoding=UTF8'";
    }

    $pdo = new PDO(
        $dsn,
        $db['username'],
        $db['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]
    );

} catch (Exception $e) {

    error_log("USER DB ERROR: " . $e->getMessage());

    respond_json([
        "error" => "DB connection failed",
        "debug" => $e->getMessage()
    ], 500);
}