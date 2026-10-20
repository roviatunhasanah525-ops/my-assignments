
<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }

require_once __DIR__ . '/../config/database.php';

function quizRedirect(int $materialId): never
{
    header(
        'Location: ../index.php?page=quiz&material_id=' . $materialId
    );
    exit;
}

$materialId = (int) ($_POST['material_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    quizRedirect($materialId);
}

$token = (string) ($_POST['csrf_token'] ?? '');

if (
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $token)
) {
    $_SESSION['quiz_flash'] = [
        'message' => 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.'
    ];
    quizRedirect($materialId);
}

$question = trim((string) ($_POST['question'] ?? ''));
$optionA = trim((string) ($_POST['option_a'] ?? ''));
$optionB = trim((string) ($_POST['option_b'] ?? ''));
$optionC = trim((string) ($_POST['option_c'] ?? ''));
$optionD = trim((string) ($_POST['option_d'] ?? ''));
$correctAnswer = strtoupper(
    trim((string) ($_POST['correct_answer'] ?? ''))
);
$explanation = trim((string) ($_POST['explanation'] ?? ''));

if (
    $materialId < 1 ||
    $question === '' ||
    $optionA === '' ||
    $optionB === '' ||
    $optionC === '' ||
    $optionD === '' ||
    !in_array($correctAnswer, ['A', 'B', 'C', 'D'], true)
) {
    $_SESSION['quiz_flash'] = [
        'message' => 'Lengkapi pertanyaan, seluruh pilihan, dan kunci jawaban.'
    ];
    quizRedirect($materialId);
}

try {
    $check = $conn->prepare(
        'SELECT id FROM learning_materials WHERE id = ?'
    );
    $check->bind_param('i', $materialId);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();

    if (!$exists) {
        throw new RuntimeException('Materi tidak ditemukan.');
    }

    $stmt = $conn->prepare(
        'INSERT INTO learning_quiz_questions
        (material_id, question, option_a, option_b, option_c,
         option_d, correct_answer, explanation)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $stmt->bind_param(
        'isssssss',
        $materialId,
        $question,
        $optionA,
        $optionB,
        $optionC,
        $optionD,
        $correctAnswer,
        $explanation
    );

    $stmt->execute();
    $stmt->close();

    $_SESSION['quiz_flash'] = [
        'message' => 'Soal berhasil disimpan.'
    ];
} catch (Throwable $error) {
    error_log($error->getMessage());

    $_SESSION['quiz_flash'] = [
        'message' => 'Soal gagal disimpan. Periksa koneksi database dan struktur tabel.'
    ];
}

quizRedirect($materialId);
