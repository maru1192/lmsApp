<?php
//設定読み込み（session_start()とh()関数はconfig.php経由でfunc.phpに含まれる）
require_once __DIR__ . '/../config.php';

// 日付フォーマット（関数を流用）
function format_event_date_jp($datetime)
{
    if (empty($datetime)) return ['', ''];
    $timestamp = strtotime($datetime);
    if ($timestamp === false) return ['', ''];

    $youbi = ['日', '月', '火', '水', '木', '金', '土'][date('w', $timestamp)];
    $dateStr = date('Y/m/d', $timestamp) . "({$youbi})";
    $timeStr = date('H:i', $timestamp) . '〜';

    return [$dateStr, $timeStr];
}

// ---- id 取得（/event_detail.php?id=1）----
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('idが不正です');
}

// ---- DB接続 ----
try {
    $pdo = new PDO('mysql:dbname=learning_app;charset=utf8;host=localhost', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    exit('DBConnectError:' . $e->getMessage());
}

// ---- 1件取得 ----
$stmt = $pdo->prepare("SELECT * FROM event_list WHERE id = :id");
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    http_response_code(404);
    exit('イベントが見つかりません');
}

// ---- 表示用データ整形 ----
$eventAtRaw = $event['event_at'] ?? '';
$endDateRaw = $event['end_date'] ?? '';
$eventTs = strtotime($eventAtRaw);
$endDateTs = strtotime($endDateRaw);
$nowTs = time();

// ステータス判定: 受付中 / 締切 / 終了
if ($eventTs && $eventTs < $nowTs) {
    $statusLabel = '終了';
    $statusClass = 'is-finished';
} elseif ($endDateTs && $endDateTs < $nowTs) {
    $statusLabel = '申し込み締切';
    $statusClass = 'is-closed';
} else {
    $statusLabel = '受付中';
    $statusClass = 'is-upcoming';
}

$month = $eventTs ? (int)date('n', $eventTs) : '';
$day   = $eventTs ? (int)date('j', $eventTs) : '';

[$dateStr, $timeStr] = format_event_date_jp($eventAtRaw);

// 募集期間（created_at → end_date を表示）
$createdAtRaw = $event['created_at'] ?? '';
$createdTs = strtotime($createdAtRaw);
$recruitStart = '';
$recruitEnd   = '';
if ($createdTs) {
    $youbi = ['日', '月', '火', '水', '木', '金', '土'][date('w', $createdTs)];
    $recruitStart = date('Y/m/d', $createdTs) . "({$youbi}) " . date('H:i', $createdTs) . '〜';
}
if ($endDateTs) {
    $youbi = ['日', '月', '火', '水', '木', '金', '土'][date('w', $endDateTs)];
    $recruitEnd = date('Y/m/d', $endDateTs) . "({$youbi}) " . date('H:i', $endDateTs);
}

// ヘッダー画像
$headerImage = trim($event['header_image'] ?? '');

// Googleカレンダー（テンプレURL）
$googleCalUrl = '#';
if ($eventTs) {
    $text = $event['title'] ?? 'イベント';
    $details = $event['detail'] ?? '';
    $location = $event['location'] ?? '';

    $start = date('Ymd\THis', $eventTs);
    $endTs = $eventTs + 60 * 60; // とりあえず+1時間（必要ならDBに終了時刻を持たせる）
    $end = date('Ymd\THis', $endTs);

    // ctzを付けて日本時間として扱いやすくする
    $googleCalUrl = "https://www.google.com/calendar/render?action=TEMPLATE"
        . "&text=" . urlencode($text)
        . "&details=" . urlencode($details)
        . "&location=" . urlencode($location)
        . "&dates={$start}/{$end}"
        . "&ctz=" . urlencode('Asia/Tokyo');
}

// ★共通レイアウト開始
require_once APP_ROOT . '/parts/layout_start.php';
?>


<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="css/reset.css" />
    <link rel="stylesheet" href="css/event_detail.css" />
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css">
    <title><?= h($event['title'] ?? 'イベント詳細') ?></title>
</head>

<body>
    <div class="page">
        <div class="layout">

            <!-- 左：メイン -->
            <main class="event_main">
                <section class="card">
                    <header class="cardHeader">
                        <div class="dateBadge">
                            <div class="badgeMonth"><?= h($month) ?>月</div>
                            <div class="badgeDay"><?= h($day) ?></div>
                        </div>

                        <div class="titleArea">
                            <h1 class="title"><?= h($event['title'] ?? '') ?></h1>
                            <h2 class="subtitle"><?= h($event['subtitle'] ?? '') ?></h2>
                        </div>
                    </header>

                    <hr class="divider" />

                    <div class="hero">
                        <?php if ($headerImage !== '' && file_exists(__DIR__ . '/' . $headerImage)): ?>
                            <img class="heroImg" src="<?= h($headerImage) ?>" alt="ヘッダー画像">
                        <?php else: ?>
                            <div class="heroPlaceholder">
                                <i class="far fa-image"></i>
                                <span>ヘッダー画像がありません</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="section">
                        <div class="sectionBar">
                            <i class="far fa-file-alt"></i>
                            <span>イベントの説明</span>
                        </div>

                        <div class="sectionBody">
                            <h2 class="sectionTitle">イベント概要</h2>
                            <p class="desc">
                                <?= nl2br(h($event['detail'] ?? '')) ?>
                            </p>
                        </div>
                    </div>
                </section>
            </main>

            <!-- 右：サイドバー -->
            <aside class="event-side">
                <section class="sideCard">
                    <div class="status <?= h($statusClass) ?>"><?= h($statusLabel) ?></div>

                    <div class="sideDate">
                        <div class="sideDateMain"><?= h($dateStr) ?></div>
                        <div class="sideTime"><?= h($timeStr) ?></div>
                    </div>

                    <div class="calendarLinks">
                        <a class="calLink" href="<?= h($googleCalUrl) ?>" target="_blank" rel="noopener">
                            <i class="far fa-calendar-plus"></i> Googleカレンダー
                        </a>
                    </div>

                    <div class="apply_area">
                        <a class="applyBtn" href="create_checkout.php?event_id=<?= h($id) ?>">
                            このイベントに申し込む
                        </a>
                    </div>

                    <div class="recruit">
                        <div class="recruitTitle">募集期間</div>
                        <div class="recruitDate">
                            <?= h($recruitStart) ?><br>
                            <?= h($recruitEnd) ?>
                        </div>
                    </div>

                    <a class="contactLink" href="mailto:info@example.com">
                        <i class="far fa-envelope"></i> イベントへのお問い合わせ
                    </a>
                </section>
            </aside>

        </div>
    </div>
<?php
require_once APP_ROOT . '/parts/layout_end.php';