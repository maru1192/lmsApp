<?php
//設定読み込み（session_start()とh()関数はconfig.php経由でfunc.phpに含まれる）
require_once __DIR__ . '/../config.php';

// ログインチェック
sschk();

$userId = (int)$_SESSION['user_id'];

//1. DB接続
try {
    $pdo = new PDO('mysql:dbname=learning_app;charset=utf8;host=localhost', 'root', '');
} catch (PDOException $e) {
    exit('DBConnectError' . $e->getMessage());
}

//日付フォーマット用関数
function format_event_date_jp($datetime)
{
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

//２．ユーザーが申し込んだイベントを取得
// event_paymentsテーブルとevent_listテーブルをJOINして取得
$stmt = $pdo->prepare("
    SELECT 
        e.*,
        p.status as payment_status,
        p.amount,
        p.paid_at,
        p.created_at as applied_at
    FROM event_payments p
    INNER JOIN event_list e ON p.event_id = e.id
    WHERE p.user_id = :user_id
    ORDER BY p.created_at DESC
");

$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$status = $stmt->execute();

//３．データ表示
$appliedEvents = [];

if ($status === false) {
    $error = $stmt->errorInfo();
    exit("ErrorQuery:" . $error[2]);
} else {
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $appliedEvents[] = $result;
    }
}

// ★共通レイアウト開始
require_once APP_ROOT . '/parts/layout_start.php';
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>申込済みイベント一覧</title>

    <style>
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

        .page-header p {
            color: #666;
            font-size: 14px;
            margin-bottom: 24px;
        }

        .empty {
            color: #666;
            padding: 40px 20px;
            text-align: center;
            background: #f9fafb;
            border-radius: 12px;
            margin: 20px 0;
        }

        .empty p {
            margin: 8px 0;
        }

        .empty a {
            color: #111;
            font-weight: 600;
            text-decoration: underline;
        }

        /* イベントリスト（縦並び） */
        .events-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
            padding: 10px 0;
        }

        /* カード */
        .event-card {
            background: #fff;
            border-radius: var(--card-radius);
            box-shadow: var(--shadow);
            border: var(--border);
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            display: flex;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .event-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 1px 15px rgba(0, 0, 0, 0.12);
        }

        /* 画像部分 */
        .thumb {
            position: relative;
            width: 200px;
            flex-shrink: 0;
            background: #111;
            overflow: hidden;
        }

        .thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* コンテンツ部分 */
        .content {
            flex: 1;
            padding: 20px 24px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .event-name {
            font-size: 11px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .title {
            font-size: 18px;
            font-weight: 700;
            line-height: 1.4;
            margin: 4px 0;
        }

        .subtitle {
            font-size: 14px;
            color: #666;
            line-height: 1.5;
        }

        .meta {
            display: flex;
            gap: 16px;
            align-items: center;
            margin-top: 12px;
            font-size: 13px;
            color: #666;
        }

        .date-time {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .location {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* 支払いステータス */
        .payment-status {
            margin-top: auto;
            padding-top: 12px;
            border-top: 1px solid rgba(0, 0, 0, 0.06);
            display: flex;
            gap: 12px;
            align-items: center;
            font-size: 13px;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
        }

        .status-paid {
            background: #d1fae5;
            color: #065f46;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-failed {
            background: #fee2e2;
            color: #991b1b;
        }

        .applied-date {
            color: #999;
            font-size: 12px;
        }

        /* レスポンシブ */
        @media (max-width: 768px) {
            .event-card {
                flex-direction: column;
            }

            .thumb {
                width: 100%;
                height: 160px;
            }

            .content {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="page-header">
            <h1>申込済みイベント一覧</h1>
            <p>あなたが申し込んだイベントの一覧です。</p>
        </div>

        <?php if (empty($appliedEvents)): ?>
            <div class="empty">
                <p>まだイベントに申し込んでいません。</p>
                <p><a href="event_list.php">イベント一覧を見る</a></p>
            </div>
        <?php else: ?>
            <div class="events-list">
                <?php foreach ($appliedEvents as $event): ?>
                    <?php
                    list($dateStr, $timeStr) = format_event_date_jp($event['event_at']);
                    
                    // ステータス表示用
                    $statusClass = 'status-pending';
                    $statusText = '決済待ち';
                    if ($event['payment_status'] === 'completed' || $event['payment_status'] === 'paid') {
                        $statusClass = 'status-paid';
                        $statusText = '決済完了';
                    } elseif ($event['payment_status'] === 'failed' || $event['payment_status'] === 'canceled') {
                        $statusClass = 'status-failed';
                        $statusText = '決済失敗';
                    }
                    
                    // 申込日時
                    $appliedDate = date('Y/m/d H:i', strtotime($event['applied_at']));
                    ?>
                    
                    <a href="event_detail.php?id=<?= h($event['id']) ?>" class="event-card">
                        <div class="thumb">
                            <?php if (!empty($event['header_image'])): ?>
                                <img src="<?= h($event['header_image']) ?>" alt="<?= h($event['title']) ?>">
                            <?php else: ?>
                                <img src="https://placehold.co/400x300/1a1a1a/ffffff?text=No+Image" alt="No Image">
                            <?php endif; ?>
                        </div>
                        
                        <div class="content">
                            <div class="event-name"><?= h($event['event_name']) ?></div>
                            <div class="title"><?= h($event['title']) ?></div>
                            <div class="subtitle"><?= h($event['subtitle']) ?></div>
                            
                            <div class="meta">
                                <div class="date-time">
                                    📅 <?= h($dateStr) ?> <?= h($timeStr) ?>
                                </div>
                                <div class="location">
                                    📍 <?= h($event['location']) ?>
                                </div>
                            </div>
                            
                            <div class="payment-status">
                                <span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span>
                                <span class="applied-date">申込日: <?= h($appliedDate) ?></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
// ★共通レイアウト終了
require_once APP_ROOT . '/parts/layout_end.php';
?>
