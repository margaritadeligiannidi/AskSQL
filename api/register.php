<?php
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
error_reporting(E_ALL);

// buffer
ob_start();

require_once "db.php";

// PHPMailer
require_once __DIR__ . "/../libs/PHPMailer-master/src/PHPMailer.php";
require_once __DIR__ . "/../libs/PHPMailer-master/src/SMTP.php";
require_once __DIR__ . "/../libs/PHPMailer-master/src/Exception.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/*  DB CONNECTION  */
$conn = getAppDB();

if (!$conn) {
    ob_clean();
    echo json_encode(["error" => "DB connection failed"]);
    exit;
}

/*  INPUT */
$data = json_decode(
    file_get_contents("php://input"),
    true
);

$username   = trim($data['username'] ?? '');
$password   = $data['password'] ?? '';
$password_2 = $data['password_2'] ?? '';
$email      = trim($data['email'] ?? '');

if (!$username || !$password || !$password_2 || !$email) {
    ob_clean();
    echo json_encode(["error" => "All fields are required"]);
    exit;
}

/* VALIDATION */
if (strlen($username) < 3) {
    ob_clean();
    echo json_encode(["error" => "Username must be at least 3 characters"]);
    exit;
}

if (strlen($password) < 8) {
    ob_clean();
    echo json_encode(["error" => "Password must be at least 8 characters"]);
    exit;
}

if (
    !preg_match('/[A-Z]/', $password) ||
    !preg_match('/[a-z]/', $password) ||
    !preg_match('/[0-9]/', $password)
) {
    ob_clean();
    echo json_encode([
        "error" => "Password must include uppercase, lowercase and number"
    ]);
    exit;
}

if ($password !== $password_2) {
    ob_clean();
    echo json_encode(["error" => "Passwords do not match"]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    ob_clean();
    echo json_encode(["error" => "Invalid email"]);
    exit;
}

/* CHECK USER EXISTS */
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");

if (!$stmt) {
    ob_clean();
    echo json_encode(["error" => "Prepare failed", "debug" => $conn->error]);
    exit;
}

$stmt->bind_param("ss", $username, $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    ob_clean();
    echo json_encode(["error" => "User already exists"]);
    exit;
}

$stmt->close();

/*  HASH PASSWORD */
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

/* TOKEN */
$verify_token = bin2hex(random_bytes(32));

/* INSERT USER */
$stmt = $conn->prepare("
INSERT INTO users (username, password, email, verification_token, verify_expires, is_verified) 
VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), 0)
");

if (!$stmt) {
    ob_clean();
    echo json_encode(["error" => "Insert prepare failed", "debug" => $conn->error]);
    exit;
}

$stmt->bind_param("ssss", $username, $hashed_password, $email, $verify_token);

if (!$stmt->execute()) {
    ob_clean();
    echo json_encode(["error" => "Execute failed", "debug" => $stmt->error]);
    exit;
}

/* SEND EMAIL */
$verify_link = "https://nireas.iee.ihu.gr/asksql/html/verify.html?token=" . $verify_token;

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['SMTP_USER'];
    $mail->Password = $_ENV['SMTP_PASS'];
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('margaritadelegiannide@gmail.com', 'AskSQL Client');
    $mail->addAddress($email, $username);

    $mail->isHTML(true);
    $mail->Subject = 'AskSQL - Email Verification';

   $mail->Body = "
<h2>Welcome to AskSQL</h2>

<p>Hello <b>{$username}</b>,</p>

<p>Thank you for creating an AskSQL account.</p>

<p>Please verify your email address by clicking the button below.</p>

<p>
<a href='{$verify_link}'
style='background:#0d6efd;
color:white;
padding:12px 20px;
text-decoration:none;
border-radius:5px;'>
Verify Email
</a>
</p>

<p>If you did not create this account, you can safely ignore this email.</p>

<hr>

<p>AskSQL Team</p>
";
    $mail->send();

} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        "error" => "Email failed",
        "debug" => $mail->ErrorInfo
    ]);
    exit;
}

/* SUCCESS */
ob_clean();
echo json_encode([
    "success" => true,
    "message" => "Check your email to verify your account"
]);
exit;