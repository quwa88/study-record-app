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

    // 選択肢の内容を配列に（元のラベル → テキスト）
    $original_choices = [
        'A' => $q ? $q['choice_a'] : '',
        'B' => $q ? $q['choice_b'] : '',
        'C' => $q ? $q['choice_c'] : '',
        'D' => $q ? $q['choice_d'] : '',
    ];
    $correct_original = $q ? $q['correct_answer'] : 'A';
    $correct_text = $original_choices[$correct_original];
    $explanation = $q ? ($q['explanation'] ?? '') : '';

    // シャッフル: 内容をシャッフルして新しいA/B/C/Dに割り当て
    $choice_values = array_values($original_choices);
    $new_labels = ['A', 'B', 'C', 'D'];
    if ($shuffle) {
        $seed = crc32($session_id . $key);
        mt_srand($seed);
        shuffle($choice_values);
        mt_srand();
    }
    // シャッフル後の正解ラベルを特定
    $correct_new_label = 'A';
    foreach ($choice_values as $i => $val) {
        if ($val === $correct_text) {
            $correct_new_label = $new_labels[$i];
            break;
        }
    }
?>
    <div class="card mb-4">
        <div class="card-header">
            <span class="text-muted small"><?= h($current_problem['chapter_name']) ?></span>
            <strong class="ms-2"><?= h($current_problem['problem_number']) ?></strong>
            <span class="badge bg-secondary ms-2"><?= $current_idx + 1 ?> / <?= $total_problems ?></span>
        </div>
        <div class="card-body">
            <?php if ($q): ?>
            <div class="mb-4" style="font-size: 1.1rem;"><?= $q['question_text'] ?></div>
            <div class="d-grid gap-2" id="choices">
                <?php foreach ($new_labels as $i => $label): ?>
                <button class="btn btn-outline-dark btn-lg text-start quiz-choice-btn" data-label="<?= $label ?>"
                        style="padding: 0.75rem 1.25rem;">
                    <strong><?= $label ?>.</strong> <?= $choice_values[$i] ?>
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

    <!-- 結果表示エリア -->
    <div id="result-area" class="card mb-4" style="display:none;">
        <div class="card-body text-center">
            <div id="result-icon" class="fs-1 mb-2"></div>
            <div id="result-text" class="fs-4 mb-3"></div>
            <?php if ($explanation): ?>
            <div id="explanation-area" class="text-start mb-3 p-3 bg-light rounded" style="display:none;">
                <strong><i class="bi bi-lightbulb"></i> 解説:</strong>
                <div class="mt-2"><?= $explanation ?></div>
            </div>
            <?php endif; ?>
            <button id="next-btn" class="btn btn-primary btn-lg"><i class="bi bi-arrow-right"></i> 次の問題へ</button>
        </div>
    </div>
<?php endif; ?>

<?php
$record_url = url('record');
$correct_label_json = json_encode($correct_new_label);
$chapter_json = json_encode($current_problem ? $current_problem['chapter_name'] : '');
$problem_json = json_encode($current_problem ? $current_problem['problem_number'] : '');
$page_scripts = <<<SCRIPT
<script>
var answered = false;
var correctLabel = {$correct_label_json};

document.querySelectorAll('.quiz-choice-btn').forEach(function(btn) {
    btn.addEventListener('click', async function() {
        if (answered) return;
        answered = true;
        var selected = this.dataset.label;
        var isCorrect = selected === correctLabel;
        var result = isCorrect ? 'correct' : 'incorrect';

        document.querySelectorAll('.quiz-choice-btn').forEach(function(b) {
            b.disabled = true;
            if (b.dataset.label === correctLabel) {
                b.classList.remove('btn-outline-dark');
                b.classList.add('btn-success');
            } else if (b === btn && !isCorrect) {
                b.classList.remove('btn-outline-dark');
                b.classList.add('btn-danger');
            }
        });

        await fetch('{$record_url}', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ session_id: {$session_id}, chapter_name: {$chapter_json}, problem_number: {$problem_json}, result: result })
        });

        var resultArea = document.getElementById('result-area');
        var resultIcon = document.getElementById('result-icon');
        var resultText = document.getElementById('result-text');
        if (isCorrect) {
            resultIcon.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill"></i></span>';
            resultText.innerHTML = '<span class="text-success">正解！</span>';
        } else {
            resultIcon.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle-fill"></i></span>';
            resultText.innerHTML = '<span class="text-danger">不正解</span> <span class="text-muted">正解は ' + correctLabel + '</span>';
        }
        resultArea.style.display = 'block';
        var explArea = document.getElementById('explanation-area');
        if (explArea) explArea.style.display = 'block';
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
