<?php
header('Content-Type: application/json; charset=utf-8');

require_once "db.php";
require_once __DIR__ . "/../libs/PHPMailer-master/src/PHPMailer.php";
require_once __DIR__ . "/../libs/PHPMailer-master/src/SMTP.php";
require_once __DIR__ . "/../libs/PHPMailer-master/src/Exception.php";

set_exception_handler(function($e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
    exit;
});

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/*  INPUT */
$data = json_decode(file_get_contents("php://input"), true);
$email = trim($data['email'] ?? '');

if (!$email) {
    echo json_encode(["success" => false, "error" => "Email required"]);
    exit;
}


/* DATABASE connection */
$db = getAppDB();

/* FIND USER */
$stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode([
        "success" => true,
        "message" => "If the email exists, a reset link was sent"
    ]);
    exit;
}

$user = $res->fetch_assoc();

/* CREATE TOKEN */
$token = bin2hex(random_bytes(32));


$stmt = $db->prepare("
    INSERT INTO password_resets (user_id, token, expires_at)
    VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
");
$stmt->bind_param("is", $user['id'], $token);

$stmt->execute();

/* EMAIL LINK */
$link = "https://nireas.iee.ihu.gr/asksql/html/reset_password.html?token=$token";

/* SEND EMAIL */
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;

    $mail->Username = 'margaritadelegiannide@gmail.com';
    $mail->Password = 'eskf vruk dvzw pcgi';

    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('margaritadelegiannide@gmail.com', 'AskSQL studio');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'Password Reset';
    $mail->Body = "
        Click the link to reset your password:<br><br>
        <a href='$link'>$link</a>
    ";

    $mail->send();

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => "Email sending failed"
    ]);
    exit;
}

/* RESPONSE */
echo json_encode([
    "success" => true,
    "message" => "If the email exists, a reset link was sent"
]);
exit;