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
$jobStressNote = '';

/**
 * 避けたい価値観・状況（仕事でしんどい/モヤモヤすること）
 * カテゴリーごとに分類
 */
$optionsGrouped = [
    '🎯 仕事のやりがいについて' => [
        '成果が正当に評価されない' => '成果が正当に評価されない',
        '業務が単調' => '業務が単調',
        '仕事の裁量権がない' => '仕事の裁量権がない',
        '成長実感が持てない' => '成長実感が持てない',
        '社会/顧客への貢献を感じにくい' => '社会/顧客への貢献を感じにくい',
        '目的/意義が不明確な仕事が多い' => '目的/意義が不明確な仕事が多い',
        '目標が曖昧' => '目標が曖昧',
        '目標が高すぎる' => '目標が高すぎる',
        '希望しない部署にいる' => '希望しない部署にいる',
    ],
    '💼 業務・働き方について' => [
        '長時間労働が多い' => '長時間労働が多い',
        '休日出勤が多い' => '休日出勤が多い',
        '人員が不足している' => '人員が不足している',
        '無駄な業務が多い' => '無駄な業務が多い',
        '通勤が大変' => '通勤が大変',
        'リモートワークができない' => 'リモートワークができない',
        '勤務時間外に連絡が来る' => '勤務時間外に連絡が来る',
        '有給休暇が取りづらい' => '有給休暇が取りづらい',
        '勤務時間に柔軟性がない' => '勤務時間に柔軟性がない',
        '休みが少ない' => '休みが少ない',
        '休みが不定期' => '休みが不定期',
    ],
    '💰 福利厚生・報酬について' => [
        '給与が低い' => '給与が低い',
        '昇給しにくい' => '昇給しにくい',
        '賞与/インセンティブが少ない' => '賞与/インセンティブが少ない',
        '福利厚生が充実していない' => '福利厚生が充実していない',
    ],
    '🤝 人間関係・社風について' => [
        '人間関係が良くない' => '人間関係が良くない',
        '上下関係がキツイ' => '上下関係がキツイ',
        '上司の指導・支援が不十分' => '上司の指導・支援が不十分',
        '意思決定が遅い' => '意思決定が遅い',
        'ハラスメントがある' => 'ハラスメントがある',
        '感謝や称賛がない' => '感謝や称賛がない',
        '情報共有が不足している' => '情報共有が不足している',
        'チームワークを感じない' => 'チームワークを感じない',
        '評価が上司の主観で左右される' => '評価が上司の主観で左右される',
        '他責文化が強い' => '他責文化が強い',
        '風通しが悪い' => '風通しが悪い',
        '社内政治が強い' => '社内政治が強い',
        '変化を嫌う体質' => '変化を嫌う体質',
        'コンプライアンス意識が低い' => 'コンプライアンス意識が低い',
    ],
    '🚀 将来性について' => [
        '会社の経営が不安' => '会社の経営が不安',
        '業界の将来が不安' => '業界の将来が不安',
        '自分のキャリアの可能性が見えない' => '自分のキャリアの可能性が見えない',
    ],
];

// バリデーション用：全選択肢を1次元配列に展開
$allOptions = [];
foreach ($optionsGrouped as $items) {
    $allOptions = array_merge($allOptions, $items);
}

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

    // 既存の回答を復元（形式：「選択肢1,選択肢2 / 補足：xxx」）
    if ($existing && !empty($existing['values_not_want'])) {
        $raw = (string)$existing['values_not_want'];
        
        $note = '';
        $parts = explode(' / 補足：', $raw, 2);
        $listPart = trim($parts[0]);
        if (count($parts) === 2) {
            $note = trim($parts[1]);
        }

        if ($listPart !== '') {
            $tmp = array_map('trim', explode(',', $listPart));
            $selectedValues = array_values(array_filter($tmp, fn($v) => $v !== ''));
        }
        $jobStressNote = $note;
    }

    // POST：保存して次へ
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // CSRFチェック
        $postedToken = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals($csrfToken, $postedToken)) {
            exit('Invalid CSRF token');
        }

        $selectedValues = $_POST['job_stress'] ?? [];
        if (!is_array($selectedValues)) $selectedValues = [];

        $jobStressNote = trim((string)($_POST['job_stress_note'] ?? ''));

        // 想定外の値を除外
        $selectedValues = array_values(array_filter($selectedValues, function ($v) use ($allOptions) {
            return is_string($v) && array_key_exists($v, $allOptions);
        }));

        // バリデーション：選択肢または補足のどちらかが必須
        if (count($selectedValues) === 0 && $jobStressNote === '') {
            $error = '選択肢を選ぶか、補足欄に記入してください。';
        } else {
            // DB保存用（CSV + 補足）
            $saveText = '';
            if (count($selectedValues) > 0) {
                $saveText = implode(',', $selectedValues);
            }
            if ($jobStressNote !== '') {
                if ($saveText !== '') {
                    $saveText .= ' / 補足：' . $jobStressNote;
                } else {
                    $saveText = '補足：' . $jobStressNote;
                }
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
    <title>アンケート - Q7</title>
    <link rel="stylesheet" href="css/style.css" />
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="qno">Q7 / 今の仕事</div>
        <h1>今の仕事で「しんどい／モヤモヤする」ことは何かありますか？<br>（複数選択可）</h1>
        <p class="desc">
            あなたの価値観に合っていない「避けたい状況」を選んでください。<br>
            これを踏まえて、無理なく続けられる学習設計を考えていきます。
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
                                name="job_stress[]"
                                value="<?= h($value) ?>"
                                <?= in_array($value, $selectedValues, true) ? 'checked' : '' ?>
                            />
                            <span><?= h($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <span class="label">補足（任意）</span>
            <textarea name="job_stress_note"><?= h($jobStressNote) ?></textarea>
            <div class="hint">※選択肢で伝えきれない具体的な状況があれば記入してください。</div>

            <div class="actions">
                <button type="submit" class="btn">次へ</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>

