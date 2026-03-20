<?php
$db = get_db();
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ? AND finished_at IS NULL");
$stmt->execute([$session_id]);
$session = $stmt->fetch();

if (!$session) {
    flash('セッションが見つかりません。', 'error');
    redirect($subject);
}

$db->prepare("UPDATE sessions SET finished_at = NOW() WHERE id = ?")->execute([$session_id]);

$stmt = $db->prepare("
    SELECT COUNT(*) as total, SUM(CASE WHEN result = 'correct' THEN 1 ELSE 0 END) as correct
    FROM records WHERE session_id = ?
");
$stmt->execute([$session_id]);
$summary = $stmt->fetch();

$total = intval($summary['total']);
$correct = intval($summary['correct']);
if ($total > 0) {
    $acc = round(100.0 * $correct / $total, 1);
    flash("学習終了！ 回答数: {$total}問 / 正解: {$correct}問 / 正答率: {$acc}%", 'success');
} else {
    flash('学習終了しました（回答なし）。', 'info');
}

redirect($subject);
