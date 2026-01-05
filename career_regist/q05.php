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

// チェック済みの値（配列）
$selectedValues = [];
// 補足テキスト
$valuesNote = '';

/**
 * 価値観 選択肢（カテゴリーごとに分類）
 * 今の仕事で「好き／続けたい」要素
 */
$optionsGrouped = [
    '🎯 仕事のやりがいについて' => [
        '成果を正当に評価される' => '成果を正当に評価される',
        '変化や刺激がある' => '変化や刺激がある',
        '仕事の裁量権がある' => '仕事の裁量権がある',
        '成長実感が持てる' => '成長実感が持てる',
        '社会/顧客への貢献を感じる' => '社会/顧客への貢献を感じる',
        '目的/意義が明確な仕事' => '目的/意義が明確な仕事',
        '適切な目標設定' => '適切な目標設定',
        '希望する部署で働ける' => '希望する部署で働ける',
        '新しいことに挑戦できる' => '新しいことに挑戦できる',
        '専門性を磨ける' => '専門性を磨ける',
    ],
    '💼 業務・働き方について' => [
        '残業が少ない' => '残業が少ない',
        '休日がしっかり取れる' => '休日がしっかり取れる',
        '適切な人員配置' => '適切な人員配置',
        '効率的な業務' => '効率的な業務',
        '通勤がラク' => '通勤がラク',
        'リモートワークができる' => 'リモートワークができる',
        'オンオフが切り替えられる' => 'オンオフが切り替えられる',
        '有給休暇が取りやすい' => '有給休暇が取りやすい',
        '勤務時間に柔軟性がある' => '勤務時間に柔軟性がある',
        '休みが多い' => '休みが多い',
        '休みが規則的' => '休みが規則的',
    ],
    '💰 福利厚生・報酬について' => [
        '給与が適正' => '給与が適正',
        '昇給しやすい' => '昇給しやすい',
        '賞与/インセンティブが充実' => '賞与/インセンティブが充実',
        '福利厚生が充実している' => '福利厚生が充実している',
    ],
    '🤝 人間関係・社風について' => [
        '人間関係が良い' => '人間関係が良い',
        'フラットな関係性' => 'フラットな関係性',
        '上司の指導・支援が充実' => '上司の指導・支援が充実',
        '意思決定が速い' => '意思決定が速い',
        'ハラスメントがない' => 'ハラスメントがない',
        '感謝や称賛の文化' => '感謝や称賛の文化',
        '情報共有が活発' => '情報共有が活発',
        'チームワークを感じる' => 'チームワークを感じる',
        '評価が公平・透明' => '評価が公平・透明',
        '当事者意識が強い' => '当事者意識が強い',
        '風通しが良い' => '風通しが良い',
        '社内政治が少ない' => '社内政治が少ない',
        '変化を歓迎する体質' => '変化を歓迎する体質',
        'コンプライアンス意識が高い' => 'コンプライアンス意識が高い',
    ],
    '🚀 将来性について' => [
        '会社の経営が安定' => '会社の経営が安定',
        '業界の将来が明るい' => '業界の将来が明るい',
        'キャリアの可能性が広がる' => 'キャリアの可能性が広がる',
    ],
];

// バリデーション用：全選択肢を1次元配列に展開
$allOptions = [];
foreach ($optionsGrouped as $items) {
    $allOptions = array_merge($allOptions, $items);
}

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
        $selectedValues = array_values(array_filter($selectedValues, function ($v) use ($allOptions) {
            return is_string($v) && array_key_exists($v, $allOptions);
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
                $insert = "INSERT INTO 
                                career_answers (session_id, user_id, values_important, created_at, updated_at)
                            VALUES
                                (:sid, :uid, :values_important, NOW(), NOW())";
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

            header('Location: q06.php');
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
        textarea {
            min-height: 120px;
        }
        .label {
            margin: 8px 0 6px;
        }
        .opt {
            padding: 12px 12px;
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="qno">Q5 / 今の仕事</div>
        <h1>今の仕事で「好き／続けたい」と思う要素はなんですか？<br>（複数選択可）</h1>
        <p class="desc">
            あなたの価値観に合っている「大事にしたい要素」を選んでください。<br>
            これを活かして、学習の方向性や目標を一緒に考えていきます。
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
                                name="values_important[]"
                                value="<?= h($value) ?>"
                                <?= in_array($value, $selectedValues, true) ? 'checked' : '' ?>
                            />
                            <span><?= h($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <span class="label">補足（任意）</span>
            <textarea name="values_note" placeholder="例）成長：資格取得のサポートがある／仲間：チームの雰囲気が良い など"><?= h($valuesNote) ?></textarea>
            <div class="hint">※選択肢で伝えきれない具体的な状況があれば記入してください。</div>

            <div class="actions">
                <button type="submit" class="btn">次へ</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
