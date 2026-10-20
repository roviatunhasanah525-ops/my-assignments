
<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$escape = static fn($value) => htmlspecialchars(
    (string) $value,
    ENT_QUOTES,
    'UTF-8'
);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$materials = [];
$materialsResult = $conn->query(
    "SELECT id, title FROM learning_materials ORDER BY title ASC"
);

while ($row = $materialsResult->fetch_assoc()) {
    $materials[] = $row;
}

$selectedMaterialId = (int) ($_GET['material_id'] ?? 0);

if (
    $selectedMaterialId === 0 &&
    !empty($materials)
) {
    $selectedMaterialId = (int) $materials[0]['id'];
}

$selectedMaterial = null;
$questions = [];

foreach ($materials as $material) {
    if ((int) $material['id'] === $selectedMaterialId) {
        $selectedMaterial = $material;
        break;
    }
}

if ($selectedMaterial) {
    $stmt = $conn->prepare(
        "SELECT id, question, option_a, option_b, option_c, option_d
         FROM learning_quiz_questions
         WHERE material_id = ?
         ORDER BY id ASC"
    );
    $stmt->bind_param('i', $selectedMaterialId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $questions[] = $row;
    }

    $stmt->close();
}

$quizResult = $_SESSION['quiz_result'] ?? null;
if (
    $quizResult &&
    (int) $quizResult['material_id'] !== $selectedMaterialId
) {
    $quizResult = null;
}
unset($_SESSION['quiz_result']);

$flash = $_SESSION['quiz_flash'] ?? null;
unset($_SESSION['quiz_flash']);

$history = [];

if ($selectedMaterialId > 0) {
    $historyStmt = $conn->prepare(
        'SELECT id, total_questions, correct_count, score, created_at
         FROM quiz_attempts
         WHERE material_id = ?
         ORDER BY created_at DESC, id DESC
         LIMIT 20'
    );

    $historyStmt->bind_param('i', $selectedMaterialId);
    $historyStmt->execute();
    $historyResult = $historyStmt->get_result();

    while ($row = $historyResult->fetch_assoc()) {
        $history[] = $row;
    }

    $historyStmt->close();
}

?>

<style>
.quiz-page {
    max-width: 1000px;
    margin: 0 auto;
}
.quiz-section {
    margin: 20px 0;
    padding: 22px;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    background: #fff;
}
.quiz-section h2 {
    margin-top: 0;
}
.quiz-form {
    display: grid;
    gap: 14px;
}
.quiz-field {
    display: grid;
    gap: 7px;
}
.quiz-field input,
.quiz-field textarea,
.quiz-field select {
    width: 100%;
    box-sizing: border-box;
    padding: 11px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font: inherit;
}
.quiz-btn {
    display: inline-block;
    width: fit-content;
    padding: 11px 17px;
    border: 0;
    border-radius: 8px;
    background: #7046c7;
    color: #fff;
    font: inherit;
    cursor: pointer;
    text-decoration: none;
}
.quiz-question {
    padding: 16px 0;
    border-bottom: 1px solid #e5e7eb;
}
.quiz-option {
    display: block;
    margin: 10px 0;
}
.quiz-notice {
    padding: 13px;
    margin: 12px 0;
    border-radius: 8px;
    background: #f3f0ff;
}
.quiz-score {
    font-size: 30px;
    font-weight: 700;
    color: #7046c7;
}
.quiz-correct {
    color: #15803d;
}
.quiz-wrong {
    color: #b91c1c;
}
.quiz-muted {
    color: #6b7280;
}
</style>

<div class="quiz-page">
    <h1>Kuis Belajar</h1>
    <p class="quiz-muted">
        Buat pertanyaan berdasarkan materi, kerjakan kuis,
        dan lihat hasilnya.
    </p>

    <?php if ($flash): ?>
        <div class="quiz-notice">
            <?= $escape($flash['message']) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($materials)): ?>
        <div class="quiz-section">
            <p>Belum ada materi belajar. Buat materi terlebih dahulu
            sebelum menambahkan soal kuis.</p>
        </div>
    <?php else: ?>

        <section class="quiz-section">
            <h2>Pilih Materi</h2>

            <form method="GET" action="index.php">
                <input type="hidden" name="page" value="quiz">

                <div class="quiz-form">
                    <div class="quiz-field">
                        <label for="material_id">Materi belajar</label>
                        <select id="material_id" name="material_id"
                                onchange="this.form.submit()">
                            <?php foreach ($materials as $material): ?>
                                <option
                                    value="<?= (int) $material['id'] ?>"
                                    <?= (int) $material['id'] === $selectedMaterialId
                                        ? 'selected' : '' ?>>
                                    <?= $escape($material['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <noscript>
                        <button class="quiz-btn" type="submit">
                            Pilih Materi
                        </button>
                    </noscript>
                </div>
            </form>
        </section>

        <?php if ($selectedMaterial): ?>
            <section class="quiz-section">
                <h2>Soal Kuis Otomatis</h2>
                <p class="quiz-muted">
                    Sistem membuat pertanyaan pilihan ganda berdasarkan ringkasan dan poin penting dari tugas yang dipilih.
                    Jika dibuat ulang, soal kuis lama untuk materi ini akan diganti.
                </p>
                <form method="POST" action="actions/generate-quiz.php">
                    <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="material_id" value="<?= $selectedMaterialId ?>">
                    <button class="quiz-btn" type="submit" onclick="return confirm('Buat soal otomatis berdasarkan materi ini? Soal lama untuk materi ini akan diganti.')">
                        ✨ Buat Soal Otomatis
                    </button>
                </form>
            </section>

            <section class="quiz-section">
                <h2>Kerjakan Kuis</h2>

                <?php if (empty($questions)): ?>
                    <p>Belum ada soal untuk materi ini.
                    Tambahkan beberapa soal terlebih dahulu.</p>
                <?php else: ?>
                    <p class="quiz-muted">
                        Total <?= count($questions) ?> soal.
                        Pilih satu jawaban untuk setiap pertanyaan.
                    </p>

                    <form class="quiz-form"
                          method="POST"
                          action="actions/submit-quiz.php">

                        <input type="hidden" name="csrf_token"
                               value="<?= $escape($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="material_id"
                               value="<?= $selectedMaterialId ?>">

                        <?php foreach ($questions as $index => $question): ?>
                            <div class="quiz-question">
                                <strong>
                                    <?= $index + 1 ?>.
                                    <?= $escape($question['question']) ?>
                                </strong>

                                <?php foreach (['A', 'B', 'C', 'D'] as $letter): ?>
                                    <label class="quiz-option">
                                        <input type="radio"
                                               name="answers[<?= (int) $question['id'] ?>]"
                                               value="<?= $letter ?>"
                                               required>
                                        <strong><?= $letter ?>.</strong>
                                        <?= $escape($question['option_' . strtolower($letter)]) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>

                        <button class="quiz-btn" type="submit"
                                onclick="return confirm('Kumpulkan jawaban kuis sekarang?')">
                            Periksa Jawaban
                        </button>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>

    <?php endif; ?>

    <?php if ($quizResult): ?>
        <section class="quiz-section">
            <h2>Hasil Kuis</h2>

            <p class="quiz-score">
                <?= number_format((float) $quizResult['score'], 2) ?>
            </p>

            <p>
                Jawaban benar:
                <strong><?= (int) $quizResult['correct_answers'] ?></strong>
                dari
                <strong><?= (int) $quizResult['total_questions'] ?></strong>
                soal.
            </p>

            <?php foreach ($quizResult['details'] as $index => $detail): ?>
                <div class="quiz-question">
                    <strong>
                        <?= $index + 1 ?>.
                        <?= $escape($detail['question']) ?>
                    </strong>

                    <p class="<?= $detail['is_correct']
                        ? 'quiz-correct' : 'quiz-wrong' ?>">
                        <?= $detail['is_correct']
                            ? 'Jawaban benar' : 'Jawaban belum tepat' ?>
                    </p>

                    <p>
                        Jawaban kamu:
                        <?= $escape($detail['selected'] ?: 'Tidak dijawab') ?>
                    </p>

                    <p>
                        Kunci jawaban:
                        <strong><?= $escape($detail['correct_answer']) ?></strong>
                        — <?= $escape(
                            $detail['option_' . strtolower($detail['correct_answer'])]
                        ) ?>
                    </p>

                    <?php if (!empty($detail['explanation'])): ?>
                        <p>
                            Penjelasan:
                            <?= $escape($detail['explanation']) ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

<section class="quiz-section">
    <h2>Riwayat Hasil Kuis</h2>

    <?php if (empty($history)): ?>
        <p class="quiz-muted">
            Belum ada riwayat pengerjaan untuk materi ini.
        </p>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="text-align:left;padding:10px;">No.</th>
                        <th style="text-align:left;padding:10px;">Benar</th>
                        <th style="text-align:left;padding:10px;">Skor</th>
                        <th style="text-align:left;padding:10px;">Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $index => $attempt): ?>
                        <tr>
                            <td style="padding:10px;border-top:1px solid #eee;">
                                <?= $index + 1 ?>
                            </td>
                            <td style="padding:10px;border-top:1px solid #eee;">
                                <?= (int) $attempt['correct_count'] ?>
                                /
                                <?= (int) $attempt['total_questions'] ?>
                            </td>
                            <td style="padding:10px;border-top:1px solid #eee;">
                                <strong>
                                    <?= number_format((float) $attempt['score'], 2) ?>
                                </strong>
                            </td>
                            <td style="padding:10px;border-top:1px solid #eee;">
                                <?= $escape($attempt['created_at']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

</div>
