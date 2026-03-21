<?php
$page_title = "$subject クイズモード - USCPA学習記録アプリ";
$db = get_db();

// 登録済み問題数を取得
$stmt = $db->prepare("SELECT COUNT(*) FROM questions WHERE subject = ?");
$stmt->execute([$subject]);
$total_questions = $stmt->fetchColumn();

$problems = load_problems_from_excel($subject);
$chapters = array_keys($problems);

// チャプターごとの登録数
$ch_counts = [];
$ch_stmt = $db->prepare("SELECT chapter_name, COUNT(*) as cnt FROM questions WHERE subject = ? GROUP BY chapter_name");
$ch_stmt->execute([$subject]);
foreach ($ch_stmt->fetchAll() as $r) {
    $ch_counts[$r['chapter_name']] = intval($r['cnt']);
}

include __DIR__ . '/../templates/header.php';
?>

<h2 class="mb-4"><i class="bi bi-lightning"></i> <?= h($subject) ?> クイズモード</h2>

<?php if ($total_questions == 0): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i> 問題が登録されていません。先に<a href="<?= url("$subject/questions") ?>">問題登録</a>から問題を登録してください。
</div>
<?php else: ?>

<div class="card mb-4">
    <div class="card-body">
        <form id="quiz-form">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold">出題数</label>
                    <input type="number" id="quiz-count" class="form-control" min="1" max="<?= $total_questions ?>" value="<?= min(20, $total_questions) ?>" placeholder="例: 20">
                    <small class="text-muted">登録済: <?= $total_questions ?>問</small>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold">出題範囲</label>
                    <select id="quiz-chapter" class="form-select">
                        <option value="">全チャプター</option>
                        <?php foreach ($chapters as $ch):
                            $cnt = $ch_counts[$ch] ?? 0;
                            if ($cnt == 0) continue;
                        ?>
                        <option value="<?= h($ch) ?>"><?= h($ch) ?> (<?= $cnt ?>問)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="quiz-shuffle" checked>
                        <label class="form-check-label" for="quiz-shuffle">選択肢をシャッフル</label>
                    </div>
                    <button type="button" id="quiz-start-btn" class="btn btn-success w-100">
                        <i class="bi bi-play-fill"></i> クイズ開始
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php endif; ?>

<?php
$subject_json = json_encode($subject);
$quiz_start_url = url('start_quiz');
$page_scripts = <<<SCRIPT
<script>
var startBtn = document.getElementById('quiz-start-btn');
if (startBtn) {
    startBtn.addEventListener('click', async function() {
        var count = parseInt(document.getElementById('quiz-count').value) || 20;
        var chapter = document.getElementById('quiz-chapter').value;
        var shuffle = document.getElementById('quiz-shuffle').checked;
        startBtn.disabled = true;
        startBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> 準備中...';
        try {
            var res = await fetch('{$quiz_start_url}', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ subject: {$subject_json}, count: count, chapter: chapter, shuffle: shuffle })
            });
            if (!res.ok) { alert('クイズの作成に失敗しました'); return; }
            var data = await res.json();
            if (data.redirect) window.location.href = data.redirect;
        } catch(e) { alert('通信エラー'); }
        finally { startBtn.disabled = false; startBtn.innerHTML = '<i class="bi bi-play-fill"></i> クイズ開始'; }
    });
}
</script>
SCRIPT;
include __DIR__ . '/../templates/footer.php';
?>
