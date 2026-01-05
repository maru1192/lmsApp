<?php

declare(strict_types=1);
session_start();

// エラー表示を有効化（デバッグ用）
ini_set('display_errors', '1');
error_reporting(E_ALL);

//関数ファイルの読み込み
require_once __DIR__ . '/../event/func.php';

//DB接続
try {
    $pdo = new PDO('mysql:dbname=learning_app;charset=utf8;host=localhost', 'root', '');
} catch (PDOException $e) {
    exit('DBConnectError' . $e->getMessage());
}

// ログインチェック
sschk();

$userId = (int)$_SESSION['user_id'];

/**
 * career_session_id を用意する関数
 * - {param} リダイレクト先のファイル名（右側はデフォルト値）あればそれを使う
 * - {return} career_session_id
 */
function ensureCareerSessionId(PDO $pdo, int $userId): int{
    if (!empty($_SESSION['career_session_id'])) {
        return (int)$_SESSION['career_session_id'];
    }

    // 進行中セッション探す
    $sql = "SELECT id
            FROM career_sessions
            WHERE user_id = :user_id AND status = 'in_progress'
            ORDER BY id DESC
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    //連想配列で取得
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $_SESSION['career_session_id'] = (int)$row['id'];
        return (int)$row['id'];
    }

    // なければ新規作成（current_step=1）
    $insert = "INSERT INTO 
                    career_sessions (user_id, status, current_step)
                VALUES 
                    (:user_id, 'in_progress', 1)";

    $stmt = $pdo->prepare($insert);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    $sid = (int)$pdo->lastInsertId();
    $_SESSION['career_session_id'] = $sid;
    return $sid;
}

//関数の実行
$careerSessionId = ensureCareerSessionId($pdo, $userId);


// CSRF（セキュリティ対策）
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_token'];

$error = '';
$employmentStatus = '';


// 選択肢（DBにはこの値が入る）
$options = [
    'employee'   => '会社員（正社員/契約/派遣/パート含む）',
    'freelance'  => 'フリーランス/個人事業主',
    'student'    => '学生',
    'jobless'    => '離職中/休職中',
    'other'      => 'その他',
];

try {
    // 既存回答（途中再開用）
    $sql = "SELECT id, employment_status
            FROM career_answers
            WHERE session_id = :sid AND user_id = :uid
            ORDER BY id DESC
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing && !empty($existing['employment_status'])) {
        $employmentStatus = (string)$existing['employment_status'];
    }

    // POST：保存して次へ
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // CSRFチェック
        $postedToken = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals($csrfToken, $postedToken)) {
            exit('Invalid CSRF token');
        }

        $employmentStatus = (string)($_POST['employment_status'] ?? '');

        // バリデーション
        if ($employmentStatus === '' || !array_key_exists($employmentStatus, $options)) {
            $error = '選択してください。';
        } else {
            $pdo->beginTransaction();

            // career_answers があるなら UPDATE、なければ INSERT
            if ($existing) {
                $update = "UPDATE career_answers
                            SET employment_status = :employment_status,
                                updated_at = NOW()
                            WHERE id = :id AND session_id = :sid AND user_id = :uid";
                $stmt = $pdo->prepare($update);
                
                $stmt->bindValue(':employment_status', $employmentStatus, PDO::PARAM_STR);
                $stmt->bindValue(':id', (int)$existing['id'], PDO::PARAM_INT);
                $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $insert = "INSERT INTO career_answers (session_id, user_id, employment_status, created_at, updated_at)
                            VALUES (:sid, :uid, :employment_status, NOW(), NOW())";
                $stmt = $pdo->prepare($insert);
                $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':employment_status', $employmentStatus, PDO::PARAM_STR);
                $stmt->execute();
            }

            // セッション進捗を次に進める（current_step=2）
            $updSession = "UPDATE career_sessions
                            SET current_step = 2,
                                updated_at = NOW()
                            WHERE id = :sid AND user_id = :uid";
            $stmt = $pdo->prepare($updSession);
            $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $pdo->commit();

            header('Location: q02.php');
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
    <title>アンケート - Q1</title>
    <link rel="stylesheet" href="css/style.css" />

    <style>
        .opt {
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="card">
            <div class="qno">Q1 / はじめに</div>
            <h1>現在の状況はどちらになりますか？</h1>
            <p class="desc">
                ここでの回答は「学習に使える時間」や「最適な進め方」を考えるために使います。
            </p>

            <?php if ($error): ?>
                <div class="err"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

                <?php foreach ($options as $value => $label): ?>
                    <label class="opt">
                        <input
                            type="radio"
                            name="employment_status"
                            value="<?= h($value) ?>"
                            <?= ($employmentStatus === $value) ? 'checked' : '' ?>
                            required />
                        <span><?= h($label) ?></span>
                    </label>
                <?php endforeach; ?>

                <div class="actions">
                    <button type="submit" class="btn">次へ</button>
                </div>
            </form>
        </div>
    </div>
</body>

</html>