<?php
/**
 * 設定ファイル - DB接続、共通定数、フラッシュメッセージ
 */
session_start();

// .env読み込み
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

// DB接続設定
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'study_records');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASSWORD'] ?? '');

// 科目一覧
define('SUBJECTS', ['FAR', 'BAR', 'REG', 'AUD']);

// ベースパス（サブディレクトリに設置する場合）
define('BASE_PATH', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'));
define('DATA_DIR', __DIR__ . '/data');

/**
 * DB接続を取得
 */
function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

/**
 * DB初期化（テーブル作成）
 */
function init_db(): void {
    $db = get_db();
    $db->exec("
        CREATE TABLE IF NOT EXISTS sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subject VARCHAR(10) NOT NULL DEFAULT '',
            chapter_name VARCHAR(255) NOT NULL,
            study_date DATE NOT NULL,
            started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            finished_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            chapter_name VARCHAR(255) NOT NULL,
            problem_number VARCHAR(50) NOT NULL,
            result ENUM('correct', 'incorrect') NOT NULL,
            study_date DATE NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (session_id) REFERENCES sessions(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS custom_session_problems (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            chapter_name VARCHAR(255) NOT NULL,
            problem_number VARCHAR(50) NOT NULL,
            FOREIGN KEY (session_id) REFERENCES sessions(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS memos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subject VARCHAR(10) NOT NULL,
            chapter_name VARCHAR(255) NOT NULL,
            problem_number VARCHAR(50) NOT NULL,
            memo TEXT NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_memo (subject, chapter_name, problem_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS marks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subject VARCHAR(10) NOT NULL,
            chapter_name VARCHAR(255) NOT NULL,
            problem_number VARCHAR(50) NOT NULL,
            mark1 TINYINT(1) NOT NULL DEFAULT 0,
            mark2 TINYINT(1) NOT NULL DEFAULT 0,
            UNIQUE KEY uq_mark (subject, chapter_name, problem_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    // 既存テーブルのproblem_numberをVARCHARに変更
    try {
        $db->exec("ALTER TABLE records MODIFY COLUMN problem_number VARCHAR(50) NOT NULL");
        $db->exec("ALTER TABLE custom_session_problems MODIFY COLUMN problem_number VARCHAR(50) NOT NULL");
    } catch (\Exception $e) {
        // 既に変更済みの場合は無視
    }
}

/**
 * フラッシュメッセージを設定
 */
function flash(string $message, string $category = 'info'): void {
    $_SESSION['flash'][] = ['category' => $category, 'message' => $message];
}

/**
 * フラッシュメッセージを取得して削除
 */
function get_flashes(): array {
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/**
 * URL生成
 */
function url(string $path = ''): string {
    return BASE_PATH . '/' . ltrim($path, '/');
}

/**
 * JSONレスポンスを返す
 */
function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * リダイレクト
 */
function redirect(string $path): void {
    header('Location: ' . url($path));
    exit;
}

/**
 * HTMLエスケープ
 */
function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// DB初期化（テーブル未作成時のみ実行される）
init_db();
