<?php
$page_title = "$subject チャプター選択 - 演習記録";
$problems = load_problems_from_excel($subject);
$chapters = array_keys($problems);

$db = get_db();
$chapter_info = [];
foreach ($chapters as $ch) {
    $problem_count = count($problems[$ch]);

    $stmt = $db->prepare("SELECT COUNT(*) FROM sessions WHERE subject = ? AND chapter_name = ? AND finished_at IS NOT NULL");
    $stmt->execute([$subject, $ch]);
    $session_count = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT id FROM sessions WHERE subject = ? AND chapter_name = ? AND finished_at IS NULL ORDER BY id DESC LIMIT 1");
    $stmt->execute([$subject, $ch]);
    $active = $stmt->fetch();

    $chapter_info[] = [
        'name' => $ch,
        'problem_count' => $problem_count,
        'session_count' => $session_count,
        'active_session_id' => $active ? $active['id'] : null,
    ];
}

include __DIR__ . '/../templates/header.php';
?>

<h2 class="mb-4"><i class="bi bi-book"></i> <?= h($subject) ?> - チャプターを選択</h2>

<?php if ($chapter_info): ?>
<div class="row g-3">
    <?php foreach ($chapter_info as $ch): ?>
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card chapter-card h-100">
            <div class="card-body">
                <h5 class="card-title"><?= h($ch['name']) ?></h5>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <span class="text-muted">
                        <i class="bi bi-list-ol"></i> <?= $ch['problem_count'] ?>問
                    </span>
                    <span class="badge bg-secondary">
                        学習回数: <?= $ch['session_count'] ?>
                    </span>
                </div>
                <div class="mt-3">
                    <?php if ($ch['active_session_id']): ?>
                    <a href="<?= url("$subject/study/{$ch['active_session_id']}") ?>"
                       class="btn btn-warning w-100">
                        <i class="bi bi-play-circle"></i> 学習を再開する
                    </a>
                    <?php else: ?>
                    <form method="POST" action="<?= url("$subject/start_session") ?>">
                        <input type="hidden" name="chapter_name" value="<?= h($ch['name']) ?>">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-play-fill"></i> 学習を開始する
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    問題データが見つかりません。<a href="<?= url("$subject/upload") ?>">Excel管理</a>からExcelファイルをアップロードしてください。
</div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
