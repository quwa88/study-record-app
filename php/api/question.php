<?php
$data = json_decode(file_get_contents('php://input'), true);
$subject = $data['subject'] ?? '';
$chapter_name = $data['chapter_name'] ?? '';
$problem_number = trim(strval($data['problem_number'] ?? ''));
$question_text = trim($data['question_text'] ?? '');
$choice_a = trim($data['choice_a'] ?? '');
$choice_b = trim($data['choice_b'] ?? '');
$choice_c = trim($data['choice_c'] ?? '');
$choice_d = trim($data['choice_d'] ?? '');
$correct_answer = strtoupper(trim($data['correct_answer'] ?? ''));

if (!in_array($subject, SUBJECTS) || $chapter_name === '' || $problem_number === '') {
    json_response(['error' => '無効なパラメータです'], 400);
}
if ($question_text === '' || $choice_a === '' || $choice_b === '' || $choice_c === '' || $choice_d === '') {
    json_response(['error' => '問題文と選択肢をすべて入力してください'], 400);
}
if (!in_array($correct_answer, ['A', 'B', 'C', 'D'])) {
    json_response(['error' => '正解はA/B/C/Dのいずれかを選択してください'], 400);
}

$db = get_db();
$db->prepare("INSERT INTO questions (subject, chapter_name, problem_number, question_text, choice_a, choice_b, choice_c, choice_d, correct_answer)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
              ON DUPLICATE KEY UPDATE question_text = VALUES(question_text), choice_a = VALUES(choice_a), choice_b = VALUES(choice_b),
              choice_c = VALUES(choice_c), choice_d = VALUES(choice_d), correct_answer = VALUES(correct_answer)")
   ->execute([$subject, $chapter_name, $problem_number, $question_text, $choice_a, $choice_b, $choice_c, $choice_d, $correct_answer]);

json_response(['success' => true]);
