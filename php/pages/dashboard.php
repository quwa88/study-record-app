<?php
$page_title = "$subject ダッシュボード - 演習記録";
$chapter_filter = $_GET['chapter'] ?? '';
$max_accuracy_input = $_GET['max_accuracy'] ?? '';
$max_acc_val = ($max_accuracy_input !== '') ? floatval($max_accuracy_input) : null;
$before_date_input = $_GET['before_date'] ?? '';
$days_ago_input = $_GET['days_ago'] ?? '';

$before_date = null;
if ($before_date_input !== '') {
    $before_date = $before_date_input;
} elseif ($days_ago_input !== '') {
    $before_date = date('Y-m-d', strtotime("-{$days_ago_input} days"));
}

$stats = get_stats($subject, $chapter_filter ?: null, $max_acc_val, $before_date);
$problems = load_problems_from_excel($subject);
$chapters = array_keys($problems);

// メモを取得
$memo_map = [];
if ($stats) {
    $memo_stmt = $db->prepare("SELECT chapter_name, problem_number, memo FROM memos WHERE subject = ?");
    $memo_stmt->execute([$subject]);
    foreach ($memo_stmt->fetchAll() as $m) {
        $memo_map[$m['chapter_name'] . '::' . $m['problem_number']] = $m['memo'];
    }
}

// 正答率でソート
usort($stats, function($a, $b) { return floatval($a['accuracy']) <=> floatval($b['accuracy']); });

include __DIR__ . '/../templates/header.php';
?>

<h2 class="mb-4"><i class="bi bi-graph-up"></i> <?= h($subject) ?> ダッシュボード</h2>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label">チャプター</label>
                <select name="chapter" class="form-select">
                    <option value="">すべて</option>
                    <?php foreach ($chapters as $ch): ?>
                    <option value="<?= h($ch) ?>" <?= $ch === $chapter_filter ? 'selected' : '' ?>><?= h($ch) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">正答率の上限 (%)</label>
                <input type="number" name="max_accuracy" class="form-control" placeholder="例: 60" min="0" max="100" step="1" value="<?= h($max_accuracy_input) ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">最終学習日以前</label>
                <input type="date" name="before_date" class="form-control" value="<?= h($before_date_input) ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">未学習日数以上</label>
                <input type="number" name="days_ago" class="form-control" placeholder="例: 60" min="1" value="<?= h($days_ago_input) ?>">
            </div>
            <div class="col-12 col-md-3">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> 絞り込み</button>
            </div>
        </form>
    </div>
</div>

<?php if ($stats): ?>
<?php
    $avg_accuracy = count($stats) > 0 ? round(array_sum(array_column($stats, 'accuracy')) / count($stats), 1) : 0;
    $total_attempts = array_sum(array_column($stats, 'total_attempts'));
    $below60 = count(array_filter($stats, function($s) { return floatval($s['accuracy']) < 60; }));
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="card text-center"><div class="card-body"><div class="fs-3 fw-bold text-primary"><?= count($stats) ?></div><div class="text-muted small">問題数</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card text-center"><div class="card-body"><div class="fs-3 fw-bold text-success"><?= $avg_accuracy ?>%</div><div class="text-muted small">平均正答率</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card text-center"><div class="card-body"><div class="fs-3 fw-bold text-info"><?= $total_attempts ?></div><div class="text-muted small">総回答数</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card text-center"><div class="card-body"><div class="fs-3 fw-bold text-warning"><?= $below60 ?></div><div class="text-muted small">正答率60%未満</div></div></div></div>
</div>

<div class="d-flex justify-content-between align-items-center mb-2">
    <div class="form-check">
        <input class="form-check-input" type="checkbox" id="select-all">
        <label class="form-check-label" for="select-all">すべて選択</label>
    </div>
    <button id="start-selected-btn" class="btn btn-primary" disabled>
        <i class="bi bi-play-fill"></i> 選択した問題で学習 (<span id="selected-count">0</span>問)
    </button>
</div>

<div class="table-responsive">
    <table class="table table-hover table-striped">
        <thead class="table-light">
            <tr><th style="width:40px"></th><th>チャプター</th><th>問題番号</th><th>正答率</th><th>正解/回答数</th><th>最終学習日</th><th>メモ</th></tr>
        </thead>
        <tbody>
            <?php foreach ($stats as $s): ?>
            <tr>
                <td><input class="form-check-input problem-check" type="checkbox" data-chapter="<?= h($s['chapter_name']) ?>" data-problem="<?= $s['problem_number'] ?>"></td>
                <td><?= h($s['chapter_name']) ?></td>
                <td><strong><?= $s['problem_number'] ?></strong></td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="progress flex-grow-1" style="height:20px">
                            <div class="progress-bar bg-<?= floatval($s['accuracy']) >= 80 ? 'success' : (floatval($s['accuracy']) >= 60 ? 'warning' : 'danger') ?>" style="width:<?= $s['accuracy'] ?>%"><?= $s['accuracy'] ?>%</div>
                        </div>
                    </div>
                </td>
                <td><?= $s['correct_count'] ?>/<?= $s['total_attempts'] ?></td>
                <td class="text-muted"><?= $s['last_study_date'] ?></td>
                <td>
                    <?php $memo_key = $s['chapter_name'] . '::' . $s['problem_number']; $memo_text = $memo_map[$memo_key] ?? ''; ?>
                    <button class="btn btn-sm btn-outline-secondary memo-edit-btn" title="メモを編集"
                            data-chapter="<?= h($s['chapter_name']) ?>" data-problem="<?= h($s['problem_number']) ?>"
                            data-memo="<?= h($memo_text) ?>">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <?php if ($memo_text): ?>
                    <button class="btn btn-sm btn-link memo-view-btn p-0 ms-1" title="<?= h($memo_text) ?>"
                            data-chapter="<?= h($s['chapter_name']) ?>" data-problem="<?= h($s['problem_number']) ?>"
                            data-memo="<?= h($memo_text) ?>" data-bs-toggle="tooltip" data-bs-placement="left">
                        <i class="bi bi-journal-text text-info"></i>
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<!-- メモ編集モーダル -->
<div class="modal fade" id="memoEditModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> メモ編集</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-2" id="memo-problem-label"></p>
                <textarea id="memo-textarea" class="form-control" rows="5" placeholder="メモを入力..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                <button type="button" class="btn btn-danger" id="memo-delete-btn">削除</button>
                <button type="button" class="btn btn-primary" id="memo-save-btn">保存</button>
            </div>
        </div>
    </div>
</div>
<!-- メモ表示モーダル -->
<div class="modal fade" id="memoViewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-journal-text"></i> メモ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-2" id="memo-view-label"></p>
                <div id="memo-view-content" style="white-space: pre-wrap;"></div>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    <?= ($chapter_filter || $max_accuracy_input) ? '条件に一致するデータがありません。' : 'まだ学習記録がありません。チャプターを選んで学習を始めましょう！' ?>
</div>
<?php endif; ?>

<?php
$subject_json = json_encode($subject);
$custom_url = url('start_custom_session');
$memo_url = url('memo');
$page_scripts = <<<SCRIPT
<script>
const selectAll = document.getElementById('select-all');
const checks = document.querySelectorAll('.problem-check');
const startBtn = document.getElementById('start-selected-btn');
const countSpan = document.getElementById('selected-count');
function updateCount() {
    const checked = document.querySelectorAll('.problem-check:checked');
    if (!countSpan) return;
    countSpan.textContent = checked.length;
    startBtn.disabled = checked.length === 0;
    if (selectAll) { selectAll.checked = checks.length > 0 && checked.length === checks.length; selectAll.indeterminate = checked.length > 0 && checked.length < checks.length; }
}
if (selectAll) { selectAll.addEventListener('change', function() { checks.forEach(cb => cb.checked = this.checked); updateCount(); }); }
checks.forEach(cb => cb.addEventListener('change', updateCount));
if (startBtn) {
    startBtn.addEventListener('click', async function() {
        const selected = [];
        document.querySelectorAll('.problem-check:checked').forEach(cb => { selected.push({ chapter_name: cb.dataset.chapter, problem_number: cb.dataset.problem }); });
        if (!selected.length) return;
        startBtn.disabled = true; startBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> 作成中...';
        try {
            const res = await fetch('{$custom_url}', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ problems: selected, subject: {$subject_json} }) });
            if (!res.ok) { alert('セッション作成失敗'); return; }
            const data = await res.json();
            if (data.redirect) window.location.href = data.redirect;
        } catch(e) { alert('通信エラー'); } finally { startBtn.disabled = false; startBtn.innerHTML = '<i class="bi bi-play-fill"></i> 選択した問題で学習 (<span id="selected-count">0</span>問)'; updateCount(); }
    });
}

// ツールチップ初期化
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
    new bootstrap.Tooltip(el);
});

// メモ編集
var memoEditModal = document.getElementById('memoEditModal');
var memoChapter = '', memoProblem = '', memoEditBtn = null;
if (memoEditModal) {
    var bsEditModal = new bootstrap.Modal(memoEditModal);
    document.querySelectorAll('.memo-edit-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            memoChapter = this.dataset.chapter;
            memoProblem = this.dataset.problem;
            memoEditBtn = this;
            document.getElementById('memo-problem-label').textContent = memoChapter + ' / ' + memoProblem;
            document.getElementById('memo-textarea').value = this.dataset.memo;
            bsEditModal.show();
        });
    });
    document.getElementById('memo-save-btn').addEventListener('click', async function() {
        var memo = document.getElementById('memo-textarea').value.trim();
        await saveMemo(memoChapter, memoProblem, memo);
        bsEditModal.hide();
    });
    document.getElementById('memo-delete-btn').addEventListener('click', async function() {
        await saveMemo(memoChapter, memoProblem, '');
        bsEditModal.hide();
    });
}

async function saveMemo(chapter, problem, memo) {
    try {
        var res = await fetch('{$memo_url}', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ subject: {$subject_json}, chapter_name: chapter, problem_number: problem, memo: memo })
        });
        if (res.ok) { location.reload(); }
        else { alert('メモの保存に失敗しました'); }
    } catch(e) { alert('通信エラー'); }
}

// メモ表示（クリックで大きく表示）
var memoViewModal = document.getElementById('memoViewModal');
if (memoViewModal) {
    var bsViewModal = new bootstrap.Modal(memoViewModal);
    document.querySelectorAll('.memo-view-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('memo-view-label').textContent = this.dataset.chapter + ' / ' + this.dataset.problem;
            document.getElementById('memo-view-content').textContent = this.dataset.memo;
            bsViewModal.show();
        });
    });
}
</script>
SCRIPT;
include __DIR__ . '/../templates/footer.php';
?>
