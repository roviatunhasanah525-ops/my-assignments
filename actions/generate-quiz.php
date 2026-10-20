<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/learning-generator.php';

$materialId = (int) ($_POST['material_id'] ?? 0);
$redirect = '../index.php?page=quiz&material_id=' . $materialId;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../index.php?page=quiz'); exit; }
$token = (string) ($_POST['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    $_SESSION['quiz_flash'] = ['message' => 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.'];
    header('Location: ' . $redirect); exit;
}
try {
    if ($materialId < 1) throw new RuntimeException('Pilih materi terlebih dahulu.');
    $stmt = $conn->prepare('SELECT title, summary, key_points FROM learning_materials WHERE id = ?');
    $stmt->bind_param('i', $materialId); $stmt->execute(); $material = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$material) throw new RuntimeException('Materi tidak ditemukan.');
    $decoded = json_decode((string)($material['key_points'] ?? '[]'), true);
    $keyPoints = is_array($decoded) ? $decoded : [];
    $generated = generateQuizQuestions((string)$material['summary'], (string)$material['summary'], $keyPoints, 8);
    // Ganti soal lama dari materi ini supaya tombol bisa digunakan untuk membuat ulang.
    $delete = $conn->prepare('DELETE FROM learning_quiz_questions WHERE material_id = ?');
    $delete->bind_param('i', $materialId); $delete->execute(); $delete->close();
    $insert = $conn->prepare('INSERT INTO learning_quiz_questions (material_id, question, option_a, option_b, option_c, option_d, correct_answer, explanation) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($generated as $q) {
        // Simpan huruf kunci dalam format kecil agar kompatibel dengan ENUM('a','b','c','d').
        $q['correct_answer'] = strtolower((string) $q['correct_answer']);
        $insert->bind_param('isssssss', $materialId, $q['question'], $q['option_a'], $q['option_b'], $q['option_c'], $q['option_d'], $q['correct_answer'], $q['explanation']);
        $insert->execute();
    }
    $insert->close();
    $_SESSION['quiz_flash'] = ['message' => 'Berhasil membuat ' . count($generated) . ' soal kuis otomatis berdasarkan materi.'];
} catch (Throwable $e) {
    error_log('Generate quiz error: ' . $e->getMessage());
    $_SESSION['quiz_flash'] = ['message' => $e instanceof RuntimeException ? $e->getMessage() : 'Kuis gagal dibuat. Pastikan struktur tabel learning_quiz_questions sudah sesuai.'];
}
header('Location: ' . $redirect); exit;
