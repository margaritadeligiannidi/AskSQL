<?php
require_once __DIR__ . '/libs/PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/libs/PHPMailer-master/src/SMTP.php';
require_once __DIR__ . '/libs/PHPMailer-master/src/Exception.php';
require_once __DIR__ . '/api/config.php';
loadEnv(__DIR__ . '/.env');
$to = 'asksqlclient@gmail.com';
$mail = new PHPMailer\PHPMailer\PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = $_ENV['SMTP_HOST'];
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['SMTP_USER'];
    $mail->Password = $_ENV['SMTP_PASS'];
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int) $_ENV['SMTP_PORT'];
    $mail->setFrom($_ENV['SMTP_USER'], 'AskSQL Test');
    $mail->addAddress($to, 'Test');
    $mail->isHTML(true);
    $mail->Subject = 'SMTP delivery test';
    $mail->Body = '<p>This is a test.</p>';
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = 'html';
    $mail->send();
    echo "SEND_OK\n";
} catch (Exception $e) {
    echo "SEND_FAIL: {$e->getMessage()}\n";
}
