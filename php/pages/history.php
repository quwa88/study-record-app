<?php
$page_title = "$subject 学習履歴 - 演習記録";
$db = get_db();
$stmt = $db->prepare("
    SELECT r.chapter_name, r.problem_number, r.result, r.study_date, r.created_at, r.session_id
    FROM records r JOIN sessions s ON r.session_id = s.id
    WHERE s.finished_at IS NOT NULL AND s.subject = ?
    ORDER BY r.created_at DESC LIMIT 200
");
$stmt->execute([$subject]);
$records = $stmt->fetchAll();

include __DIR__ . '/../templates/header.php';
?>

<h2 class="mb-4"><i class="bi bi-clock-history"></i> <?= h($subject) ?> 学習履歴</h2>

<?php if ($records): ?>
<div class="table-responsive">
    <table class="table table-hover table-sm">
        <thead class="table-light">
            <tr><th>学習日</th><th>チャプター</th><th>問題番号</th><th>結果</th><th>記録日時</th></tr>
        </thead>
        <tbody>
            <?php foreach ($records as $r): ?>
            <tr>
                <td><?= h($r['study_date']) ?></td>
                <td><?= h($r['chapter_name']) ?></td>
                <td><strong><?= $r['problem_number'] ?></strong></td>
                <td>
                    <?php if ($r['result'] === 'correct'): ?>
                        <span class="badge bg-success"><i class="bi bi-circle"></i> 正解</span>
                    <?php else: ?>
                        <span class="badge bg-danger"><i class="bi bi-x-lg"></i> 不正解</span>
                    <?php endif; ?>
                </td>
                <td class="text-muted small"><?= h($r['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="alert alert-info"><i class="bi bi-info-circle"></i> まだ学習記録がありません。</div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
