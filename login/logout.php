<?php
session_start();

//sessionを初期化
$_SESSION = array();

//Cookieに保存してあるsessionidの有効期限を過去にして破棄する
if(isset($_COOKIE[session_name()])){ //session_name()はPHPの組み込み関数
    setcookie(session_name(),'',time()-42000,'/');
}

//サーバー側でのsessionidの破棄
session_destroy();

//処理後ログインページにリダイレクト
header('Location: login.php');
exit();
?>