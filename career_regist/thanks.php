<?php

declare(strict_types=1);

// config.php（APP_ROOT / APP_URL / func.php読み込み）を使う前提
require_once __DIR__ . '/../config.php';

// ログインチェック
sschk();

// キャリア登録セッションを完了にする
$userId = (int)$_SESSION['user_id'];
$sid    = (int)($_SESSION['career_session_id'] ?? 0);
if ($sid > 0) {
    try {
        $pdo = db_conn();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->prepare("UPDATE career_sessions SET status='completed', updated_at=NOW() WHERE id=:sid AND user_id=:uid");
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();

        // 次回また最初から回答したい場合はセッションIDを消す
        // unset($_SESSION['career_session_id']);
    } catch (PDOException $e) {
        // 失敗しても表示自体は出したいので握りつぶす（必要ならログに）
    }
}

// home.php の場所が「プロジェクト直下」想定
$homeUrl = APP_URL . '/home.php';
?>

<style>
    .thanks-wrap {
        max-width: 720px;
        margin: 0 auto;
        padding: 28px 18px;
    }

    .thanks-card {
        background: #fff;
        border-radius: 14px;
        padding: 28px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, .06);
        border: 1px solid rgba(0, 0, 0, .06);
    }

    .thanks-title {
        font-size: 22px;
        font-weight: 900;
        margin: 0 0 10px;
    }

    .thanks-text {
        margin: 0 0 18px;
        color: rgba(0, 0, 0, .65);
        line-height: 1.7;
    }

    .thanks-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: 0;
        background: #111827;
        color: #fff;
        border-radius: 12px;
        padding: 12px 16px;
        font-weight: 800;
        text-decoration: none;
        cursor: pointer;
    }
</style>

<div class="thanks-wrap">
    <div class="thanks-card">
        <h1 class="thanks-title">回答ありがとうございました！</h1>
        <p class="thanks-text">
            ご回答内容をもとに、今後の進め方の最適化に活用させていただきます。<br>
            HOMEボタンを押して、早速学習をスタートしていきましょう！
        </p>

        <div class="thanks-actions">
            <a class="btn-primary" href="<?= h($homeUrl) ?>">
                <i class="fas fa-home"></i> HOMEへ進む
            </a>
        </div>
    </div>
</div>