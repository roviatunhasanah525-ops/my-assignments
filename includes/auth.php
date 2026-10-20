<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
require_once __DIR__ . '/../config/database.php';
function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function requireLogin(): void { if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; } }
function currentStudent(mysqli $conn): array {
    $id = (int)($_SESSION['user_id'] ?? 0);
    $stmt = $conn->prepare('SELECT id, full_name, email, nim, program_study, class_name, bio FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    return $row ?: ['id'=>0,'full_name'=>'Mahasiswa','email'=>'','nim'=>'','program_study'=>'','class_name'=>'','bio'=>''];
}
