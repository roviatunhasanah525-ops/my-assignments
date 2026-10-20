
<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/learning-generator.php';

function redirectWithMessage(string $message, string $type = 'error'): never
{
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;

    header('Location: ../index.php?page=materials');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?page=materials');
    exit;
}

$csrf = $_POST['csrf_token'] ?? '';

if (
    !is_string($csrf) ||
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrf)
) {
    redirectWithMessage('Sesi formulir tidak valid. Muat ulang halaman.');
}

$assignmentId = filter_var(
    $_POST['assignment_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$assignmentId || $assignmentId < 1) {
    redirectWithMessage('Tugas yang dipilih tidak valid.');
}

try {
    $stmt = $conn->prepare(
        'SELECT title, content
         FROM assignments
         WHERE id = ?'
    );
    $stmt->bind_param('i', $assignmentId);
    $stmt->execute();

    $assignment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$assignment) {
        redirectWithMessage('Tugas tidak ditemukan.');
    }

    $content = trim($assignment['content'] ?? '');

    if ($content === '') {
        redirectWithMessage(
            'Tugas belum memiliki isi. Edit tugas dan isi materinya dahulu.'
        );
    }

    $generated = generateLearningMaterial($content);
    $title = $assignment['title'];
    $summary = $generated['summary'];
    $keyPoints = json_encode(
        $generated['key_points'],
        JSON_UNESCAPED_UNICODE
    );

    $stmt = $conn->prepare(
        'INSERT INTO learning_materials
            (assignment_id, title, summary, key_points)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            summary = VALUES(summary),
            key_points = VALUES(key_points)'
    );

    $stmt->bind_param(
        'isss',
        $assignmentId,
        $title,
        $summary,
        $keyPoints
    );

    $stmt->execute();
    $stmt->close();

    redirectWithMessage('Materi berhasil dibuat.', 'success');

} catch (Throwable $e) {
    error_log('Generate material error: ' . $e->getMessage());

    redirectWithMessage(
        'Materi gagal dibuat. Periksa log PHP untuk detail kesalahan.'
    );
}
