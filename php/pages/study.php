<?php
$db = get_db();
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$session_id]);
$session = $stmt->fetch();

if (!$session) {
    flash('セッションが見つかりません。', 'error');
    redirect($subject);
}
if ($session['finished_at']) {
    flash('このセッションは既に終了しています。', 'info');
    redirect($subject);
}

$chapter_name = $session['chapter_name'];
$problems = load_problems_from_excel($subject);
if (!isset($problems[$chapter_name])) {
    flash('チャプターのデータが見つかりません。', 'error');
    redirect($subject);
}

$problem_numbers = $problems[$chapter_name];

// セッション内の回答状況
$stmt = $db->prepare("SELECT problem_number, result FROM records WHERE session_id = ?");
$stmt->execute([$session_id]);
$session_records = [];
foreach ($stmt->fetchAll() as $row) {
    $session_records[$row['problem_number']] = $row['result'];
}

// 各問題の累計統計
$problem_stats = [];
$stat_stmt = $db->prepare("
    SELECT COUNT(*) as total, SUM(CASE WHEN r.result = 'correct' THEN 1 ELSE 0 END) as correct,
    MAX(r.study_date) as last_study_date
    FROM records r JOIN sessions s ON r.session_id = s.id
    WHERE s.finished_at IS NOT NULL AND r.chapter_name = ? AND r.problem_number = ?
");
foreach ($problem_numbers as $pn) {
    $stat_stmt->execute([$chapter_name, $pn]);
    $row = $stat_stmt->fetch();
    $total = intval($row['total']);
    $correct = intval($row['correct'] ?? 0);
    $accuracy = $total > 0 ? round(100.0 * $correct / $total, 1) : null;
    $problem_stats[$pn] = [
        'total' => $total,
        'correct' => $correct,
        'accuracy' => $accuracy,
        'last_study_date' => $row['last_study_date'] ?? null,
        'session_result' => $session_records[$pn] ?? null,
    ];
}

$answered = count(array_filter($session_records));
$total_problems = count($problem_numbers);
$page_title = "$chapter_name - 演習記録";

include __DIR__ . '/../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0"><i class="bi bi-pencil-square"></i> <?= h($chapter_name) ?></h2>
</div>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="text-muted">
        <i class="bi bi-calendar"></i> <?= h($session['study_date']) ?>
        <span class="ms-3">
            <i class="bi bi-check2-square"></i> 回答済: <strong id="answered-count"><?= $answered ?></strong>/<?= $total_problems ?>
        </span>
    </div>
    <form id="finish-form" method="POST" action="<?= url("$subject/finish_session/$session_id") ?>"
          onsubmit="window._finishing = true; return confirm('学習を終了しますか？記録が保存されます。');">
        <button type="submit" class="btn btn-success">
            <i class="bi bi-check-circle"></i> 学習終了
        </button>
    </form>
</div>

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th style="width: 130px; white-space: nowrap;">問題</th>
                <th style="width: 220px;">回答</th>
                <th>累計正答率</th>
                <th>累計回数</th>
                <th>最終学習日</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($problem_numbers as $pn):
                $ps = $problem_stats[$pn];
                $row_class = $ps['session_result'] === 'correct' ? 'table-success' : ($ps['session_result'] === 'incorrect' ? 'table-danger' : '');
            ?>
            <tr id="row-<?= $pn ?>" class="<?= $row_class ?>">
                <td style="white-space: nowrap;"><strong><?= $pn ?></strong></td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-success record-btn <?= $ps['session_result'] === 'correct' ? 'active' : '' ?>"
                                data-problem="<?= $pn ?>" data-result="correct">
                            <i class="bi bi-circle"></i>
                        </button>
                        <button class="btn btn-outline-danger record-btn <?= $ps['session_result'] === 'incorrect' ? 'active' : '' ?>"
                                data-problem="<?= $pn ?>" data-result="incorrect">
                            <i class="bi bi-x-lg"></i>
                        </button>
                        <button class="btn btn-outline-secondary undo-btn"
                                data-problem="<?= $pn ?>" title="取消"
                                <?= !$ps['session_result'] ? 'disabled' : '' ?>>
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </button>
                    </div>
                </td>
                <td>
                    <span id="accuracy-<?= $pn ?>">
                        <?php if ($ps['accuracy'] !== null): ?>
                            <span class="badge bg-<?= $ps['accuracy'] >= 80 ? 'success' : ($ps['accuracy'] >= 60 ? 'warning' : 'danger') ?>">
                                <?= $ps['accuracy'] ?>%
                            </span>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </span>
                </td>
                <td>
                    <span id="count-<?= $pn ?>">
                        <?= $ps['total'] > 0 ? "{$ps['correct']}/{$ps['total']}" : '-' ?>
                    </span>
                </td>
                <td class="text-muted small">
                    <?= $ps['last_study_date'] ? h($ps['last_study_date']) : '-' ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
$cn_json = json_encode($chapter_name);
$record_url = url('record');
$undo_url = url('undo');
$finish_url = url("$subject/finish_session/$session_id");
$page_scripts = <<<SCRIPT
<script>
const SESSION_ID = {$session_id};
const CHAPTER_NAME = {$cn_json};
window._finishing = false;

window.addEventListener('beforeunload', function(e) {
    if (!window._finishing) {
        e.preventDefault();
        e.returnValue = '';
    }
});

document.querySelectorAll('a').forEach(function(link) {
    link.addEventListener('click', function(e) {
        if (window._finishing) return;
        e.preventDefault();
        var href = this.href;
        if (confirm('学習を終了して移動しますか？記録は保存されます。')) {
            window._finishing = true;
            fetch('{$finish_url}', { method: 'POST' }).finally(function() {
                window.location.href = href;
            });
        }
    });
});

function updateDisplay(pn, data) {
    const accEl = document.getElementById('accuracy-' + pn);
    if (data.accuracy !== null && data.total > 0) {
        let color = 'danger';
        if (data.accuracy >= 80) color = 'success';
        else if (data.accuracy >= 60) color = 'warning';
        accEl.innerHTML = '<span class="badge bg-' + color + '">' + data.accuracy + '%</span>';
    } else {
        accEl.innerHTML = '<span class="text-muted">-</span>';
    }
    document.getElementById('count-' + pn).textContent = data.total > 0 ? data.correct + '/' + data.total : '-';
    document.getElementById('answered-count').textContent = data.answered;
}

document.querySelectorAll('.record-btn').forEach(function(btn) {
    btn.addEventListener('click', async function() {
        const pn = this.dataset.problem;
        const result = this.dataset.result;
        const clickedBtn = this;
        if (clickedBtn.disabled) return;
        const row = document.getElementById('row-' + pn);
        row.querySelectorAll('button').forEach(function(b) { b.disabled = true; });
        try {
            const res = await fetch('{$record_url}', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ session_id: SESSION_ID, chapter_name: CHAPTER_NAME, problem_number: pn, result: result })
            });
            if (!res.ok) { alert('記録に失敗しました (' + res.status + ')'); return; }
            const data = await res.json();
            if (data.success) {
                updateDisplay(pn, data);
                row.className = result === 'correct' ? 'table-success' : 'table-danger';
                row.querySelectorAll('.record-btn').forEach(function(b) { b.classList.remove('active'); });
                clickedBtn.classList.add('active');
            }
        } catch (e) { console.error(e); alert('通信エラーが発生しました'); }
        finally {
            row.querySelectorAll('.record-btn').forEach(function(b) { b.disabled = false; });
            row.querySelector('.undo-btn').disabled = false;
        }
    });
});

document.querySelectorAll('.undo-btn').forEach(function(btn) {
    btn.addEventListener('click', async function() {
        const pn = this.dataset.problem;
        const undoBtn = this;
        if (undoBtn.disabled) return;
        const row = document.getElementById('row-' + pn);
        row.querySelectorAll('button').forEach(function(b) { b.disabled = true; });
        try {
            const res = await fetch('{$undo_url}', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ session_id: SESSION_ID, chapter_name: CHAPTER_NAME, problem_number: pn })
            });
            if (!res.ok) { alert('取消に失敗しました (' + res.status + ')'); return; }
            const data = await res.json();
            if (data.success) {
                updateDisplay(pn, data);
                row.className = '';
                row.querySelectorAll('.record-btn').forEach(function(b) { b.classList.remove('active'); });
                undoBtn.disabled = true;
            }
        } catch (e) { console.error(e); alert('通信エラーが発生しました'); }
        finally { row.querySelectorAll('.record-btn').forEach(function(b) { b.disabled = false; }); }
    });
});
</script>
SCRIPT;

include __DIR__ . '/../templates/footer.php';
?>
