
<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$student = currentStudent($conn);

/**
 * Menghitung jumlah data pada tabel yang diizinkan.
 */
function getCount(mysqli $conn, string $table): int
{
    $allowedTables = [
        'assignments',
        'subjects',
        'materials',
        'quiz_attempts'
    ];

    if (!in_array($table, $allowedTables, true)) {
        return 0;
    }

    $result = $conn->query(
        "SELECT COUNT(*) AS total FROM `$table`"
    );

    return (int) $result->fetch_assoc()['total'];
}

/**
 * Memformat tanggal.
 */
function formatDate(?string $date): string
{
    if (!$date) {
        return '-';
    }

    return date('d M Y', strtotime($date));
}

/**
 * Mengamankan teks sebelum ditampilkan di HTML.
 */
/**
 * Menentukan halaman yang sedang dibuka.
 */
$currentPage = $_GET['page'] ?? 'dashboard';

$allowedPages = [
    'dashboard',
    'assignments',
    'materials',
    'progress',
    'quiz',
    'profile'
];

if (!in_array($currentPage, $allowedPages, true)) {
    $currentPage = 'dashboard';
}

$pageTitles = [
    'dashboard' => 'Dashboard',
    'assignments' => 'My Assignments',
    'materials' => 'My Materials',
    'progress' => 'Learning Progress',
    'quiz' => 'Kuis Belajar',
    'profile' => 'Profil Mahasiswa'
];

/**
 * Mengambil statistik dashboard.
 */
$totalAssignments = getCount($conn, 'assignments');
$totalSubjects = getCount($conn, 'subjects');
$totalMaterials = getCount($conn, 'materials');
$totalAttempts = getCount($conn, 'quiz_attempts');

/**
 * Mengambil tugas terbaru.
 */
$recentAssignments = $conn->query(
    "SELECT
        a.id,
        a.title,
        a.assignment_date,
        a.created_at,
        s.name AS subject_name
     FROM assignments a
     LEFT JOIN subjects s ON s.id = a.subject_id
     ORDER BY a.created_at DESC
     LIMIT 5"
);

/**
 * Mengambil materi terbaru.
 */
$recentMaterials = $conn->query(
    "SELECT id, title, created_at
     FROM materials
     ORDER BY created_at DESC
     LIMIT 3"
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f5f8ff">

    <title><?= e($pageTitles[$currentPage]) ?> | My Assignments</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="app-layout">

    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <div class="main-area">

        <header class="topbar">
            <div class="topbar-left">

                <button
                    type="button"
                    class="menu-toggle"
                    id="menuToggle"
                    aria-label="Buka atau tutup menu"
                    aria-expanded="false"
                >☰</button>

                <div>
                    <div class="breadcrumb">
                        Workspace / <?= e($pageTitles[$currentPage]) ?>
                    </div>

                    <h1><?= e($pageTitles[$currentPage]) ?></h1>
                </div>

            </div>

            <div class="topbar-right">
                <div class="date-chip">
                    <span>◷</span>
                    <?= date('d M Y') ?>
                </div>

                <a href="index.php?page=profile" title="Profil mahasiswa" class="avatar avatar-top"><?= e(mb_strtoupper(mb_substr($student['full_name'], 0, 1))) ?></a>
            </div>
        </header>

        <main class="content">

            <?php if ($currentPage === 'dashboard'): ?>

                <!-- DASHBOARD -->

                <section class="welcome-section">
                    <div>
                        <span class="eyebrow">
                            YOUR PERSONAL LEARNING SPACE
                        </span>

                        <h2>Halo, <?= e($student['full_name']) ?>! 👋</h2>

                        <p>
                            Setiap tugas menyimpan pengetahuan.
                            Yuk, buka kembali dan pelajari hal baru.
                        </p>
                    </div>

                    <a
                        href="index.php?page=assignments"
                        class="button button-primary"
                    >
                        <span>＋</span> Tambah Tugas
                    </a>
                </section>

                <!-- STATISTIK -->

                <section class="stats-grid">

                    <article class="stat-card">
                        <div class="stat-top">
                            <span>Total Tugas</span>
                            <span class="stat-icon blue">▤</span>
                        </div>

                        <div class="stat-number">
                            <?= $totalAssignments ?>
                        </div>

                        <p>Tugas yang tersimpan</p>
                    </article>

                    <article class="stat-card">
                        <div class="stat-top">
                            <span>Mata Kuliah</span>
                            <span class="stat-icon purple">▦</span>
                        </div>

                        <div class="stat-number">
                            <?= $totalSubjects ?>
                        </div>

                        <p>Mata kuliah tercatat</p>
                    </article>

                    <article class="stat-card">
                        <div class="stat-top">
                            <span>Materi Belajar</span>
                            <span class="stat-icon green">▧</span>
                        </div>

                        <div class="stat-number">
                            <?= $totalMaterials ?>
                        </div>

                        <p>Rangkuman tersimpan</p>
                    </article>

                    <article class="stat-card">
                        <div class="stat-top">
                            <span>Kuis Dikerjakan</span>
                            <span class="stat-icon orange">✓</span>
                        </div>

                        <div class="stat-number">
                            <?= $totalAttempts ?>
                        </div>

                        <p>Total percobaan kuis</p>
                    </article>

                </section>

                <!-- TUGAS TERBARU DAN MATERI BELAJAR -->

                <section class="dashboard-grid">

                    <article class="panel">

                        <div class="panel-heading">
                            <div>
                                <h3>Tugas Terbaru</h3>
                                <p>Daftar tugas yang baru kamu simpan.</p>
                            </div>

                            <a
                                href="index.php?page=assignments"
                                class="text-link"
                            >
                                Lihat semua →
                            </a>
                        </div>

                        <?php if ($recentAssignments->num_rows > 0): ?>

                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Judul Tugas</th>
                                            <th>Mata Kuliah</th>
                                            <th>Tanggal</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <?php while (
                                            $item = $recentAssignments->fetch_assoc()
                                        ): ?>

                                            <tr>
                                                <td>
                                                    <div class="task-title">
                                                        <span class="task-dot"></span>

                                                        <span>
                                                            <?= e($item['title']) ?>
                                                        </span>
                                                    </div>
                                                </td>

                                                <td>
                                                    <?= e(
                                                        $item['subject_name']
                                                        ?: 'Belum ditentukan'
                                                    ) ?>
                                                </td>

                                                <td>
                                                    <?= e(
                                                        formatDate(
                                                            $item['assignment_date']
                                                        )
                                                    ) ?>
                                                </td>
                                            </tr>

                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>

                        <?php else: ?>

                            <div class="empty-state compact">
                                <div class="empty-icon">▤</div>

                                <strong>Belum ada tugas</strong>

                                <p>
                                    Tugas yang kamu tambahkan
                                    akan muncul di sini.
                                </p>

                                <a
                                    href="index.php?page=assignments"
                                    class="button button-primary"
                                >
                                    + Tambah Tugas
                                </a>
                            </div>

                        <?php endif; ?>

                    </article>

                    <article class="panel materials-panel">

                        <div class="panel-heading">
                            <div>
                                <h3>Materi Belajar</h3>
                                <p>Rangkuman yang tersimpan.</p>
                            </div>

                            <a
                                href="index.php?page=materials"
                                class="text-link"
                            >
                                Lihat →
                            </a>
                        </div>

                        <?php if ($recentMaterials->num_rows > 0): ?>

                            <div class="material-list">

                                <?php while (
                                    $material = $recentMaterials->fetch_assoc()
                                ): ?>

                                    <div class="material-item">

                                        <div class="material-icon">▧</div>

                                        <div class="material-info">
                                            <strong>
                                                <?= e($material['title']) ?>
                                            </strong>

                                            <span>
                                                Dibuat
                                                <?= e(
                                                    formatDate(
                                                        $material['created_at']
                                                    )
                                                ) ?>
                                            </span>
                                        </div>

                                    </div>

                                <?php endwhile; ?>

                            </div>

                        <?php else: ?>

                            <div class="empty-state compact">
                                <div class="empty-icon">▧</div>

                                <strong>Belum ada materi</strong>

                                <p>
                                    Rangkumanmu akan muncul setelah disimpan.
                                </p>
                            </div>

                        <?php endif; ?>

                    </article>

                </section>

                <!-- BANNER BELAJAR DAN TOMBOL KUIS -->

                <section class="learning-banner">

                    <div class="banner-icon">✦</div>

                    <div>
                        <span>YOUR NEXT STEP</span>

                        <h3>Jadikan tugasmu sumber belajar.</h3>

                        <p>
                            Mulai dari satu tugas, pahami satu konsep baru hari ini.
                        </p>
                    </div>

                    <div class="banner-actions">
                        <a
                            href="index.php?page=assignments"
                            class="button button-light"
                        >
                            Mulai belajar →
                        </a>

                        <a
                            href="index.php?page=quiz"
                            class="button button-primary"
                        >
                            ✎ Buat dan Kerjakan Kuis
                        </a>
                    </div>

                </section>

            <?php elseif ($currentPage === 'assignments'): ?>

                <!-- HALAMAN MY ASSIGNMENTS -->

                <?php
                $assignmentPage = __DIR__ . '/pages/assignments.php';

                if (is_file($assignmentPage)) {
                    require $assignmentPage;
                } else {
                    ?>

                    <section class="placeholder-panel">
                        <div class="placeholder-icon">▤</div>

                        <h2>File halaman tugas belum ditemukan</h2>

                        <p>
                            Pastikan file pages/assignments.php
                            sudah dibuat di folder proyek.
                        </p>

                        <a
                            href="index.php"
                            class="button button-primary"
                        >
                            ← Kembali ke Dashboard
                        </a>
                    </section>

                    <?php
                }
                ?>

            <?php elseif ($currentPage === 'materials'): ?>

                <!-- HALAMAN MY MATERIALS -->

                <?php
                $materialsPage = __DIR__ . '/pages/materials.php';

                if (is_file($materialsPage)) {
                    require $materialsPage;
                } else {
                    ?>

                    <section class="placeholder-panel">
                        <div class="placeholder-icon">▧</div>

                        <h2>File My Materials belum ditemukan</h2>

                        <p>
                            Pastikan file pages/materials.php
                            sudah tersedia di folder proyek.
                        </p>

                        <a
                            href="index.php"
                            class="button button-primary"
                        >
                            ← Kembali ke Dashboard
                        </a>
                    </section>

                    <?php
                }
                ?>

            <?php elseif ($currentPage === 'quiz'): ?>

                <!-- HALAMAN KUIS -->

                <?php
                $quizPage = __DIR__ . '/pages/quiz.php';

                if (is_file($quizPage)) {
                    require $quizPage;
                } else {
                    ?>

                    <section class="placeholder-panel">
                        <div class="placeholder-icon">✎</div>

                        <h2>Halaman kuis belum ditemukan</h2>

                        <p>
                            Pastikan file pages/quiz.php sudah dibuat
                            di folder proyek My Assignments.
                        </p>

                        <a
                            href="index.php?page=materials"
                            class="button button-primary"
                        >
                            ← Kembali ke My Materials
                        </a>
                    </section>

                    <?php
                }
                ?>

            <?php elseif ($currentPage === 'progress'): ?>
                <?php
                $progressAttempts = $conn->query('SELECT score, total_questions, correct_count, created_at FROM quiz_attempts ORDER BY created_at DESC, id DESC');
                $attemptRows = $progressAttempts->fetch_all(MYSQLI_ASSOC);
                $avgScore = count($attemptRows) ? array_sum(array_map(fn($r) => (float)$r['score'], $attemptRows)) / count($attemptRows) : 0;
                $bestScore = count($attemptRows) ? max(array_map(fn($r) => (float)$r['score'], $attemptRows)) : 0;
                ?>
                <section class="page-heading"><div><span class="eyebrow">RINGKASAN PEMBELAJARAN</span><h2>Learning Progress</h2><p>Pantau hasil kuis yang sudah kamu kerjakan.</p></div></section>
                <section class="stats-grid"><article class="stat-card"><span>Total pengerjaan kuis</span><strong><?= count($attemptRows) ?></strong><small>Semua percobaan tersimpan</small></article><article class="stat-card"><span>Rata-rata nilai</span><strong><?= number_format($avgScore, 1) ?>%</strong><small>Akumulasi seluruh nilai</small></article><article class="stat-card"><span>Nilai tertinggi</span><strong><?= number_format($bestScore, 1) ?>%</strong><small>Hasil terbaik sejauh ini</small></article></section>
                <section class="panel" style="margin-top:22px;padding:22px"><div class="panel-heading"><div><h3>Riwayat kuis</h3><p>Urutan pengerjaan terbaru.</p></div></div><?php if (!$attemptRows): ?><div class="empty-state compact"><strong>Belum ada riwayat kuis</strong><p>Kerjakan kuis dari menu Kuis Belajar untuk melihat perkembanganmu.</p><a class="button button-primary" href="index.php?page=quiz">Mulai kuis</a></div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Tanggal</th><th>Jawaban benar</th><th>Total soal</th><th>Nilai</th></tr></thead><tbody><?php foreach($attemptRows as $attempt): ?><tr><td><?= e(formatDate($attempt['created_at'])) ?></td><td><?= (int)$attempt['correct_count'] ?></td><td><?= (int)$attempt['total_questions'] ?></td><td><strong><?= number_format((float)$attempt['score'],1) ?>%</strong></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
            <?php elseif ($currentPage === 'profile'): ?>
                <?php require __DIR__ . '/pages/profile.php'; ?>
            <?php endif; ?>

            <footer class="app-footer">
                <span>© <?= date('Y') ?> My Assignments</span>

                <span>Learn from what you've done.</span>
            </footer>

        </main>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script src="assets/js/app.js"></script>

</body>
</html>
