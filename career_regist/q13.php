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
$selectedObstacles = [];

/**
 * 学習の障害・不安（カテゴリーごとに分類）
 */
$obstaclesGrouped = [
    '⏰ 時間・生活リズム' => [
        '時間がない' => '時間がない',
        '仕事が忙しすぎる（残業・突発）' => '仕事が忙しすぎる（残業・突発）',
        '家庭の事情（育児・介護など）' => '家庭の事情（育児・介護など）',
        '予定が不規則で学習時間が固定できない' => '予定が不規則で学習時間が固定できない',
        '睡眠不足で夜に勉強できない' => '睡眠不足で夜に勉強できない',
        '移動が多く落ち着いて学べない' => '移動が多く落ち着いて学べない',
    ],
    '💪 体力・健康' => [
        '体力がない' => '体力がない',
        '体調を崩しやすい' => '体調を崩しやすい',
        'メンタルが不安定になりやすい' => 'メンタルが不安定になりやすい',
        '集中力が続かない' => '集中力が続かない',
    ],
    '📚 学習の進め方・設計' => [
        'やり方がわからない' => 'やり方がわからない',
        '何から始めればいいかわからない' => '何から始めればいいかわからない',
        '優先順位がつけられない' => '優先順位がつけられない',
        '計画を立てるのが苦手' => '計画を立てるのが苦手',
        '途中で詰まったときの解決手段がない' => '途中で詰まったときの解決手段がない',
        '学習のゴール（到達ライン）が曖昧' => '学習のゴール（到達ライン）が曖昧',
        '自分に合う学び方がわからない' => '自分に合う学び方がわからない',
    ],
    '🔄 継続・習慣化' => [
        '継続できない' => '継続できない',
        'モチベが上下する' => 'モチベが上下する',
        '三日坊主になりやすい' => '三日坊主になりやすい',
        '完璧主義で手が止まる' => '完璧主義で手が止まる',
        '先延ばし癖がある' => '先延ばし癖がある',
        '進捗が見えず不安になる' => '進捗が見えず不安になる',
        '自己管理（ルーティン化）が苦手' => '自己管理（ルーティン化）が苦手',
    ],
    '🎓 理解・スキル不安' => [
        '基礎が足りない気がする' => '基礎が足りない気がする',
        '学習スピードが遅くて不安' => '学習スピードが遅くて不安',
        '理解できないときに焦ってしまう' => '理解できないときに焦ってしまう',
        'つまずきを放置しがち' => 'つまずきを放置しがち',
        'アウトプット（課題制作）が苦手' => 'アウトプット（課題制作）が苦手',
        '実践に落とし込める自信がない' => '実践に落とし込める自信がない',
    ],
    '🤝 環境・サポート' => [
        '相談相手がいない' => '相談相手がいない',
        '一人だと続かない（伴走がほしい）' => '一人だと続かない（伴走がほしい）',
        '質問するのが苦手（遠慮してしまう）' => '質問するのが苦手（遠慮してしまう）',
        '周りに学習を邪魔されやすい（家族/職場など）' => '周りに学習を邪魔されやすい（家族/職場など）',
        '学習環境が整っていない（机・場所がない）' => '学習環境が整っていない（机・場所がない）',
        'PC/ネット環境が弱い' => 'PC/ネット環境が弱い',
    ],
    '🔍 情報過多・迷い' => [
        '迷いが多い' => '迷いが多い',
        '情報が多すぎて選べない' => '情報が多すぎて選べない',
        '他の教材に浮気してしまう' => '他の教材に浮気してしまう',
        '自分に向いているか不安' => '自分に向いているか不安',
        '目標設定に自信がない' => '目標設定に自信がない',
    ],
    '💭 対人・心理' => [
        '周りと比べて落ち込む' => '周りと比べて落ち込む',
        '失敗が怖い' => '失敗が怖い',
        '人に見られるのが苦手（発表・提出が不安）' => '人に見られるのが苦手（発表・提出が不安）',
        '自信がない（できる気がしない）' => '自信がない（できる気がしない）',
    ],
];

// バリデーション用：全選択肢を1次元配列に展開
$allObstacles = [];
foreach ($obstaclesGrouped as $items) {
    $allObstacles = array_merge($allObstacles, $items);
}

try {
    // 既存回答（途中再開用）
    $sql = "SELECT id, obstacles
            FROM career_answers
            WHERE session_id = :sid AND user_id = :uid
            ORDER BY id DESC
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    // 既存の回答を復元（CSV形式）
    if ($existing && !empty($existing['obstacles'])) {
        $raw = (string)$existing['obstacles'];
        $tmp = array_map('trim', explode(',', $raw));
        $selectedObstacles = array_values(array_filter($tmp, fn($v) => $v !== ''));
    }

    // POST：保存して次へ
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // CSRFチェック
        $postedToken = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals($csrfToken, $postedToken)) {
            exit('Invalid CSRF token');
        }

        $selectedObstacles = $_POST['obstacles'] ?? [];
        if (!is_array($selectedObstacles)) $selectedObstacles = [];

        // 想定外の値を除外
        $selectedObstacles = array_values(array_filter($selectedObstacles, function ($v) use ($allObstacles) {
            return is_string($v) && array_key_exists($v, $allObstacles);
        }));

        // バリデーション：少なくとも1つ選択
        if (count($selectedObstacles) === 0) {
            $error = '少なくとも1つ選択してください。';
        } else {
            // CSV形式で保存
            $saveText = implode(',', $selectedObstacles);

            $pdo->beginTransaction();

            if ($existing) {
                $update = "UPDATE career_answers
                            SET obstacles = :obstacles,
                                updated_at = NOW()
                            WHERE id = :id AND session_id = :sid AND user_id = :uid";
                $stmt = $pdo->prepare($update);
                $stmt->bindValue(':obstacles', $saveText, PDO::PARAM_STR);
                $stmt->bindValue(':id', (int)$existing['id'], PDO::PARAM_INT);
                $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $insert = "INSERT INTO career_answers (session_id, user_id, obstacles, created_at, updated_at)
                            VALUES (:sid, :uid, :obstacles, NOW(), NOW())";
                $stmt = $pdo->prepare($insert);
                $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':obstacles', $saveText, PDO::PARAM_STR);
                $stmt->execute();
            }

            // セッション進捗を完了へ（current_step=14）
            $updSession = "UPDATE career_sessions
                            SET current_step = 14,
                                status = 'completed',
                                updated_at = NOW()
                            WHERE id = :sid AND user_id = :uid";
            $stmt = $pdo->prepare($updSession);
            $stmt->bindValue(':sid', $careerSessionId, PDO::PARAM_INT);
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $pdo->commit();

            // thanks.phpへリダイレクト
            header('Location: thanks.php');
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
    <title>アンケート - Q13</title>
    <link rel="stylesheet" href="css/style.css" />
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="qno">Q13 / 学習の障害</div>
        <h1>半年後ゴールに向けて「いま感じている不安」を選んでください<br>（複数選択可）</h1>
        <p class="desc">
            学習を進める上で、あなたが感じている障害や不安を選んでください。<br>
            これを把握することで、具体的な対策を一緒に考えていきます。
        </p>

        <?php if ($error): ?>
            <div class="err"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

            <?php foreach ($obstaclesGrouped as $groupLabel => $items): ?>
                <h3 style="font-size: 16px; font-weight: bold; margin: 24px 0 12px; color: #111827;"><?= h($groupLabel) ?></h3>
                <div class="grid">
                    <?php foreach ($items as $value => $label): ?>
                        <label class="opt">
                            <input
                                type="checkbox"
                                name="obstacles[]"
                                value="<?= h($value) ?>"
                                <?= in_array($value, $selectedObstacles, true) ? 'checked' : '' ?>
                            />
                            <span><?= h($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div class="hint" style="margin-top: 20px;">
                ※正直に選んでください。障害を把握することで、あなたに最適な学習計画を設計できます。
            </div>

            <div class="actions">
                <button type="submit" class="btn">完了</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
