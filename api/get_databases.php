<?php

header('Content-Type: application/json; charset=utf-8');

require_once "db_connection.php"; // will initialize $pdo and $db via connection_id

try {
    if (strtolower($db['type']) === 'mysql') {
        $stmt = $pdo->query('SHOW DATABASES');
        $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // filter out MySQL system databases
        $blacklist = ['mysql', 'information_schema', 'performance_schema', 'sys'];
        $databases = array_values(array_filter($databases, function($d) use ($blacklist) {
            return $d && !in_array(strtolower($d), $blacklist, true);
        }));

    } else {
        // For Postgres return the current database (changing DBs via UI is uncommon)

        $databases = [$db['database'] ?? null];
        $blacklist_pg = ['postgres', 'template0', 'template1'];
        $databases = array_values(array_filter($databases, function($d) use ($blacklist_pg) {
            return $d && !in_array(strtolower($d), $blacklist_pg, true);
        }));
    }

    echo json_encode([
        'success' => true,
        'databases' => $databases
    ]);

} catch (Exception $e) {
    error_log('GET_DATABASES ERROR: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch databases'
    ]);
}