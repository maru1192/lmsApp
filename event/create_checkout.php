<?php
// create_checkout.php
session_start();

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config/stripe.php';

// ログインしてる前提（あなたの実装に合わせて調整OK）
if(!isset($_SESSION['chk_ssid'])||$_SESSION['chk_ssid'] != session_id()){
    exit('LOGIN ERROR');
} else {
    //Login成功時
    session_regenerate_id(true);
    $_SESSION['chk_ssid'] = session_id();
}

// GETで event_id を受け取る（event_detail.php から飛ばす想定）
$eventId = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
if (!$eventId) {
    http_response_code(400);
    exit('event_idが不正です');
}

// DB接続（あなたのdb_conn()があるならそれ使ってOK）
try {
    $pdo = db_conn();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    exit('DBConnectError:' . $e->getMessage());
}

// イベント情報を取る（fee=円の整数）
$stmt = $pdo->prepare("SELECT id, title, fee FROM event_list WHERE id = :id");
$stmt->bindValue(':id', $eventId, PDO::PARAM_INT);
$stmt->execute();
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    http_response_code(404);
    exit('イベントが見つかりません');
}

// Stripe初期化
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

// user_id をセッションから取る（あなたのログイン実装に合わせて）
// まだuser_idをSESSIONに入れてないなら、いったん仮で name でもいいけど
// 決済者特定したいので本当は user_id を入れるのがベスト。
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(400);
    exit('user_idがセッションにありません（ログイン処理を確認してください）');
}

// Checkout Session作成
//ここに記載した内容で決済ページを作成するようにStripeに指示
$session = \Stripe\Checkout\Session::create([
    'mode' => 'payment',
    'payment_method_types' => ['card'],
    'line_items' => [[
        'quantity' => 1,
        'price_data' => [
            'currency' => 'jpy',
            'unit_amount' => (int)$event['fee'], // 円（例: 3000）
            'product_data' => [
                'name' => $event['title'],
            ],
        ],
    ]],

    // ✅ 重要：誰が何を買ったか識別するためにmetadataに入れる
    'metadata' => [
        'event_id' => (string)$event['id'],
        'user_id'  => $userId ? (string)$userId : '',
    ],

    // 戻り先
    'success_url' => APP_BASE_URL . '/success.php?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url'  => APP_BASE_URL . '/cancel.php?event_id=' . $event['id'],
]);

// ✅ ここで「pending」で1行作る（WebhookがUPDATEできるようにする）
$ins = $pdo->prepare("INSERT INTO event_payments
    (event_id, user_id, stripe_session_id, amount, currency, status, created_at)
  VALUES
    (:event_id, :user_id, :sid, :amount, :currency, 'pending', NOW())
");

$ins->execute([
    ':event_id' => (int)$event['id'],
    ':user_id'  => (int)$userId,
    ':sid'      => $session->id,
    ':amount'   => (int)$event['fee'], // とりあえずイベント金額
    ':currency' => 'jpy',
]);


// Stripeの決済ページへリダイレクト
header('Location: ' . $session->url);
exit;
