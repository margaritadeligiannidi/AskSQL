<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ob_start();

set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(function() {
    $err = error_get_last();
    if ($err) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Internal server error', 'message' => $err['message'], 'file' => $err['file'], 'line' => $err['line']]);
        exit;
    }
});

header('Content-Type: application/json; charset=utf-8');

/* DATABASE connection */
require_once "db.php";
require_once "auth_helpers.php";
$conn = getAppDB();

/* INPUT */
$data = json_decode(
    file_get_contents("php://input"),
    true
);

$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';

if (!$username || !$password) {
    ob_clean();
    echo json_encode(["error" => "Missing fields"]);
    exit;
}

/* GET USER */
$stmt = $conn->prepare("
SELECT id, username, password, role, is_verified 
FROM users 
WHERE username = ?
");

$stmt->bind_param("s", $username);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 1) {

    $user = $result->fetch_assoc();

    if (password_verify($password, $user['password'])) {
        if ($user['is_verified'] == 0) {
            ob_clean();
            echo json_encode([
                "error" => "Please verify your email before logging in"
            ]);
            exit;
        }

        // Create JWT payload (issued now, expire in 8 hours)
        $payload = [
            "user_id" => (int)$user['id'],
            "username" => $user['username'],
            "role" => $user['role'],
            "iat" => time(),
            "exp" => time() + 8 * 3600
        ];

        $token = jwt_encode($payload);

        ob_clean();
        echo json_encode([
            "success" => true,
            "access_token" => $token,
            "role" => $user['role']
        ]);
        exit;
    }
}

/* ERROR */
sleep(1);

ob_clean();
echo json_encode(["error" => "Wrong username or password"]);

$stmt->close();
$conn->close();