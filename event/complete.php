<?php
// complete.php
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登録完了</title>
    <style>
        body {
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            padding: 20px;
        }

        .box {
            max-width: 720px;
            margin: 0 auto;
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 16px;
        }

        a {
            display: inline-block;
            margin-top: 12px;
            margin-right: 8px;
            padding: 10px 20px;
            border: 1px solid #ddd;
            background: #f8f9fa;
            border-radius: 999px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            color: #333;
            transition: all 0.2s ease;
        }

        a:hover {
            background: #e9ecef;
            border-color: #adb5bd;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        a:active {
            transform: translateY(0);
            box-shadow: none;
        }
    </style>
</head>

<body>
    <div class="box">
        <h1>登録が完了しました ✅</h1>
        <p>イベント情報を登録しました。</p>
        <a href="form_append.php">入力ページへ戻る</a>
        <a href="event_list.php">イベント一覧を見る</a>

        <!-- 一覧ページがあるならここに導線を追加 -->
        <!-- <a href="result.php">一覧を見る</a> -->
    </div>
</body>

</html>