<?php

//XSS対策用エスケープ関数
//@param{string} エスケープ対象文字列
//@return{string} エスケープ後文字列
function h($s){
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

//DB接続用関数
//@param なし 
//@return{} 
function db_conn(){
try {
    $db_name = 'learning_app';
    $db_id = 'root';
    $db_pw = '';    //XAMPPの場合は不要
    $db_host = 'localhost';

    //Password:MAMP='root',XAMPP=''
    $pdo = new PDO("mysql:dbname=$db_name;charset=utf8;host=$db_host", $db_id, $db_pw);
    return $pdo;
} catch (PDOException $e) {
    exit('DBConnectError' . $e->getMessage());
}
}

//別ページにリダイレクトさせる関数
//@param リダイレクト先のファイル名（右側はデフォルト値）
//@return なし（ページ先にリダイレクト）
function redirect($to = 'form_append.php'){
    header("Location: {$to}");
    exit;
}

//Sessionチェック関数
//@param なし
//@return なし（不正アクセス時にexit()）
function sschk(){
if(!isset($_SESSION['chk_ssid'])||$_SESSION['chk_ssid'] != session_id()){
    exit('LOGIN ERROR');
} else {
    //Login成功時
    session_regenerate_id(true);
    $_SESSION['chk_ssid'] = session_id();
}
}

