<?php
declare(strict_types=1);

// 設定読み込み
require_once __DIR__ . '/../config.php';

// ページ設定
$ACTIVE_MENU = 'user';
$ACTIVE_SUB = 'survey';
$pageTitle = 'アンケート回答内容';

// ユーザーIDを取得
$userId = $_SESSION['user_id'] ?? 0;

if (!$userId) {
    exit('ユーザー情報が取得できません');
}

// DB接続
try {
    $pdo = new PDO('mysql:dbname=learning_app;charset=utf8;host=localhost', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    exit('DBConnectError:' . $e->getMessage());
}

// 最新の完了したキャリアセッションと回答を取得
$sql = "SELECT 
            cs.id as session_id,
            cs.status,
            cs.current_step,
            cs.started_at,
            cs.completed_at,
            ca.*
        FROM career_sessions cs
        LEFT JOIN career_answers ca ON cs.id = ca.session_id
        WHERE cs.user_id = :user_id
        ORDER BY cs.id DESC
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$stmt->execute();
$survey = $stmt->fetch(PDO::FETCH_ASSOC);

// 雇用形態の選択肢
$employmentOptions = [
    'employee'   => '会社員（正社員/契約/派遣/パート含む）',
    'freelance'  => 'フリーランス/個人事業主',
    'student'    => '学生',
    'jobless'    => '離職中/休職中',
    'other'      => 'その他',
];

// 共通レイアウト開始
require_once APP_ROOT . '/parts/layout_start.php';
?>

<link rel="stylesheet" href="<?= h(APP_URL . '/user/css/survey.css') ?>">

<div class="page-header">
    <h1 class="page-title">
        <i class="far fa-comment-dots"></i>
        アンケート回答内容
    </h1>
</div>

<?php if (!$survey): ?>
    <div class="empty-state">
        <i class="far fa-file-alt"></i>
        <p>まだアンケートに回答していません</p>
        <a href="<?= h(APP_URL . '/career_regist/index.php') ?>" class="btn-start">
            <i class="far fa-edit"></i>
            アンケートに回答する
        </a>
    </div>
<?php else: ?>
    <div class="survey-status">
        <?php if ($survey['status'] === 'completed'): ?>
            <span class="status-badge completed">
                <i class="fas fa-check-circle"></i>
                回答完了
            </span>
        <?php else: ?>
            <span class="status-badge in-progress">
                <i class="fas fa-clock"></i>
                回答中（ステップ <?= h($survey['current_step']) ?>）
            </span>
        <?php endif; ?>
        
        <?php if (!empty($survey['completed_at'])): ?>
            <span class="date-info">
                回答日: <?= h(date('Y年m月d日 H:i', strtotime($survey['completed_at']))) ?>
            </span>
        <?php endif; ?>
    </div>

    <div class="content-card">
        <div class="survey-sections">
            
            <!-- Q1: 雇用状況 -->
            <?php if (!empty($survey['employment_status'])): ?>
                <div class="survey-section">
                    <div class="question-number">Q1</div>
                    <div class="question-content">
                        <h3 class="question-title">現在の雇用状況</h3>
                        <div class="answer-text">
                            <?= h($employmentOptions[$survey['employment_status']] ?? $survey['employment_status']) ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Q3: 職種・業界 -->
            <?php if (!empty($survey['current_job_role']) || !empty($survey['industry'])): ?>
                <div class="survey-section">
                    <div class="question-number">Q3</div>
                    <div class="question-content">
                        <h3 class="question-title">現在の職種・業界</h3>
                        <?php if (!empty($survey['current_job_role'])): ?>
                            <div class="answer-label">職種:</div>
                            <div class="answer-text"><?= h($survey['current_job_role']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($survey['industry'])): ?>
                            <div class="answer-label">業界:</div>
                            <div class="answer-text"><?= h($survey['industry']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Q4: 誇れる実績 -->
            <?php if (!empty($survey['proud_achievement'])): ?>
                <div class="survey-section">
                    <div class="question-number">Q4</div>
                    <div class="question-content">
                        <h3 class="question-title">これまでの仕事で一番誇れる成果</h3>
                        <div class="answer-text long-text">
                            <?= nl2br(h($survey['proud_achievement'])) ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Q5: 強み -->
            <?php if (!empty($survey['strengths'])): ?>
                <div class="survey-section">
                    <div class="question-number">Q5</div>
                    <div class="question-content">
                        <h3 class="question-title">あなたの強みや得意なこと</h3>
                        <div class="answer-text long-text">
                            <?= nl2br(h($survey['strengths'])) ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Q6: 大切にしたい価値観 -->
            <?php if (!empty($survey['values_important'])): ?>
                <div class="survey-section">
                    <div class="question-number">Q6</div>
                    <div class="question-content">
                        <h3 class="question-title">あなたが大事にしたい価値観</h3>
                        <div class="answer-text long-text">
                            <?= nl2br(h($survey['values_important'])) ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Q7: 避けたい価値観 -->
            <?php if (!empty($survey['values_not_want'])): ?>
                <div class="survey-section">
                    <div class="question-number">Q7</div>
                    <div class="question-content">
                        <h3 class="question-title">あなたが絶対に避けたいこと／やりたくないこと</h3>
                        <div class="answer-text long-text">
                            <?= nl2br(h($survey['values_not_want'])) ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>

        <?php if ($survey['status'] === 'in_progress'): ?>
            <div class="action-buttons">
                <a href="<?= h(APP_URL . '/career_regist/index.php') ?>" class="btn-continue">
                    <i class="fas fa-arrow-right"></i>
                    回答を続ける
                </a>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
// 共通レイアウト終了
require_once APP_ROOT . '/parts/layout_end.php';
?>
