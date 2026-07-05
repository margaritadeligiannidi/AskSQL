<?php

require_once "db.php";

header('Content-Type: application/json; charset=utf-8');

/* JSON INPUT */

$data = json_decode(
    file_get_contents("php://input"),
    true
) ?? [];

$token = $data['token'] ?? '';

$message = "";
$type = "danger";
$success = false;

$conn = getAppDB();

/* TOKEN CHECK */

if (!$token) {

    $message = "Invalid verification link.";

} else {

    $stmt = $conn->prepare("
        SELECT
            id,
            verify_expires,
            is_verified
        FROM users
        WHERE verification_token = ?
    ");

    $stmt->bind_param("s", $token);

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {

        $message = "Invalid or used token.";

    } else {

        $user = $result->fetch_assoc();

        /* ALREADY VERIFIED */

        if ((int)$user['is_verified'] === 1) {

            $message = "Account already verified.";
            $type = "info";

        }

        /* TOKEN EXPIRED */

        elseif (strtotime($user['verify_expires']) < time()) {

            $message = "Verification link expired.";

        }

        /* VERIFY ACCOUNT */

        else {

            $stmt = $conn->prepare("
                UPDATE users
                SET
                    is_verified = 1,
                    verification_token = NULL,
                    verify_expires = NULL
                WHERE id = ?
            ");

            $stmt->bind_param("i", $user['id']);

            $stmt->execute();

            $message = "Your account has been verified! You can now login.";

            $type = "success";
            $success = true;
        }
    }
}

$conn->close();

echo json_encode([
    "success" => $success,
    "type"    => $type,
    "message" => $message
]);

exit;