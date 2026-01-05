<?php
declare(strict_types=1);
session_start();

// デバッグ用
ini_set('display_errors', '1');
error_reporting(E_ALL);

// 関数読み込み
require_once __DIR__ . '/../event/func.php';

// DB接続
try {
    $pdo = new PDO('mysql:dbname=learning_app;charset=utf8;host=localhost', 'root', '');
} catch (PDOException $e) {
    exit('DBConnectError' . $e->getMessage());
}

// ログインチェック
sschk();

$userId = (int)$_SESSION['user_id'];

/**
 * career_session_id を用意（q01と同じ）
 */
function ensureCareerSessionId(PDO $pdo, int $userId): int {
    if (!empty($_SESSION['career_session_id'])) {
        return (int)$_SESSION['career_session_id'];
    }

    $sql = "SELECT id
            FROM career_sessions
            WHERE user_id = :user_id AND status = 'in_progress'
            ORDER BY id DESC
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $_SESSION['career_session_id'] = (int)$row['id'];
        return (int)$row['id'];
    }

    $insert = "INSERT INTO career_sessions (user_id, status, current_step)
                VALUES (:user_id, 'in_progress', 1)";
    $stmt = $pdo->prepare($insert);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    $sid = (int)$pdo->lastInsertId();
    $_SESSION['career_session_id'] = $sid;
    return $sid;
}

$careerSessionId = ensureCareerSessionId($pdo, $userId);

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_token'];

$error = '';
$valuesNotWant = '';

try {
    // 既存回答（途中再開用）
    $sql = "SELECT id, values_not_want
            FROM career_answers
            WHERE session_id = :sid AND user_id = :uid
            ORDER BY id DESC
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing && !empty($existing['values_not_want'])) {
        $valuesNotWant = (string)$existing['values_not_want'];
    }

    // POST：保存して次へ
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // CSRFチェック
        $postedToken = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals($csrfToken, $postedToken)) {
            exit('Invalid CSRF token');
        }

        $valuesNotWant = trim((string)($_POST['values_not_want'] ?? ''));

        // バリデーション
        if ($valuesNotWant === '') {
            $error = '入力してください。';
        } elseif (mb_strlen($valuesNotWant) > 5000) {
            $error = '長すぎます（5000文字以内）。';
        } else {
            $pdo->beginTransaction();

            // career_answers があるなら UPDATE、なければ INSERT
            if ($existing) {
                $update = "UPDATE career_answers
                            SET values_not_want = :values_not_want,
                                updated_at = NOW()
                            WHERE id = :id AND session_id = :sid AND user_id = :uid";
                $stmt = $pdo->prepare($update);
                $stmt->bindValue(':values_not_want', $valuesNotWant, PDO::PARAM_STR);
                $stmt->bindValue(':id', (int)$existing['id'], PDO::PARAM_INT);
                $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $insert = "INSERT INTO career_answers (session_id, user_id, values_not_want, created_at, updated_at)
                            VALUES (:sid, :uid, :values_not_want, NOW(), NOW())";
                $stmt = $pdo->prepare($insert);
                $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':values_not_want', $valuesNotWant, PDO::PARAM_STR);
                $stmt->execute();
            }

            // セッション進捗を次に進める（current_step=12）
            $updSession = "UPDATE career_sessions
                            SET current_step = 12,
                                updated_at = NOW()
                            WHERE id = :sid AND user_id = :uid";
            $stmt = $pdo->prepare($updSession);
            $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $pdo->commit();

            header('Location: q12.php');
            exit;
        }
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    exit('DB error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>アンケート - Q11</title>
    <link rel="stylesheet" href="css/style.css" />
    <style>
        body { font-family: system-ui, -apple-system, "Noto Sans JP", sans-serif; background:#f6f7fb; margin:0; }
        .wrap { max-width:720px; margin:0 auto; padding:24px; }
        .card { background:#fff; border-radius:14px; padding:20px; box-shadow:0 6px 20px rgba(0,0,0,.06); }
        .qno { font-weight:700; color:#6b7280; margin-bottom:6px; }
        h1 { font-size:20px; margin:0 0 12px; }
        .desc { color:#6b7280; margin:0 0 16px; font-size:14px; line-height:1.6; }
        .err { background:#fff1f2; color:#9f1239; padding:10px 12px; border-radius:10px; margin-bottom:12px; }

        textarea {
            width:100%;
            min-height: 240px;
            padding: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            font-size: 16px;
            box-sizing: border-box;
            resize: vertical;
            line-height: 1.6;
        }

        .hint {
            font-size:13px;
            color:#6b7280;
            margin-top:8px;
            line-height:1.6;
        }

        .actions { display:flex; justify-content:flex-end; margin-top:14px; }
        .btn { border:0; background:#111827; color:#fff; border-radius:12px; padding:12px 16px; font-weight:700; cursor:pointer; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="qno">Q11 / 価値観</div>
        <h1>あなたが「絶対に避けたいこと／やりたくないこと」は何ですか？</h1>
        <p class="desc">
            ここが明確になると、学習設計やキャリアの意思決定がブレにくくなります。<br>
            例：長時間労働、理不尽な評価、裁量がない、ルールが曖昧、ノルマ至上主義、人間関係が悪い、成長実感がない など
        </p>

        <?php if ($error): ?>
            <div class="err"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

            <textarea
                name="values_not_want"
                placeholder="例）
・避けたいこと（3〜10個くらい）
・なぜ避けたいか（過去の経験があれば）
・それが起きると、どんな状態になると最悪か"><?= h($valuesNotWant) ?></textarea>

            <div class="hint">
                書きづらければ「過去に一番しんどかった仕事/環境は？」「もう二度とやりたくないことは？」から思い出すと出やすいです。
            </div>

            <div class="actions">
                <button type="submit" class="btn">次へ</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
