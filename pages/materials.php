
<?php
// Halaman ini dipanggil dari index.php.
// Variabel $conn dan helper e() diasumsikan sudah tersedia.

$flashMessage = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'success';

unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$sql = "
    SELECT
        a.id AS assignment_id,
        a.title AS assignment_title,
        m.id AS material_id,
        m.title AS material_title,
        m.summary,
        m.key_points,
        m.updated_at
    FROM assignments a
    LEFT JOIN learning_materials m
        ON m.assignment_id = a.id
    ORDER BY a.created_at DESC
";

$result = $conn->query($sql);
$materials = $result->fetch_all(MYSQLI_ASSOC);
?>

<section class="page-heading">
    <div>
        <h1>Materi Belajar</h1>
        <p>Ubah isi tugas menjadi materi belajar yang bisa kamu pelajari kembali.</p>
    </div>
</section>

<?php if ($flashMessage !== ''): ?>
    <div class="flash-message <?= e($flashType) ?>">
        <?= e($flashMessage) ?>
    </div>
<?php endif; ?>

<div class="assignment-list">
    <?php if (empty($materials)): ?>
        <div class="empty-state">
            <h3>Belum ada tugas</h3>
            <p>Tambahkan tugas terlebih dahulu untuk mulai membuat materi.</p>
        </div>
    <?php endif; ?>

    <?php foreach ($materials as $item): ?>
        <article class="assignment-card">
            <div class="assignment-card-content">
                <h3><?= e($item['assignment_title']) ?></h3>

                <?php if ($item['material_id']): ?>
                    <span class="status-badge">Materi tersedia</span>

                    <p><?= e($item['summary']) ?></p>

                    <?php
                    $points = json_decode(
                        $item['key_points'] ?? '[]',
                        true
                    );

                    if (!is_array($points)) {
                        $points = [];
                    }
                    ?>

                    <?php if ($points): ?>
                        <h4>Poin penting</h4>
                        <ul>
                            <?php foreach ($points as $point): ?>
                                <li><?= e($point) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <p class="muted">
                        Terakhir diperbarui:
                        <?= e($item['updated_at']) ?>
                    </p>
                <?php else: ?>
                    <p>Materi untuk tugas ini belum dibuat.</p>
                <?php endif; ?>
            </div>

            <form
                action="actions/generate-material.php"
                method="POST"
                class="inline-form"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_token']) ?>"
                >

                <input
                    type="hidden"
                    name="assignment_id"
                    value="<?= (int) $item['assignment_id'] ?>"
                >

                <button type="submit" class="button">
                    <?= $item['material_id']
                        ? 'Perbarui Materi'
                        : 'Buat Materi' ?>
                </button>
            </form>
        </article>
    <?php endforeach; ?>
</div>
