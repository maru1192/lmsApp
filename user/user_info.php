<?php
declare(strict_types=1);

// 設定読み込み
require_once __DIR__ . '/../config.php';

// ページ設定
$ACTIVE_MENU = 'user';
$ACTIVE_SUB = 'user_info';
$pageTitle = 'ユーザー登録情報';

// ユーザーIDを取得
$userId = $_SESSION['user_id'] ?? 0;

if (!$userId) {
    exit('ユーザー情報が取得できません');
}

// DB接続
try {
    $pdo = db_conn();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    exit('DBConnectError:' . $e->getMessage());
}

// ユーザー情報を取得
$stmt = $pdo->prepare("SELECT * FROM user_table WHERE id = :id AND life_flg = 1");
$stmt->bindValue(':id', $userId, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    exit('ユーザー情報が見つかりません');
}

// 年齢計算
$age = '';
if (!empty($user['birthday'])) {
    $birthday = new DateTime($user['birthday']);
    $today = new DateTime();
    $age = $today->diff($birthday)->y . '歳';
}

// 共通レイアウト開始
require_once APP_ROOT . '/parts/layout_start.php';
?>

<link rel="stylesheet" href="<?= h(APP_URL . '/user/css/user_info.css') ?>">

<div class="page-header">
    <h1 class="page-title">
        <i class="far fa-id-card"></i>
        ユーザー登録情報
    </h1>
</div>

<div class="content-card">
    <div class="info-grid">
        <div class="info-row">
            <div class="info-label">お名前</div>
            <div class="info-value"><?= h($user['name_sei'] ?? '') ?> <?= h($user['name_mei'] ?? '') ?></div>
        </div>

        <div class="info-row">
            <div class="info-label">性別</div>
            <div class="info-value"><?= h($user['gender'] ?? '未設定') ?></div>
        </div>

        <div class="info-row">
            <div class="info-label">生年月日</div>
            <div class="info-value">
                <?php if (!empty($user['birthday'])): ?>
                    <?= h(date('Y年m月d日', strtotime($user['birthday']))) ?>
                    （<?= h($age) ?>）
                <?php else: ?>
                    未設定
                <?php endif; ?>
            </div>
        </div>

        <div class="info-row">
            <div class="info-label">メールアドレス</div>
            <div class="info-value"><?= h($user['lid'] ?? '') ?></div>
        </div>

        <div class="info-row">
            <div class="info-label">登録日</div>
            <div class="info-value">
                <?php if (!empty($user['reg_time'])): ?>
                    <?= h(date('Y年m月d日 H:i', strtotime($user['reg_time']))) ?>
                <?php else: ?>
                    -
                <?php endif; ?>
            </div>
        </div>

        <div class="info-row">
            <div class="info-label">最終更新日</div>
            <div class="info-value">
                <?php if (!empty($user['upd_time'])): ?>
                    <?= h(date('Y年m月d日 H:i', strtotime($user['upd_time']))) ?>
                <?php else: ?>
                    -
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="action-buttons">
        <a href="<?= h(APP_URL . '/user/user_edit.php') ?>" class="btn-edit">
            <i class="far fa-edit"></i>
            編集する
        </a>
    </div>
</div>

<?php
// 共通レイアウト終了
require_once APP_ROOT . '/parts/layout_end.php';
?>
