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

// チェック済みの値（配列）
$selectedValues = [];
// 補足テキスト
$valuesNote = '';

/**
 * 価値観 選択肢（DBに入る値 => 表示ラベル）
 * ※値は半角英数でも日本語でもOK。ここでは日本語で統一。
 */
$options = [
    '成長'       => '成長（学び続けたい／伸びていたい）',
    '挑戦'       => '挑戦（新しいことにトライしたい）',
    '自由'       => '自由（時間・場所・裁量がほしい）',
    '安定'       => '安定（収入・環境・将来の安心感）',
    '成果'       => '成果（結果で評価されたい）',
    '貢献'       => '貢献（誰かの役に立ちたい）',
    '仲間'       => '仲間（チームで協力したい）',
    '専門性'     => '専門性（スキルを極めたい）',
    'ワークライフ' => 'ワークライフ（家族/健康/余白を大切に）',
    '報酬'       => '報酬（高収入・単価アップ）',
    '社会性'     => '社会性（社会課題・意義を感じたい）',
    '創造性'     => '創造性（つくる/表現するのが好き）',
];

try {
    // 既存回答（途中再開用）
    $sql = "SELECT id, values_important
            FROM career_answers
            WHERE session_id = :sid AND user_id = :uid
            ORDER BY id DESC
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    // 既存の保存形式：「成長,自由 / 補足：xxx」を想定して復元
    if ($existing && !empty($existing['values_important'])) {
        $raw = (string)$existing['values_important'];

        // 補足があれば取り出す
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

        // checkboxは配列で来る
        $selectedValues = $_POST['values_important'] ?? [];
        if (!is_array($selectedValues)) $selectedValues = [];

        // 補足
        $valuesNote = trim((string)($_POST['values_note'] ?? ''));

        // 選択肢のバリデーション（想定外の値を除外）
        $selectedValues = array_values(array_filter($selectedValues, function ($v) use ($options) {
            return is_string($v) && array_key_exists($v, $options);
        }));

        // 必須：1つ以上選択
        if (count($selectedValues) === 0) {
            $error = '少なくとも1つ選択してください。';
        } else {
            // DB保存用（CSV + 補足）
            $saveText = implode(', ', $selectedValues);
            if ($valuesNote !== '') {
                $saveText .= ' / 補足：' . $valuesNote;
            }

            $pdo->beginTransaction();

            // career_answers があるなら UPDATE、なければ INSERT
            if ($existing) {
                $update = "UPDATE career_answers
                            SET values_important = :values_important,
                                updated_at = NOW()
                            WHERE id = :id AND session_id = :sid AND user_id = :uid";
                $stmt = $pdo->prepare($update);
                $stmt->bindValue(':values_important', $saveText, PDO::PARAM_STR);
                $stmt->bindValue(':id', (int)$existing['id'], PDO::PARAM_INT);
                $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $insert = "INSERT INTO career_answers (session_id, user_id, values_important, created_at, updated_at)
                            VALUES (:sid, :uid, :values_important, NOW(), NOW())";
                $stmt = $pdo->prepare($insert);
                $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':values_important', $saveText, PDO::PARAM_STR);
                $stmt->execute();
            }

            // セッション進捗を次へ（current_step=7）
            $updSession = "UPDATE career_sessions
                            SET current_step = 7,
                                updated_at = NOW()
                            WHERE id = :sid AND user_id = :uid";
            $stmt = $pdo->prepare($updSession);
            $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $pdo->commit();

            header('Location: q07.php');
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
    <title>アンケート - Q6</title>
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
        <div class="qno">Q6 / 価値観</div>
        <h1>大事にしたい価値観を選んでください（複数選択可）</h1>
        <p class="desc">
            あなたの学習やキャリアの「優先順位」を一緒に整理するための質問です。<br>
            直感でOKなので、近いものを選んでください。
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
                            name="values_important[]"
                            value="<?= h($value) ?>"
                            <?= in_array($value, $selectedValues, true) ? 'checked' : '' ?>
                        />
                        <span><?= h($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <span class="label">補足（任意）</span>
            <textarea name="values_note" placeholder="例）自由：在宅で働きたい／挑戦：新規事業に関わりたい など"><?= h($valuesNote) ?></textarea>
            <div class="hint">※補足は任意です。選択した理由や具体例があると、後の最適化がしやすくなります。</div>

            <div class="actions">
                <button type="submit" class="btn">次へ</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
