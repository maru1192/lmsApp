<?php
//最初にセッションを開始
session_start();

$lid = $_POST['lid'];   //lid
$lpw = $_POST['lpw'];   //lpw

//関数ファイルの読み込み
require_once __DIR__ . '/../event/func.php';

//2. DB接続します
$pdo = db_conn();
//３．データ登録SQL作成
//  ① SQL文を用意
$stmt = $pdo->prepare("SELECT * FROM user_table WHERE lid = :lid AND life_flg = 1");
$stmt->bindValue(':lid', $lid, PDO::PARAM_STR);
$status = $stmt->execute();


//  ④ データ登録処理後
if ($status === false) {
    //SQL実行時にエラーがある場合（エラーオブジェクト取得して表示）
    $error = $stmt->errorInfo();
    exit('ErrorMessage:' . $error[2]);
}

//抽出データ数を取得
$val = $stmt->fetch();  //1レコードだけを取得する方法

// ユーザーが存在しない場合
if ($val === false) {
    redirect('login.php');
}

//該当1レコードがあればSESSIONに値を代入
//入力したパスワードと暗号化されたパスワードを比較！[戻り値：rtrue(一致)｜false(不一致)]
$pw = password_verify($lpw, $val["lpw"]);   //$val["lpw"]:DBから取得した暗号化済みパスワード → password_verifyで照合
if($pw === true){
    //Login成功時
    $_SESSION['chk_ssid'] = session_id();
    $_SESSION['kanri_flg'] = $val["kanri_flg"];
    $_SESSION['name_sei'] = $val["name_sei"];
    $_SESSION['name_mei'] = $val["name_mei"];
    $_SESSION['user_id'] = (int)$val['id'];
    
    //リダイレクト（event_list.phpへ）
    redirect('../home.php');
}else{
    //Login失敗時
    redirect('login.php');
}


