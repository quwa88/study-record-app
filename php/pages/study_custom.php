<?php
$db = get_db();
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$session_id]);
$session = $stmt->fetch();

if (!$session) { flash('セッションが見つかりません。', 'error'); redirect($subject); }
if ($session['finished_at']) { flash('このセッションは既に終了しています。', 'info'); redirect($subject); }

$stmt = $db->prepare("SELECT chapter_name, problem_number FROM custom_session_problems WHERE session_id = ? ORDER BY chapter_name, problem_number");
$stmt->execute([$session_id]);
$custom_problems = $stmt->fetchAll();

if (!$custom_problems) { flash('カスタムセッションの問題が見つかりません。', 'error'); redirect($subject); }

$stmt = $db->prepare("SELECT chapter_name, problem_number, result FROM records WHERE session_id = ?");
$stmt->execute([$session_id]);
$session_records = [];
foreach ($stmt->fetchAll() as $row) {
    $session_records[$row['chapter_name'] . '_' . $row['problem_number']] = $row['result'];
}

$stat_stmt = $db->prepare("
    SELECT COUNT(*) as total, SUM(CASE WHEN r.result = 'correct' THEN 1 ELSE 0 END) as correct,
    MAX(r.study_date) as last_study_date
    FROM records r JOIN sessions s ON r.session_id = s.id
    WHERE s.finished_at IS NOT NULL AND r.chapter_name = ? AND r.problem_number = ?
");
$problem_stats = [];
foreach ($custom_problems as $p) {
    $key = $p['chapter_name'] . '_' . $p['problem_number'];
    $stat_stmt->execute([$p['chapter_name'], $p['problem_number']]);
    $row = $stat_stmt->fetch();
    $total = intval($row['total']); $correct = intval($row['correct'] ?? 0);
    $problem_stats[$key] = [
        'total' => $total, 'correct' => $correct,
        'accuracy' => $total > 0 ? round(100.0 * $correct / $total, 1) : null,
        'last_study_date' => $row['last_study_date'] ?? null,
        'session_result' => $session_records[$key] ?? null,
    ];
}

$answered = count($session_records);
$total_problems = count($custom_problems);
$page_title = 'カスタム学習 - 演習記録';

include __DIR__ . '/../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0"><i class="bi bi-pencil-square"></i> カスタム学習</h2>
</div>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="text-muted">
        <i class="bi bi-calendar"></i> <?= h($session['study_date']) ?>
        <span class="ms-3"><i class="bi bi-check2-square"></i> 回答済: <strong id="answered-count"><?= $answered ?></strong>/<?= $total_problems ?></span>
    </div>
    <form method="POST" action="<?= url("$subject/finish_session/$session_id") ?>" onsubmit="return confirm('学習を終了しますか？記録が保存されます。')">
        <button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> 学習終了</button>
    </form>
</div>

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-light">
            <tr><th>チャプター</th><th style="width:80px">問題</th><th style="width:220px">回答</th><th>累計正答率</th><th>累計回数</th><th>最終学習日</th></tr>
        </thead>
        <tbody>
            <?php foreach ($custom_problems as $idx => $p):
                $key = $p['chapter_name'] . '_' . $p['problem_number'];
                $ps = $problem_stats[$key];
                $row_class = $ps['session_result'] === 'correct' ? 'table-success' : ($ps['session_result'] === 'incorrect' ? 'table-danger' : '');
            ?>
            <tr id="row-<?= $idx ?>" class="<?= $row_class ?>">
                <td class="text-muted small"><?= h($p['chapter_name']) ?></td>
                <td><strong><?= $p['problem_number'] ?></strong></td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-success record-btn <?= $ps['session_result'] === 'correct' ? 'active' : '' ?>"
                                data-index="<?= $idx ?>" data-chapter="<?= h($p['chapter_name']) ?>" data-problem="<?= $p['problem_number'] ?>" data-result="correct">
                            <i class="bi bi-circle"></i> 正解
                        </button>
                        <button class="btn btn-outline-danger record-btn <?= $ps['session_result'] === 'incorrect' ? 'active' : '' ?>"
                                data-index="<?= $idx ?>" data-chapter="<?= h($p['chapter_name']) ?>" data-problem="<?= $p['problem_number'] ?>" data-result="incorrect">
                            <i class="bi bi-x-lg"></i> 不正解
                        </button>
                        <button class="btn btn-outline-secondary undo-btn" data-index="<?= $idx ?>" data-chapter="<?= h($p['chapter_name']) ?>" data-problem="<?= $p['problem_number'] ?>" title="取消" <?= !$ps['session_result'] ? 'disabled' : '' ?>>
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </button>
                    </div>
                </td>
                <td><span id="accuracy-<?= $idx ?>"><?php if ($ps['accuracy'] !== null): ?><span class="badge bg-<?= $ps['accuracy'] >= 80 ? 'success' : ($ps['accuracy'] >= 60 ? 'warning' : 'danger') ?>"><?= $ps['accuracy'] ?>%</span><?php else: ?><span class="text-muted">-</span><?php endif; ?></span></td>
                <td><span id="count-<?= $idx ?>"><?= $ps['total'] > 0 ? "{$ps['correct']}/{$ps['total']}" : '-' ?></span></td>
                <td class="text-muted small"><?= $ps['last_study_date'] ? h($ps['last_study_date']) : '-' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
$record_url = url('record');
$undo_url = url('undo');
$page_scripts = <<<SCRIPT
<script>
const SESSION_ID = {$session_id};
function updateDisplay(idx, data) {
    const accEl = document.getElementById('accuracy-' + idx);
    if (data.accuracy !== null && data.total > 0) {
        let color = data.accuracy >= 80 ? 'success' : data.accuracy >= 60 ? 'warning' : 'danger';
        accEl.innerHTML = '<span class="badge bg-' + color + '">' + data.accuracy + '%</span>';
    } else { accEl.innerHTML = '<span class="text-muted">-</span>'; }
    document.getElementById('count-' + idx).textContent = data.total > 0 ? data.correct + '/' + data.total : '-';
    document.getElementById('answered-count').textContent = data.answered;
}
document.querySelectorAll('.record-btn').forEach(function(btn) {
    btn.addEventListener('click', async function() {
        const idx = this.dataset.index, ch = this.dataset.chapter, pn = this.dataset.problem, result = this.dataset.result, clicked = this;
        if (clicked.disabled) return;
        const row = document.getElementById('row-' + idx);
        row.querySelectorAll('button').forEach(b => b.disabled = true);
        try {
            const res = await fetch('{$record_url}', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ session_id: SESSION_ID, chapter_name: ch, problem_number: pn, result: result }) });
            if (!res.ok) { alert('記録に失敗しました'); return; }
            const data = await res.json();
            if (data.success) { updateDisplay(idx, data); row.className = result === 'correct' ? 'table-success' : 'table-danger'; row.querySelectorAll('.record-btn').forEach(b => b.classList.remove('active')); clicked.classList.add('active'); }
        } catch(e) { alert('通信エラー'); } finally { row.querySelectorAll('.record-btn').forEach(b => b.disabled = false); row.querySelector('.undo-btn').disabled = false; }
    });
});
document.querySelectorAll('.undo-btn').forEach(function(btn) {
    btn.addEventListener('click', async function() {
        const idx = this.dataset.index, ch = this.dataset.chapter, pn = this.dataset.problem, undoBtn = this;
        if (undoBtn.disabled) return;
        const row = document.getElementById('row-' + idx);
        row.querySelectorAll('button').forEach(b => b.disabled = true);
        try {
            const res = await fetch('{$undo_url}', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ session_id: SESSION_ID, chapter_name: ch, problem_number: pn }) });
            if (!res.ok) { alert('取消に失敗しました'); return; }
            const data = await res.json();
            if (data.success) { updateDisplay(idx, data); row.className = ''; row.querySelectorAll('.record-btn').forEach(b => b.classList.remove('active')); undoBtn.disabled = true; }
        } catch(e) { alert('通信エラー'); } finally { row.querySelectorAll('.record-btn').forEach(b => b.disabled = false); }
    });
});
</script>
SCRIPT;
include __DIR__ . '/../templates/footer.php';
?>
