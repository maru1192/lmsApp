<?php
declare(strict_types=1);
session_start();

// エラー表示（デバッグ用）
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
 * career_session_id を用意する関数（q01/q02と同じ）
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
                VALUES (:user_id, 'in_progress', 3)";
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
$selectedTimeSlots = [];

/**
 * 時間帯の選択肢（DBに入る値 => 表示ラベル）
 */
$options = [
    '平日朝' => '平日朝',
    '平日昼休み' => '平日昼休み',
    '平日夜' => '平日夜',
    '通勤中' => '通勤中',
    '土日午前' => '土日午前',
    '土日午後' => '土日午後',
    '土日夜' => '土日夜',
    '不定期' => '不定期',
];

try {
    // 既存回答（途中再開用）
    $sql = "SELECT id, study_time_slots
            FROM career_answers
            WHERE session_id = :sid AND user_id = :uid
            ORDER BY id DESC
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    // 既存の保存形式：カンマ区切りで復元
    if ($existing && !empty($existing['study_time_slots'])) {
        $raw = (string)$existing['study_time_slots'];
        $selectedTimeSlots = array_filter(explode(',', $raw));
    }

    // POST：保存して次へ
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // CSRFチェック
        $postedToken = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals($csrfToken, $postedToken)) {
            exit('Invalid CSRF token');
        }

        // checkboxは配列で来る
        $selectedTimeSlots = $_POST['study_time_slots'] ?? [];
        if (!is_array($selectedTimeSlots)) $selectedTimeSlots = [];

        // 選択肢のバリデーション（想定外の値を除外）
        $selectedTimeSlots = array_values(array_filter($selectedTimeSlots, function ($v) use ($options) {
            return is_string($v) && array_key_exists($v, $options);
        }));

        // 必須：1つ以上選択、最大3つまで
        if (count($selectedTimeSlots) === 0) {
            $error = '少なくとも1つ選択してください。';
        } elseif (count($selectedTimeSlots) > 3) {
            $error = '最大3つまで選択できます。';
        } else {
            $pdo->beginTransaction();

            // カンマ区切りで保存
            $timeSlotsValue = implode(',', $selectedTimeSlots);

            // career_answers があるなら UPDATE、なければ INSERT
            if ($existing) {
                $update = "UPDATE career_answers
                            SET study_time_slots = :study_time_slots
                            WHERE id = :id AND session_id = :sid AND user_id = :uid";
                $stmt = $pdo->prepare($update);
                $stmt->bindValue(':study_time_slots', $timeSlotsValue, PDO::PARAM_STR);
                $stmt->bindValue(':id', (int)$existing['id'], PDO::PARAM_INT);
                $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                // 途中から来た場合でも最低限INSERTできるようにする
                $insert = "INSERT INTO career_answers (session_id, user_id, study_time_slots)
                            VALUES (:sid, :uid, :study_time_slots)";
                $stmt = $pdo->prepare($insert);
                $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':study_time_slots', $timeSlotsValue, PDO::PARAM_STR);
                $stmt->execute();
            }

            // セッション進捗を次に（current_step=4）
            $updSession = "UPDATE career_sessions
                            SET current_step = 4
                            WHERE id = :sid AND user_id = :uid";
            $stmt = $pdo->prepare($updSession);
            $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $pdo->commit();

            header('Location: q04.php');
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
    <title>アンケート - Q3</title>
    <link rel="stylesheet" href="css/style.css" />
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="qno">Q3 / 学習時間</div>
        <h1>学習に使えそうな時間帯はどこですか？（最大3つ）</h1>
        <p class="desc">
            あなたの生活リズムに合わせた学習プランを提案するため、学習可能な時間帯を教えてください。
        </p>

        <?php if ($error): ?>
            <div class="err"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

            <div class="grid">
                <?php foreach ($options as $value => $label): ?>
                    <label class="opt">
                        <input
                            type="checkbox"
                            name="study_time_slots[]"
                            value="<?= h($value) ?>"
                            <?= in_array($value, $selectedTimeSlots) ? 'checked' : '' ?> />
                        <span><?= h($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="actions">
                <button type="submit" class="btn">次へ</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
