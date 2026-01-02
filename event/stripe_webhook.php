<?php
// stripe_webhook.php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config/stripe.php';

// 1) payload & signature
$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sigHeader,
        STRIPE_WEBHOOK_SECRET
    );
} catch (\UnexpectedValueException $e) {
    http_response_code(400);
    exit('Invalid payload');
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit('Invalid signature');
}

// 2) DB接続（あなたのdb_conn()があるならそれでOK）
$pdo = new PDO('mysql:dbname=learning_app;charset=utf8;host=localhost', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 3) event typeで分岐
switch ($event->type) {

    case 'checkout.session.completed':
        $session  = $event->data->object;

        $sid      = $session->id;
        $pi       = $session->payment_intent ?? null;
        $amount   = $session->amount_total ?? 0;
        $currency = $session->currency ?? null;
        $email    = $session->customer_details->email ?? null;

        $eventId  = (int)($session->metadata->event_id ?? 0);
        $userId   = (int)($session->metadata->user_id ?? 0);

        // ✅（任意）DB更新前ログは残してOK
        $chk = $pdo->prepare("
        SELECT id,event_id,user_id,amount,currency,status,payment_intent_id
        FROM event_payments
        WHERE stripe_session_id = :sid
        LIMIT 1
    ");
        $chk->execute([':sid' => $sid]);
        $before = $chk->fetch(PDO::FETCH_ASSOC);
        error_log("[webhook] before=" . json_encode($before, JSON_UNESCAPED_SLASHES) . "\n", 3, __DIR__ . "/logs/stripe_webhook.log");

        // ✅ ここから下を「UPDATE」ではなく「UPSERT」にする（←ここが挿入ポイント）
        $sql = "
    INSERT INTO event_payments
        (stripe_session_id, event_id, user_id, payment_intent_id, amount, currency, status, customer_email, paid_at)
        VALUES
        (:sid, :event_id, :user_id, :pi, :amount, :currency, 'paid', :email, NOW())
        ON DUPLICATE KEY UPDATE
        payment_intent_id = VALUES(payment_intent_id),
        amount           = VALUES(amount),
        currency         = VALUES(currency),
        status           = 'paid',
        customer_email   = VALUES(customer_email),
        paid_at          = NOW(),
        event_id         = IF(VALUES(event_id)=0, event_id, VALUES(event_id)),
        user_id          = IF(VALUES(user_id)=0,  user_id,  VALUES(user_id))
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':sid'      => $sid,
            ':event_id' => $eventId,
            ':user_id'  => $userId,
            ':pi'       => (string)($pi ?? ''),
            ':amount'   => (int)$amount,
            ':currency' => (string)($currency ?? 'jpy'),
            ':email'    => $email,
        ]);

        // ✅ rowCountログ（INSERTでもUPDATEでも増減が見れる）
        $rows = $stmt->rowCount();
        error_log("[webhook] DB rowCount={$rows} sid={$sid} eventId={$eventId} userId={$userId} pi={$pi} amount={$amount} currency={$currency}\n", 3, __DIR__ . "/logs/stripe_webhook.log");

        break;


    default:
        // 他のイベントは今は無視でOK
        break;
}

http_response_code(200);
echo 'ok';
