<?php
$data = json_decode(file_get_contents('php://input'), true);
$subject = $data['subject'] ?? '';
$chapter_name = $data['chapter_name'] ?? '';
$problem_number = trim(strval($data['problem_number'] ?? ''));
$mark_type = $data['mark_type'] ?? '';
$value = intval($data['value'] ?? 0);

if (!in_array($subject, SUBJECTS) || $chapter_name === '' || $problem_number === '' || !in_array($mark_type, ['mark1', 'mark2'])) {
    json_response(['error' => '無効なパラメータです'], 400);
}

$db = get_db();
$col = $mark_type === 'mark1' ? 'mark1' : 'mark2';
$val = $value ? 1 : 0;

$db->prepare("INSERT INTO marks (subject, chapter_name, problem_number, {$col}) VALUES (?, ?, ?, ?)
              ON DUPLICATE KEY UPDATE {$col} = ?")
   ->execute([$subject, $chapter_name, $problem_number, $val, $val]);

json_response(['success' => true]);
