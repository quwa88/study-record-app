<?php
$page_title = "クイズモード - USCPA学習記録アプリ";
$db = get_db();

$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$session_id]);
$session = $stmt->fetch();

if (!$session) { flash('セッションが見つかりません。', 'error'); redirect($subject); }
if ($session['finished_at']) { flash('このセッションは既に終了しています。', 'info'); redirect($subject); }

$shuffle = isset($_SESSION["quiz_shuffle_{$session_id}"]) ? $_SESSION["quiz_shuffle_{$session_id}"] : 1;

// セッションの問題を取得
$stmt = $db->prepare("SELECT chapter_name, problem_number FROM custom_session_problems WHERE session_id = ? ORDER BY id");
$stmt->execute([$session_id]);
$session_problems = $stmt->fetchAll();

// 問題データを取得
$q_map = [];
$q_stmt = $db->prepare("SELECT * FROM questions WHERE subject = ? AND chapter_name = ? AND problem_number = ?");
foreach ($session_problems as $sp) {
    $q_stmt->execute([$subject, $sp['chapter_name'], $sp['problem_number']]);
    $q = $q_stmt->fetch();
    if ($q) $q_map[$sp['chapter_name'] . '::' . $sp['problem_number']] = $q;
}

// 回答済みを取得
$stmt = $db->prepare("SELECT chapter_name, problem_number, result FROM records WHERE session_id = ?");
$stmt->execute([$session_id]);
$answered_map = [];
foreach ($stmt->fetchAll() as $row) {
    $answered_map[$row['chapter_name'] . '::' . $row['problem_number']] = $row['result'];
}

$total_problems = count($session_problems);
$answered_count = count($answered_map);

// 次の未回答問題を探す
$current_idx = null;
$current_problem = null;
foreach ($session_problems as $idx => $sp) {
    $key = $sp['chapter_name'] . '::' . $sp['problem_number'];
    if (!isset($answered_map[$key])) {
        $current_idx = $idx;
        $current_problem = $sp;
        break;
    }
}

include __DIR__ . '/../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0"><i class="bi bi-lightning"></i> クイズモード</h2>
    <form method="POST" action="<?= url("$subject/finish_session/$session_id") ?>"
          onsubmit="return confirm('クイズを終了しますか？')">
        <button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> 終了</button>
    </form>
</div>

<div class="mb-3">
    <div class="progress" style="height: 25px;">
        <div class="progress-bar bg-primary" style="width: <?= $total_problems > 0 ? round(100 * $answered_count / $total_problems) : 0 ?>%">
            <?= $answered_count ?> / <?= $total_problems ?>
        </div>
    </div>
</div>

<?php if ($current_problem === null): ?>
    <!-- 全問回答済み -->
    <?php
    $correct_count = 0;
    foreach ($answered_map as $result) { if ($result === 'correct') $correct_count++; }
    $acc = $total_problems > 0 ? round(100 * $correct_count / $total_problems, 1) : 0;
    ?>
    <div class="card border-success">
        <div class="card-body text-center">
            <h3><i class="bi bi-trophy"></i> 全問回答完了！</h3>
            <div class="fs-1 fw-bold text-<?= $acc >= 80 ? 'success' : ($acc >= 60 ? 'warning' : 'danger') ?>"><?= $acc ?>%</div>
            <p class="text-muted"><?= $correct_count ?> / <?= $total_problems ?> 正解</p>
            <form method="POST" action="<?= url("$subject/finish_session/$session_id") ?>">
                <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-check-circle"></i> 記録を保存して終了</button>
            </form>
        </div>
    </div>
<?php else:
    $key = $current_problem['chapter_name'] . '::' . $current_problem['problem_number'];
    $q = $q_map[$key] ?? null;

    // 選択肢を用意（シャッフル対応）
    $choices = [
        'A' => $q ? $q['choice_a'] : '',
        'B' => $q ? $q['choice_b'] : '',
        'C' => $q ? $q['choice_c'] : '',
        'D' => $q ? $q['choice_d'] : '',
    ];
    $choice_keys = array_keys($choices);
    if ($shuffle) {
        // シードをセッション+問題番号で固定してリロードしても同じ順序にする
        $seed = crc32($session_id . $key);
        mt_srand($seed);
        shuffle($choice_keys);
        mt_srand();
    }
    $correct_original = $q ? $q['correct_answer'] : 'A';
?>
    <div class="card mb-4">
        <div class="card-header">
            <span class="text-muted small"><?= h($current_problem['chapter_name']) ?></span>
            <strong class="ms-2"><?= h($current_problem['problem_number']) ?></strong>
            <span class="badge bg-secondary ms-2"><?= $current_idx + 1 ?> / <?= $total_problems ?></span>
        </div>
        <div class="card-body">
            <?php if ($q): ?>
            <div class="mb-4" style="font-size: 1.1rem; white-space: pre-wrap;"><?= h($q['question_text']) ?></div>
            <div class="d-grid gap-2" id="choices">
                <?php foreach ($choice_keys as $ck): ?>
                <button class="btn btn-outline-dark btn-lg text-start quiz-choice-btn" data-original="<?= $ck ?>"
                        style="padding: 0.75rem 1.25rem;">
                    <strong><?= $ck ?>.</strong> <?= h($choices[$ck]) ?>
                </button>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="alert alert-warning">この問題は未登録です。</div>
            <div class="d-grid gap-2">
                <button class="btn btn-outline-success btn-lg quiz-skip-btn" data-result="correct"><i class="bi bi-circle"></i> 正解</button>
                <button class="btn btn-outline-danger btn-lg quiz-skip-btn" data-result="incorrect"><i class="bi bi-x-lg"></i> 不正解</button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 結果表示エリア（非表示） -->
    <div id="result-area" class="card mb-4" style="display:none;">
        <div class="card-body text-center">
            <div id="result-icon" class="fs-1 mb-2"></div>
            <div id="result-text" class="fs-4 mb-3"></div>
            <button id="next-btn" class="btn btn-primary btn-lg"><i class="bi bi-arrow-right"></i> 次の問題へ</button>
        </div>
    </div>
<?php endif; ?>

<?php
$record_url = url('record');
$correct_json = json_encode($correct_original);
$chapter_json = json_encode($current_problem ? $current_problem['chapter_name'] : '');
$problem_json = json_encode($current_problem ? $current_problem['problem_number'] : '');
$page_scripts = <<<SCRIPT
<script>
var answered = false;
var correctAnswer = {$correct_json};

document.querySelectorAll('.quiz-choice-btn').forEach(function(btn) {
    btn.addEventListener('click', async function() {
        if (answered) return;
        answered = true;
        var selected = this.dataset.original;
        var isCorrect = selected === correctAnswer;
        var result = isCorrect ? 'correct' : 'incorrect';

        // ボタンの色を変更
        document.querySelectorAll('.quiz-choice-btn').forEach(function(b) {
            b.disabled = true;
            if (b.dataset.original === correctAnswer) {
                b.classList.remove('btn-outline-dark');
                b.classList.add('btn-success');
            } else if (b === btn && !isCorrect) {
                b.classList.remove('btn-outline-dark');
                b.classList.add('btn-danger');
            }
        });

        // 記録を送信
        await fetch('{$record_url}', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ session_id: {$session_id}, chapter_name: {$chapter_json}, problem_number: {$problem_json}, result: result })
        });

        // 結果表示
        var resultArea = document.getElementById('result-area');
        var resultIcon = document.getElementById('result-icon');
        var resultText = document.getElementById('result-text');
        if (isCorrect) {
            resultIcon.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill"></i></span>';
            resultText.innerHTML = '<span class="text-success">正解！</span>';
        } else {
            resultIcon.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle-fill"></i></span>';
            resultText.innerHTML = '<span class="text-danger">不正解</span> <span class="text-muted">正解は ' + correctAnswer + '</span>';
        }
        resultArea.style.display = 'block';
        resultArea.scrollIntoView({ behavior: 'smooth' });
    });
});

document.querySelectorAll('.quiz-skip-btn').forEach(function(btn) {
    btn.addEventListener('click', async function() {
        if (answered) return;
        answered = true;
        await fetch('{$record_url}', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ session_id: {$session_id}, chapter_name: {$chapter_json}, problem_number: {$problem_json}, result: this.dataset.result })
        });
        location.reload();
    });
});

var nextBtn = document.getElementById('next-btn');
if (nextBtn) {
    nextBtn.addEventListener('click', function() { location.reload(); });
}
</script>
SCRIPT;
include __DIR__ . '/../templates/footer.php';
?>
