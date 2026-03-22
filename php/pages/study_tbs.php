<?php
$db = get_db();
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$session_id]);
$session = $stmt->fetch();

$display_name = subject_display_name($subject);

if (!$session) {
    flash('セッションが見つかりません。', 'error');
    redirect($subject);
}
if ($session['finished_at']) {
    flash('このセッションは既に終了しています。', 'info');
    redirect($subject);
}

$chapter_name = $session['chapter_name'];

// TBS問題データ取得
$tbs_problems = load_tbs_problems_from_excel($subject);

// カスタムセッションの場合
$stmt_custom = $db->prepare("SELECT chapter_name, problem_number FROM custom_session_problems WHERE session_id = ? ORDER BY id");
$stmt_custom->execute([$session_id]);
$custom_problems = $stmt_custom->fetchAll();

if ($custom_problems) {
    // カスタムセッション: 複数チャプターにまたがる可能性がある
    $problem_list = [];
    foreach ($custom_problems as $cp) {
        // TBSのExcelから小問数を探す
        $subq = 0;
        if (isset($tbs_problems[$cp['chapter_name']])) {
            foreach ($tbs_problems[$cp['chapter_name']] as $item) {
                if ($item['number'] === $cp['problem_number']) {
                    $subq = $item['subquestions'];
                    break;
                }
            }
        }
        $problem_list[] = [
            'chapter_name' => $cp['chapter_name'],
            'problem_number' => $cp['problem_number'],
            'subquestions' => $subq,
        ];
    }
} else {
    // 通常セッション: 1チャプター
    if (!isset($tbs_problems[$chapter_name])) {
        flash('チャプターのデータが見つかりません。', 'error');
        redirect($subject);
    }
    $problem_list = [];
    foreach ($tbs_problems[$chapter_name] as $item) {
        $problem_list[] = [
            'chapter_name' => $chapter_name,
            'problem_number' => $item['number'],
            'subquestions' => $item['subquestions'],
        ];
    }
}

// セッション内の回答状況
$stmt = $db->prepare("SELECT chapter_name, problem_number, correct_count, total_subquestions FROM tbs_records WHERE session_id = ?");
$stmt->execute([$session_id]);
$session_records = [];
foreach ($stmt->fetchAll() as $row) {
    $key = $row['chapter_name'] . '::' . $row['problem_number'];
    $session_records[$key] = [
        'correct_count' => intval($row['correct_count']),
        'total_subquestions' => intval($row['total_subquestions']),
    ];
}

// 各問題の累計統計
$problem_stats = [];
$stat_stmt = $db->prepare("
    SELECT SUM(r.correct_count) as sum_correct, SUM(r.total_subquestions) as sum_total, COUNT(*) as total_attempts,
    MAX(r.study_date) as last_study_date
    FROM tbs_records r JOIN sessions s ON r.session_id = s.id
    WHERE s.finished_at IS NOT NULL AND r.chapter_name = ? AND r.problem_number = ?
");
foreach ($problem_list as $p) {
    $stat_stmt->execute([$p['chapter_name'], $p['problem_number']]);
    $row = $stat_stmt->fetch();
    $sum_correct = intval($row['sum_correct'] ?? 0);
    $sum_total = intval($row['sum_total'] ?? 0);
    $total_attempts = intval($row['total_attempts'] ?? 0);
    $accuracy = $sum_total > 0 ? round(100.0 * $sum_correct / $sum_total, 1) : null;
    $key = $p['chapter_name'] . '::' . $p['problem_number'];
    $problem_stats[$key] = [
        'sum_correct' => $sum_correct,
        'sum_total' => $sum_total,
        'total_attempts' => $total_attempts,
        'accuracy' => $accuracy,
        'last_study_date' => $row['last_study_date'] ?? null,
        'session_record' => $session_records[$key] ?? null,
    ];
}

$answered = count($session_records);
$total_problems = count($problem_list);
$page_title = ($custom_problems ? 'カスタム学習' : $chapter_name) . " - USCPA学習記録アプリ";

include __DIR__ . '/../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0"><i class="bi bi-pencil-square"></i> <?= h($custom_problems ? "$display_name カスタム学習" : $chapter_name) ?></h2>
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
                <?php if ($custom_problems): ?><th>チャプター</th><?php endif; ?>
                <th style="width: 130px; white-space: nowrap;">問題</th>
                <th style="width: 280px;">回答</th>
                <th>累計正答率</th>
                <th>累計</th>
                <th>最終学習日</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($problem_list as $p):
                $key = $p['chapter_name'] . '::' . $p['problem_number'];
                $ps = $problem_stats[$key];
                $sr = $ps['session_record'];
                $row_class = '';
                if ($sr !== null) {
                    $row_class = ($sr['correct_count'] === $sr['total_subquestions']) ? 'table-success' : (($sr['correct_count'] === 0) ? 'table-danger' : 'table-warning');
                }
                $pn_safe = htmlspecialchars($p['problem_number'], ENT_QUOTES, 'UTF-8');
                $ch_safe = htmlspecialchars($p['chapter_name'], ENT_QUOTES, 'UTF-8');
            ?>
            <tr id="row-<?= $pn_safe ?>" class="<?= $row_class ?>" data-chapter="<?= $ch_safe ?>">
                <?php if ($custom_problems): ?><td class="text-muted small"><?= h($p['chapter_name']) ?></td><?php endif; ?>
                <td style="white-space: nowrap;"><strong><?= $pn_safe ?></strong></td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <input type="number" class="form-control form-control-sm tbs-input" style="width:70px"
                               min="0" max="<?= $p['subquestions'] ?>"
                               data-problem="<?= $pn_safe ?>" data-chapter="<?= $ch_safe ?>"
                               data-subquestions="<?= $p['subquestions'] ?>"
                               value="<?= $sr !== null ? $sr['correct_count'] : '' ?>"
                               placeholder="">
                        <span class="text-muted">/ <?= $p['subquestions'] ?></span>
                        <button class="btn btn-sm btn-primary tbs-record-btn" data-problem="<?= $pn_safe ?>" data-chapter="<?= $ch_safe ?>">
                            <i class="bi bi-check"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary tbs-undo-btn" data-problem="<?= $pn_safe ?>" data-chapter="<?= $ch_safe ?>"
                                title="取消" <?= $sr === null ? 'disabled' : '' ?>>
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </button>
                    </div>
                </td>
                <td>
                    <span id="accuracy-<?= $pn_safe ?>">
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
                    <span id="count-<?= $pn_safe ?>">
                        <?= $ps['total_attempts'] > 0 ? "{$ps['sum_correct']}/{$ps['sum_total']}" : '-' ?>
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
$record_url = url('record');
$undo_url = url('undo');
$finish_url = url("$subject/finish_session/$session_id");
$page_scripts = <<<SCRIPT
<script>
const SESSION_ID = {$session_id};
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

function updateTbsDisplay(pn, data) {
    var accEl = document.getElementById('accuracy-' + pn);
    if (data.accuracy !== null && data.sum_total > 0) {
        var color = 'danger';
        if (data.accuracy >= 80) color = 'success';
        else if (data.accuracy >= 60) color = 'warning';
        accEl.innerHTML = '<span class="badge bg-' + color + '">' + data.accuracy + '%</span>';
    } else {
        accEl.innerHTML = '<span class="text-muted">-</span>';
    }
    document.getElementById('count-' + pn).textContent = data.sum_total > 0 ? data.sum_correct + '/' + data.sum_total : '-';
    document.getElementById('answered-count').textContent = data.answered;
}

// 記録ボタン
document.querySelectorAll('.tbs-record-btn').forEach(function(btn) {
    btn.addEventListener('click', async function() {
        var pn = this.dataset.problem;
        var ch = this.dataset.chapter;
        var input = document.querySelector('.tbs-input[data-problem="' + pn + '"][data-chapter="' + ch + '"]');
        var correctCount = parseInt(input.value);
        var totalSub = parseInt(input.dataset.subquestions);

        if (isNaN(correctCount) || correctCount < 0 || correctCount > totalSub) {
            alert('0〜' + totalSub + 'の範囲で入力してください');
            return;
        }

        var row = document.getElementById('row-' + pn);
        row.querySelectorAll('button').forEach(function(b) { b.disabled = true; });

        try {
            var res = await fetch('{$record_url}', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ session_id: SESSION_ID, chapter_name: ch, problem_number: pn, correct_count: correctCount, total_subquestions: totalSub })
            });
            if (!res.ok) { alert('記録に失敗しました (' + res.status + ')'); return; }
            var data = await res.json();
            if (data.success) {
                updateTbsDisplay(pn, data);
                if (correctCount === totalSub) {
                    row.className = 'table-success';
                } else if (correctCount === 0) {
                    row.className = 'table-danger';
                } else {
                    row.className = 'table-warning';
                }
            }
        } catch (e) { console.error(e); alert('通信エラーが発生しました'); }
        finally {
            row.querySelectorAll('button').forEach(function(b) { b.disabled = false; });
            row.querySelector('.tbs-undo-btn').disabled = false;
        }
    });
});

// 取消ボタン
document.querySelectorAll('.tbs-undo-btn').forEach(function(btn) {
    btn.addEventListener('click', async function() {
        var pn = this.dataset.problem;
        var ch = this.dataset.chapter;
        var undoBtn = this;
        if (undoBtn.disabled) return;
        var row = document.getElementById('row-' + pn);
        row.querySelectorAll('button').forEach(function(b) { b.disabled = true; });

        try {
            var res = await fetch('{$undo_url}', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ session_id: SESSION_ID, chapter_name: ch, problem_number: pn })
            });
            if (!res.ok) { alert('取消に失敗しました (' + res.status + ')'); return; }
            var data = await res.json();
            if (data.success) {
                updateTbsDisplay(pn, data);
                row.className = '';
                var input = row.querySelector('.tbs-input');
                if (input) input.value = '';
                undoBtn.disabled = true;
            }
        } catch (e) { console.error(e); alert('通信エラーが発生しました'); }
        finally { row.querySelectorAll('.tbs-record-btn').forEach(function(b) { b.disabled = false; }); }
    });
});

// Enterキーで記録
document.querySelectorAll('.tbs-input').forEach(function(input) {
    input.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            var pn = this.dataset.problem;
            var ch = this.dataset.chapter;
            var btn = document.querySelector('.tbs-record-btn[data-problem="' + pn + '"][data-chapter="' + ch + '"]');
            if (btn) btn.click();
        }
    });
});
</script>
SCRIPT;

include __DIR__ . '/../templates/footer.php';
?>
