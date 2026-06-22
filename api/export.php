<?php

header('Content-Type: text/csv; charset=utf-8');

/* JWT FROM QUERY PARAM */
if (
    !isset($_SERVER['HTTP_AUTHORIZATION'])
    && isset($_GET['token'])
) {
    $_SERVER['HTTP_AUTHORIZATION'] =
        'Bearer ' . $_GET['token'];
}

require_once "auth_helpers.php";

/* AUTH CHECK */
$auth = require_auth();

require_once "db_connection.php";

$sql = $_GET['sql'] ?? null;

if (!$sql) {
    die("No query");
}

try {

    $stmt = $pdo->query($sql);

    header('Content-Disposition: attachment; filename="export.csv"');

    $output = fopen("php://output", "w");

    /* HEADERS */
    $firstRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($firstRow) {

        fputcsv($output, array_keys($firstRow));

        fputcsv($output, $firstRow);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
    }

    fclose($output);

} catch (Exception $e) {

    echo "Error: " . $e->getMessage();
}