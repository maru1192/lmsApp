<?php
//設定読み込み（session_start()とh()関数はconfig.php経由でfunc.phpに含まれる）
require_once __DIR__ . '/../config.php';

//ログイン情報がなければログインページへリダイレクト
sschk();

//POSTで情報を受け取る
$date = date('Y/m/d H:i:s');
$achievement_rate = $_POST['speed'];
$achievement = $_POST['achievement'];
$emotions = $_POST['emotions'];
$emotionsText = implode(' / ', $emotions);
$thoughts = $_POST['thoughts'];

$hours_mon = $_POST['hours_mon'];
$hours_tue = $_POST['hours_tue'];
$hours_wed = $_POST['hours_wed'];
$hours_thu = $_POST['hours_thu'];
$hours_fri = $_POST['hours_fri'];
$hours_sat = $_POST['hours_sat'];
$hours_sun = $_POST['hours_sun'];
$hours_sum = $_POST['hours_sum'];

$curriculum_title = $_POST['curriculum_title'];
$original_goal = $_POST['original_goal'];
$mentor_consultation = $_POST['mentor_consultation'];


//2. DB接続
try {
    $pdo = new PDO('mysql:dbname=learning_app;charset=utf8;host=localhost', 'root', '');
} catch (PDOException $e) {
    exit('DBConnectError:' . $e->getMessage());
}


//３．データ登録SQL作成
// 1. SQL文を用意
$user_id = $_SESSION['user_id'];

//{weekly_reviews}テーブルにデータを挿入
$stmt = $pdo->prepare("INSERT INTO 
                            weekly_reviews(user_id, achievement_rate, achievement_text, thoughts, emotions, hours_mon, hours_tue, hours_wed, hours_thu, hours_fri, hours_sat, hours_sun, hours_sum, curriculum_title, original_goal, mentor_consultation, created_at) 
                        VALUES
                            (:user_id, :achievement_rate, :achievement, :thoughts, :emotionsText, :hours_mon, :hours_tue, :hours_wed, :hours_thu, :hours_fri, :hours_sat, :hours_sun, :hours_sum, :curriculum_title, :original_goal, :mentor_consultation, now())");

//{weekly_review_emotions}テーブルにデータを挿入


//  2. バインド変数を用意
// Integer 数値の場合 PDO::PARAM_INT
// String文字列の場合 PDO::PARAM_STR
$stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
$stmt->bindValue(':achievement_rate', $achievement_rate, PDO::PARAM_INT);
$stmt->bindValue(':achievement', $achievement, PDO::PARAM_STR);
$stmt->bindValue(':thoughts', $thoughts, PDO::PARAM_STR);
$stmt->bindValue(':emotionsText', $emotionsText, PDO::PARAM_STR);
$stmt->bindValue(':hours_mon', $hours_mon, PDO::PARAM_STR);
$stmt->bindValue(':hours_tue', $hours_tue, PDO::PARAM_STR);
$stmt->bindValue(':hours_wed', $hours_wed, PDO::PARAM_STR);
$stmt->bindValue(':hours_thu', $hours_thu, PDO::PARAM_STR);
$stmt->bindValue(':hours_fri', $hours_fri, PDO::PARAM_STR);
$stmt->bindValue(':hours_sat', $hours_sat, PDO::PARAM_STR);
$stmt->bindValue(':hours_sun', $hours_sun, PDO::PARAM_STR);
$stmt->bindValue(':hours_sum', $hours_sum, PDO::PARAM_STR);
$stmt->bindValue(':curriculum_title', $curriculum_title, PDO::PARAM_STR);
$stmt->bindValue(':original_goal', $original_goal, PDO::PARAM_STR);
$stmt->bindValue(':mentor_consultation', $mentor_consultation, PDO::PARAM_STR);

//  3. 実行
$status = $stmt->execute();

//４．データ登録処理後
if ($status === false) {
    //SQL実行時にエラーがある場合（エラーオブジェクト取得して表示）
    $error = $stmt->errorInfo();
    exit('ErrorMessage:' . $error[2]);
}

// ★共通レイアウト開始
require_once APP_ROOT . '/parts/layout_start.php';
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/reset.css">
    <link rel="stylesheet" href="css/write.css">

    <title>記録完了</title>
</head>

<body>
    <div class="swrite">
        <div class="swrite_inner">

            <div class="swrite_head">
                <div class="swrite_badge">✅ 記録が完了しました</div>
                <h1 class="swrite_title">学習の記録（送信内容）</h1>
                <p class="swrite_sub">送信された内容を保存しました。</p>
            </div>

            <!-- 今週の振り返り -->
            <section class="swrite_card">
                <div class="swrite_cardHead">
                    <h2 class="swrite_cardTitle">今週の振り返り</h2>
                    <span class="swrite_stamp">記録日：<?= $date ?></span>
                </div>

                <dl class="swrite_dl">
                    <div class="swrite_row">
                        <dt>① 目標の達成率</dt>
                        <dd><strong><?= $achievement_rate ?>%</strong></dd>
                    </div>

                    <div class="swrite_row">
                        <dt>② 達成要因 / 未達要因</dt>
                        <dd class="swrite_pre"><?= $achievement ?></dd>
                    </div>

                    <div class="swrite_row">
                        <dt>③ 印象に残っている感情</dt>
                        <dd><?= $emotionsText ?></dd>
                    </div>

                    <div class="swrite_row">
                        <dt>④ 感想・学び・今の気持ち</dt>
                        <dd class="swrite_pre"><?= $thoughts ?></dd>
                    </div>
                </dl>
            </section>

            <!-- 来週の目標 -->
            <section class="swrite_card">
                <div class="swrite_cardHead">
                    <h2 class="swrite_cardTitle">来週の目標</h2>
                    <span class="swrite_stamp">目標学習時間（h）</span>
                </div>

                <div class="swrite_tableWrap">
                    <table class="swrite_table">
                        <thead>
                            <tr>
                                <th>月</th>
                                <th>火</th>
                                <th>水</th>
                                <th>木</th>
                                <th>金</th>
                                <th>土</th>
                                <th>日</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?= $hours_mon ?></td>
                                <td><?= $hours_tue ?></td>
                                <td><?= $hours_wed ?></td>
                                <td><?= $hours_thu ?></td>
                                <td><?= $hours_fri ?></td>
                                <td><?= $hours_sat ?></td>
                                <td><?= $hours_sun ?></td>
                            </tr>
                            <tr>
                                <td colspan="5" class="swrite_blank"></td>
                                <td class="swrite_sumLabel">合計</td>
                                <td class="swrite_sumVal"><?= $hours_sum ?> h</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <dl class="swrite_dl swrite_dl_mt">
                    <div class="swrite_row">
                        <dt>② 来週のカリキュラム達成目標</dt>
                        <dd><?= $curriculum_title ?></dd>
                    </div>
                    <div class="swrite_row">
                        <dt>③ 来週のオリジナル目標</dt>
                        <dd class="swrite_pre"><?= $original_goal ?></dd>
                    </div>
                    <div class="swrite_row">
                        <dt>④ メンターに相談したいこと</dt>
                        <dd class="swrite_pre"><?= $mentor_consultation ?></dd>
                    </div>
                </dl>

                <div class="swrite_actions">
                    <a class="swrite_btn swrite_btn_primary" href="index.php">← フォームに戻る</a>
                    <a class="list_btn list_btn_primary" href="result.php">フォーム回答一覧</a>
                </div>
            </section>

        </div>
    </div>
    <?php
    // ★共通レイアウト終了
    require_once APP_ROOT . '/parts/layout_end.php';
    ?>
</body>

</html>