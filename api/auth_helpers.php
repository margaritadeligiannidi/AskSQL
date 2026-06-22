<?php
require_once __DIR__ . "/config.php";
loadEnv(__DIR__ . "/../.env");
require_once __DIR__ . "/db.php";

function get_jwt_secret() {
    return $_ENV['JWT_SECRET'] ?? 'asksql-jwt-secret-change-this';
}

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

function jwt_encode(array $payload, int $ttl = 3600) {
    $header = [
        'alg' => 'HS256',
        'typ' => 'JWT'
    ];

    $payload['iat'] = time();
    $payload['exp'] = time() + $ttl;

    $headerEncoded = base64url_encode(json_encode($header));
    $payloadEncoded = base64url_encode(json_encode($payload));

    $signature = hash_hmac(
        'sha256',
        $headerEncoded . '.' . $payloadEncoded,
        get_jwt_secret(),
        true
    );

    return $headerEncoded . '.' . $payloadEncoded . '.' . base64url_encode($signature);
}

function jwt_decode(string $token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }

    [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

    $payloadJson = base64url_decode($payloadEncoded);
    if ($payloadJson === false) {
        return null;
    }

    $payload = json_decode($payloadJson, true);
    if (!is_array($payload)) {
        return null;
    }

    $expected = hash_hmac(
        'sha256',
        $headerEncoded . '.' . $payloadEncoded,
        get_jwt_secret(),
        true
    );

    $signature = base64url_decode($signatureEncoded);
    if ($signature === false || !hash_equals($expected, $signature)) {
        return null;
    }

    if (isset($payload['exp']) && time() > $payload['exp']) {
        return null;
    }

    return $payload;
}

function get_bearer_token() {
    $authHeader = null;

    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (!empty($_SERVER['Authorization'])) {
        $authHeader = $_SERVER['Authorization'];
    } elseif (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (!empty($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
        } elseif (!empty($headers['authorization'])) {
            $authHeader = $headers['authorization'];
        }
    }

    if ($authHeader && preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
        return $matches[1];
    }

    return null;
}

function respond_json($payload, int $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function unauthorized() {
    respond_json(['error' => 'Unauthorized'], 401);
}

function require_auth() {
    $token = get_bearer_token();
    if (!$token) {
        unauthorized();
    }

    $payload = jwt_decode($token);
    if (!$payload || !isset($payload['user_id'])) {
        unauthorized();
    }

    return $payload;
}

function get_user_by_id(int $user_id) {
    $db = getAppDB();
    $stmt = $db->prepare("SELECT id, username, email, role, created_at FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user ?: null;
}

function require_admin(array $authPayload) {
    $user = get_user_by_id((int)$authPayload['user_id']);
    if (!$user || ($user['role'] ?? '') !== 'admin') {
        unauthorized();
    }
    return $user;
}

function get_user_connection(int $connection_id, int $user_id) {

    require_once __DIR__ . '/crypto.php';

    $db = getAppDB();

    /* FIRST: TRY SAVED CONNECTIONS */

    $stmt = $db->prepare("
        SELECT *
        FROM connections
        WHERE id = ? AND user_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("ii", $connection_id, $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    $connection = $result->fetch_assoc();

    $stmt->close();

    /* FOUND IN CONNECTIONS*/

    if ($connection) {

        if (!empty($connection['db_password'])) {
            $connection['db_password'] =
                decryptData($connection['db_password']);
        }

        if (!empty($connection['ssh_password'])) {
            $connection['ssh_password'] =
                decryptData($connection['ssh_password']);
        }

        return $connection;
    }


    return [

        'id' => $connection_id,

        'db_type' => $config['type'] ?? null,
        'host' => $config['host'] ?? null,
        'port' => $config['port'] ?? null,

        'db_username' => $config['username'] ?? null,
        'db_password' => $config['password'] ?? null,

        'db_name' => $config['database'] ?? null,

        'ssh' => $config['ssh'] ?? 0,

        'ssh_host' => $config['ssh_host'] ?? null,
        'ssh_port' => $config['ssh_port'] ?? null,
        'ssh_user' => $config['ssh_user'] ?? null,
        'ssh_password' => $config['ssh_password'] ?? null
    ];
}

function get_db_config_from_connection(array $connection, ?string $database = null) {
    return [
        'type' => $connection['db_type'],
        'host' => $connection['host'],
        'port' => $connection['port'],
        'username' => $connection['db_username'],
        'password' => $connection['db_password'],
        'database' => $database ?? $connection['db_name'],
        'ssh' => $connection['ssh'],
        'ssh_host' => $connection['ssh_host'],
        'ssh_port' => $connection['ssh_port'],
        'ssh_user' => $connection['ssh_user'],
        'ssh_password' => $connection['ssh_password'],
    ];
}
