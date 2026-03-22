<?php
$data = json_decode(file_get_contents('php://input'), true);
$session_id = intval($data['session_id'] ?? 0);
$problem_number = trim(strval($data['problem_number'] ?? ''));
$chapter_name = $data['chapter_name'] ?? '';

$db = get_db();

// セッションの科目を取得
$stmt = $db->prepare("SELECT subject FROM sessions WHERE id = ?");
$stmt->execute([$session_id]);
$session = $stmt->fetch();
$subject = $session ? $session['subject'] : '';

if (is_tbs($subject)) {
    $db->prepare("DELETE FROM tbs_records WHERE session_id = ? AND problem_number = ? AND chapter_name = ?")->execute([$session_id, $problem_number, $chapter_name]);

    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM tbs_records WHERE session_id = ?");
    $stmt->execute([$session_id]);
    $answered = $stmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT SUM(r.correct_count) as sum_correct, SUM(r.total_subquestions) as sum_total, COUNT(*) as total_attempts
        FROM tbs_records r JOIN sessions s ON r.session_id = s.id
        WHERE s.finished_at IS NOT NULL AND r.chapter_name = ? AND r.problem_number = ?
    ");
    $stmt->execute([$chapter_name, $problem_number]);
    $row = $stmt->fetch();

    $sum_correct = intval($row['sum_correct'] ?? 0);
    $sum_total = intval($row['sum_total'] ?? 0);
    $total_attempts = intval($row['total_attempts'] ?? 0);
    $accuracy = $sum_total > 0 ? round(100.0 * $sum_correct / $sum_total, 1) : null;

    json_response([
        'success' => true,
        'sum_correct' => $sum_correct,
        'sum_total' => $sum_total,
        'total_attempts' => $total_attempts,
        'accuracy' => $accuracy,
        'answered' => $answered,
    ]);
} else {
    $db->prepare("DELETE FROM records WHERE session_id = ? AND problem_number = ? AND chapter_name = ?")->execute([$session_id, $problem_number, $chapter_name]);

    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM records WHERE session_id = ?");
    $stmt->execute([$session_id]);
    $answered = $stmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT COUNT(*) as total, SUM(CASE WHEN r.result = 'correct' THEN 1 ELSE 0 END) as correct
        FROM records r JOIN sessions s ON r.session_id = s.id
        WHERE s.finished_at IS NOT NULL AND r.chapter_name = ? AND r.problem_number = ?
    ");
    $stmt->execute([$chapter_name, $problem_number]);
    $row = $stmt->fetch();

    $total = intval($row['total']);
    $correct = intval($row['correct'] ?? 0);
    $accuracy = $total > 0 ? round(100.0 * $correct / $total, 1) : null;

    json_response([
        'success' => true,
        'total' => $total,
        'correct' => $correct,
        'accuracy' => $accuracy,
        'answered' => $answered,
    ]);
}
