<?php
$data = json_decode(file_get_contents('php://input'), true);
$session_id = intval($data['session_id'] ?? 0);
$chapter_name = $data['chapter_name'] ?? '';
$problem_number = trim(strval($data['problem_number'] ?? ''));

$db = get_db();
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ? AND finished_at IS NULL");
$stmt->execute([$session_id]);
$session = $stmt->fetch();

if (!$session) {
    json_response(['error' => 'セッションが無効です'], 400);
}

$study_date = $session['study_date'];
$subject = $session['subject'];

if (is_tbs($subject)) {
    // TBS: correct_count と total_subquestions を記録
    $correct_count = intval($data['correct_count'] ?? 0);
    $total_subquestions = intval($data['total_subquestions'] ?? 0);

    if ($total_subquestions <= 0) {
        json_response(['error' => '小問数が無効です'], 400);
    }
    if ($correct_count < 0 || $correct_count > $total_subquestions) {
        json_response(['error' => '正解数が無効です'], 400);
    }

    $db->prepare("DELETE FROM tbs_records WHERE session_id = ? AND problem_number = ? AND chapter_name = ?")->execute([$session_id, $problem_number, $chapter_name]);
    $db->prepare("INSERT INTO tbs_records (session_id, chapter_name, problem_number, correct_count, total_subquestions, study_date) VALUES (?, ?, ?, ?, ?, ?)")
       ->execute([$session_id, $chapter_name, $problem_number, $correct_count, $total_subquestions, $study_date]);

    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM tbs_records WHERE session_id = ?");
    $stmt->execute([$session_id]);
    $answered = $stmt->fetchColumn();

    // 累計統計
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
    // MC: 従来の correct/incorrect 記録
    $result = $data['result'] ?? '';
    if (!in_array($result, ['correct', 'incorrect'])) {
        json_response(['error' => '無効な結果です'], 400);
    }

    $db->prepare("DELETE FROM records WHERE session_id = ? AND problem_number = ? AND chapter_name = ?")->execute([$session_id, $problem_number, $chapter_name]);
    $db->prepare("INSERT INTO records (session_id, chapter_name, problem_number, result, study_date) VALUES (?, ?, ?, ?, ?)")->execute([$session_id, $chapter_name, $problem_number, $result, $study_date]);

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
