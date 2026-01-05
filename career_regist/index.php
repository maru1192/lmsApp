<?php
session_start();

//関数ファイルの読み込み
require_once __DIR__ . '/../event/func.php';

// ログインチェック
sschk();

//DB接続
try {
    $pdo = db_conn();
} catch (PDOException $e) {
    exit('DBConnectError' . $e->getMessage());
}

//ユーザーIDを取得
$userId = (int)$_SESSION['user_id'];


try {
    // ① 進行中セッションを探す（最新1件）
    //IDを取得｜career_sessionsテーブルから｜statusが'in_progress'のもの｜最新1件を取得
    $sql = "SELECT id
            FROM career_sessions
            WHERE user_id = :user_id AND status = 'in_progress'
            ORDER BY id DESC
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    //連想配列で取得
    $row = $stmt->fetch(PDO::FETCH_ASSOC);


    if ($row) {
        // ② あればそれを使う
        $careerSessionId = (int)$row['id'];
    } else {
        // ③ なければ新規作成

        $stmt = $pdo->prepare("INSERT INTO 
                        career_sessions (user_id, status, current_step, started_at)
                    VALUES 
                        (:user_id, 'in_progress', 1, now())");

        //バインド変数を用意（プレースホルダーにデータを差し込み）
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $careerSessionId = (int)$pdo->lastInsertId();
    }

    // ④ PHPセッションに保存（以降はこれを使って紐付け）
    $_SESSION['career_session_id'] = $careerSessionId;

    // ⑤ 次の質問へ
    header('Location: q01.php');
    exit;

    //tryの中で例外が発生した場合はcatchへ → 処理を強制終了/エラーメッセージ表示
} catch (PDOException $e) {
    // 開発中は表示、公開時はログ推奨
    exit('DB error: ' . $e->getMessage());
}
