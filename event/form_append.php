<?php
//最初にセッションを開始
session_start();

//関数ファイルの読み込み
include('funcs.php');

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/reset.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css">
    <title>イベント登録ページ</title>
</head>

<body>
    <form action="confirm.php" method="post" enctype="multipart/form-data">

        <div class="event_name">
            <p class="sub_title">イベント名（管理用）</p>
            <input type="text" name="event_name">
        </div>

        <div class="event_title">
            <p class="sub_title">イベントタイトル</p>
            <input type="text" name="title">
        </div>

        <div class="event_title_sub">
            <p class="sub_title">サブタイトル</p>
            <input type="text" name="subtitle">
        </div>

        <div class="event_header_image">
            <p class="sub_title">ヘッダー画像</p>
            <input type="file" name="header_image">
        </div>

        <div class="event_date">
            <p class="sub_title">開催日時</p>
        <input type="datetime-local" name="date">

        </div>

        <div class="event_location">
            <p class="sub_title">開催場所</p>
            <input type="text" name="location">
        </div>

        <div class="event_detail">
            <p class="sub_title">イベント詳細</p>
            <textarea id="achievement" name="detail"></textarea>
        </div>

        <div class="event_fee">
            <p class="sub_title">参加費</p>
            <input type="number" name="fee">
        </div>

        <div class="event_fee">
            <p class="sub_title">イベント申し込み期日</p>
            <input type="date" name="end_date">
        </div>

        <!-- 送信ボタン -->
        <input type="submit" value="送信" class="btn">
    </form>

</body>

</html>