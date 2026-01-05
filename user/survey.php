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
    $pdo = new PDO('mysql:dbname=learning_app;charset=utf8mb4;host=localhost', 'root', '');
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
            
            <!-- Q1: 雇用状況・就業時間 -->
            <?php if (!empty($survey['employment_status'])): ?>
                <div class="survey-section">
                    <div class="question-number">Q1</div>
                    <div class="question-content">
                        <h3 class="question-title">現在の雇用状況・就業時間</h3>
                        <div class="answer-label">雇用形態:</div>
                        <div class="answer-text">
                            <?= h($employmentOptions[$survey['employment_status']] ?? $survey['employment_status']) ?>
                        </div>
                        
                        <?php if (!empty($survey['work_start_time']) && !empty($survey['work_end_time'])): ?>
                            <div class="answer-label">勤務時間:</div>
                            <div class="answer-text">
                                <?= h(substr($survey['work_start_time'], 0, 5)) ?> 〜 <?= h(substr($survey['work_end_time'], 0, 5)) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($survey['work_days'])): ?>
                            <div class="answer-label">勤務曜日:</div>
                            <div class="answer-text"><?= h($survey['work_days']) ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($survey['overtime_hours_month'])): ?>
                            <div class="answer-label">月間残業時間:</div>
                            <div class="answer-text"><?= h($survey['overtime_hours_month']) ?> 時間</div>
                        <?php endif; ?>
                        
                        <?php if (isset($survey['weekend_work_count'])): ?>
                            <div class="answer-label">月間休日出勤:</div>
                            <div class="answer-text"><?= h($survey['weekend_work_count']) ?> 回</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Q2: 職種・業界 -->
            <?php if (!empty($survey['current_job_role']) || !empty($survey['industry'])): ?>
                <div class="survey-section">
                    <div class="question-number">Q2</div>
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

            <!-- Q3: 学習可能時間帯 -->
            <?php if (!empty($survey['study_time_slots'])): ?>
                <div class="survey-section">
                    <div class="question-number">Q3</div>
                    <div class="question-content">
                        <h3 class="question-title">学習に使える時間帯</h3>
                        <div class="answer-text">
                            <?php
                            $slots = json_decode($survey['study_time_slots'], true);
                            if ($slots && is_array($slots)) {
                                foreach ($slots as $day => $times) {
                                    if (!empty($times)) {
                                        echo '<div class="time-slot-item">';
                                        echo '<strong>' . h($day) . ':</strong> ';
                                        echo h(implode(', ', $times));
                                        echo '</div>';
                                    }
                                }
                            } else {
                                echo h($survey['study_time_slots']);
                            }
                            ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Q4: キャリアの流れ -->
            <?php if (!empty($survey['job_history'])): ?>
                <div class="survey-section">
                    <div class="question-number">Q4</div>
                    <div class="question-content">
                        <h3 class="question-title">これまでのキャリアの全体像</h3>
                        <div class="answer-text long-text">
                            <?= nl2br(h($survey['job_history'])) ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Q5: 好き/続けたい要素 -->
            <?php if (!empty($survey['values_important'])): ?>
                <div class="survey-section">
                    <div class="question-number">Q5</div>
                    <div class="question-content">
                        <h3 class="question-title">今の仕事で「好き／続けたい」要素</h3>
                        <div class="answer-tags">
                            <?php
                            // 「選択肢 / 補足：xxx」形式から選択肢を取り出す
                            $parts = explode(' / 補足：', $survey['values_important']);
                            $values = array_map('trim', explode(',', $parts[0]));
                            foreach ($values as $value) {
                                if ($value !== '') {
                                    echo '<span class="tag tag-positive">' . h($value) . '</span>';
                                }
                            }
                            ?>
                        </div>
                        <?php if (isset($parts[1]) && $parts[1] !== ''): ?>
                            <div class="answer-label">補足:</div>
                            <div class="answer-text"><?= nl2br(h($parts[1])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Q6: しんどい/モヤモヤすること -->
            <?php if (!empty($survey['values_not_want']) || !empty($survey['job_stress_note'])): ?>
                <div class="survey-section">
                    <div class="question-number">Q6</div>
                    <div class="question-content">
                        <h3 class="question-title">今の仕事で「しんどい／モヤモヤする」こと</h3>
                        <?php if (!empty($survey['values_not_want'])): ?>
                            <div class="answer-tags">
                                <?php
                                $values = array_map('trim', explode(',', $survey['values_not_want']));
                                foreach ($values as $value) {
                                    if ($value !== '') {
                                        echo '<span class="tag tag-negative">' . h($value) . '</span>';
                                    }
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($survey['job_stress_note'])): ?>
                            <div class="answer-label">補足:</div>
                            <div class="answer-text"><?= nl2br(h($survey['job_stress_note'])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Q7: やりがいを感じた瞬間 -->
            <?php if (!empty($survey['proud_achievement'])): ?>
                <div class="survey-section">
                    <div class="question-number">Q7</div>
                    <div class="question-content">
                        <h3 class="question-title">これまでの仕事で「やりがいを感じた瞬間」「成果を出した瞬間」</h3>
                        <div class="answer-text long-text">
                            <?= nl2br(h($survey['proud_achievement'])) ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Q8: 強み -->
            <?php if (!empty($survey['strengths'])): ?>
                <div class="survey-section">
                    <div class="question-number">Q8</div>
                    <div class="question-content">
                        <h3 class="question-title">あなたの強みや得意なこと</h3>
                        <div class="answer-text long-text">
                            <?= nl2br(h($survey['strengths'])) ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Q9: 大事にしたい価値観 -->
            <?php if (!empty($survey['core_values']) || !empty($survey['core_values_note'])): ?>
                <div class="survey-section">
                    <div class="question-number">Q9</div>
                    <div class="question-content">
                        <h3 class="question-title">あなたが「大事にしたい価値観」</h3>
                        <?php if (!empty($survey['core_values'])): ?>
                            <div class="answer-tags">
                                <?php
                                $values = array_map('trim', explode(',', $survey['core_values']));
                                foreach ($values as $value) {
                                    if ($value !== '') {
                                        echo '<span class="tag tag-value">' . h($value) . '</span>';
                                    }
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($survey['core_values_note'])): ?>
                            <div class="answer-label">補足:</div>
                            <div class="answer-text"><?= nl2br(h($survey['core_values_note'])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Q10: 挫折パターン -->
            <?php if (!empty($survey['failure_patterns']) || !empty($survey['failure_patterns_note'])): ?>
                <div class="survey-section">
                    <div class="question-number">Q10</div>
                    <div class="question-content">
                        <h3 class="question-title">過去に挫折したパターン</h3>
                        <?php if (!empty($survey['failure_patterns'])): ?>
                            <div class="answer-tags">
                                <?php
                                $patterns = array_map('trim', explode(',', $survey['failure_patterns']));
                                foreach ($patterns as $pattern) {
                                    if ($pattern !== '') {
                                        echo '<span class="tag tag-warning">' . h($pattern) . '</span>';
                                    }
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($survey['failure_patterns_note'])): ?>
                            <div class="answer-label">補足:</div>
                            <div class="answer-text"><?= nl2br(h($survey['failure_patterns_note'])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Q11: 未来のビジョン -->
            <?php if (!empty($survey['future_vision'])): ?>
                <div class="survey-section">
                    <div class="question-number">Q11</div>
                    <div class="question-content">
                        <h3 class="question-title">未来のビジョン（3年後・1年後）</h3>
                        <?php
                        $vision = json_decode($survey['future_vision'], true);
                        if ($vision && is_array($vision)):
                        ?>
                            <?php if (!empty($vision['3y'])): ?>
                                <div class="vision-block">
                                    <div class="vision-label">3年後の姿:</div>
                                    <?php foreach ($vision['3y'] as $key => $value): ?>
                                        <?php if (!empty($value)): ?>
                                            <div class="vision-item">
                                                <strong><?= h($key) ?>:</strong> <?= h($value) ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($vision['1y'])): ?>
                                <div class="vision-block">
                                    <div class="vision-label">1年後の姿:</div>
                                    <?php foreach ($vision['1y'] as $key => $value): ?>
                                        <?php if (!empty($value)): ?>
                                            <div class="vision-item">
                                                <strong><?= h($key) ?>:</strong> <?= h($value) ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="answer-text"><?= nl2br(h($survey['future_vision'])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Q12: 半年後のゴール -->
            <?php if (!empty($survey['half_year_goals'])): ?>
                <div class="survey-section">
                    <div class="question-number">Q12</div>
                    <div class="question-content">
                        <h3 class="question-title">半年後のゴール（逆算設計）</h3>
                        <?php
                        $goals = json_decode($survey['half_year_goals'], true);
                        if ($goals && is_array($goals)):
                        ?>
                            <div class="goals-cascade">
                                <?php if (!empty($goals['income'])): ?>
                                    <div class="goal-item">
                                        <span class="goal-icon">💰</span>
                                        <div class="goal-content">
                                            <div class="goal-label">収入面のゴール</div>
                                            <div class="goal-text"><?= h($goals['income']) ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($goals['achievement'])): ?>
                                    <div class="goal-item">
                                        <span class="goal-icon">🎯</span>
                                        <div class="goal-content">
                                            <div class="goal-label">達成したいこと</div>
                                            <div class="goal-text"><?= h($goals['achievement']) ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($goals['skill'])): ?>
                                    <div class="goal-item">
                                        <span class="goal-icon">📚</span>
                                        <div class="goal-content">
                                            <div class="goal-label">身につけたいスキル</div>
                                            <div class="goal-text"><?= h($goals['skill']) ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($goals['habit'])): ?>
                                    <div class="goal-item">
                                        <span class="goal-icon">🔄</span>
                                        <div class="goal-content">
                                            <div class="goal-label">習慣化したいこと</div>
                                            <div class="goal-text"><?= h($goals['habit']) ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="answer-text"><?= nl2br(h($survey['half_year_goals'])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Q13: 学習の障害 -->
            <?php if (!empty($survey['obstacles'])): ?>
                <div class="survey-section">
                    <div class="question-number">Q13</div>
                    <div class="question-content">
                        <h3 class="question-title">半年後ゴールに向けて「いま感じている不安」</h3>
                        <div class="answer-tags">
                            <?php
                            $obstacles = array_map('trim', explode(',', $survey['obstacles']));
                            foreach ($obstacles as $obstacle) {
                                if ($obstacle !== '') {
                                    echo '<span class="tag tag-obstacle">' . h($obstacle) . '</span>';
                                }
                            }
                            ?>
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
