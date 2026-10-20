
<?php
// File ini dimuat melalui index.php, sehingga $conn, e(),
// formatDate(), dan token sesi sudah tersedia.

$action = $_GET['action'] ?? 'list';
$validActions = ['list', 'create', 'edit', 'detail'];

if (!in_array($action, $validActions, true)) {
    $action = 'list';
}

$assignmentId = filter_var(
    $_GET['id'] ?? '',
    FILTER_VALIDATE_INT
);

if ($assignmentId !== false && $assignmentId < 1) {
    $assignmentId = false;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$subjectsResult = $conn->query(
    'SELECT id, name FROM subjects ORDER BY name ASC'
);
$subjects = $subjectsResult->fetch_all(MYSQLI_ASSOC);

$assignment = null;

if (in_array($action, ['edit', 'detail'], true)) {
    if (!$assignmentId) {
        $action = 'list';
        $flash = [
            'message' => 'ID tugas tidak valid.',
            'type' => 'error'
        ];
    } else {
        $stmt = $conn->prepare(
            'SELECT a.*, s.name AS subject_name
             FROM assignments a
             LEFT JOIN subjects s ON s.id = a.subject_id
             WHERE a.id = ?'
        );
        $stmt->bind_param('i', $assignmentId);
        $stmt->execute();
        $assignment = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$assignment) {
            $action = 'list';
            $flash = [
                'message' => 'Tugas tidak ditemukan.',
                'type' => 'error'
            ];
        }
    }
}

$isForm = in_array($action, ['create', 'edit'], true);

$formData = [
    'title' => '',
    'subject_id' => '',
    'assignment_date' => '',
    'description' => '',
    'content' => ''
];

if ($action === 'edit' && $assignment) {
    $formData = [
        'title' => $assignment['title'],
        'subject_id' => $assignment['subject_id'] ?? '',
        'assignment_date' => $assignment['assignment_date'] ?? '',
        'description' => $assignment['description'] ?? '',
        'content' => $assignment['content']
    ];
}

$q = trim($_GET['q'] ?? '');
$filterSubject = $_GET['subject_id'] ?? '';
$selectedSubject = '';

if ($filterSubject !== '') {
    $validatedFilter = filter_var(
        $filterSubject,
        FILTER_VALIDATE_INT
    );

    if ($validatedFilter && $validatedFilter > 0) {
        $selectedSubject = (string) $validatedFilter;
    } else {
        $filterSubject = '';
    }
}

$assignments = [];

if ($action === 'list') {
    $sql = 'SELECT a.id, a.title, a.assignment_date,
                   a.description, a.content, a.created_at,
                   s.name AS subject_name, s.id AS subject_id
            FROM assignments a
            LEFT JOIN subjects s ON s.id = a.subject_id
            WHERE 1=1';

    $params = [];
    $types = '';

    if ($q !== '') {
        $sql .= ' AND (
            a.title LIKE ? OR
            a.description LIKE ? OR
            a.content LIKE ?
        )';

        $keyword = '%' . $q . '%';
        $params[] = $keyword;
        $params[] = $keyword;
        $params[] = $keyword;
        $types .= 'sss';
    }

    if ($selectedSubject !== '') {
        $sql .= ' AND a.subject_id = ?';
        $params[] = (int) $selectedSubject;
        $types .= 'i';
    }

    $sql .= ' ORDER BY a.created_at DESC, a.id DESC';

    $stmt = $conn->prepare($sql);

    if ($params) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $assignments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<?php if ($flash): ?>
    <div class="flash-message <?= e($flash['type']) ?>" role="status">
        <span><?= $flash['type'] === 'error' ? '!' : '✓' ?></span>
        <?= e($flash['message']) ?>
    </div>
<?php endif; ?>

<?php if ($action === 'list'): ?>

    <section class="assignment-heading">
        <div>
            <span class="eyebrow">YOUR ACADEMIC COLLECTION</span>
            <h2>Semua Tugas</h2>
            <p>
                Simpan tugas kuliahmu dan ubah menjadi bahan belajar
                yang bisa digunakan kembali.
            </p>
        </div>

        <a
            class="button button-primary"
            href="index.php?page=assignments&amp;action=create"
        >
            <span>＋</span> Tambah Tugas
        </a>
    </section>

    <section class="assignment-toolbar">
        <form method="GET" action="index.php" class="filter-form">
            <input type="hidden" name="page" value="assignments">

            <label class="search-box">
                <span aria-hidden="true">⌕</span>
                <input
                    type="search"
                    name="q"
                    value="<?= e($q) ?>"
                    placeholder="Cari judul atau isi tugas..."
                    aria-label="Cari tugas"
                >
            </label>

            <select
                name="subject_id"
                class="subject-filter"
                aria-label="Filter mata kuliah"
            >
                <option value="">Semua Mata Kuliah</option>
                <?php foreach ($subjects as $subject): ?>
                    <option
                        value="<?= (int) $subject['id'] ?>"
                        <?= $selectedSubject === (string) $subject['id']
                            ? 'selected'
                            : '' ?>
                    >
                        <?= e($subject['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button class="button button-primary" type="submit">
                Terapkan
            </button>

            <?php if ($q !== '' || $selectedSubject !== ''): ?>
                <a class="button button-secondary" href="index.php?page=assignments">
                    Reset
                </a>
            <?php endif; ?>
        </form>
    </section>

    <section class="assignment-list-panel">
        <div class="list-panel-heading">
            <div>
                <h3>Daftar Tugas</h3>
                <p>
                    <?= count($assignments) ?> tugas ditemukan
                    <?php if ($q !== ''): ?>
                        untuk pencarian "<?= e($q) ?>"
                    <?php endif; ?>
                </p>
            </div>
            <span class="result-count"><?= count($assignments) ?> tugas</span>
        </div>

        <?php if ($assignments): ?>
            <div class="assignment-cards">
                <?php foreach ($assignments as $item): ?>
                    <article class="assignment-card">
                        <div class="assignment-card-top">
                            <div class="assignment-card-icon">▤</div>
                            <div class="assignment-card-main">
                                <h3><?= e($item['title']) ?></h3>
                                <span class="subject-badge">
                                    <?= e($item['subject_name'] ?: 'Belum ada mata kuliah') ?>
                                </span>
                            </div>
                        </div>

                        <p class="assignment-description">
                            <?php
                            $description = trim($item['description'] ?? '');
                            echo $description !== ''
                                ? nl2br(e(mb_strimwidth($description, 0, 180, '...')))
                                : 'Belum ada deskripsi untuk tugas ini.';
                            ?>
                        </p>

                        <div class="assignment-meta">
                            <span>
                                <span aria-hidden="true">◷</span>
                                <?= e(formatDate($item['assignment_date'])) ?>
                            </span>
                            <span>
                                Ditambahkan <?= e(formatDate($item['created_at'])) ?>
                            </span>
                        </div>

                        <div class="assignment-actions">
                            <a
                                class="button button-secondary"
                                href="index.php?page=assignments&amp;action=detail&amp;id=<?= (int) $item['id'] ?>"
                            >Lihat Detail</a>

                            <a
                                class="button button-outline-blue"
                                href="index.php?page=assignments&amp;action=edit&amp;id=<?= (int) $item['id'] ?>"
                            >Edit</a>

                            <form
                                method="POST"
                                action="actions/delete-assignment.php"
                                class="delete-form"
                                onsubmit="return confirm('Yakin ingin menghapus tugas ini? Tindakan ini tidak dapat dibatalkan.');"
                            >
                                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <button type="submit" class="button button-danger">
                                    Hapus
                                </button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="assignment-empty">
                <div class="empty-icon">▤</div>
                <h3>
                    <?= ($q !== '' || $selectedSubject !== '')
                        ? 'Tugas tidak ditemukan'
                        : 'Belum ada tugas' ?>
                </h3>
                <p>
                    <?= ($q !== '' || $selectedSubject !== '')
                        ? 'Coba kata kunci atau filter mata kuliah yang berbeda.'
                        : 'Tambahkan tugas pertamamu untuk mulai membangun koleksi materi belajar.' ?>
                </p>

                <?php if ($q !== '' || $selectedSubject !== ''): ?>
                    <a class="button button-secondary" href="index.php?page=assignments">
                        Hapus Filter
                    </a>
                <?php else: ?>
                    <a class="button button-primary" href="index.php?page=assignments&amp;action=create">
                        ＋ Tambah Tugas
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

<?php elseif ($isForm): ?>

    <section class="assignment-heading">
        <div>
            <span class="eyebrow">ASSIGNMENT MANAGEMENT</span>
            <h2><?= $action === 'edit' ? 'Edit Tugas' : 'Tambah Tugas Baru' ?></h2>
            <p>
                Isi informasi tugas agar mudah ditemukan dan dipelajari kembali.
            </p>
        </div>
        <a class="button button-secondary" href="index.php?page=assignments">
            ← Kembali
        </a>
    </section>

    <section class="assignment-form-panel">
        <form method="POST" action="actions/save-assignment.php" class="assignment-form">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="<?= $action === 'edit' ? 'edit' : 'create' ?>">

            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="id" value="<?= (int) $assignment['id'] ?>">
            <?php endif; ?>

            <div class="form-section-title">
                <span class="form-section-icon">01</span>
                <div>
                    <h3>Informasi Tugas</h3>
                    <p>Judul, mata kuliah, dan tanggal pengerjaan.</p>
                </div>
            </div>

            <div class="form-field">
                <label for="title">Judul Tugas <span>*</span></label>
                <input
                    id="title"
                    name="title"
                    type="text"
                    maxlength="200"
                    required
                    value="<?= e($formData['title']) ?>"
                    placeholder="Contoh: Analisis Sistem Informasi"
                >
            </div>

            <div class="form-grid">
                <div class="form-field">
                    <label for="subject_id">Mata Kuliah</label>
                    <select id="subject_id" name="subject_id">
                        <option value="">Pilih mata kuliah (opsional)</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option
                                value="<?= (int) $subject['id'] ?>"
                                <?= (string) $formData['subject_id'] === (string) $subject['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($subject['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Pilih mata kuliah yang sudah terdaftar.</small>
                </div>

                <div class="form-field">
                    <label for="new_subject">Atau Tambah Mata Kuliah Baru</label>
                    <input
                        id="new_subject"
                        name="new_subject"
                        type="text"
                        maxlength="150"
                        placeholder="Contoh: Manajemen Operasi"
                    >
                    <small>Jika diisi, pilihan mata kuliah di sebelah kiri harus kosong.</small>
                </div>
            </div>

            <div class="form-field">
                <label for="assignment_date">Tanggal Tugas</label>
                <input
                    id="assignment_date"
                    name="assignment_date"
                    type="date"
                    value="<?= e($formData['assignment_date']) ?>"
                >
            </div>

            <div class="form-section-title form-section-spaced">
                <span class="form-section-icon">02</span>
                <div>
                    <h3>Deskripsi dan Isi Tugas</h3>
                    <p>Masukkan penjelasan singkat dan isi tugas untuk dipelajari kembali.</p>
                </div>
            </div>

            <div class="form-field">
                <label for="description">Deskripsi</label>
                <textarea
                    id="description"
                    name="description"
                    rows="3"
                    maxlength="10000"
                    placeholder="Jelaskan tujuan atau instruksi tugas..."
                ><?= e($formData['description']) ?></textarea>
            </div>

            <div class="form-field">
                <label for="content">Isi Tugas <span>*</span></label>
                <textarea
                    id="content"
                    name="content"
                    rows="10"
                    required
                    maxlength="1000000"
                    placeholder="Tempelkan teks tugas, pembahasan, jawaban, atau materi yang ingin kamu pelajari kembali..."
                ><?= e($formData['content']) ?></textarea>
                <small>
                    Saat ini, masukkan teks secara langsung. Unggah file PDF/DOCX belum tersedia.
                </small>
            </div>

            <div class="form-actions">
                <a href="index.php?page=assignments" class="button button-secondary">
                    Batal
                </a>
                <button type="submit" class="button button-primary">
                    <?= $action === 'edit' ? 'Simpan Perubahan' : 'Simpan Tugas' ?>
                </button>
            </div>
        </form>
    </section>

<?php elseif ($action === 'detail' && $assignment): ?>

    <section class="assignment-heading">
        <div>
            <span class="eyebrow">ASSIGNMENT DETAILS</span>
            <h2>Detail Tugas</h2>
            <p>Lihat kembali informasi dan isi tugas yang tersimpan.</p>
        </div>
        <a class="button button-secondary" href="index.php?page=assignments">
            ← Kembali
        </a>
    </section>

    <section class="assignment-detail-panel">
        <div class="detail-title-row">
            <div class="assignment-card-icon">▤</div>
            <div>
                <span class="subject-badge">
                    <?= e($assignment['subject_name'] ?: 'Belum ada mata kuliah') ?>
                </span>
                <h2><?= e($assignment['title']) ?></h2>
                <p class="detail-date">
                    Tanggal tugas: <?= e(formatDate($assignment['assignment_date'])) ?>
                </p>
            </div>
        </div>

        <div class="detail-section">
            <h3>Deskripsi</h3>
            <div class="detail-content">
                <?= trim($assignment['description'] ?? '') !== ''
                    ? nl2br(e($assignment['description']))
                    : 'Belum ada deskripsi.' ?>
            </div>
        </div>

        <div class="detail-section">
            <h3>Isi Tugas</h3>
            <div class="detail-content assignment-content">
                <?= nl2br(e($assignment['content'])) ?>
            </div>
        </div>

        <div class="detail-footer">
            <span>
                Terakhir diperbarui:
                <?= e(formatDate($assignment['updated_at'])) ?>
            </span>
            <a
                class="button button-primary"
                href="index.php?page=assignments&amp;action=edit&amp;id=<?= (int) $assignment['id'] ?>"
            >Edit Tugas</a>
        </div>
    </section>

<?php endif; ?>
