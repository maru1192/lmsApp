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
$selectedPatterns = [];
$motivationNote = '';

/**
 * 挫折パターンの選択肢（カテゴリーごとに分類）
 */
$optionsGrouped = [
    '🎯 目標・計画が原因（設計ミス）' => [
        '曖昧な目標で迷う（ゴールが見えず不安になる）' => '曖昧な目標で迷う（ゴールが見えず不安になる）',
        '手順が分からず停止（次に何をやればいいか迷子になる）' => '手順が分からず停止（次に何をやればいいか迷子になる）',
        '計画倒れで挫折（スケジュールが現実的じゃない）' => '計画倒れで挫折（スケジュールが現実的じゃない）',
        '期限がないと先延ばし（締切がないと動けない）' => '期限がないと先延ばし（締切がないと動けない）',
    ],
    '🔄 実行・習慣が原因（続かない／日常に負ける）' => [
        '忙しさに流されて後回し（優先順位が下がる）' => '忙しさに流されて後回し（優先順位が下がる）',
        '習慣化できず空白が空く（1回抜けると戻れない）' => '習慣化できず空白が空く（1回抜けると戻れない）',
        '最初に詰め込みすぎて燃え尽き（スタートダッシュで力尽きる）' => '最初に詰め込みすぎて燃え尽き（スタートダッシュで力尽きる）',
        'やらされ感で続かない（義務感だけで取り組む）' => 'やらされ感で続かない（義務感だけで取り組む）',
    ],
    '⚠️ つまずき対応が原因（詰まって止まる）' => [
        '一人で抱え込んで挫折（質問できず詰まる）' => '一人で抱え込んで挫折（質問できず詰まる）',
        'つまずきを放置して離脱（小さなエラーで止まったまま）' => 'つまずきを放置して離脱（小さなエラーで止まったまま）',
        '質問の仕方が分からない（何をどう聞けばいいか不明）' => '質問の仕方が分からない（何をどう聞けばいいか不明）',
        '難易度が合わず挫折（簡単すぎor難しすぎ）' => '難易度が合わず挫折（簡単すぎor難しすぎ）',
    ],
    '💭 心理・自信が原因（怖さ／比較／完璧主義）' => [
        '完璧主義で進まない（100%を求めて手が止まる）' => '完璧主義で進まない（100%を求めて手が止まる）',
        '比較して自信を失う（他人と比べて落ち込む）' => '比較して自信を失う（他人と比べて落ち込む）',
        'アウトプットが怖い（提出・公開が不安で止まる）' => 'アウトプットが怖い（提出・公開が不安で止まる）',
        '成果が見えず不安（進捗実感がない）' => '成果が見えず不安（進捗実感がない）',
    ],
    '🏠 環境・コンディションが原因（物理的な要因）' => [
        '生活リズムが崩れて停滞（睡眠不足で集中できない）' => '生活リズムが崩れて停滞（睡眠不足で集中できない）',
        '体調・メンタルの波で中断（気分が落ちると止まる）' => '体調・メンタルの波で中断（気分が落ちると止まる）',
    ],
];

// バリデーション用：全選択肢を1次元配列に展開
$allOptions = [];
foreach ($optionsGrouped as $items) {
    $allOptions = array_merge($allOptions, $items);
}

try {
    // 既存回答（途中再開用）
    $sql = "SELECT id, failure_patterns, failure_patterns_note
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
        if (!empty($existing['failure_patterns'])) {
            $selectedPatterns = array_map('trim', explode(',', $existing['failure_patterns']));
        }
        // 補足の復元（専用カラムから）
        if (!empty($existing['failure_patterns_note'])) {
            $motivationNote = (string)$existing['failure_patterns_note'];
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
        $selectedPatterns = $_POST['failure_patterns'] ?? [];
        if (!is_array($selectedPatterns)) $selectedPatterns = [];

        // 補足
        $motivationNote = trim((string)($_POST['motivation_note'] ?? ''));

        // 選択肢のバリデーション（想定外の値を除外）
        $selectedPatterns = array_values(array_filter($selectedPatterns, function ($v) use ($allOptions) {
            return is_string($v) && array_key_exists($v, $allOptions);
        }));

        // 必須：1つ以上選択、または補足記入
        if (count($selectedPatterns) === 0 && $motivationNote === '') {
            $error = '挫折パターンを1つ以上選択するか、補足を記入してください。';
        } elseif (mb_strlen($motivationNote) > 3000) {
            $error = '補足が長すぎます（3000文字以内）。';
        } else {
            $pdo->beginTransaction();

            // 選択肢はCSV、補足は別カラムに保存
            $patternsValue = count($selectedPatterns) > 0 ? implode(',', $selectedPatterns) : '';

            // career_answers があるなら UPDATE、なければ INSERT
            if ($existing) {
                $update = "UPDATE career_answers
                            SET failure_patterns = :failure_patterns,
                                failure_patterns_note = :failure_patterns_note,
                                updated_at = NOW()
                            WHERE id = :id AND session_id = :sid AND user_id = :uid";
                $stmt = $pdo->prepare($update);
                $stmt->bindValue(':failure_patterns', $patternsValue, PDO::PARAM_STR);
                $stmt->bindValue(':failure_patterns_note', $motivationNote, PDO::PARAM_STR);
                $stmt->bindValue(':id', (int)$existing['id'], PDO::PARAM_INT);
                $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $insert = "INSERT INTO 
                                career_answers (session_id, user_id, failure_patterns, failure_patterns_note, created_at, updated_at)
                            VALUES 
                                (:sid, :uid, :failure_patterns, :failure_patterns_note, NOW(), NOW())";
                $stmt = $pdo->prepare($insert);
                $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':failure_patterns', $patternsValue, PDO::PARAM_STR);
                $stmt->bindValue(':failure_patterns_note', $motivationNote, PDO::PARAM_STR);
                $stmt->execute();
            }

            // セッションを完了に更新（current_step=12, status=completed, completed_at設定）
            $updSession = "UPDATE career_sessions
                            SET current_step = 12,
                                status = 'completed',
                                completed_at = NOW(),
                                updated_at = NOW()
                            WHERE id = :sid AND user_id = :uid";
            $stmt = $pdo->prepare($updSession);
            $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $pdo->commit();

            header('Location: q11.php');
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
        <div class="qno">Q10 / 挫折パターン</div>
        <h1>過去に挫折したパターンを教えてください</h1>
        <p class="desc">
            自分の「うまくいかないパターン」を知ると、無理のない学習設計ができます。<br>
            当てはまるものを選び、補足があれば自由に記入してください（選択のみでもOK）
        </p>

        <?php if ($error): ?>
            <div class="err"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

            <?php foreach ($optionsGrouped as $groupLabel => $items): ?>
                <h3 style="font-size: 16px; font-weight: bold; margin: 24px 0 12px; color: #111827;"><?= h($groupLabel) ?></h3>
                <div class="grid">
                    <?php foreach ($items as $value => $label): ?>
                        <label class="opt">
                            <input
                                type="checkbox"
                                name="failure_patterns[]"
                                value="<?= h($value) ?>"
                                <?= in_array($value, $selectedPatterns) ? 'checked' : '' ?>
                            />
                            <span><?= h($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <span class="label">他にどんな状況だとモチベーションが下がりそうですか？</span>
            <textarea
                name="motivation_note"
                placeholder="例）
・具体的な状況や場面
・過去にそうなった時のエピソード
・それが起きると、どう感じるか"><?= h($motivationNote) ?></textarea>

            <div class="hint">
                選択肢だけでもOK、補足だけでもOKです。自分のペースで振り返ってみてください。
            </div>

            <div class="actions">
                <button type="submit" class="btn">次へ</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
