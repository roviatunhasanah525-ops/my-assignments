
<?php
$currentPage = $_GET['page'] ?? 'dashboard';

$navigation = [
    [
        'page' => 'dashboard',
        'label' => 'Dashboard',
        'icon' => '⌂'
    ],
    [
        'page' => 'assignments',
        'label' => 'My Assignments',
        'icon' => '▤'
    ],
    [
        'page' => 'materials',
        'label' => 'My Materials',
        'icon' => '▧'
    ],
    [
        'page' => 'progress',
        'label' => 'Learning Progress',
        'icon' => '▥'
    ],
    [
        'page' => 'profile',
        'label' => 'Profil Mahasiswa',
        'icon' => '◎'
    ],
];
?>

<aside class="sidebar" id="sidebar">
    <a class="brand" href="index.php">
        <span class="brand-icon">M</span>
        <span>My Assignments</span>
    </a>

    <div class="sidebar-label">WORKSPACE</div>

    <nav class="nav-menu">
        <?php foreach ($navigation as $item): ?>
            <a
                class="nav-link <?= $currentPage === $item['page']
                    ? 'active'
                    : '' ?>"
                href="index.php?page=<?= $item['page'] ?>"
            >
                <span class="nav-icon">
                    <?= htmlspecialchars($item['icon']) ?>
                </span>
                <span><?= htmlspecialchars($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-bottom">
        <div class="sidebar-tip">
            <div class="tip-icon">✦</div>
            <strong>Small steps, big progress.</strong>
            <p>Ubah tugasmu menjadi pengetahuan baru.</p>
        </div>
        <div class="sidebar-footer">
            <span class="avatar">M</span>
            <div>
                <strong><?= htmlspecialchars($_SESSION['student_name'] ?? 'Mahasiswa', ENT_QUOTES, 'UTF-8') ?></strong>
                <p><a href="index.php?page=profile">Profil mahasiswa</a></p>
            </div>
        </div>
    </div>
</aside>
