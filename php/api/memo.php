<?php
$data = json_decode(file_get_contents('php://input'), true);
$subject = $data['subject'] ?? '';
$chapter_name = $data['chapter_name'] ?? '';
$problem_number = trim(strval($data['problem_number'] ?? ''));
$memo = trim($data['memo'] ?? '');

if (!in_array($subject, SUBJECTS) || $chapter_name === '' || $problem_number === '') {
    json_response(['error' => '無効なパラメータです'], 400);
}

$db = get_db();

if ($memo === '') {
    $db->prepare("DELETE FROM memos WHERE subject = ? AND chapter_name = ? AND problem_number = ?")
       ->execute([$subject, $chapter_name, $problem_number]);
} else {
    $db->prepare("INSERT INTO memos (subject, chapter_name, problem_number, memo) VALUES (?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE memo = VALUES(memo)")
       ->execute([$subject, $chapter_name, $problem_number, $memo]);
}

json_response(['success' => true]);
