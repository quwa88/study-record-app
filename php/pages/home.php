<?php
$page_title = '科目選択 - 演習記録';
$subject = '';
$db = get_db();

$subject_info = [];
foreach (SUBJECTS as $subj) {
    $problems = load_problems_from_excel($subj);
    $chapter_count = count($problems);
    $total_problems = array_sum(array_map('count', $problems));

    $stmt = $db->prepare("SELECT COUNT(*) FROM sessions WHERE subject = ? AND finished_at IS NOT NULL");
    $stmt->execute([$subj]);
    $session_count = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM sessions WHERE subject = ? AND finished_at IS NULL");
    $stmt->execute([$subj]);
    $active_count = $stmt->fetchColumn();

    $subject_info[] = [
        'name' => $subj,
        'chapter_count' => $chapter_count,
        'total_problems' => $total_problems,
        'session_count' => $session_count,
        'active_count' => $active_count,
    ];
}

include __DIR__ . '/../templates/header.php';
?>

<h2 class="mb-4"><i class="bi bi-mortarboard"></i> 科目を選択</h2>

<div class="row g-4">
    <?php foreach ($subject_info as $subj): ?>
    <div class="col-12 col-md-6 col-lg-3">
        <a href="<?= url($subj['name']) ?>" class="text-decoration-none">
            <div class="card subject-card h-100 text-center">
                <div class="card-body d-flex flex-column justify-content-center">
                    <div class="subject-cover mb-3">
                        <i class="bi bi-book-half display-1 text-primary"></i>
                    </div>
                    <h3 class="card-title fw-bold"><?= h($subj['name']) ?></h3>
                    <div class="mt-3 text-muted">
                        <?php if ($subj['chapter_count'] > 0): ?>
                        <div><i class="bi bi-list-ol"></i> <?= $subj['chapter_count'] ?>チャプター / <?= $subj['total_problems'] ?>問</div>
                        <?php else: ?>
                        <div class="text-warning"><i class="bi bi-exclamation-triangle"></i> 未登録</div>
                        <?php endif; ?>
                    </div>
                    <div class="mt-2">
                        <span class="badge bg-secondary">学習回数: <?= $subj['session_count'] ?></span>
                        <?php if ($subj['active_count'] > 0): ?>
                        <span class="badge bg-warning text-dark">進行中: <?= $subj['active_count'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
