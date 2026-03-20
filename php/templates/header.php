<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title ?? '演習記録') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= url('static/style.css') ?>" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?= url('/') ?>">
                <i class="bi bi-journal-check"></i> 演習記録
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= url('/') ?>">
                            <i class="bi bi-house"></i> 科目選択
                        </a>
                    </li>
                    <?php if (!empty($subject)): ?>
                    <li class="nav-item">
                        <a class="nav-link fw-bold" href="<?= url($subject) ?>">
                            <i class="bi bi-book"></i> <?= h($subject) ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= url("$subject/dashboard") ?>">
                            <i class="bi bi-graph-up"></i> ダッシュボード
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= url("$subject/history") ?>">
                            <i class="bi bi-clock-history"></i> 履歴
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= url("$subject/upload") ?>">
                            <i class="bi bi-upload"></i> Excel管理
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container py-4">
        <?php foreach (get_flashes() as $flash): ?>
        <div class="alert alert-<?= $flash['category'] === 'error' ? 'danger' : h($flash['category']) ?> alert-dismissible fade show">
            <?= h($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endforeach; ?>
