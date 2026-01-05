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
    $pdo = new PDO('mysql:dbname=learning_app;charset=utf8mb4;host=localhost', 'root', '');
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

// 理想の姿（各項目）
$future3y = [
    'work' => '',
    'money' => '',
    'life' => '',
    'relationship' => '',
    'health' => '',
];
$future1y = [
    'work' => '',
    'money' => '',
    'life' => '',
    'relationship' => '',
    'health' => '',
];

try {
    // 既存回答（途中再開用）
    $sql = "SELECT id, future_vision
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
    if ($existing && !empty($existing['future_vision'])) {
        $data = json_decode($existing['future_vision'], true);
        if (is_array($data)) {
            if (isset($data['3y']) && is_array($data['3y'])) {
                $future3y = array_merge($future3y, $data['3y']);
            }
            if (isset($data['1y']) && is_array($data['1y'])) {
                $future1y = array_merge($future1y, $data['1y']);
            }
        }
    }

    // POST：保存して次へ
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // CSRFチェック
        $postedToken = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals($csrfToken, $postedToken)) {
            exit('Invalid CSRF token');
        }

        // 3年後の理想
        $future3y = [
            'work' => trim((string)($_POST['future_3y_work'] ?? '')),
            'money' => trim((string)($_POST['future_3y_money'] ?? '')),
            'life' => trim((string)($_POST['future_3y_life'] ?? '')),
            'relationship' => trim((string)($_POST['future_3y_relationship'] ?? '')),
            'health' => trim((string)($_POST['future_3y_health'] ?? '')),
        ];

        // 1年後の理想
        $future1y = [
            'work' => trim((string)($_POST['future_1y_work'] ?? '')),
            'money' => trim((string)($_POST['future_1y_money'] ?? '')),
            'life' => trim((string)($_POST['future_1y_life'] ?? '')),
            'relationship' => trim((string)($_POST['future_1y_relationship'] ?? '')),
            'health' => trim((string)($_POST['future_1y_health'] ?? '')),
        ];

        // バリデーション：3年後の少なくとも1項目が必須
        $has3yContent = false;
        foreach ($future3y as $val) {
            if ($val !== '') {
                $has3yContent = true;
                break;
            }
        }

        if (!$has3yContent) {
            $error = '3年後の理想について、少なくとも1つの項目を記入してください。';
        } else {
            // JSON形式で保存
            $saveData = json_encode([
                '3y' => $future3y,
                '1y' => $future1y,
            ], JSON_UNESCAPED_UNICODE);

            $pdo->beginTransaction();

            if ($existing) {
                $update = "UPDATE career_answers
                            SET future_vision = :future_vision,
                                updated_at = NOW()
                            WHERE id = :id AND session_id = :sid AND user_id = :uid";
                $stmt = $pdo->prepare($update);
                $stmt->bindValue(':future_vision', $saveData, PDO::PARAM_STR);
                $stmt->bindValue(':id', (int)$existing['id'], PDO::PARAM_INT);
                $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $insert = "INSERT INTO career_answers (session_id, user_id, future_vision, created_at, updated_at)
                            VALUES (:sid, :uid, :future_vision, NOW(), NOW())";
                $stmt = $pdo->prepare($insert);
                $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':future_vision', $saveData, PDO::PARAM_STR);
                $stmt->execute();
            }

            // セッション進捗を完了へ（current_step=12）
            $updSession = "UPDATE career_sessions
                            SET current_step = 12,
                                status = 'completed',
                                updated_at = NOW()
                            WHERE id = :sid AND user_id = :uid";
            $stmt = $pdo->prepare($updSession);
            $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $pdo->commit();

            // 完了ページへリダイレクト（仮にhome.phpとする）
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
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #111827;
            margin: 32px 0 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
        }
        .section-title:first-of-type {
            margin-top: 24px;
        }
        .future-item {
            margin-bottom: 20px;
        }
        .future-item label {
            display: block;
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 8px;
            color: #374151;
        }
        .future-item textarea {
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
        .arrow-down {
            text-align: center;
            font-size: 24px;
            color: #6b7280;
            margin: 24px 0;
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="qno">Q11 / 未来の理想</div>
        <h1>未来の理想をステップで描いてください</h1>
        <p class="desc">
            3年後→1年後という順番で、理想の姿を具体的に描いてください。<br>
            まず長期的な視点から考えることで、より明確な目標設定ができます。
        </p>

        <?php if ($error): ?>
            <div class="err"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

            <!-- 3年後の理想 -->
            <div class="section-title">📅 3年後の理想の姿を教えてください</div>

            <div class="future-item">
                <label>仕事</label>
                <textarea name="future_3y_work" placeholder="例）"><?= h($future3y['work']) ?></textarea>
            </div>

            <div class="future-item">
                <label>お金</label>
                <textarea name="future_3y_money" placeholder="例）"><?= h($future3y['money']) ?></textarea>
            </div>

            <div class="future-item">
                <label>生活・環境</label>
                <textarea name="future_3y_life" placeholder="例）"><?= h($future3y['life']) ?></textarea>
            </div>

            <div class="future-item">
                <label>人間関係</label>
                <textarea name="future_3y_relationship" placeholder="例）"><?= h($future3y['relationship']) ?></textarea>
            </div>

            <div class="future-item">
                <label>健康・メンタル（心）</label>
                <textarea name="future_3y_health" placeholder="例）"><?= h($future3y['health']) ?></textarea>
            </div>

            <div class="arrow-down">↓</div>

            <!-- 1年後の理想 -->
            <div class="section-title">📅 1年後の理想の姿を教えてください</div>

            <div class="future-item">
                <label>仕事</label>
                <textarea name="future_1y_work" placeholder="例）"><?= h($future1y['work']) ?></textarea>
            </div>

            <div class="future-item">
                <label>お金</label>
                <textarea name="future_1y_money" placeholder="例）"><?= h($future1y['money']) ?></textarea>
            </div>

            <div class="future-item">
                <label>生活・環境</label>
                <textarea name="future_1y_life" placeholder="例）"><?= h($future1y['life']) ?></textarea>
            </div>

            <div class="future-item">
                <label>人間関係</label>
                <textarea name="future_1y_relationship" placeholder="例）"><?= h($future1y['relationship']) ?></textarea>
            </div>

            <div class="future-item">
                <label>健康・メンタル（心）</label>
                <textarea name="future_1y_health" placeholder="例）"><?= h($future1y['health']) ?></textarea>
            </div>

            <div class="hint" style="margin-top: 20px;">
                ※3年後の理想は必須です。1年後は任意ですが、記入することで段階的な目標設定ができます。
            </div>

            <div class="actions">
                <button type="submit" class="btn">完了</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
