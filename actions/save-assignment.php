
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

function redirectWithMessage(string $message, string $type = 'success'): never
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
    redirectWithMessage('Sesi formulir tidak valid. Silakan coba lagi.', 'error');
}

$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? 'create';

if (!in_array($action, ['create', 'edit'], true)) {
    redirectWithMessage('Aksi tidak valid.', 'error');
}

if ($action === 'edit' && (!$id || $id < 1)) {
    redirectWithMessage('ID tugas tidak valid.', 'error');
}

$title = trim($_POST['title'] ?? '');
$subjectIdRaw = $_POST['subject_id'] ?? '';
$newSubject = trim($_POST['new_subject'] ?? '');
$dateRaw = trim($_POST['assignment_date'] ?? '');
$description = trim($_POST['description'] ?? '');
$content = trim($_POST['content'] ?? '');

if ($title === '' || mb_strlen($title) > 200) {
    redirectWithMessage(
        'Judul wajib diisi dan maksimal 200 karakter.',
        'error'
    );
}

if ($content === '') {
    redirectWithMessage('Isi tugas wajib diisi.', 'error');
}

if (mb_strlen($description) > 10000 || mb_strlen($content) > 1000000) {
    redirectWithMessage('Deskripsi atau isi tugas terlalu panjang.', 'error');
}

if ($newSubject !== '' && mb_strlen($newSubject) > 150) {
    redirectWithMessage('Nama mata kuliah maksimal 150 karakter.', 'error');
}

if ($newSubject !== '' && $subjectIdRaw !== '') {
    redirectWithMessage(
        'Pilih mata kuliah yang sudah ada atau masukkan mata kuliah baru, jangan keduanya.',
        'error'
    );
}

$assignmentDate = null;

if ($dateRaw !== '') {
    $dateObject = DateTime::createFromFormat('!Y-m-d', $dateRaw);

    if (!$dateObject || $dateObject->format('Y-m-d') !== $dateRaw) {
        redirectWithMessage('Format tanggal tidak valid.', 'error');
    }

    $assignmentDate = $dateRaw;
}

$subjectId = null;

try {
    if ($newSubject !== '') {
        // Cari mata kuliah yang namanya sama terlebih dahulu.
        $stmt = $conn->prepare(
            'SELECT id FROM subjects WHERE name = ? LIMIT 1'
        );
        $stmt->bind_param('s', $newSubject);
        $stmt->execute();
        $existingSubject = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existingSubject) {
            $subjectId = (int) $existingSubject['id'];
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO subjects (name) VALUES (?)'
            );
            $stmt->bind_param('s', $newSubject);
            $stmt->execute();
            $subjectId = (int) $conn->insert_id;
            $stmt->close();
        }
    } elseif ($subjectIdRaw !== '') {
        $validatedSubjectId = filter_var(
            $subjectIdRaw,
            FILTER_VALIDATE_INT
        );

        if (!$validatedSubjectId || $validatedSubjectId < 1) {
            redirectWithMessage('Mata kuliah tidak valid.', 'error');
        }

        $stmt = $conn->prepare(
            'SELECT id FROM subjects WHERE id = ?'
        );
        $stmt->bind_param('i', $validatedSubjectId);
        $stmt->execute();
        $existingSubject = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$existingSubject) {
            redirectWithMessage('Mata kuliah tidak ditemukan.', 'error');
        }

        $subjectId = (int) $existingSubject['id'];
    }

    if ($action === 'create') {
        $stmt = $conn->prepare(
            'INSERT INTO assignments
                (subject_id, title, assignment_date, description, content)
             VALUES (?, ?, ?, ?, ?)'
        );

        $stmt->bind_param(
            'issss',
            $subjectId,
            $title,
            $assignmentDate,
            $description,
            $content
        );

        $stmt->execute();
        $stmt->close();

        redirectWithMessage('Tugas berhasil ditambahkan.');
    }

    // Pastikan tugas yang akan diedit memang ada.
    $stmt = $conn->prepare(
        'SELECT id FROM assignments WHERE id = ?'
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $assignmentExists = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$assignmentExists) {
        redirectWithMessage('Tugas tidak ditemukan.', 'error');
    }

    $stmt = $conn->prepare(
        'UPDATE assignments
         SET subject_id = ?, title = ?, assignment_date = ?,
             description = ?, content = ?
         WHERE id = ?'
    );

    $stmt->bind_param(
        'issssi',
        $subjectId,
        $title,
        $assignmentDate,
        $description,
        $content,
        $id
    );

    $stmt->execute();
    $stmt->close();

    redirectWithMessage('Perubahan tugas berhasil disimpan.');

} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());

    redirectWithMessage(
        'Tugas gagal disimpan. Periksa koneksi database dan data yang dimasukkan.',
        'error'
    );
}
