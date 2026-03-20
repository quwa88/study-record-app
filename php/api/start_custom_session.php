<?php
$data = json_decode(file_get_contents('php://input'), true);
$problems = $data['problems'] ?? [];
$subject = $data['subject'] ?? '';

if (!$problems || !in_array($subject, SUBJECTS)) {
    json_response(['error' => '問題が選択されていません'], 400);
}

$today = date('Y-m-d');
$db = get_db();
$stmt = $db->prepare("INSERT INTO sessions (subject, chapter_name, study_date) VALUES (?, ?, ?)");
$stmt->execute([$subject, 'カスタム', $today]);
$session_id = $db->lastInsertId();

$ins = $db->prepare("INSERT INTO custom_session_problems (session_id, chapter_name, problem_number) VALUES (?, ?, ?)");
foreach ($problems as $p) {
    $ins->execute([$session_id, $p['chapter_name'], intval($p['problem_number'])]);
}

json_response(['redirect' => url("$subject/study_custom/$session_id")]);
