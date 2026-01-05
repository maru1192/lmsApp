<?php
// success.php - 決済完了ページ
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

// セッションIDを取得
$sessionId = $_GET['session_id'] ?? '';

if (!$sessionId) {
    header('Location: event_list.php');
    exit;
}

// DB接続
try {
    $pdo = db_conn();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    exit('DBConnectError:' . $e->getMessage());
}

// 支払い情報とイベント情報を取得
$stmt = $pdo->prepare("
    SELECT 
        ep.*,
        el.title,
        el.subtitle,
        el.event_at,
        el.location,
        el.header_image
    FROM event_payments ep
    LEFT JOIN event_list el ON ep.event_id = el.id
    WHERE ep.stripe_session_id = :session_id
    LIMIT 1
");
$stmt->execute([':session_id' => $sessionId]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    header('Location: event_list.php');
    exit;
}

[$dateStr, $timeStr] = format_event_date_jp($payment['event_at'] ?? '');
$amountJpy = number_format($payment['amount'] ?? 0);
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/reset.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css">
    <title>お申し込み完了</title>
    <style>
        body {
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            /* background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); */
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

        .success-card {
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

        .success-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 30px;
            text-align: center;
            color: #fff;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            animation: checkmark 0.8s ease;
        }

        @keyframes checkmark {
            0% {
                transform: scale(0);
            }
            50% {
                transform: scale(1.2);
            }
            100% {
                transform: scale(1);
            }
        }

        .success-icon i {
            font-size: 40px;
        }

        .success-header h1 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .success-header p {
            font-size: 16px;
            opacity: 0.9;
        }

        .event-info {
            padding: 30px;
        }

        .event-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            color: #667eea;
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
            color: #667eea;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.5);
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
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            border-radius: 8px;
            font-size: 13px;
            color: #856404;
            line-height: 1.6;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="success-card">
            <div class="success-header">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h1>お申し込みが完了しました！</h1>
                <p>イベントへのお申し込みありがとうございます</p>
            </div>

            <div class="event-info">
                <div class="event-image">
                    <?php if (!empty($payment['header_image']) && file_exists(__DIR__ . '/' . $payment['header_image'])): ?>
                        <img src="<?= h($payment['header_image']) ?>" alt="<?= h($payment['title']) ?>">
                    <?php else: ?>
                        <div class="event-image-placeholder">
                            <i class="far fa-image"></i>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="event-title"><?= h($payment['title'] ?? 'イベント') ?></div>
                <?php if (!empty($payment['subtitle'])): ?>
                    <div class="event-subtitle"><?= h($payment['subtitle']) ?></div>
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

                <?php if (!empty($payment['location'])): ?>
                <div class="info-row">
                    <div class="info-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">開催場所</div>
                        <div class="info-value"><?= h($payment['location']) ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="info-row">
                    <div class="info-icon">
                        <i class="fas fa-yen-sign"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">お支払い金額</div>
                        <div class="info-value amount-highlight">¥<?= h($amountJpy) ?></div>
                    </div>
                </div>

                <?php if (!empty($payment['customer_email'])): ?>
                <div class="info-row">
                    <div class="info-icon">
                        <i class="far fa-envelope"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">確認メール送信先</div>
                        <div class="info-value"><?= h($payment['customer_email']) ?></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="notice">
                <i class="fas fa-info-circle"></i> 登録いただいたメールアドレス宛に、お申し込み完了メールをお送りしました。当日はメールに記載された内容をご確認の上、ご参加ください。
            </div>

            <div class="button-group">
                <a href="event_detail.php?id=<?= (int)$payment['event_id'] ?>" class="btn btn-secondary">
                    <i class="far fa-file-alt"></i>
                    詳細を確認
                </a>
                <a href="event_list.php" class="btn btn-primary">
                    <i class="fas fa-list"></i>
                    イベント一覧へ
                </a>
            </div>
        </div>
    </div>
</body>

</html>
