<?php
//最初にセッションを開始
session_start();

//関数ファイルの読み込み
require_once __DIR__ . '/../event/func.php';

//ログイン情報がなければログインページへリダイレクト
sschk();

//1.  DB接続します
try {
  //Password:MAMP='root',XAMPP=''
  $pdo = new PDO('mysql:dbname=learning_app;charset=utf8;host=localhost','root','');
} catch (PDOException $e) {
  exit('DBConnectError'.$e->getMessage());
}

//２．データ取得SQL作成
$stmt = $pdo->prepare("SELECT * FROM weekly_reviews ORDER BY created_at DESC");     //新しい順に表示
$status = $stmt->execute();

//３．データ表示
$records=[];

if ($status==false) {
    //execute（SQL実行時にエラーがある場合）
  $error = $stmt->errorInfo();
  exit("ErrorQuery:".$error[2]);

}else{
  //Selectデータの数だけ自動でループしてくれる
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $records[] = $result;
    }
}

?>

<!DOCTYPE html>
<html lang="ja">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="css/reset.css">
        <link rel="stylesheet" href="css/result.css">
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css">
        <title>フォーム回答一覧</title>
    </head>

    <body>
        <div class="result-header">
            <a class="back_btn back_btn_primary" href="index.html">
                <i class="fas fa-arrow-left"></i>
                フォームに戻る
            </a>
        </div>
        
        <h1 class="title">
            <i class="fas fa-list-alt"></i>
            フォーム回答一覧
        </h1>

        <?php if (empty($records)): ?>
            <div class="no-data">
                <i class="fas fa-inbox" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
                <p>まだ記録がありません</p>
            </div>
        <?php else: ?>

        <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>日時</th>
                    <th>目標達成率</th>
                    <th>印象に残っている感情</th>
                    <th style="text-align: center; width: 120px;">操作</th>
                </tr>        
            </thead>

            <tbody>
                <?php foreach($records as $index => $r): ?>
                    <tr>
                        <td><?= h($r['created_at'] ?? '') ?></td>
                        <td><?= h($r['achievement_rate'] ?? '') . "%" ?></td>
                        <td><?= h($r['emotions'] ?? '') ?></td>
                        <td style="text-align: center;">
                            <button class="detail-btn" onclick="showDetail(<?= $index ?>)">
                                <i class="fas fa-eye"></i>
                                詳細
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <?php endif; ?>

        <!-- モーダル -->
        <div id="detailModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="modal-title">
                        <i class="fas fa-file-alt"></i>
                        回答詳細
                    </div>
                    <button class="close-btn" onclick="closeModal()">&times;</button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- JavaScriptで内容を動的に挿入 -->
                </div>
            </div>
        </div>

        <script>
            const records = <?= json_encode($records, JSON_UNESCAPED_UNICODE) ?>;

            function showDetail(index) {
                const record = records[index];
                const modalBody = document.getElementById('modalBody');
                
                let html = `
                    <div class="detail-section">
                        <div class="detail-label">
                            <i class="fas fa-calendar-alt"></i>
                            記録日時
                        </div>
                        <div class="detail-value">${record.created_at || '-'}</div>
                    </div>

                    <div class="detail-section">
                        <div class="detail-label">
                            <i class="fas fa-chart-line"></i>
                            ①目標の達成率
                        </div>
                        <div class="detail-value">${record.achievement_rate || '0'}%</div>
                    </div>

                    <div class="detail-section">
                        <div class="detail-label">
                            <i class="fas fa-clipboard-check"></i>
                            ②目標の達成要因/未達要因
                        </div>
                        <div class="detail-value">${record.achievement_text || '-'}</div>
                    </div>

                    <div class="detail-section">
                        <div class="detail-label">
                            <i class="fas fa-heart"></i>
                            ③印象に残っている感情
                        </div>
                        <div class="detail-value">${record.emotions || '-'}</div>
                    </div>

                    <div class="detail-section">
                        <div class="detail-label">
                            <i class="fas fa-comment-dots"></i>
                            ④今週の感想・学び・今の気持ち
                        </div>
                        <div class="detail-value">${record.thoughts || '-'}</div>
                    </div>

                    <div class="detail-section">
                        <div class="detail-label">
                            <i class="fas fa-clock"></i>
                            ①目標学習時間
                        </div>
                        <div class="hours-grid">
                            <div class="hour-item">
                                <div class="hour-day">月</div>
                                <div class="hour-value">${record.hours_mon || 0}h</div>
                            </div>
                            <div class="hour-item">
                                <div class="hour-day">火</div>
                                <div class="hour-value">${record.hours_tue || 0}h</div>
                            </div>
                            <div class="hour-item">
                                <div class="hour-day">水</div>
                                <div class="hour-value">${record.hours_wed || 0}h</div>
                            </div>
                            <div class="hour-item">
                                <div class="hour-day">木</div>
                                <div class="hour-value">${record.hours_thu || 0}h</div>
                            </div>
                            <div class="hour-item">
                                <div class="hour-day">金</div>
                                <div class="hour-value">${record.hours_fri || 0}h</div>
                            </div>
                            <div class="hour-item">
                                <div class="hour-day">土</div>
                                <div class="hour-value">${record.hours_sat || 0}h</div>
                            </div>
                            <div class="hour-item">
                                <div class="hour-day">日</div>
                                <div class="hour-value">${record.hours_sun || 0}h</div>
                            </div>
                            <div class="hour-item total-hours">
                                <div class="hour-day">合計</div>
                                <div class="hour-value">${record.hours_sum || 0}h</div>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <div class="detail-label">
                            <i class="fas fa-bullseye"></i>
                            ②来週のカリキュラム達成目標
                        </div>
                        <div class="detail-value">${record.curriculum_title || '-'}</div>
                    </div>

                    <div class="detail-section">
                        <div class="detail-label">
                            <i class="fas fa-star"></i>
                            ③来週のオリジナル目標
                        </div>
                        <div class="detail-value">${record.original_goal || '-'}</div>
                    </div>

                    <div class="detail-section">
                        <div class="detail-label">
                            <i class="fas fa-question-circle"></i>
                            ④メンターに相談したいこと
                        </div>
                        <div class="detail-value">${record.mentor_consultation || '-'}</div>
                    </div>
                `;

                modalBody.innerHTML = html;
                document.getElementById('detailModal').classList.add('show');
                document.body.style.overflow = 'hidden';
            }

            function closeModal() {
                document.getElementById('detailModal').classList.remove('show');
                document.body.style.overflow = 'auto';
            }

            // モーダル外をクリックしたら閉じる
            window.onclick = function(event) {
                const modal = document.getElementById('detailModal');
                if (event.target === modal) {
                    closeModal();
                }
            }

            // Escapeキーでモーダルを閉じる
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    closeModal();
                }
            });
        </script>
    </body>
</html>