<?php
//最初にセッションを開始
session_start();

//XSS対策用エスケープ関数
function h($s){
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

//別ページにリダイレクトさせる関数
//@param リダイレクト先のファイル名（右側はデフォルト値）
//@return なし（ページ先にリダイレクト）
function redirect($to = 'form_append.php'){
    header("Location: {$to}");
    exit;
}

// 直アクセス対策
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(); // 入力ページへ
}

// 入力値取得（文字はtrim）
$event_name    = trim($_POST['event_name'] ?? '');
$title    = trim($_POST['title'] ?? '');
$subtitle = trim($_POST['subtitle'] ?? '');
$date     = trim($_POST['date'] ?? '');
$location = trim($_POST['location'] ?? '');
$detail   = trim($_POST['detail'] ?? '');
$fee      = trim($_POST['fee'] ?? '');
$end_date = trim($_POST['end_date'] ?? '');


// 簡易バリデーション
$errors = [];
if ($event_name === '')    $errors[] = 'イベント名は必須です。';
if ($title === '')    $errors[] = 'イベントタイトルは必須です。';
if ($date === '')     $errors[] = '開催日時は必須です。';
if ($location === '') $errors[] = '開催場所は必須です。';
if ($end_date === '') $errors[] = '申し込み期日は必須です。';
if (mb_strlen($title) > 100) {
    $errors[] = 'イベントタイトルは100文字以内で入力してください。';
}
if (mb_strlen($subtitle) > 100) {
    $errors[] = 'サブタイトルは100文字以内で入力してください。';
}

// 画像アップロード（確認ページで一旦 tmp/ に退避）
$tmpImageRel = '';     // hiddenで送る相対パス (tmp/xxxx.png)
$tmpImageAbs = '';     // 絶対パス
$previewUrl  = '';     // <img>用
$uploadError = '';

if (isset($_FILES['header_image']) && $_FILES['header_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $f = $_FILES['header_image'];


    if ($f['error'] !== UPLOAD_ERR_OK) {
        $uploadError = '画像のアップロードに失敗しました（error=' . $f['error'] . '）';
    } else {
        // サイズ制限（例：5MB）
        if ($f['size'] > 5 * 1024 * 1024) {
            $uploadError = '画像サイズが大きすぎます（5MBまで）。';
        } else {
            // MIMEチェック
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($f['tmp_name']);
            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
            ];
            if (!isset($allowed[$mime])) {
                $uploadError = '対応していない画像形式です（JPG/PNG/WEBPのみ）。';
            } else {
                $ext = $allowed[$mime];

                $tmpDir = __DIR__ . '/tmp';

                // tmpフォルダ作成（失敗チェック付き）
                if (!is_dir($tmpDir)) {
                    if (!mkdir($tmpDir, 0777, true)) {
                        $uploadError = 'tmpフォルダを作成できませんでした（権限/場所を確認してください）';
                    }
                }

                // 書き込み権限チェック
                if ($uploadError === '' && !is_writable($tmpDir)) {
                    $uploadError = 'tmpフォルダに書き込み権限がありません（権限を確認してください）';
                }

                if ($uploadError === '') {
                    $tmpName = 'header_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $tmpImageAbs = $tmpDir . '/' . $tmpName;

                    if (!move_uploaded_file($f['tmp_name'], $tmpImageAbs)) {
                        $uploadError = '画像の保存に失敗しました（tmpフォルダの権限/存在を確認してください）';
                    } else {
                        $tmpImageRel = 'tmp/' . $tmpName;
                        $previewUrl  = $tmpImageRel;
                        $_SESSION['tmp_header_image'] = $tmpImageRel;
                    }
                }
            }
        }
    }
}

// エラーがあれば表示して、登録ボタンは出さない
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>確認ページ</title>
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

        .row {
            margin: 12px 0;
        }

        .label {
            font-weight: 700;
            margin-bottom: 6px;
        }

        .val {
            white-space: pre-wrap;
            background: #f7f7f7;
            padding: 10px;
            border-radius: 8px;
        }

        .err {
            background: #fff3f3;
            border: 1px solid #f2b8b8;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 12px;
        }

        .btns {
            display: flex;
            gap: 10px;
            margin-top: 16px;
        }

        button,
        input[type="submit"] {
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid #ccc;
            background: #fff;
            cursor: pointer;
        }

        .primary {
            background: #111;
            color: #111;
            border-color: #111;
        }

        img {
            max-width: 100%;
            border-radius: 10px;
            border: 1px solid #eee;
        }
    </style>
</head>

<body>
    <div class="box">
        <h1>入力内容の確認</h1>

        <?php if (!empty($errors) || $uploadError !== ''): ?>
            <div class="err">
                <div style="font-weight:700;margin-bottom:6px;">入力エラーがあります</div>
                <ul style="margin:0;padding-left:18px;">
                    <?php foreach ($errors as $e): ?>
                        <li><?= h($e) ?></li>
                    <?php endforeach; ?>
                    <?php if ($uploadError !== ''): ?>
                        <li><?= h($uploadError) ?></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="btns">
                <button type="button" onclick="history.back()">戻って修正する</button>
            </div>

        <?php else: ?>
            <div class="row">
                <div class="label">イベント名</div>
                <div class="val"><?= h($event_name) ?></div>
            </div>

            <div class="row">
                <div class="label">イベントタイトル</div>
                <div class="val"><?= h($title) ?></div>
            </div>

            <div class="row">
                <div class="label">サブタイトル</div>
                <div class="val"><?= h($subtitle) ?></div>
            </div>

            <div class="row">
                <div class="label">ヘッダー画像</div>
                <div class="val">
                    <?php if ($previewUrl): ?>
                        <img src="<?= h($previewUrl) ?>" alt="header preview">
                    <?php else: ?>
                        （画像なし）
                    <?php endif; ?>
                </div>
            </div>

            <div class="row">
                <div class="label">開催日時</div>
                <div class="val"><?= h($date) ?></div>
            </div>

            <div class="row">
                <div class="label">開催場所</div>
                <div class="val"><?= h($location) ?></div>
            </div>

            <div class="row">
                <div class="label">イベント詳細</div>
                <div class="val"><?= h($detail) ?></div>
            </div>

            <div class="row">
                <div class="label">参加費</div>
                <div class="val"><?= h($fee) ?></div>
            </div>

            <div class="row">
                <div class="label">申し込み期日</div>
                <div class="val"><?= h($end_date) ?></div>
            </div>

            <!-- 登録確定（write.phpへ） -->
            <form action="write.php" method="post">
                <input type="hidden" name="event_name" value="<?= h($event_name) ?>">
                <input type="hidden" name="title" value="<?= h($title) ?>">
                <input type="hidden" name="subtitle" value="<?= h($subtitle) ?>">
                <input type="hidden" name="date" value="<?= h($date) ?>">
                <input type="hidden" name="location" value="<?= h($location) ?>">
                <input type="hidden" name="detail" value="<?= h($detail) ?>">
                <input type="hidden" name="fee" value="<?= h($fee) ?>">
                <input type="hidden" name="end_date" value="<?= h($end_date) ?>">

                <!-- tmpに置いた画像パス（改ざん対策でwrite.php側でセッション突合します） -->
                <input type="hidden" name="tmp_header_image" value="<?= h($tmpImageRel) ?>">

                <div class="btns">
                    <button type="button" onclick="history.back()">戻って修正する</button>
                    <input type="submit" value="この内容で登録する" class="primary">
                </div>
            </form>

        <?php endif; ?>
    </div>
</body>

</html>