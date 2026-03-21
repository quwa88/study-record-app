<?php
$page_title = "$subject 問題登録 - USCPA学習記録アプリ";
$problems = load_problems_from_excel($subject);
$chapters = array_keys($problems);
$chapter_filter = $_GET['chapter'] ?? '';

$db = get_db();

// 登録済み問題を取得
$q_map = [];
$q_stmt = $db->prepare("SELECT chapter_name, problem_number, question_text, choice_a, choice_b, choice_c, choice_d, correct_answer, explanation FROM questions WHERE subject = ?");
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
                            data-correct="<?= $registered ? $q_map[$key]['correct_answer'] : '' ?>"
                            data-explanation="<?= h($registered ? ($q_map[$key]['explanation'] ?? '') : '') ?>">
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
                    <label class="form-label fw-bold">問題文 <small class="text-muted fw-normal">(HTML対応)</small></label>
                    <textarea id="q-text" class="form-control" rows="4" placeholder="問題文を入力..."></textarea>
                </div>

                <!-- モード切替 -->
                <div class="mb-3">
                    <div class="btn-group" role="group">
                        <input type="radio" class="btn-check" name="input-mode" id="mode-simple" value="simple" checked>
                        <label class="btn btn-outline-primary" for="mode-simple"><i class="bi bi-text-left"></i> 通常モード</label>
                        <input type="radio" class="btn-check" name="input-mode" id="mode-table" value="table">
                        <label class="btn btn-outline-primary" for="mode-table"><i class="bi bi-table"></i> テーブルモード</label>
                    </div>
                </div>

                <!-- 通常モード -->
                <div id="simple-mode">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">A <small class="text-muted fw-normal">(HTML対応)</small></label>
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
                </div>

                <!-- テーブルモード -->
                <div id="table-mode" style="display:none;">
                    <div class="mb-2">
                        <label class="form-label fw-bold">列ヘッダー</label>
                        <div class="row g-2" id="col-headers">
                            <div class="col"><input type="text" class="form-control form-control-sm col-header" placeholder="列1 (例: Common stock)"></div>
                            <div class="col"><input type="text" class="form-control form-control-sm col-header" placeholder="列2 (例: Preferred stock)"></div>
                            <div class="col"><input type="text" class="form-control form-control-sm col-header" placeholder="列3"></div>
                        </div>
                        <div class="mt-1">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="add-col-btn"><i class="bi bi-plus"></i> 列追加</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="remove-col-btn"><i class="bi bi-dash"></i> 列削除</button>
                        </div>
                    </div>
                    <table class="table table-bordered table-sm mt-2">
                        <thead id="table-header-row">
                            <tr>
                                <th style="width:40px"></th>
                                <th id="th-0">列1</th>
                                <th id="th-1">列2</th>
                                <th id="th-2">列3</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td><strong>A</strong></td><td><input type="text" class="form-control form-control-sm tbl-val" data-row="a" data-col="0"></td><td><input type="text" class="form-control form-control-sm tbl-val" data-row="a" data-col="1"></td><td><input type="text" class="form-control form-control-sm tbl-val" data-row="a" data-col="2"></td></tr>
                            <tr><td><strong>B</strong></td><td><input type="text" class="form-control form-control-sm tbl-val" data-row="b" data-col="0"></td><td><input type="text" class="form-control form-control-sm tbl-val" data-row="b" data-col="1"></td><td><input type="text" class="form-control form-control-sm tbl-val" data-row="b" data-col="2"></td></tr>
                            <tr><td><strong>C</strong></td><td><input type="text" class="form-control form-control-sm tbl-val" data-row="c" data-col="0"></td><td><input type="text" class="form-control form-control-sm tbl-val" data-row="c" data-col="1"></td><td><input type="text" class="form-control form-control-sm tbl-val" data-row="c" data-col="2"></td></tr>
                            <tr><td><strong>D</strong></td><td><input type="text" class="form-control form-control-sm tbl-val" data-row="d" data-col="0"></td><td><input type="text" class="form-control form-control-sm tbl-val" data-row="d" data-col="1"></td><td><input type="text" class="form-control form-control-sm tbl-val" data-row="d" data-col="2"></td></tr>
                        </tbody>
                    </table>
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
                <div class="mt-3">
                    <label class="form-label fw-bold">解説 <small class="text-muted fw-normal">(HTML対応・任意)</small></label>
                    <textarea id="q-explanation" class="form-control" rows="3" placeholder="解説を入力..."></textarea>
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
var isTableMode = false;
var colCount = 3;

// モード切替
document.querySelectorAll('input[name="input-mode"]').forEach(function(r) {
    r.addEventListener('change', function() {
        isTableMode = this.value === 'table';
        document.getElementById('simple-mode').style.display = isTableMode ? 'none' : 'block';
        document.getElementById('table-mode').style.display = isTableMode ? 'block' : 'none';
    });
});

// 列ヘッダーの変更をテーブルヘッダーに反映
document.getElementById('col-headers').addEventListener('input', function() {
    var headers = document.querySelectorAll('.col-header');
    headers.forEach(function(h, i) {
        var th = document.getElementById('th-' + i);
        if (th) th.textContent = h.value || ('列' + (i + 1));
    });
});

// 列追加
document.getElementById('add-col-btn').addEventListener('click', function() {
    if (colCount >= 6) return;
    var idx = colCount;
    colCount++;
    // ヘッダー入力追加
    var headerDiv = document.createElement('div');
    headerDiv.className = 'col';
    headerDiv.innerHTML = '<input type="text" class="form-control form-control-sm col-header" placeholder="列' + colCount + '">';
    document.getElementById('col-headers').appendChild(headerDiv);
    // テーブルヘッダー追加
    var th = document.createElement('th');
    th.id = 'th-' + idx;
    th.textContent = '列' + colCount;
    document.getElementById('table-header-row').querySelector('tr').appendChild(th);
    // 各行にセル追加
    ['a','b','c','d'].forEach(function(row) {
        var tr = document.querySelector('.tbl-val[data-row="' + row + '"][data-col="0"]').closest('tr');
        var td = document.createElement('td');
        td.innerHTML = '<input type="text" class="form-control form-control-sm tbl-val" data-row="' + row + '" data-col="' + idx + '">';
        tr.appendChild(td);
    });
});

// 列削除
document.getElementById('remove-col-btn').addEventListener('click', function() {
    if (colCount <= 1) return;
    colCount--;
    var idx = colCount;
    // ヘッダー入力削除
    var headers = document.getElementById('col-headers');
    if (headers.lastElementChild) headers.removeChild(headers.lastElementChild);
    // テーブルヘッダー削除
    var th = document.getElementById('th-' + idx);
    if (th) th.remove();
    // 各行のセル削除
    document.querySelectorAll('.tbl-val[data-col="' + idx + '"]').forEach(function(el) {
        el.closest('td').remove();
    });
});

// テーブルモードからHTMLを生成
function buildChoiceHtml(row) {
    var headers = document.querySelectorAll('.col-header');
    var parts = [];
    for (var i = 0; i < colCount; i++) {
        var header = headers[i] ? headers[i].value : '';
        var val = '';
        var input = document.querySelector('.tbl-val[data-row="' + row + '"][data-col="' + i + '"]');
        if (input) val = input.value;
        if (header && val) {
            parts.push('<b>' + header + ':</b> ' + val);
        } else if (val) {
            parts.push(val);
        }
    }
    return parts.join(' / ');
}

// 編集ボタン
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
        document.getElementById('q-explanation').value = this.dataset.explanation || '';
        var correct = this.dataset.correct;
        document.querySelectorAll('input[name="correct"]').forEach(function(r) {
            r.checked = r.value === correct;
        });
        // 通常モードにリセット
        document.getElementById('mode-simple').checked = true;
        isTableMode = false;
        document.getElementById('simple-mode').style.display = 'block';
        document.getElementById('table-mode').style.display = 'none';
        // テーブルの入力をクリア
        document.querySelectorAll('.tbl-val').forEach(function(el) { el.value = ''; });
        document.querySelectorAll('.col-header').forEach(function(el) { el.value = ''; });
        bsQModal.show();
    });
});

// 保存
document.getElementById('q-save-btn').addEventListener('click', async function() {
    var correctEl = document.querySelector('input[name="correct"]:checked');
    if (!correctEl) { alert('正解を選択してください'); return; }

    var choiceA, choiceB, choiceC, choiceD;
    if (isTableMode) {
        choiceA = buildChoiceHtml('a');
        choiceB = buildChoiceHtml('b');
        choiceC = buildChoiceHtml('c');
        choiceD = buildChoiceHtml('d');
    } else {
        choiceA = document.getElementById('q-a').value;
        choiceB = document.getElementById('q-b').value;
        choiceC = document.getElementById('q-c').value;
        choiceD = document.getElementById('q-d').value;
    }

    var data = {
        subject: {$subject_json},
        chapter_name: qChapter,
        problem_number: qProblem,
        question_text: document.getElementById('q-text').value,
        choice_a: choiceA,
        choice_b: choiceB,
        choice_c: choiceC,
        choice_d: choiceD,
        correct_answer: correctEl.value,
        explanation: document.getElementById('q-explanation').value
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
