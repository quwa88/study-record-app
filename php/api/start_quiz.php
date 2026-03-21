<?php
$data = json_decode(file_get_contents('php://input'), true);
$subject = $data['subject'] ?? '';
$count = intval($data['count'] ?? 20);
$chapter = $data['chapter'] ?? '';
$shuffle = $data['shuffle'] ?? true;

if (!in_array($subject, SUBJECTS)) {
    json_response(['error' => '無効な科目です'], 400);
}

$db = get_db();

// 問題を取得
if ($chapter !== '') {
    $stmt = $db->prepare("SELECT chapter_name, problem_number FROM questions WHERE subject = ? AND chapter_name = ? ORDER BY RAND() LIMIT ?");
    $stmt->bindValue(1, $subject);
    $stmt->bindValue(2, $chapter);
    $stmt->bindValue(3, $count, PDO::PARAM_INT);
} else {
    $stmt = $db->prepare("SELECT chapter_name, problem_number FROM questions WHERE subject = ? ORDER BY RAND() LIMIT ?");
    $stmt->bindValue(1, $subject);
    $stmt->bindValue(2, $count, PDO::PARAM_INT);
}
$stmt->execute();
$questions = $stmt->fetchAll();

if (!$questions) {
    json_response(['error' => '問題が見つかりません'], 400);
}

// カスタムセッションとして作成
$today = date('Y-m-d');
$sess_stmt = $db->prepare("INSERT INTO sessions (subject, chapter_name, study_date) VALUES (?, ?, ?)");
$sess_stmt->execute([$subject, 'クイズ', $today]);
$session_id = $db->lastInsertId();

$ins = $db->prepare("INSERT INTO custom_session_problems (session_id, chapter_name, problem_number) VALUES (?, ?, ?)");
foreach ($questions as $q) {
    $ins->execute([$session_id, $q['chapter_name'], $q['problem_number']]);
}

// シャッフル設定をセッションに保存
$_SESSION["quiz_shuffle_{$session_id}"] = $shuffle ? 1 : 0;
$_SESSION["quiz_mode_{$session_id}"] = 1;

json_response(['redirect' => url("$subject/quiz/$session_id")]);
