<?php
// write.php
session_start();

//別ページにリダイレクトさせる関数
//@param リダイレクト先のファイル名（右側はデフォルト値）
//@return なし（ページ先にリダイレクト）
function redirect($to = 'form_append.php')
{
    header("Location: {$to}");
    exit; //phpの処理を強制終了させる
}

// 直アクセス対策
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect();
}

// 値の取得
$event_name    = trim($_POST['event_name'] ?? '');
$title    = trim($_POST['title'] ?? '');
$subtitle = trim($_POST['subtitle'] ?? '');
$date     = trim($_POST['date'] ?? '');
$location = trim($_POST['location'] ?? '');
$detail   = trim($_POST['detail'] ?? '');
$fee      = trim($_POST['fee'] ?? '');
$tmpRel   = trim($_POST['tmp_header_image'] ?? '');
$end_date = trim($_POST['end_date'] ?? '');

// 追加バリデーション（最低限）
if ($title === '' || $date === '' || $location === '') {
    redirect();
}

// 画像を tmp から本番へ移動
$finalImageRel = ''; // uploads/headers/xxx.jpg
if ($tmpRel !== '') {
    // セッションの値と一致するか（改ざん対策）
    $sessionTmp = $_SESSION['tmp_header_image'] ?? '';
    if ($sessionTmp && hash_equals($sessionTmp, $tmpRel)) {
        $tmpAbs = __DIR__ . '/' . $tmpRel;

        if (is_file($tmpAbs)) {
            $uploadDir = __DIR__ . '/uploads/headers';

            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            // 拡張子はtmpファイル名から取得
            $ext = pathinfo($tmpAbs, PATHINFO_EXTENSION);
            $finalName = 'header_' . bin2hex(random_bytes(8)) . '.' . $ext;

            $finalAbs = $uploadDir . '/' . $finalName;
            if (rename($tmpAbs, $finalAbs)) {
                $finalImageRel = 'uploads/headers/' . $finalName;
            } else {
                // rename失敗時はコピー→削除でもOK（環境次第）
                if (copy($tmpAbs, $finalAbs)) {
                    unlink($tmpAbs);
                    $finalImageRel = 'uploads/headers/' . $finalName;
                }
            }
        }
    }
}

//2.  データベースに接続
try {
    //ID:'root', Password: xamppは 空白 ''
    $pdo = db_conn();
} catch (PDOException $e) {
    exit('DBConnectError:' . $e->getMessage());
}

//３．データ登録SQL作成
//  ① SQL文を用意
$stmt = $pdo->prepare("INSERT INTO 
                            event_list(event_name, title, subtitle, header_image, event_at, location, detail, fee, end_date) 
                        VALUES
                            (:event_name, :title, :subtitle, :header_image, :event_at, :location, :detail, :fee, :end_date)");


//  ② バインド変数を用意
// Integer 数値の場合 PDO::PARAM_INT
// String文字列の場合 PDO::PARAM_STR

$stmt->bindValue(':event_name', $event_name, PDO::PARAM_STR);
$stmt->bindValue(':title', $title, PDO::PARAM_STR);
$stmt->bindValue(':subtitle', $subtitle, PDO::PARAM_STR);
$stmt->bindValue(':header_image', $finalImageRel, PDO::PARAM_STR);
$stmt->bindValue(':event_at', $date, PDO::PARAM_STR);
$stmt->bindValue(':location', $location, PDO::PARAM_STR);
$stmt->bindValue(':detail', $detail, PDO::PARAM_STR);
$stmt->bindValue(':fee', is_numeric($fee) ? (int)$fee : null, PDO::PARAM_INT);
$stmt->bindValue(':end_date', $end_date, PDO::PARAM_STR);

//  ③ 実行
$status = $stmt->execute();

//  ④ データ登録処理後
if ($status === false) {
    //SQL実行時にエラーがある場合（エラーオブジェクト取得して表示）
    $error = $stmt->errorInfo();
    exit('ErrorMessage:' . $error[2]);
} else {
    // tmp情報は使い終わったのでクリア
    unset($_SESSION['tmp_header_image']);
    //  ⑤ complete.phpへリダイレクト
    redirect('complete.php');
}
