<?php
/**
 * ヘルパー関数 - Excel読み込み、統計取得
 */
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * 科目のExcelファイルパスを返す
 */
function get_excel_path(string $subject): string {
    return DATA_DIR . "/problems_{$subject}.xlsx";
}

/**
 * Excelから問題データを読み込む
 */
function load_problems_from_excel(string $subject): array {
    $path = get_excel_path($subject);
    if (!file_exists($path)) {
        return [];
    }

    $spreadsheet = IOFactory::load($path);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true);

    $problems = [];
    $first = true;
    foreach ($rows as $row) {
        if ($first) { $first = false; continue; } // ヘッダースキップ
        $chapter = trim($row['A'] ?? '');
        $number = trim(strval($row['B'] ?? ''));
        if ($chapter === '' || $number === '') continue;
        $problems[$chapter][] = $number;
    }

    foreach ($problems as &$nums) {
        sort($nums, SORT_NATURAL);
    }

    return $problems;
}

/**
 * 問題ごとの統計を取得（完了済みセッションのみ）
 */
function get_stats(?string $subject = null, ?string $chapter_name = null, ?float $max_accuracy = null): array {
    $db = get_db();

    $sql = "
        SELECT
            r.chapter_name,
            r.problem_number,
            COUNT(*) as total_attempts,
            SUM(CASE WHEN r.result = 'correct' THEN 1 ELSE 0 END) as correct_count,
            ROUND(
                100.0 * SUM(CASE WHEN r.result = 'correct' THEN 1 ELSE 0 END) / COUNT(*),
                1
            ) as accuracy,
            MAX(r.study_date) as last_study_date
        FROM records r
        JOIN sessions s ON r.session_id = s.id
        WHERE s.finished_at IS NOT NULL
    ";

    $params = [];
    if ($subject) {
        $sql .= " AND s.subject = ?";
        $params[] = $subject;
    }
    if ($chapter_name) {
        $sql .= " AND r.chapter_name = ?";
        $params[] = $chapter_name;
    }

    $sql .= " GROUP BY r.chapter_name, r.problem_number";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $stats = $stmt->fetchAll();

    if ($max_accuracy !== null) {
        $stats = array_filter($stats, function($s) use ($max_accuracy) {
            return floatval($s['accuracy']) <= $max_accuracy;
        });
        $stats = array_values($stats);
    }

    return $stats;
}
