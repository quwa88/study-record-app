<?php
$chapter_name = $_POST['chapter_name'] ?? '';

if (is_tbs($subject)) {
    $problems = load_tbs_problems_from_excel($subject);
} else {
    $problems = load_problems_from_excel($subject);
}

if (!isset($problems[$chapter_name])) {
    flash('指定されたチャプターが見つかりません。', 'error');
    redirect($subject);
}

$today = date('Y-m-d');
$db = get_db();
$stmt = $db->prepare("INSERT INTO sessions (subject, chapter_name, study_date) VALUES (?, ?, ?)");
$stmt->execute([$subject, $chapter_name, $today]);
$session_id = $db->lastInsertId();

redirect("$subject/study/$session_id");
