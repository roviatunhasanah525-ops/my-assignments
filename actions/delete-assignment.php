
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

function finishDelete(string $message, string $type = 'success'): never
{
    $_SESSION['flash'] = [
        'message' => $message,
        'type' => $type
    ];

    header('Location: ../index.php?page=assignments');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?page=assignments');
    exit;
}

if (
    empty($_SESSION['csrf_token']) ||
    !isset($_POST['csrf_token']) ||
    !is_string($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    finishDelete('Sesi tidak valid. Muat ulang halaman dan coba lagi.', 'error');
}

$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    finishDelete('ID tugas tidak valid.', 'error');
}

try {
    $stmt = $conn->prepare(
        'DELETE FROM assignments WHERE id = ?'
    );

    $stmt->bind_param('i', $id);
    $stmt->execute();

    $deleted = $stmt->affected_rows;
    $stmt->close();

    if ($deleted === 0) {
        finishDelete('Tugas tidak ditemukan atau sudah dihapus.', 'error');
    }

    finishDelete('Tugas berhasil dihapus.');

} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());

    finishDelete(
        'Tugas gagal dihapus. Silakan periksa database.',
        'error'
    );
}
