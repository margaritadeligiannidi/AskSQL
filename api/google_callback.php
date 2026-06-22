<?php

require_once "config.php";
require_once "db.php";
require_once "auth_helpers.php";

loadEnv(__DIR__ . "/../.env");

/* authorization code */

if (!isset($_GET["code"])) {
    die("No authorization code.");
}

$code = $_GET["code"];

/* Exchange code -> access token*/

$post = [
    "code" => $code,
    "client_id" => $_ENV["GOOGLE_CLIENT_ID"],
    "client_secret" => $_ENV["GOOGLE_CLIENT_SECRET"],
    "redirect_uri" => $_ENV["GOOGLE_REDIRECT_URI"],
    "grant_type" => "authorization_code"
];

$ch = curl_init("https://oauth2.googleapis.com/token");

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);

curl_close($ch);

$data = json_decode($response, true);

if (!isset($data["access_token"])) {
    die("Google authentication failed.");
}

$accessToken = $data["access_token"];

/* Παίρνουμε στοιχεία χρήστη */

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => "https://www.googleapis.com/oauth2/v2/userinfo",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . $accessToken
    ]
]);

$userInfo = curl_exec($ch);

curl_close($ch);

$user = json_decode($userInfo, true);

$email = $user["email"];
$username = explode("@", $email)[0];

/* Έλεγχος αν υπάρχει */

$conn = getAppDB();

$stmt = $conn->prepare("
SELECT id, username, role
FROM users
WHERE email = ?
LIMIT 1
");

$stmt->bind_param("s", $email);
$stmt->execute();

$result = $stmt->get_result();

/* Αν δεν υπάρχει -> δημιουργία */

if ($result->num_rows == 0) {

    $randomPassword =
        password_hash(
            bin2hex(random_bytes(16)),
            PASSWORD_DEFAULT
        );

    $role = "demo";
    $verified = 1;

    $insert = $conn->prepare("
    INSERT INTO users
    (username,email,password,role,is_verified)
    VALUES (?,?,?,?,?)
    ");

    $insert->bind_param(
        "ssssi",
        $username,
        $email,
        $randomPassword,
        $role,
        $verified
    );

    $insert->execute();

    $user_id = $conn->insert_id;

} else {

    $row = $result->fetch_assoc();

    $user_id = $row["id"];
    $username = $row["username"];
    $role = $row["role"];
}

/* JWT */

$payload = [
    "user_id" => (int)$user_id,
    "username" => $username,
    "role" => $role,
    "iat" => time(),
    "exp" => time() + 8 * 3600
];

$token = jwt_encode($payload);

/*  Redirect */

header(
    "Location: ../html/google_success.html?token="
    . urlencode($token)
);

exit;