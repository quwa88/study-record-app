<?php
/**
 * ルーター - 全リクエストのエントリーポイント
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

$route = trim($_GET['route'] ?? '', '/');
$method = $_SERVER['REQUEST_METHOD'];

// ルーティング
if ($route === '') {
    require __DIR__ . '/pages/home.php';

} elseif ($route === 'record' && $method === 'POST') {
    require __DIR__ . '/api/record.php';

} elseif ($route === 'undo' && $method === 'POST') {
    require __DIR__ . '/api/undo.php';

} elseif ($route === 'start_custom_session' && $method === 'POST') {
    require __DIR__ . '/api/start_custom_session.php';

} elseif ($route === 'memo' && $method === 'POST') {
    require __DIR__ . '/api/memo.php';

} elseif (preg_match('#^([A-Z]+)$#', $route, $m) && in_array($m[1], SUBJECTS)) {
    $subject = $m[1];
    require __DIR__ . '/pages/chapters.php';

} elseif (preg_match('#^([A-Z]+)/start_session$#', $route, $m) && in_array($m[1], SUBJECTS) && $method === 'POST') {
    $subject = $m[1];
    require __DIR__ . '/api/start_session.php';

} elseif (preg_match('#^([A-Z]+)/study/(\d+)$#', $route, $m) && in_array($m[1], SUBJECTS)) {
    $subject = $m[1];
    $session_id = intval($m[2]);
    require __DIR__ . '/pages/study.php';

} elseif (preg_match('#^([A-Z]+)/study_custom/(\d+)$#', $route, $m) && in_array($m[1], SUBJECTS)) {
    $subject = $m[1];
    $session_id = intval($m[2]);
    require __DIR__ . '/pages/study_custom.php';

} elseif (preg_match('#^([A-Z]+)/finish_session/(\d+)$#', $route, $m) && in_array($m[1], SUBJECTS) && $method === 'POST') {
    $subject = $m[1];
    $session_id = intval($m[2]);
    require __DIR__ . '/api/finish_session.php';

} elseif (preg_match('#^([A-Z]+)/dashboard$#', $route, $m) && in_array($m[1], SUBJECTS)) {
    $subject = $m[1];
    require __DIR__ . '/pages/dashboard.php';

} elseif (preg_match('#^([A-Z]+)/history$#', $route, $m) && in_array($m[1], SUBJECTS)) {
    $subject = $m[1];
    require __DIR__ . '/pages/history.php';

} elseif (preg_match('#^([A-Z]+)/upload$#', $route, $m) && in_array($m[1], SUBJECTS)) {
    $subject = $m[1];
    require __DIR__ . '/pages/upload.php';

} else {
    http_response_code(404);
    echo '<h1>404 Not Found</h1>';
}
