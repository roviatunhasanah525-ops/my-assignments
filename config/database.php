
<?php

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'my_assignments';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(
        $host,
        $username,
        $password,
        $database
    );

    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());

    http_response_code(500);
    exit(
        'Koneksi database gagal. Periksa Laragon, ' .
        'nama database, dan pengaturan config/database.php.'
    );
}
