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
$selectedValues = [];
$valuesNote = '';

/**
 * 価値観の選択肢（DBに入る値 => 表示ラベル）
 */
$options = [
    '成長（上達）' => '成長（上達）',
    '挑戦（未知へ）' => '挑戦（未知へ）',
    '探究（本質理解）' => '探究（本質理解）',
    '創造（つくる）' => '創造（つくる）',
    '自由（選べる）' => '自由（選べる）',
    '自律（自分で決める）' => '自律（自分で決める）',
    '成果（結果）' => '成果（結果）',
    '卓越（品質）' => '卓越（品質）',
    'スピード（即決即実行）' => 'スピード（即決即実行）',
    '効率（ムダを減らす）' => '効率（ムダを減らす）',
    '収入（経済的豊かさ）' => '収入（経済的豊かさ）',
    '誠実（言行一致）' => '誠実（言行一致）',
    '信頼（長期関係）' => '信頼（長期関係）',
    '協調（チーム最適）' => '協調（チーム最適）',
    '貢献（役に立つ）' => '貢献（役に立つ）',
    '影響（波及を生む）' => '影響（波及を生む）',
    '安心・安定（リスク回避）' => '安心・安定（リスク回避）',
    '健康（持続可能）' => '健康（持続可能）',
    '意味・納得' => '意味・納得',
    '自分らしさ（ありのまま）' => '自分らしさ（ありのまま）',
];

try {
    // 既存回答（途中再開用）
    $sql = "SELECT id, core_values, core_values_note
            FROM career_answers
            WHERE session_id = :sid AND user_id = :uid
            ORDER BY id DESC
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    // 既存の回答を復元
    if ($existing) {
        // 選択肢の復元（カンマ区切り）
        if (!empty($existing['core_values'])) {
            $selectedValues = array_map('trim', explode(',', $existing['core_values']));
        }
        // 補足の復元（専用カラムから）
        if (!empty($existing['core_values_note'])) {
            $valuesNote = (string)$existing['core_values_note'];
        }
    }

    // POST：保存して次へ
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // CSRFチェック
        $postedToken = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals($csrfToken, $postedToken)) {
            exit('Invalid CSRF token');
        }

        // checkboxは配列で来る
        $selectedValues = $_POST['important_values'] ?? [];
        if (!is_array($selectedValues)) $selectedValues = [];

        // 補足
        $valuesNote = trim((string)($_POST['values_note'] ?? ''));

        // 選択肢のバリデーション（想定外の値を除外）
        $selectedValues = array_values(array_filter($selectedValues, function ($v) use ($options) {
            return is_string($v) && array_key_exists($v, $options);
        }));

        // 必須：1つ以上選択、または補足記入
        if (count($selectedValues) === 0 && $valuesNote === '') {
            $error = '価値観を1つ以上選択するか、補足を記入してください。';
        } elseif (mb_strlen($valuesNote) > 3000) {
            $error = '補足が長すぎます（3000文字以内）。';
        } else {
            $pdo->beginTransaction();

            // 選択肢はCSV、補足は別カラムに保存
            $valuesValue = count($selectedValues) > 0 ? implode(',', $selectedValues) : '';

            // career_answers があるなら UPDATE、なければ INSERT
            if ($existing) {
                $update = "UPDATE career_answers
                            SET core_values = :core_values,
                                core_values_note = :core_values_note,
                                updated_at = NOW()
                            WHERE id = :id AND session_id = :sid AND user_id = :uid";
                $stmt = $pdo->prepare($update);
                $stmt->bindValue(':core_values', $valuesValue, PDO::PARAM_STR);
                $stmt->bindValue(':core_values_note', $valuesNote, PDO::PARAM_STR);
                $stmt->bindValue(':id', (int)$existing['id'], PDO::PARAM_INT);
                $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $insert = "INSERT INTO 
                                career_answers (session_id, user_id, core_values, core_values_note, created_at, updated_at)
                            VALUES 
                                (:sid, :uid, :core_values, :core_values_note, NOW(), NOW())";
                $stmt = $pdo->prepare($insert);
                $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':core_values', $valuesValue, PDO::PARAM_STR);
                $stmt->bindValue(':core_values_note', $valuesNote, PDO::PARAM_STR);
                $stmt->execute();
            }

            // セッション進捗を次に進める（current_step=11）
            $updSession = "UPDATE career_sessions
                            SET current_step = 11,
                                updated_at = NOW()
                            WHERE id = :sid AND user_id = :uid";
            $stmt = $pdo->prepare($updSession);
            $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $pdo->commit();

            header('Location: q10.php');
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
    <title>アンケート - Q10</title>
    <link rel="stylesheet" href="css/style.css" />
    <style>
        .grid {
            grid-template-columns: repeat(2, 1fr);
        }
        textarea {
            min-height: 140px;
        }
        .label {
            margin: 8px 0 6px;
            padding-top: 20px;
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="qno">Q9 / 価値観</div>
        <h1>あなたが「大事にしたい価値観」は何ですか？</h1>
        <p class="desc">
            学習を続けるための軸になる価値観を明確にします。<br>
            当てはまるものを選び、補足があれば自由に記入してください（選択のみでもOK）
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
                            name="important_values[]"
                            value="<?= h($value) ?>"
                            <?= in_array($value, $selectedValues) ? 'checked' : '' ?> />
                        <span><?= h($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <span class="label">任意：上記で伝えきれない部分があれば記載してください！</span>
            <textarea
                name="values_note"
                placeholder="例）
・なぜその価値観を大事にしたいか
・過去にその価値観が満たされて嬉しかった経験
・満たされないと、どう感じるか"><?= h($valuesNote) ?></textarea>

            <div class="hint">
                選択肢だけでもOK、補足だけでもOKです。自分のペースで考えてみてください。
            </div>

            <div class="actions">
                <button type="submit" class="btn">次へ</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
