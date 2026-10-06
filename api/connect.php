<?php

require_once "crypto.php";
require_once "db.php";
require_once "auth_helpers.php";

/*
 * 2026-09-23 — Server-side-only connection diagnostics.
 * Replaced:
 * error_reporting(E_ALL);
 * ini_set('display_errors', 1);
 *
 * with logging to the private nireas_asksql PHP-FPM error log.  Browser
 * responses remain generic, so connection details cannot be disclosed to a
 * caller.  The diagnostic helpers below deliberately log only allow-listed
 * metadata and failure categories; they never log a request payload,
 * credential, SSH stderr or PDO exception message.
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

function asksql_connect_log_event(string $event, array $context): void
{
    $safe = [
        'event' => $event,
        'user_id' => isset($context['user_id']) ? (int)$context['user_id'] : null,
        'connection_id' => isset($context['connection_id']) ? (int)$context['connection_id'] : null,
        'new_connection' => !empty($context['new_connection']),
        'db_type' => isset($context['db_type']) ? (string)$context['db_type'] : null,
        'ssh_port' => isset($context['ssh_port']) ? (int)$context['ssh_port'] : null,
        'exit_code' => isset($context['exit_code']) ? (int)$context['exit_code'] : null,
        'failure_category' => isset($context['failure_category'])
            ? (string)$context['failure_category']
            : null,
        'pdo_code' => isset($context['pdo_code']) ? (string)$context['pdo_code'] : null,
    ];

    error_log('[AskSQL connect] ' . json_encode(
        $safe,
        JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    ));
}

function asksql_connect_ssh_failure_category(array $output): string
{
    // Categorise locally but do not persist arbitrary SSH stderr.
    $stderr = strtolower(implode("\n", array_map('strval', $output)));

    if (str_contains($stderr, 'permission denied')) {
        return 'ssh_authentication_failed';
    }
    if (str_contains($stderr, 'host key verification failed')) {
        return 'ssh_host_key_verification_failed';
    }
    if (str_contains($stderr, 'connection refused')) {
        return 'ssh_connection_refused';
    }
    if (str_contains($stderr, 'connection timed out') || str_contains($stderr, 'operation timed out')) {
        return 'ssh_timeout';
    }
    if (str_contains($stderr, 'could not resolve hostname') || str_contains($stderr, 'name or service not known')) {
        return 'ssh_hostname_unresolved';
    }
    if (str_contains($stderr, 'address already in use')) {
        return 'ssh_local_port_unavailable';
    }

    return 'ssh_nonzero_exit';
}

function asksql_connect_pdo_failure_category(Throwable $exception): string
{
    // Do not write the raw exception: some drivers embed user/host context.
    $message = strtolower($exception->getMessage());

    if (str_contains($message, 'access denied') || str_contains($message, 'authentication')) {
        return 'db_authentication_failed';
    }
    if (str_contains($message, 'connection refused')) {
        return 'db_connection_refused';
    }
    if (str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
        return 'db_timeout';
    }
    if (str_contains($message, 'unknown database') || str_contains($message, 'does not exist')) {
        return 'db_database_not_found';
    }

    return 'db_pdo_exception';
}

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


/*
 * 2026-09-21: replaced the complete shell-based SSH tunnel block below.
 * It used exec(), sshpass and a shell command; credentials could reach
 * process arguments and input validation depended on shell escaping.
 * Replaced block:
 * // SSH TUNNEL 
 * if ((int)$useSSH === 1) {
 * 
 *     $localPort = rand(20000, 65000);
 * 
 *     $remoteDbPort =
 *         ($type === 'mysql')
 *         ? 3306
 *         : 5432;
 * 
 *     $ssh_host = escapeshellarg($ssh_host_input);
 *     $ssh_user = escapeshellarg($ssh_user_input);
 *     $escaped_host = escapeshellarg($host);
 * 
 *     if (!empty($ssh_pass_input)) {
 * 
 *         $ssh_pass = escapeshellarg($ssh_pass_input);
 *         $cmd = "sshpass -p $ssh_pass ssh "
 *              . "-o UserKnownHostsFile=/dev/null "
 *              . "-o StrictHostKeyChecking=no "
 *              . "-o ExitOnForwardFailure=yes "
 *              . "-o LogLevel=ERROR "
 *              . "-f -N "
 *              . "-L {$localPort}:{$escaped_host}:{$remoteDbPort} "
 *              . "-p {$ssh_port_input} {$ssh_user}@{$ssh_host}";
 * 
 *     } else {
 * 
 *         $cmd = "ssh "
 *              . "-o UserKnownHostsFile=/dev/null "
 *              . "-o StrictHostKeyChecking=no "
 *              . "-o ExitOnForwardFailure=yes "
 *              . "-o LogLevel=ERROR "
 *              . "-f -N "
 *              . "-L {$localPort}:{$escaped_host}:{$remoteDbPort} "
 *              . "-p {$ssh_port_input} {$ssh_user}@{$ssh_host}";
 *     }
 * 
 *     $homeDir = sys_get_temp_dir();
 * 
 *     exec(
 *         "HOME=" . escapeshellarg($homeDir) . " " . $cmd . " 2>&1",
 *         $output,
 *         $result
 *     );
 * 
 *     if ($result !== 0) {
 * 
 *         echo json_encode([
 *             "error" => "SSH tunnel failed",
 *             "debug" => implode("\n", $output)
 *         ]);
 * 
 *         exit;
 *     }
 * 
 *     usleep(500000);
 * 
 *     $host = "127.0.0.1";
 *     $port = $localPort;
 * }
 * 
 * 
 * // CONNECT PDO 
 *
 * Reason: tunnel creation now uses the restricted local Unix-socket broker,
 * argument-vector ssh execution and an inherited password FD (no shell).
 */
// CONNECT PDO
if ((int)$useSSH === 1) {
    $localPort = random_int(20000, 65000);
    $remoteDbPort = ($type === 'mysql') ? 3306 : 5432;
    $sshPort = filter_var($ssh_port_input, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 65535],
    ]);
    if ($sshPort === false) {
        asksql_connect_log_event('invalid_ssh_port', [
            'user_id' => $user_id,
            'connection_id' => $connection_id,
            'new_connection' => !$connection_id,
            'db_type' => $type,
            'failure_category' => 'invalid_ssh_port',
        ]);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid SSH port.']);
        exit;
    }

    /*
     * 2026-09-21: the local broker was withdrawn at the operator's request.
     * exec() is enabled only in the nireas_asksql FPM pool. Every dynamic shell
     * argument below is escaped as one complete argument; SSH options are fixed.
     */
    $sshHome = '/srv/nireas.iee.ihu.gr/apps/nireas_asksql/tmp';
    $forward = '127.0.0.1:' . $localPort . ':' . (string)$host . ':' . $remoteDbPort;
    $target = (string)$ssh_user_input . '@' . (string)$ssh_host_input;
    $ssh = 'ssh '
         . '-o UserKnownHostsFile=' . escapeshellarg($sshHome . '/asksql-known_hosts') . ' '
         . '-o StrictHostKeyChecking=accept-new '
         . '-o ExitOnForwardFailure=yes -o LogLevel=ERROR -f -N '
         . '-L ' . escapeshellarg($forward) . ' '
         . '-p ' . escapeshellarg((string)$sshPort) . ' '
         . escapeshellarg($target);
    $cmd = empty($ssh_pass_input)
        ? $ssh
        : 'sshpass -p ' . escapeshellarg((string)$ssh_pass_input) . ' ' . $ssh;

    exec(
        'HOME=' . escapeshellarg($sshHome) . ' ' . $cmd . ' 2>&1',
        $output,
        $result
    );
    if ($result !== 0) {
        asksql_connect_log_event('ssh_tunnel_failed', [
            'user_id' => $user_id,
            'connection_id' => $connection_id,
            'new_connection' => !$connection_id,
            'db_type' => $type,
            'ssh_port' => $sshPort,
            'exit_code' => $result,
            'failure_category' => asksql_connect_ssh_failure_category($output),
        ]);
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => 'SSH tunnel failed.']);
        exit;
    }

    usleep(500000);
    $host = '127.0.0.1';
    $port = $localPort;
}

// CONNECT PDO
try {
    if ($type === 'mysql') {
            // If database is empty, connect without specifying a database
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

    asksql_connect_log_event('pdo_connection_failed', [
        'user_id' => $user_id,
        'connection_id' => $connection_id,
        'new_connection' => !$connection_id,
        'db_type' => $type,
        'failure_category' => asksql_connect_pdo_failure_category($e),
        'pdo_code' => $e->getCode(),
    ]);

    echo json_encode([
        "error" => "Connection failed"
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
