<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once "config.php";
require_once "auth_helpers.php";

/* AUTH */

$auth = require_auth();

$user_id = (int)$auth['user_id'];

/*   ENV */

loadEnv(__DIR__ . "/../.env");

/*  INPUT*/

$raw = file_get_contents("php://input");

$data = json_decode($raw, true);

$question = substr(
    trim($data['question'] ?? ''),
    0,
    1000
);

$ddl = substr(
    trim($data['ddl'] ?? ''),
    0,
    8000
);



if (!$question || !$ddl) {

    echo json_encode([
        "success" => false,
        "error" => "Missing data"
    ]);

    exit;
}

/*API KEY */

$apiKey = $_ENV['OPENAI_API_KEY'] ?? null;

if (!$apiKey) {

    echo json_encode([
        "success" => false,
        "error" => "API key missing"
    ]);

    exit;
}

/* PROMPT */

$prompt = "

You are a SQL generator.

STRICT RULES:

- Return ONLY ONE SQL query
- No explanations
- No comments
- No multiple statements
- No markdown

Database schema:

$ddl

User request:

$question

";

/*  HELPERS */

function callLLM($payload, $apiKey)
{
    $ch = curl_init("https://api.openai.com/v1/chat/completions");

    curl_setopt_array($ch, [

        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,

        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer $apiKey"
        ],

        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        curl_close($ch);
        return null;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($httpCode !== 200) {
        return null;
    }

    return json_decode($response, true);
}

function cleanSQL($sql)
{
    $sql = preg_replace('/```sql|```/i', '', $sql);

    $sql = trim($sql);

    $sql = preg_replace('/--.*(\n|$)/', '', $sql);

    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

    $sql = trim($sql);

    $sql = rtrim($sql, ';');

    return trim($sql);
}

function isDangerous($sql)
{
    return preg_match(
        '/\b(drop|delete|update|insert|alter|truncate)\b/i',
        $sql
    );
}

function isVeryDangerous($sql)
{
    $sql = strtolower(trim($sql));

    if (
        preg_match(
            '/delete\s+from\s+\w+\s*;?$/',
            $sql
        )
    ) {
        return true;
    }

    if (
        preg_match(
            '/update\s+\w+\s+set\s+.+$/',
            $sql
        ) &&
        !str_contains($sql, 'where')
    ) {
        return true;
    }

    return false;
}

/*  LLM REQUEST */

$payload = [

    "model" => "gpt-4o-mini",

    "messages" => [

        [
            "role" => "user",
            "content" => $prompt
        ]
    ],

    "temperature" => 0
];

/* FIRST TRY */

$result = callLLM($payload, $apiKey);

if (
    !$result ||
    !isset($result['choices'][0]['message']['content'])
) {

    echo json_encode([

        "success" => false,

        "error" => "LLM request failed"

    ]);

    exit;
}

/* CLEAN SQL */

$sql = $result['choices'][0]['message']['content'];

$sql = cleanSQL($sql);

/* RETRY IF INVALID*/

if (
    !$sql ||
    strlen($sql) < 10 ||
    substr_count($sql, ';') > 0
) {

    $payload['messages'][0]['content'] .=
        "\n\nIMPORTANT: Return ONLY ONE valid SQL query. No text.";

    $retryResult = callLLM($payload, $apiKey);

    if (
        $retryResult &&
        isset($retryResult['choices'][0]['message']['content'])
    ) {

        $sql = $retryResult['choices'][0]['message']['content'];

        $sql = cleanSQL($sql);
    }
}

/* FINAL VALIDATION*/

if (!$sql) {

    echo json_encode([

        "success" => false,

        "error" => "Empty SQL"

    ]);

    exit;
}

/*  BLOCK MULTI QUERY */

if (substr_count($sql, ';') > 0) {

    echo json_encode([

        "success" => false,

        "error" => "Multiple queries are not allowed"

    ]);

    exit;
}

/*  RESPONSE*/

echo json_encode([

    "success" => true,

    "sql" => $sql,

    "dangerous" => isDangerous($sql) ? true : false,

    "veryDangerous" => isVeryDangerous($sql) ? true : false

]);

exit;