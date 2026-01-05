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
    $pdo = db_conn();
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

// 半年後の目標（各項目）
$halfYearGoals = [
    'income' => '',
    'achievement' => '',
    'skill' => '',
    'habit' => '',
];

try {
    // 既存回答（途中再開用）
    $sql = "SELECT id, half_year_goals
            FROM career_answers
            WHERE session_id = :sid AND user_id = :uid
            ORDER BY id DESC
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    // 既存データがあれば復元（JSON形式を想定）
    if ($existing && !empty($existing['half_year_goals'])) {
        $data = json_decode($existing['half_year_goals'], true);
        if (is_array($data)) {
            $halfYearGoals = array_merge($halfYearGoals, $data);
        }
    }

    // POST：保存して次へ
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // CSRFチェック
        $postedToken = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals($csrfToken, $postedToken)) {
            exit('Invalid CSRF token');
        }

        // 半年後の目標
        $halfYearGoals = [
            'income' => trim((string)($_POST['income'] ?? '')),
            'achievement' => trim((string)($_POST['achievement'] ?? '')),
            'skill' => trim((string)($_POST['skill'] ?? '')),
            'habit' => trim((string)($_POST['habit'] ?? '')),
        ];

        // バリデーション：少なくとも1項目が必須
        $hasContent = false;
        foreach ($halfYearGoals as $val) {
            if ($val !== '') {
                $hasContent = true;
                break;
            }
        }

        if (!$hasContent) {
            $error = '少なくとも1つの項目を記入してください。';
        } else {
            // JSON形式で保存
            $saveData = json_encode($halfYearGoals, JSON_UNESCAPED_UNICODE);

            $pdo->beginTransaction();

            if ($existing) {
                $update = "UPDATE career_answers
                            SET half_year_goals = :half_year_goals,
                                updated_at = NOW()
                            WHERE id = :id AND session_id = :sid AND user_id = :uid";
                $stmt = $pdo->prepare($update);
                $stmt->bindValue(':half_year_goals', $saveData, PDO::PARAM_STR);
                $stmt->bindValue(':id', (int)$existing['id'], PDO::PARAM_INT);
                $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $insert = "INSERT INTO career_answers (session_id, user_id, half_year_goals, created_at, updated_at)
                            VALUES (:sid, :uid, :half_year_goals, NOW(), NOW())";
                $stmt = $pdo->prepare($insert);
                $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':half_year_goals', $saveData, PDO::PARAM_STR);
                $stmt->execute();
            }

            // セッション進捗を完了へ（current_step=13）
            $updSession = "UPDATE career_sessions
                            SET current_step = 13,
                                status = 'completed',
                                updated_at = NOW()
                            WHERE id = :sid AND user_id = :uid";
            $stmt = $pdo->prepare($updSession);
            $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $pdo->commit();

            // 完了ページへリダイレクト
            header('Location: q13.php');
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
    <title>アンケート - Q12</title>
    <link rel="stylesheet" href="css/style.css" />
    <style>
        .goal-flow {
            margin: 24px 0;
            padding: 20px;
            background: #f9fafb;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
        }
        .goal-item {
            margin-bottom: 24px;
            position: relative;
        }
        .goal-item:not(:last-child)::after {
            content: '↑';
            position: absolute;
            left: -30px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 24px;
            color: #6b7280;
        }
        .goal-item label {
            display: block;
            font-weight: 700;
            font-size: 16px;
            margin-bottom: 8px;
            color: #111827;
        }
        .goal-item textarea {
            width: 100%;
            min-height: 80px;
            padding: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            font-size: 15px;
            box-sizing: border-box;
            resize: vertical;
            line-height: 1.6;
        }
        .goal-hint {
            font-size: 13px;
            color: #6b7280;
            margin-top: 6px;
            line-height: 1.5;
        }
        @media (max-width: 600px) {
            .goal-item:not(:last-child)::after {
                left: 50%;
                top: auto;
                bottom: -20px;
                transform: translateX(-50%) rotate(90deg);
            }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="qno">Q12 / 半年後の目標</div>
        <h1>その「1年後」を実現するために、半年後に何ができていたら理想ですか？</h1>
        <p class="desc">
            1年後の理想から逆算して、半年後に達成しておきたいことを具体的に書き出してください。<br>
            下から上へ、積み上げていくイメージで考えてみましょう。
        </p>

        <?php if ($error): ?>
            <div class="err"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

            <div class="goal-flow">
                <!-- 収入 -->
                <div class="goal-item">
                    <label>💰 収入</label>
                    <textarea name="income" placeholder="例）"><?= h($halfYearGoals['income']) ?></textarea>
                    <div class="goal-hint">半年後に達成したい収入目標を書いてください</div>
                </div>

                <!-- 実績 -->
                <div class="goal-item">
                    <label>📊 実績</label>
                    <textarea name="achievement" placeholder="例）"><?= h($halfYearGoals['achievement']) ?></textarea>
                    <div class="goal-hint">収入を得るために必要な実績・成果を書いてください</div>
                </div>

                <!-- スキル -->
                <div class="goal-item">
                    <label>🎓 スキル</label>
                    <textarea name="skill" placeholder="例）"><?= h($halfYearGoals['skill']) ?></textarea>
                    <div class="goal-hint">実績を作るために必要なスキルを書いてください</div>
                </div>

                <!-- 習慣 -->
                <div class="goal-item">
                    <label>⏰ 習慣（週○時間）</label>
                    <textarea name="habit" placeholder="例）"><?= h($halfYearGoals['habit']) ?></textarea>
                    <div class="goal-hint">スキルを身につけるために必要な学習習慣を書いてください</div>
                </div>
            </div>

            <div class="hint" style="margin-top: 20px;">
                ※少なくとも1つの項目を記入してください。すべて埋める必要はありませんが、具体的に書くほど学習計画が立てやすくなります。
            </div>

            <div class="actions">
                <button type="submit" class="btn">完了</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
