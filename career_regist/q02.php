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
    $pdo = new PDO('mysql:dbname=learning_app;charset=utf8;host=localhost', 'root', '');
} catch (PDOException $e) {
    exit('DBConnectError' . $e->getMessage());
}

// ログインチェック
sschk();

$userId = (int)$_SESSION['user_id'];

/**
 * career_session_id を用意する関数（q01.phpと同じ）
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

// 画面で使う変数（初期値）
$workStartTime = '';
$workEndTime = '';
$workDays = [];               // 例: ['mon','tue',...]
$overtimeHoursMonth = '';
$weekendWorkCount = '';

$dayOptions = [
    'mon' => '月',
    'tue' => '火',
    'wed' => '水',
    'thu' => '木',
    'fri' => '金',
    'sat' => '土',
    'sun' => '日',
];

try {
    // 既存回答取得（途中再開用）
    $sql = "SELECT id, work_start_time, work_end_time, work_days, overtime_hours_month, weekend_work_count
            FROM career_answers
            WHERE session_id = :sid AND user_id = :uid
            ORDER BY id DESC
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // TIME は "HH:MM:SS" で来ることがあるので "HH:MM" に整形
        if (!empty($existing['work_start_time'])) {
            $workStartTime = substr((string)$existing['work_start_time'], 0, 5);
        }
        if (!empty($existing['work_end_time'])) {
            $workEndTime = substr((string)$existing['work_end_time'], 0, 5);
        }
        if (!empty($existing['work_days'])) {
            $workDays = array_filter(explode(',', (string)$existing['work_days']));
        }
        if ($existing['overtime_hours_month'] !== null && $existing['overtime_hours_month'] !== '') {
            $overtimeHoursMonth = (string)$existing['overtime_hours_month'];
        }
        if ($existing['weekend_work_count'] !== null && $existing['weekend_work_count'] !== '') {
            $weekendWorkCount = (string)$existing['weekend_work_count'];
        }
    }

    // POST：保存して次へ
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // CSRFチェック
        $postedToken = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals($csrfToken, $postedToken)) {
            exit('Invalid CSRF token');
        }

        // 入力取得
        $workStartTime = (string)($_POST['work_start_time'] ?? '');
        $workEndTime = (string)($_POST['work_end_time'] ?? '');
        $workDays = (array)($_POST['work_days'] ?? []);
        $overtimeHoursMonth = (string)($_POST['overtime_hours_month'] ?? '');
        $weekendWorkCount = (string)($_POST['weekend_work_count'] ?? '');

        // 正規化
        $workDays = array_values(array_intersect($workDays, array_keys($dayOptions)));
        $workDaysCsv = implode(',', $workDays);

        // バリデーション（MVP用：ゆるめ）
        if ($workStartTime === '' || $workEndTime === '') {
            $error = '勤務時間（開始・終了）を入力してください。';
        } elseif (count($workDays) === 0) {
            $error = '勤務日を選択してください。';
        } else {
            // 数値系（空ならNULLにする）
            $overtimeVal = ($overtimeHoursMonth === '') ? null : (float)$overtimeHoursMonth;
            $weekendVal  = ($weekendWorkCount === '') ? null : (int)$weekendWorkCount;

            // TIME型に入れるため "HH:MM" → "HH:MM:00"
            $startForDb = $workStartTime . ':00';
            $endForDb   = $workEndTime . ':00';

            $pdo->beginTransaction();

            // career_answers があるなら UPDATE、なければ INSERT
            if ($existing) {
                $update = "UPDATE career_answers
                            SET work_start_time = :work_start_time,
                                work_end_time = :work_end_time,
                                work_days = :work_days,
                                overtime_hours_month = :overtime_hours_month,
                                weekend_work_count = :weekend_work_count,
                                updated_at = NOW()
                            WHERE id = :id AND session_id = :sid AND user_id = :uid";
                $stmt = $pdo->prepare($update);
                $stmt->bindValue(':work_start_time', $startForDb, PDO::PARAM_STR);
                $stmt->bindValue(':work_end_time', $endForDb, PDO::PARAM_STR);
                $stmt->bindValue(':work_days', $workDaysCsv, PDO::PARAM_STR);

                // NULLを正しく入れる
                if ($overtimeVal === null) {
                    $stmt->bindValue(':overtime_hours_month', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':overtime_hours_month', $overtimeVal, PDO::PARAM_STR);
                }

                if ($weekendVal === null) {
                    $stmt->bindValue(':weekend_work_count', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':weekend_work_count', $weekendVal, PDO::PARAM_INT);
                }

                $stmt->bindValue(':id', (int)$existing['id'], PDO::PARAM_INT);
                $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $insert = "INSERT INTO career_answers
                            (session_id, user_id, work_start_time, work_end_time, work_days, overtime_hours_month, weekend_work_count, created_at, updated_at)
                            VALUES
                            (:sid, :uid, :work_start_time, :work_end_time, :work_days, :overtime_hours_month, :weekend_work_count, NOW(), NOW())";
                $stmt = $pdo->prepare($insert);
                $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':work_start_time', $startForDb, PDO::PARAM_STR);
                $stmt->bindValue(':work_end_time', $endForDb, PDO::PARAM_STR);
                $stmt->bindValue(':work_days', $workDaysCsv, PDO::PARAM_STR);

                if ($overtimeVal === null) {
                    $stmt->bindValue(':overtime_hours_month', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':overtime_hours_month', $overtimeVal, PDO::PARAM_STR);
                }

                if ($weekendVal === null) {
                    $stmt->bindValue(':weekend_work_count', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':weekend_work_count', $weekendVal, PDO::PARAM_INT);
                }

                $stmt->execute();
            }

            // セッション進捗を次に（current_step=3）
            $updSession = "UPDATE career_sessions
                            SET current_step = 3,
                                updated_at = NOW()
                            WHERE id = :sid AND user_id = :uid";
            $stmt = $pdo->prepare($updSession);
            $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $pdo->commit();

            header('Location: q03.php');
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
    <title>アンケート - Q2</title>
    <link rel="stylesheet" href="css/style.css" />
    <style>
        .days { display:flex; flex-wrap:wrap; gap:10px; }
        .day {
            display:flex; align-items:center; gap:8px;
            padding:10px 12px; border:1px solid #e5e7eb; border-radius:12px;
        }
        .row { display:flex; gap:12px; }
        .col { flex:1; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="qno">Q2 / 勤務状況</div>
        <h1>現在の勤務状況を教えてください</h1>
        <p class="desc">
            学習に使える時間を見積もるため、勤務時間や残業の目安を把握します。ざっくりでOKです。
        </p>

        <?php if ($error): ?>
            <div class="err"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

            <div class="field">
                <span class="label">勤務時間（開始・終了）</span>
                <div class="row">
                    <div class="col">
                        <input type="time" name="work_start_time" value="<?= h($workStartTime) ?>" required>
                    </div>
                    <div class="col">
                        <input type="time" name="work_end_time" value="<?= h($workEndTime) ?>" required>
                    </div>
                </div>
            </div>

            <div class="field">
                <span class="label">勤務日（複数選択）</span>
                <div class="days">
                    <?php foreach ($dayOptions as $val => $lab): ?>
                        <label class="day">
                            <input
                                type="checkbox"
                                name="work_days[]"
                                value="<?= h($val) ?>"
                                <?= in_array($val, $workDays, true) ? 'checked' : '' ?>>
                            <span><?= h($lab) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="field">
                <span class="label">月の残業時間（目安）</span>
                <input type="number" name="overtime_hours_month" inputmode="decimal" step="0.5" min="0"
                        value="<?= h($overtimeHoursMonth) ?>" placeholder="例：10">
            </div>

            <div class="field">
                <span class="label">土日祝の出勤回数（目安 / 月）</span>
                <input type="number" name="weekend_work_count" step="1" min="0"
                        value="<?= h($weekendWorkCount) ?>" placeholder="例：0">
            </div>

            <div class="actions">
                <button type="submit" class="btn">次へ</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
