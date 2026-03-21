<?php
$page_title = "$subject 問題登録 - USCPA学習記録アプリ";
$problems = load_problems_from_excel($subject);
$chapters = array_keys($problems);
$chapter_filter = $_GET['chapter'] ?? '';

$db = get_db();

// 登録済み問題を取得
$q_map = [];
$q_stmt = $db->prepare("SELECT chapter_name, problem_number, question_text, choice_a, choice_b, choice_c, choice_d, correct_answer FROM questions WHERE subject = ?");
$q_stmt->execute([$subject]);
foreach ($q_stmt->fetchAll() as $q) {
    $q_map[$q['chapter_name'] . '::' . $q['problem_number']] = $q;
}

// 表示する問題リスト
$display_problems = [];
if ($chapter_filter && isset($problems[$chapter_filter])) {
    foreach ($problems[$chapter_filter] as $pn) {
        $display_problems[] = ['chapter' => $chapter_filter, 'number' => $pn];
    }
} else {
    foreach ($problems as $ch => $nums) {
        foreach ($nums as $pn) {
            $display_problems[] = ['chapter' => $ch, 'number' => $pn];
        }
    }
}

$registered_count = count($q_map);
$total_count = count($display_problems);

include __DIR__ . '/../templates/header.php';
?>

<h2 class="mb-4"><i class="bi bi-pencil-square"></i> <?= h($subject) ?> 問題登録</h2>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-12 col-md-6">
                <label class="form-label">チャプター</label>
                <select name="chapter" class="form-select">
                    <option value="">すべて</option>
                    <?php foreach ($chapters as $ch): ?>
                    <option value="<?= h($ch) ?>" <?= $ch === $chapter_filter ? 'selected' : '' ?>><?= h($ch) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> 絞り込み</button>
            </div>
            <div class="col-12 col-md-3">
                <span class="text-muted">登録済: <?= $registered_count ?> 問</span>
            </div>
        </form>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-hover table-striped">
        <thead class="table-light">
            <tr>
                <th>チャプター</th>
                <th style="width:120px">問題番号</th>
                <th>登録状況</th>
                <th style="width:100px">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($display_problems as $dp):
                $key = $dp['chapter'] . '::' . $dp['number'];
                $registered = isset($q_map[$key]);
            ?>
            <tr>
                <td class="text-muted small"><?= h($dp['chapter']) ?></td>
                <td><strong><?= h($dp['number']) ?></strong></td>
                <td>
                    <?php if ($registered): ?>
                        <span class="badge bg-success"><i class="bi bi-check"></i> 登録済</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">未登録</span>
                    <?php endif; ?>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-primary question-edit-btn"
                            data-chapter="<?= h($dp['chapter']) ?>"
                            data-problem="<?= h($dp['number']) ?>"
                            data-question="<?= h($registered ? $q_map[$key]['question_text'] : '') ?>"
                            data-a="<?= h($registered ? $q_map[$key]['choice_a'] : '') ?>"
                            data-b="<?= h($registered ? $q_map[$key]['choice_b'] : '') ?>"
                            data-c="<?= h($registered ? $q_map[$key]['choice_c'] : '') ?>"
                            data-d="<?= h($registered ? $q_map[$key]['choice_d'] : '') ?>"
                            data-correct="<?= $registered ? $q_map[$key]['correct_answer'] : '' ?>">
                        <i class="bi bi-<?= $registered ? 'pencil' : 'plus-circle' ?>"></i> <?= $registered ? '編集' : '登録' ?>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- 問題編集モーダル -->
<div class="modal fade" id="questionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square"></i> 問題登録</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3" id="q-label"></p>
                <div class="mb-3">
                    <label class="form-label fw-bold">問題文</label>
                    <textarea id="q-text" class="form-control" rows="4" placeholder="問題文を入力..."></textarea>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">A</label>
                        <textarea id="q-a" class="form-control" rows="2" placeholder="選択肢A"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">B</label>
                        <textarea id="q-b" class="form-control" rows="2" placeholder="選択肢B"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">C</label>
                        <textarea id="q-c" class="form-control" rows="2" placeholder="選択肢C"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">D</label>
                        <textarea id="q-d" class="form-control" rows="2" placeholder="選択肢D"></textarea>
                    </div>
                </div>
                <div class="mt-3">
                    <label class="form-label fw-bold">正解</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="correct" id="correct-a" value="A">
                        <label class="btn btn-outline-success" for="correct-a">A</label>
                        <input type="radio" class="btn-check" name="correct" id="correct-b" value="B">
                        <label class="btn btn-outline-success" for="correct-b">B</label>
                        <input type="radio" class="btn-check" name="correct" id="correct-c" value="C">
                        <label class="btn btn-outline-success" for="correct-c">C</label>
                        <input type="radio" class="btn-check" name="correct" id="correct-d" value="D">
                        <label class="btn btn-outline-success" for="correct-d">D</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                <button type="button" class="btn btn-primary" id="q-save-btn"><i class="bi bi-check-lg"></i> 保存</button>
            </div>
        </div>
    </div>
</div>

<?php
$subject_json = json_encode($subject);
$question_url = url('question');
$page_scripts = <<<SCRIPT
<script>
var qModal = document.getElementById('questionModal');
var bsQModal = new bootstrap.Modal(qModal);
var qChapter = '', qProblem = '';

document.querySelectorAll('.question-edit-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        qChapter = this.dataset.chapter;
        qProblem = this.dataset.problem;
        document.getElementById('q-label').textContent = qChapter + ' / ' + qProblem;
        document.getElementById('q-text').value = this.dataset.question;
        document.getElementById('q-a').value = this.dataset.a;
        document.getElementById('q-b').value = this.dataset.b;
        document.getElementById('q-c').value = this.dataset.c;
        document.getElementById('q-d').value = this.dataset.d;
        var correct = this.dataset.correct;
        document.querySelectorAll('input[name="correct"]').forEach(function(r) {
            r.checked = r.value === correct;
        });
        bsQModal.show();
    });
});

document.getElementById('q-save-btn').addEventListener('click', async function() {
    var correctEl = document.querySelector('input[name="correct"]:checked');
    if (!correctEl) { alert('正解を選択してください'); return; }
    var data = {
        subject: {$subject_json},
        chapter_name: qChapter,
        problem_number: qProblem,
        question_text: document.getElementById('q-text').value,
        choice_a: document.getElementById('q-a').value,
        choice_b: document.getElementById('q-b').value,
        choice_c: document.getElementById('q-c').value,
        choice_d: document.getElementById('q-d').value,
        correct_answer: correctEl.value
    };
    try {
        var res = await fetch('{$question_url}', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        if (res.ok) { bsQModal.hide(); location.reload(); }
        else {
            var err = await res.json();
            alert(err.error || '保存に失敗しました');
        }
    } catch(e) { alert('通信エラー'); }
});
</script>
SCRIPT;
include __DIR__ . '/../templates/footer.php';
?>
