<?php

// .env ideally
define("SECRET_KEY", hash('sha256', 'CHANGE_THIS_TO_RANDOM_SECRET_KEY'));
define("CIPHER", "AES-256-CBC");

/* ENCRYPT */
function encryptData($data) {

    $ivLength = openssl_cipher_iv_length(CIPHER);
    $iv = random_bytes($ivLength);

    $encrypted = openssl_encrypt(
        $data,
        CIPHER,
        SECRET_KEY,
        OPENSSL_RAW_DATA,
        $iv
    );

    $hmac = hash_hmac(
        'sha256',
        $encrypted,
        SECRET_KEY,
        true
    );

    return base64_encode($iv . $hmac . $encrypted);
}

/* DECRYPT */
function decryptData($data) {

    $decoded = base64_decode($data);

    $ivLength = openssl_cipher_iv_length(CIPHER);

    $iv = substr($decoded, 0, $ivLength);
    $hmac = substr($decoded, $ivLength, 32);
    $encrypted = substr($decoded, $ivLength + 32);

    $calculated_hmac = hash_hmac(
        'sha256',
        $encrypted,
        SECRET_KEY,
        true
    );

    if (!hash_equals($hmac, $calculated_hmac)) {
        return false;
    }

    return openssl_decrypt(
        $encrypted,
        CIPHER,
        SECRET_KEY,
        OPENSSL_RAW_DATA,
        $iv
    );
}