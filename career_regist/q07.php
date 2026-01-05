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
 * 避けたい価値観（DBに入る値 => 表示ラベル）
 * ※「こういう状態だとしんどい/続かない」を選ばせる想定
 */
$options = [
    '過度な残業'       => '過度な残業（プライベートが消える）',
    '低い裁量'         => '低い裁量（決められない／任されない）',
    '不透明な評価'     => '不透明な評価（頑張りが報われない）',
    '人間関係ストレス' => '人間関係ストレス（気を遣いすぎる）',
    '単調・退屈'       => '単調・退屈（変化がなく飽きる）',
    '成長実感なし'     => '成長実感がない（スキルが伸びない）',
    '将来不安'         => '将来不安（先が見えない）',
    '収入が上がらない' => '収入が上がらない（生活/挑戦が難しい）',
    '価値観の不一致'   => '価値観の不一致（会社/上司の方針が合わない）',
    '自由がない'       => '自由がない（時間/場所/働き方が固定）',
    '責任が重すぎる'   => '責任が重すぎる（プレッシャー過多）',
    '社会的意義が薄い' => '社会的意義が薄い（やりがいが感じにくい）',
];

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

    // 既存の保存形式：「A, B / 補足：xxx」を想定して復元
    if ($existing && !empty($existing['values_not_want'])) {
        $raw = (string)$existing['values_not_want'];

        $note = '';
        $parts = explode('/ 補足：', $raw, 2);
        $listPart = trim($parts[0]);
        if (count($parts) === 2) {
            $note = trim($parts[1]);
        }

        if ($listPart !== '') {
            $tmp = array_map('trim', explode(',', $listPart));
            $selectedValues = array_values(array_filter($tmp, fn($v) => $v !== ''));
        }
        $valuesNote = $note;
    }

    // POST：保存して次へ
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // CSRFチェック
        $postedToken = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals($csrfToken, $postedToken)) {
            exit('Invalid CSRF token');
        }

        $selectedValues = $_POST['values_not_want'] ?? [];
        if (!is_array($selectedValues)) $selectedValues = [];

        $valuesNote = trim((string)($_POST['values_note'] ?? ''));

        // 想定外の値を除外
        $selectedValues = array_values(array_filter($selectedValues, function ($v) use ($options) {
            return is_string($v) && array_key_exists($v, $options);
        }));

        // 必須：1つ以上
        if (count($selectedValues) === 0) {
            $error = '少なくとも1つ選択してください。';
        } else {
            $saveText = implode(', ', $selectedValues);
            if ($valuesNote !== '') {
                $saveText .= ' / 補足：' . $valuesNote;
            }

            $pdo->beginTransaction();

            if ($existing) {
                $update = "UPDATE career_answers
                            SET values_not_want = :values_not_want,
                                updated_at = NOW()
                            WHERE id = :id AND session_id = :sid AND user_id = :uid";
                $stmt = $pdo->prepare($update);
                $stmt->bindValue(':values_not_want', $saveText, PDO::PARAM_STR);
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
                $stmt->bindValue(':values_not_want', $saveText, PDO::PARAM_STR);
                $stmt->execute();
            }

            // セッション進捗を次へ（current_step=8）
            $updSession = "UPDATE career_sessions
                            SET current_step = 8,
                                updated_at = NOW()
                            WHERE id = :sid AND user_id = :uid";
            $stmt = $pdo->prepare($updSession);
            $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $pdo->commit();

            header('Location: q08.php');
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
    <title>アンケート - Q7</title>
    <link rel="stylesheet" href="css/style.css" />
    <style>
        body { font-family: system-ui, -apple-system, "Noto Sans JP", sans-serif; background:#f6f7fb; margin:0; }
        .wrap { max-width:720px; margin:0 auto; padding:24px; }
        .card { background:#fff; border-radius:14px; padding:20px; box-shadow:0 6px 20px rgba(0,0,0,.06); }
        .qno { font-weight:700; color:#6b7280; margin-bottom:6px; }
        h1 { font-size:20px; margin:0 0 12px; }
        .desc { color:#6b7280; margin:0 0 16px; font-size:14px; line-height:1.6; }
        .err { background:#fff1f2; color:#9f1239; padding:10px 12px; border-radius:10px; margin-bottom:12px; }

        .grid { display:grid; grid-template-columns:1fr; gap:10px; margin: 12px 0 14px; }
        .opt {
            display:flex; align-items:center; gap:10px;
            padding:12px 12px; border:1px solid #e5e7eb; border-radius:12px;
            cursor:pointer; background:#fff;
        }
        .opt input { transform: scale(1.2); }
        textarea {
            width:100%;
            min-height: 120px;
            padding: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            font-size: 16px;
            box-sizing: border-box;
            resize: vertical;
        }
        .label { font-weight:700; display:block; margin: 8px 0 6px; }
        .actions { display:flex; justify-content:flex-end; margin-top:14px; }
        .btn { border:0; background:#111827; color:#fff; border-radius:12px; padding:12px 16px; font-weight:700; cursor:pointer; }
        .hint { font-size:13px; color:#6b7280; margin-top:8px; line-height:1.6; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="qno">Q7 / 価値観</div>
        <h1>避けたい状態（しんどい・続かない）を選んでください（複数選択可）</h1>
        <p class="desc">
            「これが続くとモチベが下がる／無理になる」を先に知っておくと、学習設計が安定します。<br>
            直感でOKです。
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
                            name="values_not_want[]"
                            value="<?= h($value) ?>"
                            <?= in_array($value, $selectedValues, true) ? 'checked' : '' ?>
                        />
                        <span><?= h($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <span class="label">補足（任意）</span>
            <textarea name="values_note" placeholder="例）人間関係：評価の圧が強い環境が苦手／自由がない：通勤が負担 など"><?= h($valuesNote) ?></textarea>
            <div class="hint">※補足は任意です。具体例があると、設計の精度が上がります。</div>

            <div class="actions">
                <button type="submit" class="btn">次へ</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
