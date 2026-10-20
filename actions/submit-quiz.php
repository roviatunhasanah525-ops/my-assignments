
<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }

require_once __DIR__ . '/../config/database.php';

function submitQuizRedirect(int $materialId): never
{
    header(
        'Location: ../index.php?page=quiz&material_id=' . $materialId
    );
    exit;
}

$materialId = (int) ($_POST['material_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    submitQuizRedirect($materialId);
}

$token = (string) ($_POST['csrf_token'] ?? '');

if (
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $token)
) {
    $_SESSION['quiz_flash'] = [
        'message' => 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.'
    ];
    submitQuizRedirect($materialId);
}

if ($materialId < 1) {
    $_SESSION['quiz_flash'] = [
        'message' => 'Materi kuis tidak valid.'
    ];
    submitQuizRedirect($materialId);
}

try {
    $materialStmt = $conn->prepare(
        'SELECT title FROM learning_materials WHERE id = ?'
    );
    $materialStmt->bind_param('i', $materialId);
    $materialStmt->execute();
    $material = $materialStmt->get_result()->fetch_assoc();
    $materialStmt->close();

    if (!$material) {
        throw new RuntimeException('Materi tidak ditemukan.');
    }

    $stmt = $conn->prepare(
        'SELECT id, question, option_a, option_b, option_c,
                option_d, correct_answer, explanation
         FROM learning_quiz_questions
         WHERE material_id = ?
         ORDER BY id ASC'
    );
    $stmt->bind_param('i', $materialId);
    $stmt->execute();
    $result = $stmt->get_result();

    $questions = [];
    while ($row = $result->fetch_assoc()) {
        $questions[] = $row;
    }
    $stmt->close();

    $totalQuestions = count($questions);

    if ($totalQuestions === 0) {
        throw new RuntimeException('Materi ini belum memiliki soal kuis.');
    }

    $answers = $_POST['answers'] ?? [];

    if (!is_array($answers)) {
        throw new RuntimeException('Format jawaban tidak valid.');
    }

    $correctAnswers = 0;
    $details = [];

    foreach ($questions as $question) {
        $questionId = (int) $question['id'];
        $selected = strtoupper(
            trim((string) ($answers[$questionId] ?? ''))
        );

        if (!in_array($selected, ['A', 'B', 'C', 'D'], true)) {
            throw new RuntimeException(
                'Semua pertanyaan wajib dijawab sebelum dikumpulkan.'
            );
        }

        $correctAnswer = strtoupper($question['correct_answer']);
        $isCorrect = ($selected === $correctAnswer);

        if ($isCorrect) {
            $correctAnswers++;
        }

        $details[] = [
            'question' => $question['question'],
            'selected' => $selected,
            'correct_answer' => $correctAnswer,
            'option_a' => $question['option_a'],
            'option_b' => $question['option_b'],
            'option_c' => $question['option_c'],
            'option_d' => $question['option_d'],
            'explanation' => $question['explanation'],
            'is_correct' => $isCorrect
        ];
    }

    $score = round(
        ($correctAnswers / $totalQuestions) * 100,
        2
    );

    $save = $conn->prepare(
        'INSERT INTO quiz_attempts
        (material_id, total_questions, correct_count, score)
        VALUES (?, ?, ?, ?)'
    );

    $save->bind_param(
        'iiid',
        $materialId,
        $totalQuestions,
        $correctAnswers,
        $score
    );

    $save->execute();
    $attemptId = (int) $conn->insert_id;
    $save->close();

    $_SESSION['quiz_result'] = [
        'attempt_id' => $attemptId,
        'material_id' => $materialId,
        'material_title' => $material['title'],
        'total_questions' => $totalQuestions,
        'correct_answers' => $correctAnswers,
        'score' => $score,
        'details' => $details
    ];
} catch (Throwable $error) {
    error_log($error->getMessage());

    $_SESSION['quiz_flash'] = [
        'message' => $error instanceof RuntimeException
            ? $error->getMessage()
            : 'Hasil kuis gagal diproses. Silakan periksa konfigurasi database.'
    ];
}

submitQuizRedirect($materialId);
