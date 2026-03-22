<?php
$display_name = subject_display_name($subject);
$page_title = "$display_name Excel管理 - USCPA学習記録アプリ";
$is_tbs_subject = is_tbs($subject);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['xlsx', 'xls'])) {
            if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
            move_uploaded_file($_FILES['file']['tmp_name'], get_excel_path($subject));
            flash("{$display_name}のExcelファイルをアップロードしました。", 'success');
        } else {
            flash('有効なExcelファイル(.xlsx)を選択してください。', 'error');
        }
    } else {
        flash('ファイルのアップロードに失敗しました。', 'error');
    }
    redirect("$subject/upload");
}

if ($is_tbs_subject) {
    $tbs_problems = load_tbs_problems_from_excel($subject);
    $total_problems = array_sum(array_map('count', $tbs_problems));
} else {
    $problems = load_problems_from_excel($subject);
    $total_problems = array_sum(array_map('count', $problems));
}

include __DIR__ . '/../templates/header.php';
?>

<h2 class="mb-4"><i class="bi bi-upload"></i> <?= h($display_name) ?> Excel管理</h2>

<div class="card mb-4">
    <div class="card-header">Excelファイルのアップロード</div>
    <div class="card-body">
        <?php if ($is_tbs_subject): ?>
        <p class="text-muted">Excelファイルの形式: 1列目に「チャプター名」、2列目に「問題番号」、3列目に「小問数」（1行目はヘッダー）</p>
        <?php else: ?>
        <p class="text-muted">Excelファイルの形式: 1列目に「チャプター名」、2列目に「問題番号」（1行目はヘッダー）</p>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data" class="d-flex gap-2">
            <input type="file" name="file" accept=".xlsx,.xls" class="form-control" required>
            <button type="submit" class="btn btn-primary text-nowrap"><i class="bi bi-upload"></i> アップロード</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">現在の問題データ <span class="badge bg-primary ms-2">合計 <?= $total_problems ?>問</span></div>
    <div class="card-body">
        <?php if ($is_tbs_subject && $tbs_problems): ?>
        <div class="accordion" id="problemAccordion">
            <?php $idx = 0; foreach ($tbs_problems as $chapter => $items): $idx++; ?>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $idx ?>">
                        <?= h($chapter) ?> <span class="badge bg-secondary ms-2"><?= count($items) ?>問</span>
                    </button>
                </h2>
                <div id="collapse-<?= $idx ?>" class="accordion-collapse collapse" data-bs-parent="#problemAccordion">
                    <div class="accordion-body">
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($items as $item): ?>
                            <span class="badge bg-light text-dark border"><?= $item['number'] ?> <small class="text-muted">(<?= $item['subquestions'] ?>問)</small></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php elseif (!$is_tbs_subject && $problems): ?>
        <div class="accordion" id="problemAccordion">
            <?php $idx = 0; foreach ($problems as $chapter => $numbers): $idx++; ?>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $idx ?>">
                        <?= h($chapter) ?> <span class="badge bg-secondary ms-2"><?= count($numbers) ?>問</span>
                    </button>
                </h2>
                <div id="collapse-<?= $idx ?>" class="accordion-collapse collapse" data-bs-parent="#problemAccordion">
                    <div class="accordion-body">
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($numbers as $n): ?>
                            <span class="badge bg-light text-dark border"><?= $n ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-muted mb-0">Excelファイルがアップロードされていません。</p>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
