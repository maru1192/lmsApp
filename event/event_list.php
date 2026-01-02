<?php
//最初にセッションを開始
session_start();


//XSS対策用エスケープ関数作成
function h($s){
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

//1.  DB接続します
try {
    //Password:MAMP='root',XAMPP=''
    $pdo = new PDO('mysql:dbname=learning_app;charset=utf8;host=localhost', 'root', '');
} catch (PDOException $e) {
    exit('DBConnectError' . $e->getMessage());
}

//日付フォーマット用関数
//@param{string} 日時文字列
//@return{array} [$dateStr, $timeStr] の 配列（2つセット）
function format_event_date_jp($datetime){
    if (empty($datetime)) {
        return ['', ''];
    }
    
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return ['', ''];
    }
    
    // 例: "12/31 (水)" + "14:00〜"
    $dateStr = date('n/j', $timestamp) . ' (' . ['日', '月', '火', '水', '木', '金', '土'][date('w', $timestamp)] . ')';
    $timeStr = date('H:i', $timestamp) . '〜';
    
    return [$dateStr, $timeStr];
}

//２．データ取得SQL作成
$stmt = $pdo->prepare("SELECT * FROM event_list ORDER BY event_at ASC");

$status = $stmt->execute();


//３．データ表示
$events = [];

if ($status === false) {
    //execute（SQL実行時にエラーがある場合）
    $error = $stmt->errorInfo();
    exit("ErrorQuery:" . $error[2]);
} else {
    //Selectデータの数だけ自動でループしてくれる
    //FETCH_ASSOC=http://php.net/manual/ja/pdostatement.fetch.php
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $events[] = $result;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>

    <style>
    /* event_list.css */

    :root {
    --card-radius: 18px;
    --shadow: 0 1px 15px rgba(0, 0, 0, 0.08);
    --border: 1px solid rgba(0, 0, 0, 0.06);
    }

    body {
    font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
    background: #ffffff;
    color: #111;
    }

    .page {
    max-width: 1100px;
    margin: 0 auto;
    padding: 28px 18px;
    }

    .page-header h1 {
    font-size: 22px;
    font-weight: 800;
    margin-bottom: 14px;
    }

    .empty {
    color: #666;
    padding: 10px 0;
    }

    /* 横スクロール行 */
    .events-row {
    display: flex;
    gap: 16px;
    overflow-x: auto;
    padding: 10px 6px 18px;
    scroll-snap-type: x mandatory;
    -webkit-overflow-scrolling: touch;
    }

    .events-row::-webkit-scrollbar {
    height: 10px;
    }
    .events-row::-webkit-scrollbar-thumb {
    background: rgba(0,0,0,0.12);
    border-radius: 999px;
    }
    .events-row::-webkit-scrollbar-track {
    background: rgba(0,0,0,0.04);
    border-radius: 999px;
    }

    /* カード */
    .event-card {
    width: 260px;
    flex: 0 0 260px;
    background: #fff;
    border-radius: var(--card-radius);
    box-shadow: var(--shadow);
    border: var(--border);
    overflow: hidden;
    scroll-snap-align: start;
    text-decoration: none;
    color: inherit;
    display: block;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .event-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 1px 15px rgba(0, 0, 0, 0.12);
    }

    /* 画像部分 */
    .thumb {
    position: relative;
    height: 140px;
    background: #111;
    overflow: hidden;
    }

    .thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    }

    .thumb-noimg {
    height: 100%;
    display: grid;
    place-items: center;
    color: rgba(255, 255, 255, 0.8);
    font-weight: 700;
    letter-spacing: 0.04em;
    background: linear-gradient(135deg, #111, #444);
    }

    /* 下の白い部分 */
    .card-body {
    padding: 12px 14px 14px;
    }

    /* イベント名（管理用）＋アイコン */
    .host {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
    }

    .avatar {
    width: 85px;
    height: 26px;
    border-radius: 999px;
    display: grid;
    place-items: center;
    background: rgba(0,0,0,0.06);
    color: rgba(0,0,0,0.65);
    font-weight: 800;
    font-size: 13px;
    }

    .host-name {
    font-size: 12px;
    color: rgba(0,0,0,0.65);
    font-weight: 700;
    }

    /* 日付 */
    .date-line {
    font-size: 16px;
    font-weight: 900;
    letter-spacing: -0.02em;
    line-height: 1.15;
    }

    .time-line {
    font-size: 12px;
    color: rgba(0,0,0,0.55);
    margin-top: 7px;
    }

    /* タイトル等 */
    .title {
    margin-top: 6px;
    font-size: 14px;
    font-weight: 800;
    color: rgba(0,0,0,0.86);
    }

    .subtitle {
    margin-top: 4px;
    font-size: 12px;
    color: rgba(0,0,0,0.6);
    line-height: 1.4;
    }

    /* 下のメタ（場所/料金） */
    .meta {
    margin-top: 10px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    }

    .pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 999px;
    background: rgba(0,0,0,0.05);
    color: rgba(0,0,0,0.7);
    font-size: 12px;
    font-weight: 700;
    }

    .pill i {
    font-size: 12px;
    }
    </style>
</head>

<body>
    <div class="page">
        <header class="page-header">
            <h1>イベント情報</h1>
        </header>

        <?php if (!$events): ?>
            <p class="empty">現在予定されているイベントはありません。</p>
        <?php else: ?>

            <div class="events-row" aria-label="イベント一覧">
                <?php foreach ($events as $ev): ?>
                    <?php
                    $img = trim((string)($ev['header_image'] ?? ''));
                    $eventName = trim((string)($ev['event_name'] ?? ''));
                    $title = (string)($ev['title'] ?? '');
                    $subtitle = (string)($ev['subtitle'] ?? '');

                    $dateParts = format_event_date_jp($ev['event_at'] ?? '');
                    $dateStr = $dateParts[0] ?? '';
                    $timeStr = $dateParts[1] ?? '';

                    // アイコン用（イベント名の頭文字）
                    $avatarChar = $eventName !== '' ? mb_substr($eventName, 0, 1) : 'E';
                    $eventId = (int)($ev['id'] ?? 0);
                    ?>

                    <a href="event_detail.php?id=<?= $eventId ?>" class="event-card">
                        <div class="thumb">
                            <?php if ($img !== ''): ?>
                                <img src="<?= h($img) ?>" alt="<?= h($title) ?>">
                            <?php else: ?>
                                <div class="thumb-noimg">No Image</div>
                            <?php endif; ?>

                            <button class="heart" type="button" aria-label="お気に入り">
                                <i class="far fa-heart"></i>
                            </button>
                        </div>

                        <div class="card-body">
                            <div class="host">
                                <div class="avatar" aria-hidden="true"><?= h($dateStr) ?></div>
                                <div class="host-name"><?= h($timeStr) ?></div>
                            </div>

                            <div class="date-line"><?= h($title) ?></div>
                            <div class="time-line"><?= h($subtitle) ?></div>

                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </div>
</body>

</html>