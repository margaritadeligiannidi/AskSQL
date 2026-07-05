
<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once "config.php";
require_once "auth_helpers.php";

/* AUTH */

$auth = require_auth();

$user_id = (int)$auth['user_id'];

/* ENV */

loadEnv(__DIR__ . "/../.env");

/* INPUT */

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

$provider = strtolower(
    trim($data['provider'] ?? 'openai')
);

if (!$question || !$ddl) {

    echo json_encode([
        "success" => false,
        "error" => "Missing data"
    ]);

    exit;
}

/* SELECT PROVIDER */

switch ($provider) {

    case "deepseek":

        $apiKey = $_ENV['DEEPSEEK_API_KEY'] ?? '';

        $url =
            "https://api.deepseek.com/chat/completions";

        $model =
            "deepseek-chat";

        break;

    case "openai":
    default:

        $apiKey = $_ENV['OPENAI_API_KEY'] ?? '';

        $url =
            "https://api.openai.com/v1/chat/completions";

        $model =
            "gpt-4o-mini";

        break;
}

if (!$apiKey) {

    echo json_encode([
        "success" => false,
        "error" => strtoupper($provider) . " API key missing"
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

/* HELPERS */

function callLLM(
    string $url,
    string $apiKey,
    array $payload
) {

    $ch = curl_init($url);

    curl_setopt_array($ch, [

        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,

        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer $apiKey"
        ],

        CURLOPT_POSTFIELDS =>
            json_encode($payload)
    ]);

    $response = curl_exec($ch);

    if ($response === false) {

        $err = curl_error($ch);

        curl_close($ch);

        throw new Exception($err);
    }

    $httpCode =
        curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

    curl_close($ch);

    if ($httpCode >= 400) {

        throw new Exception(
            "HTTP $httpCode : $response"
        );
    }

    return json_decode(
        $response,
        true
    );
}

function cleanSQL($sql)
{
    $sql = preg_replace(
        '/```sql|```/i',
        '',
        $sql
    );

    $sql = trim($sql);

    $sql = preg_replace(
        '/--.*(\n|$)/',
        '',
        $sql
    );

    $sql = preg_replace(
        '/\/\*.*?\*\//s',
        '',
        $sql
    );

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
    $sql = strtolower(
        trim($sql)
    );

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
        !str_contains(
            $sql,
            'where'
        )
    ) {
        return true;
    }

    return false;
}

/* PAYLOAD */

$payload = [

    "model" => $model,

    "messages" => [

        [
            "role" => "system",
            "content" =>
                "You generate SQL only."
        ],

        [
            "role" => "user",
            "content" => $prompt
        ]
    ],

    "temperature" => 0
];

try {

    $result = callLLM(
        $url,
        $apiKey,
        $payload
    );

    $usage = $result["usage"] ?? $result["usageMetadata"] ?? [];

$promptTokens =
    $usage["prompt_tokens"]
    ?? $usage["promptTokenCount"]
    ?? 0;

$completionTokens =
    $usage["completion_tokens"]
    ?? $usage["candidatesTokenCount"]
    ?? 0;

$totalTokens =
    $usage["total_tokens"]
    ?? $usage["totalTokenCount"]
    ?? ($promptTokens + $completionTokens);

  

    if (
        !$result ||
        !isset(
            $result['choices'][0]['message']['content']
        )
    ) {

        echo json_encode([
            "success" => false,
            "error" => "LLM request failed"
        ]);

        exit;
    }

    $sql =
        $result['choices'][0]['message']['content'];

    $sql = cleanSQL($sql);

    if (
        !$sql ||
        strlen($sql) < 5
    ) {

        echo json_encode([
            "success" => false,
            "error" => "Empty SQL"
        ]);

        exit;
    }

    if (
        substr_count($sql, ';') > 0
    ) {

        echo json_encode([
            "success" => false,
            "error" =>
                "Multiple queries are not allowed"
        ]);

        exit;
    }

   echo json_encode([

    "success" => true,

    "provider" => $provider,

    "model" => $model,

    "sql" => $sql,

    "dangerous" => isDangerous($sql),

    "veryDangerous" => isVeryDangerous($sql),

    "metrics" => [

        "prompt_tokens" => $promptTokens,

        "completion_tokens" => $completionTokens,

        "total_tokens" => $totalTokens
    ]
]);

} catch (Throwable $e) {

    echo json_encode([

        "success" => false,

        "error" =>
            $e->getMessage()
    ]);
}

