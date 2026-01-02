<?php
// cancel.php - 決済キャンセルページ
session_start();

// XSS対策
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// 日付フォーマット
function format_event_date_jp($datetime) {
    if (empty($datetime)) return ['', ''];
    $timestamp = strtotime($datetime);
    if ($timestamp === false) return ['', ''];
    
    $youbi = ['日', '月', '火', '水', '木', '金', '土'][date('w', $timestamp)];
    $dateStr = date('Y/m/d', $timestamp) . "({$youbi})";
    $timeStr = date('H:i', $timestamp) . '〜';
    
    return [$dateStr, $timeStr];
}

// イベントIDを取得
$eventId = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);

if (!$eventId) {
    header('Location: event_list.php');
    exit;
}

// DB接続
try {
    $pdo = new PDO('mysql:dbname=learning_app;charset=utf8;host=localhost', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    exit('DBConnectError:' . $e->getMessage());
}

// イベント情報を取得
$stmt = $pdo->prepare("SELECT * FROM event_list WHERE id = :id");
$stmt->execute([':id' => $eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    header('Location: event_list.php');
    exit;
}

[$dateStr, $timeStr] = format_event_date_jp($event['event_at'] ?? '');
$amountJpy = number_format($event['fee'] ?? 0);
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/reset.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css">
    <title>お申し込みキャンセル</title>
    <style>
        body {
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            /* background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); */
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            width: 100%;
        }

        .cancel-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .cancel-header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            padding: 40px 30px;
            text-align: center;
            color: #fff;
        }

        .cancel-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            animation: shake 0.8s ease;
        }

        @keyframes shake {
            0%, 100% {
                transform: translateX(0);
            }
            25% {
                transform: translateX(-10px);
            }
            75% {
                transform: translateX(10px);
            }
        }

        .cancel-icon i {
            font-size: 40px;
        }

        .cancel-header h1 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .cancel-header p {
            font-size: 16px;
            opacity: 0.9;
        }

        .event-info {
            padding: 30px;
        }

        .event-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .event-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .event-image-placeholder {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, 0.8);
            font-size: 48px;
        }

        .event-title {
            font-size: 22px;
            font-weight: 800;
            color: #111;
            margin-bottom: 8px;
        }

        .event-subtitle {
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-icon {
            width: 40px;
            height: 40px;
            background: #f8f9fa;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: #f5576c;
            font-size: 16px;
        }

        .info-content {
            flex: 1;
        }

        .info-label {
            font-size: 12px;
            color: #999;
            margin-bottom: 3px;
        }

        .info-value {
            font-size: 15px;
            font-weight: 600;
            color: #111;
        }

        .amount-highlight {
            color: #f5576c;
            font-size: 18px;
        }

        .button-group {
            padding: 20px 30px 30px;
            display: flex;
            gap: 12px;
        }

        .btn {
            flex: 1;
            padding: 14px 20px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: #fff;
            box-shadow: 0 4px 12px rgba(245, 87, 108, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(245, 87, 108, 0.5);
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #333;
        }

        .btn-secondary:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }

        .notice {
            margin: 20px 30px 0;
            padding: 15px;
            background: #e7f3ff;
            border-left: 4px solid #2196f3;
            border-radius: 8px;
            font-size: 13px;
            color: #0c5393;
            line-height: 1.6;
        }

        .reasons {
            margin: 0 30px 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
        }

        .reasons-title {
            font-size: 14px;
            font-weight: 700;
            color: #111;
            margin-bottom: 12px;
        }

        .reasons ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .reasons li {
            font-size: 13px;
            color: #666;
            padding: 8px 0;
            padding-left: 20px;
            position: relative;
        }

        .reasons li:before {
            content: "•";
            position: absolute;
            left: 0;
            color: #f5576c;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="cancel-card">
            <div class="cancel-header">
                <div class="cancel-icon">
                    <i class="fas fa-times"></i>
                </div>
                <h1>お申し込みがキャンセルされました</h1>
                <p>決済は完了していません</p>
            </div>

            <div class="event-info">
                <div class="event-image">
                    <?php if (!empty($event['header_image']) && file_exists(__DIR__ . '/' . $event['header_image'])): ?>
                        <img src="<?= h($event['header_image']) ?>" alt="<?= h($event['title']) ?>">
                    <?php else: ?>
                        <div class="event-image-placeholder">
                            <i class="far fa-image"></i>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="event-title"><?= h($event['title'] ?? 'イベント') ?></div>
                <?php if (!empty($event['subtitle'])): ?>
                    <div class="event-subtitle"><?= h($event['subtitle']) ?></div>
                <?php endif; ?>

                <div class="info-row">
                    <div class="info-icon">
                        <i class="far fa-calendar"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">開催日時</div>
                        <div class="info-value"><?= h($dateStr) ?> <?= h($timeStr) ?></div>
                    </div>
                </div>

                <?php if (!empty($event['location'])): ?>
                <div class="info-row">
                    <div class="info-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">開催場所</div>
                        <div class="info-value"><?= h($event['location']) ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="info-row">
                    <div class="info-icon">
                        <i class="fas fa-yen-sign"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">参加費用</div>
                        <div class="info-value amount-highlight">¥<?= h($amountJpy) ?></div>
                    </div>
                </div>
            </div>

            <div class="reasons">
                <div class="reasons-title">よくあるキャンセル理由</div>
                <ul>
                    <li>決済情報の入力を間違えた</li>
                    <li>他のイベントと比較検討したい</li>
                    <li>参加日程を再確認したい</li>
                    <li>参加人数を変更したい</li>
                </ul>
            </div>

            <div class="notice">
                <i class="fas fa-info-circle"></i> お申し込みをキャンセルされました。再度お申し込みをご希望の場合は、下記のボタンから詳細ページに戻り、もう一度お申し込み手続きを行ってください。
            </div>

            <div class="button-group">
                <a href="event_list.php" class="btn btn-secondary">
                    <i class="fas fa-list"></i>
                    イベント一覧へ
                </a>
                <a href="event_detail.php?id=<?= (int)$event['id'] ?>" class="btn btn-primary">
                    <i class="fas fa-redo"></i>
                    再度申し込む
                </a>
            </div>
        </div>
    </div>
</body>

</html>
