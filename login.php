<?php
require_once __DIR__ . '/includes/auth.php';
if (!empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) $error = 'Sesi berakhir. Muat ulang halaman.';
    else {
        $email = strtolower(trim((string)($_POST['email'] ?? ''))); $password = (string)($_POST['password'] ?? '');
        $stmt = $conn->prepare('SELECT id, full_name, password_hash FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email); $stmt->execute(); $user = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true); $_SESSION['user_id'] = (int)$user['id']; $_SESSION['student_name'] = $user['full_name']; $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); header('Location: index.php'); exit;
        } else $error = 'Email atau kata sandi belum sesuai.';
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Login | My Assignments</title><link rel="stylesheet" href="assets/css/style.css"><style>
.auth-wrap{min-height:100vh;display:grid;grid-template-columns:1fr 1fr;background:#f5f8ff}.auth-intro{padding:8vw;display:flex;flex-direction:column;justify-content:center;background:linear-gradient(145deg,#2456c7,#7046c7);color:white}.auth-intro h1{font-size:clamp(32px,4vw,54px);line-height:1.12;margin:22px 0}.auth-intro p{max-width:440px;color:#eef1ff;font-size:16px}.auth-card-wrap{display:grid;place-items:center;padding:24px}.auth-card{width:min(440px,100%);background:white;border:1px solid #e8ecf5;border-radius:22px;padding:34px;box-shadow:0 18px 60px #20376a12}.auth-card h2{margin:0 0 8px}.auth-card .field{display:grid;gap:7px;margin:17px 0}.auth-card input{width:100%;padding:13px;border:1px solid #dce2ef;border-radius:10px;font:inherit}.auth-card button{width:100%;border:0}.auth-error{padding:12px;background:#fff0f0;color:#a32d2d;border-radius:9px}.auth-foot{margin-top:20px;text-align:center;color:#68758c}.auth-foot a{color:#356fe5;font-weight:700}@media(max-width:760px){.auth-wrap{grid-template-columns:1fr}.auth-intro{padding:34px 26px}.auth-intro h1{font-size:34px;margin:12px 0}.auth-intro p{font-size:14px}.auth-card-wrap{padding:20px}}
</style></head><body><div class="auth-wrap"><section class="auth-intro"><div class="brand"><span class="brand-icon">M</span><span>My Assignments</span></div><h1>Belajar dari tugas yang sudah kamu selesaikan.</h1><p>Simpan tugas, buat materi belajar, kerjakan kuis, dan pantau perkembangan belajarmu dalam satu tempat.</p><div style="margin-top:26px;opacity:.8">Personal Learning Workspace</div></section><section class="auth-card-wrap"><form class="auth-card" method="post"><span class="eyebrow">SELAMAT DATANG KEMBALI</span><h2>Masuk ke akunmu</h2><p class="muted">Lanjutkan perjalanan belajarmu.</p><?php if($error): ?><div class="auth-error"><?=e($error)?></div><?php endif; ?><input type="hidden" name="csrf_token" value="<?=e($_SESSION['csrf_token'])?>"><div class="field"><label for="email">Email</label><input id="email" name="email" type="email" autocomplete="email" required placeholder="nama@email.com" value="<?=e($_POST['email']??'')?>"></div><div class="field"><label for="password">Kata sandi</label><input id="password" name="password" type="password" autocomplete="current-password" required placeholder="Masukkan kata sandi"></div><button class="button button-primary" type="submit">Login</button><div class="auth-foot">Belum punya akun? <a href="register.php">Daftar mahasiswa</a></div></form></section></div></body></html>
