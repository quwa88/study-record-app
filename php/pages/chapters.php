<?php
$display_name = subject_display_name($subject);
$page_title = "$display_name チャプター選択 - USCPA学習記録アプリ";
$is_tbs_subject = is_tbs($subject);
if ($is_tbs_subject) {
    $tbs_problems = load_tbs_problems_from_excel($subject);
    $chapters = array_keys($tbs_problems);
    // MC互換の形式も作成（問題番号のフラットリスト）
    $problems = [];
    foreach ($tbs_problems as $ch => $items) {
        $problems[$ch] = array_column($items, 'number');
    }
} else {
    $problems = load_problems_from_excel($subject);
    $chapters = array_keys($problems);
}

$db = get_db();
$chapter_info = [];
foreach ($chapters as $ch) {
    $problem_count = count($problems[$ch]);

    $stmt = $db->prepare("SELECT COUNT(*) FROM sessions WHERE subject = ? AND chapter_name = ? AND finished_at IS NOT NULL");
    $stmt->execute([$subject, $ch]);
    $session_count = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT id FROM sessions WHERE subject = ? AND chapter_name = ? AND finished_at IS NULL ORDER BY id DESC LIMIT 1");
    $stmt->execute([$subject, $ch]);
    $active = $stmt->fetch();

    $chapter_info[] = [
        'name' => $ch,
        'problem_count' => $problem_count,
        'session_count' => $session_count,
        'active_session_id' => $active ? $active['id'] : null,
    ];
}

// 要復習問題: 30日以上未学習 かつ 正答率75%以下
$cutoff_date = date('Y-m-d', strtotime('-30 days'));
if ($is_tbs_subject) {
    $review_stats = get_tbs_stats($subject, null, 75.0, $cutoff_date);
} else {
    $review_stats = get_stats($subject, null, 75.0, $cutoff_date);
}

// チャプターごとにグループ化
$review_by_chapter = [];
foreach ($review_stats as $s) {
    $review_by_chapter[$s['chapter_name']][] = $s;
}

include __DIR__ . '/../templates/header.php';
?>

<?php if ($review_stats): ?>
<div class="card border-warning mb-4">
    <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-exclamation-triangle"></i> 要復習 (30日以上未学習 &amp; 正答率75%以下) — <?= count($review_stats) ?>問</strong>
        <button id="start-review-btn" class="btn btn-sm btn-dark" disabled>
            <i class="bi bi-play-fill"></i> 選択した問題で学習 (<span id="review-selected-count">0</span>問)
        </button>
    </div>
    <div class="card-body" style="max-height: 500px; overflow-y: auto;">
        <div class="mb-2">
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" id="review-select-all">
                <label class="form-check-label fw-bold" for="review-select-all">すべて選択</label>
            </div>
        </div>
        <?php foreach ($review_by_chapter as $ch_name => $ch_problems): ?>
        <div class="mb-3">
            <div class="form-check mb-1">
                <input class="form-check-input review-chapter-check" type="checkbox" data-chapter="<?= h($ch_name) ?>">
                <label class="form-check-label fw-bold text-primary"><?= h($ch_name) ?> (<?= count($ch_problems) ?>問)</label>
            </div>
            <div class="ms-4">
                <?php foreach ($ch_problems as $s): ?>
                <div class="form-check form-check-inline">
                    <input class="form-check-input review-problem-check" type="checkbox"
                           data-chapter="<?= h($s['chapter_name']) ?>" data-problem="<?= $s['problem_number'] ?>">
                    <label class="form-check-label small">
                        <?= $s['problem_number'] ?>
                        <span class="badge bg-<?= floatval($s['accuracy']) >= 60 ? 'warning' : 'danger' ?>"><?= $s['accuracy'] ?>%</span>
                        <span class="text-muted">(<?= $s['last_study_date'] ?>)</span>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<h2 class="mb-4"><i class="bi bi-book"></i> <?= h($display_name) ?> - チャプターを選択</h2>

<?php if ($chapter_info): ?>
<div class="row g-3">
    <?php foreach ($chapter_info as $ch): ?>
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card chapter-card h-100">
            <div class="card-body">
                <h5 class="card-title"><?= h($ch['name']) ?></h5>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <span class="text-muted">
                        <i class="bi bi-list-ol"></i> <?= $ch['problem_count'] ?>問
                    </span>
                    <span class="badge bg-secondary">
                        学習回数: <?= $ch['session_count'] ?>
                    </span>
                </div>
                <div class="mt-3">
                    <?php if ($ch['active_session_id']): ?>
                    <a href="<?= url("$subject/study/{$ch['active_session_id']}") ?>"
                       class="btn btn-warning w-100">
                        <i class="bi bi-play-circle"></i> 学習を再開する
                    </a>
                    <?php else: ?>
                    <form method="POST" action="<?= url("$subject/start_session") ?>">
                        <input type="hidden" name="chapter_name" value="<?= h($ch['name']) ?>">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-play-fill"></i> 学習を開始する
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    問題データが見つかりません。<a href="<?= url("$subject/upload") ?>">Excel管理</a>からExcelファイルをアップロードしてください。
</div>
<?php endif; ?>

<?php
$subject_json = json_encode($subject);
$custom_url = url('start_custom_session');
$page_scripts = <<<SCRIPT
<script>
(function() {
    const allCheck = document.getElementById('review-select-all');
    const chapterChecks = document.querySelectorAll('.review-chapter-check');
    const problemChecks = document.querySelectorAll('.review-problem-check');
    const startBtn = document.getElementById('start-review-btn');
    const countSpan = document.getElementById('review-selected-count');
    if (!startBtn) return;

    function updateCount() {
        const checked = document.querySelectorAll('.review-problem-check:checked');
        countSpan.textContent = checked.length;
        startBtn.disabled = checked.length === 0;
        if (allCheck) {
            allCheck.checked = problemChecks.length > 0 && checked.length === problemChecks.length;
            allCheck.indeterminate = checked.length > 0 && checked.length < problemChecks.length;
        }
        chapterChecks.forEach(function(cc) {
            const ch = cc.dataset.chapter;
            const chProblems = document.querySelectorAll('.review-problem-check[data-chapter="' + ch + '"]');
            const chChecked = document.querySelectorAll('.review-problem-check[data-chapter="' + ch + '"]:checked');
            cc.checked = chProblems.length > 0 && chChecked.length === chProblems.length;
            cc.indeterminate = chChecked.length > 0 && chChecked.length < chProblems.length;
        });
    }

    if (allCheck) {
        allCheck.addEventListener('change', function() {
            problemChecks.forEach(function(cb) { cb.checked = allCheck.checked; });
            chapterChecks.forEach(function(cc) { cc.checked = allCheck.checked; cc.indeterminate = false; });
            updateCount();
        });
    }

    chapterChecks.forEach(function(cc) {
        cc.addEventListener('change', function() {
            var ch = this.dataset.chapter;
            document.querySelectorAll('.review-problem-check[data-chapter="' + ch + '"]').forEach(function(cb) {
                cb.checked = cc.checked;
            });
            updateCount();
        });
    });

    problemChecks.forEach(function(cb) { cb.addEventListener('change', updateCount); });

    startBtn.addEventListener('click', async function() {
        var selected = [];
        document.querySelectorAll('.review-problem-check:checked').forEach(function(cb) {
            selected.push({ chapter_name: cb.dataset.chapter, problem_number: cb.dataset.problem });
        });
        if (!selected.length) return;
        startBtn.disabled = true;
        startBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> 作成中...';
        try {
            var res = await fetch('{$custom_url}', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ problems: selected, subject: {$subject_json} })
            });
            if (!res.ok) { alert('セッション作成失敗'); return; }
            var data = await res.json();
            if (data.redirect) window.location.href = data.redirect;
        } catch(e) { alert('通信エラー'); }
        finally { startBtn.disabled = false; startBtn.innerHTML = '<i class="bi bi-play-fill"></i> 選択した問題で学習 (<span id="review-selected-count">0</span>問)'; updateCount(); }
    });
})();
</script>
SCRIPT;
include __DIR__ . '/../templates/footer.php';
?>
