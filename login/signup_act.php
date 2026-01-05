<?php
//最初にセッションを開始
session_start();

// エラー表示を有効化（デバッグ用）
ini_set('display_errors', 1);
error_reporting(E_ALL);

//関数ファイルの読み込み
require_once __DIR__ . '/../event/func.php';

//1. POSTデータ取得
$lastName = $_POST['lastName'] ?? '';
$firstName = $_POST['firstName'] ?? '';
$gender = $_POST['gender'] ?? '';
$birthdate = $_POST['birthdate'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

// 入力値チェック
if (empty($lastName) || empty($firstName) || empty($email) || empty($password)) {
    exit('必須項目が入力されていません');
}

//2. DB接続します
$pdo = db_conn();

//３．データ登録SQL作成

//パスワードをハッシュ保存（セキュリティ対策）
$hashed = password_hash($password, PASSWORD_DEFAULT);

// 1. SQL文を用意
$stmt = $pdo->prepare("INSERT INTO 
                            user_table(name_sei, name_mei, gender, birthday, lid, lpw, kanri_flg, life_flg) 
                        VALUES
                            (:lastName, :firstName, :gender, :birthdate, :email, :password, 0, 1)");


//  2. バインド変数を用意
// Integer 数値の場合 PDO::PARAM_INT
// String文字列の場合 PDO::PARAM_STR
$stmt->bindValue(':lastName', $lastName, PDO::PARAM_STR);
$stmt->bindValue(':firstName', $firstName, PDO::PARAM_STR);
$stmt->bindValue(':gender', $gender, PDO::PARAM_STR);
$stmt->bindValue(':birthdate', $birthdate, PDO::PARAM_STR);
$stmt->bindValue(':email', $email, PDO::PARAM_STR);
$stmt->bindValue(':password', $hashed, PDO::PARAM_STR);

//  3. 実行
$status = $stmt->execute();

//４．データ登録処理後
if ($status === false) {
    //SQL実行時にエラーがある場合（エラーオブジェクト取得して表示）
    $error = $stmt->errorInfo();
    exit('ErrorMessage:' . $error[2]);
} else {
    //５．リダイレクト（ログインページへ）
    redirect('../career_regist/index.php');
}
