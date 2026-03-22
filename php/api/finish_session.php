<?php
$db = get_db();
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ? AND finished_at IS NULL");
$stmt->execute([$session_id]);
$session = $stmt->fetch();

$is_fetch = (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
          || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
          || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');

if (!$session) {
    if ($is_fetch) { json_response(['error' => 'セッションが見つかりません'], 400); }
    flash('セッションが見つかりません。', 'error');
    redirect($subject);
}

$db->prepare("UPDATE sessions SET finished_at = NOW() WHERE id = ?")->execute([$session_id]);

if (is_tbs($subject)) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as total, SUM(correct_count) as sum_correct, SUM(total_subquestions) as sum_total
        FROM tbs_records WHERE session_id = ?
    ");
    $stmt->execute([$session_id]);
    $summary = $stmt->fetch();

    $total = intval($summary['total']);
    $sum_correct = intval($summary['sum_correct']);
    $sum_total = intval($summary['sum_total']);

    if ($is_fetch) {
        json_response(['success' => true, 'total' => $total, 'sum_correct' => $sum_correct, 'sum_total' => $sum_total]);
    }

    if ($total > 0) {
        $acc = $sum_total > 0 ? round(100.0 * $sum_correct / $sum_total, 1) : 0;
        flash("学習終了！ 回答数: {$total}問 / 正解小問: {$sum_correct}/{$sum_total} / 正答率: {$acc}%", 'success');
    } else {
        flash('学習終了しました（回答なし）。', 'info');
    }
} else {
    $stmt = $db->prepare("
        SELECT COUNT(*) as total, SUM(CASE WHEN result = 'correct' THEN 1 ELSE 0 END) as correct
        FROM records WHERE session_id = ?
    ");
    $stmt->execute([$session_id]);
    $summary = $stmt->fetch();

    $total = intval($summary['total']);
    $correct = intval($summary['correct']);

    if ($is_fetch) {
        json_response(['success' => true, 'total' => $total, 'correct' => $correct]);
    }

    if ($total > 0) {
        $acc = round(100.0 * $correct / $total, 1);
        flash("学習終了！ 回答数: {$total}問 / 正解: {$correct}問 / 正答率: {$acc}%", 'success');
    } else {
        flash('学習終了しました（回答なし）。', 'info');
    }
}

redirect($subject);
