<?php
//設定読み込み（session_start()とh()関数はconfig.php経由でfunc.phpに含まれる）
require_once __DIR__ . '/../config.php';

// ★共通レイアウト開始
require_once APP_ROOT . '/parts/layout_start.php';
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/reset.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css">
    <title>日報 - 週次振り返りフォーム</title>
</head>

<body>
    <div class="review">
        <div class="review_inner">
            <form action="write.php" method="post">

                <!-- 今週の振り返り -->
                <div class="thisweek_title">
                    <i class="fas fa-file-signature"></i>
                    <span>今週の振り返り</span>
                </div>

                <div class="this_week">
                    <p class="sub_title">①目標の達成率：</p>
                    <div class="achieve_bar element">
                        <span class="range-now" id="rangeNow">0%</span>
                        <input class="range" type="range" name="speed" min="0" max="100" value="0">
                    </div>


                    <div class="form_achieve element">
                        <p class="sub_title">②目標の達成要因/未達要因：</p>
                        <textarea id="achievement" name="achievement"></textarea>
                    </div>

                    <div class="emotion_thought">
                        <div class="form_emotion element">
                            <p class="sub_title">③今週を振り返って印象に残っている感情はありますか？（複数選択可）</p>

                            <div class="emotion-grid">
                                <label class="emotion-item tone-good">
                                    <input type="checkbox" name="emotions[]" value="満足">
                                    <span class="dot"></span>
                                    <span class="label">満足</span>
                                </label>

                                <label class="emotion-item tone-good">
                                    <input type="checkbox" name="emotions[]" value="楽しい">
                                    <span class="dot"></span>
                                    <span class="label">楽しい</span>
                                </label>

                                <label class="emotion-item tone-calm">
                                    <input type="checkbox" name="emotions[]" value="退屈">
                                    <span class="dot"></span>
                                    <span class="label">退屈</span>
                                </label>

                                <label class="emotion-item tone-calm">
                                    <input type="checkbox" name="emotions[]" value="不安">
                                    <span class="dot"></span>
                                    <span class="label">不安</span>
                                </label>

                                <label class="emotion-item tone-bad">
                                    <input type="checkbox" name="emotions[]" value="焦り">
                                    <span class="dot"></span>
                                    <span class="label">焦り</span>
                                </label>

                                <label class="emotion-item tone-bad">
                                    <input type="checkbox" name="emotions[]" value="イライラ">
                                    <span class="dot"></span>
                                    <span class="label">イライラ</span>
                                </label>

                                <label class="emotion-item tone-good">
                                    <input type="checkbox" name="emotions[]" value="ワクワク">
                                    <span class="dot"></span>
                                    <span class="label">ワクワク</span>
                                </label>

                                <label class="emotion-item tone-good">
                                    <input type="checkbox" name="emotions[]" value="安心">
                                    <span class="dot"></span>
                                    <span class="label">安心</span>
                                </label>

                                <label class="emotion-item tone-calm">
                                    <input type="checkbox" name="emotions[]" value="疲れた">
                                    <span class="dot"></span>
                                    <span class="label">疲れた</span>
                                </label>

                                <label class="emotion-item tone-calm">
                                    <input type="checkbox" name="emotions[]" value="緊張">
                                    <span class="dot"></span>
                                    <span class="label">緊張</span>
                                </label>

                                <label class="emotion-item tone-bad">
                                    <input type="checkbox" name="emotions[]" value="怖い">
                                    <span class="dot"></span>
                                    <span class="label">怖い</span>
                                </label>

                                <label class="emotion-item tone-bad">
                                    <input type="checkbox" name="emotions[]" value="悲しい">
                                    <span class="dot"></span>
                                    <span class="label">悲しい</span>
                                </label>
                            </div>
                        </div>


                        <div class="form_thoughts element">
                            <p class="sub_title">④今週の感想・学び・今の気持ち：</p>
                            <textarea id="thoughts" name="thoughts"></textarea>
                        </div>
                    </div>
                </div>

                <!-- 来週の目標 -->
                <div class="nextweek_title">
                    <i class="fas fa-file-signature"></i>
                    <span>来週の目標</span>
                </div>

                <div class="next_week">
                    <p class="sub_title">①来週の目標勉強時間</p>
                    <table class="week-table element">
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
                            <!-- 入力行（大きいセル） -->
                            <tr class="sum-row">
                                <!-- 月〜木は下の「合計行」まで伸ばす -->
                                <td class="cell big">
                                    <div class="hours">
                                        <input type="text" name="hours_mon" inputmode="decimal" placeholder="" />
                                        <span class="unit">h</span>
                                    </div>
                                </td>
                                <td class="cell big">
                                    <div class="hours">
                                        <input type="text" name="hours_tue" inputmode="decimal" />
                                        <span class="unit">h</span>
                                    </div>
                                </td>
                                <td class="cell big">
                                    <div class="hours">
                                        <input type="text" name="hours_wed" inputmode="decimal" />
                                        <span class="unit">h</span>
                                    </div>
                                </td>
                                <td class="cell big">
                                    <div class="hours">
                                        <input type="text" name="hours_thu" inputmode="decimal" />
                                        <span class="unit">h</span>
                                    </div>
                                </td>

                                <!-- 金〜日は通常 -->
                                <td class="cell big">
                                    <div class="hours">
                                        <input type="text" name="hours_fri" inputmode="decimal" />
                                        <span class="unit">h</span>
                                    </div>
                                </td>
                                <td class="cell big">
                                    <div class="hours">
                                        <input type="text" name="hours_sat" inputmode="decimal" />
                                        <span class="unit">h</span>
                                    </div>
                                </td>
                                <td class="cell big">
                                    <div class="hours">
                                        <input type="text" name="hours_sun" inputmode="decimal" />
                                        <span class="unit">h</span>
                                    </div>
                                </td>
                            </tr>

                            <!-- 合計行（下段：金〜日だけ） -->
                            <tr class="sum_row">
                                <td class="blank" colspan="5"></td>
                                <td class="cell total">合計</td>
                                <td class="cell auto" colspan="2">
                                    <div class="sum-field">
                                        <input type="text" name="hours_sum" inputmode="decimal" />
                                        <span class="unit">h</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="curriculum element">
                        <label for="curriculum_title" class="sub_title">②来週のカリキュラム達成目標：</label>
                        <select id="curriculum_title" name="curriculum_title">
                            <optgroup label="基礎編｜“土台”を固める">
                                <option value="xx">▽ 選択する ▽</option>
                                <option value="デザイン基礎：良いデザインの3原則（整列・反復・近接）">デザイン基礎：良いデザインの3原則（整列・反復・近接）</option>
                                <option value="配色入門：失敗しない色の選び方（トーン＆アクセント）">配色入門：失敗しない色の選び方（トーン＆アクセント）</option>
                                <option value="タイポグラフィ：読みやすさを作る文字設計">タイポグラフィ：読みやすさを作る文字設計</option>
                                <option value="余白設計：情報を“スッと入れる”レイアウト術">余白設計：情報を“スッと入れる”レイアウト術</option>
                                <option value="情報設計：見出し・階層・優先度の作り方">情報設計：見出し・階層・優先度の作り方</option>
                                <option value="アイコン＆図形：統一感の出し方（線・角丸・余白）">アイコン＆図形：統一感の出し方（線・角丸・余白）</option>
                                <option value="写真の扱い：トリミングと見栄え補正の基本">写真の扱い：トリミングと見栄え補正の基本</option>
                                <option value="デザインリサーチ：参考の探し方と分解の型">デザインリサーチ：参考の探し方と分解の型</option>
                                <option value="世界観づくり：ムードボード作成">世界観づくり：ムードボード作成</option>
                            </optgroup>

                            <optgroup label="実践編｜“作れる”を増やす">
                                <option value="デザインルール化：カラーパレット＆文字ルールの整備">デザインルール化：カラーパレット＆文字ルールの整備</option>
                                <option value="UI基礎：ボタン・フォーム・カードの設計">UI基礎：ボタン・フォーム・カードの設計</option>
                                <option value="UX基礎：ユーザー視点の要件整理（課題→解決）">UX基礎：ユーザー視点の要件整理（課題→解決）</option>
                                <option value="ワイヤーフレーム：最短で形にする設計図">ワイヤーフレーム：最短で形にする設計図</option>
                                <option value="画面遷移：導線設計と迷わせない構造">画面遷移：導線設計と迷わせない構造</option>
                                <option value="コンポーネント設計：再利用できるUI作り">コンポーネント設計：再利用できるUI作り</option>
                                <option value="デザインシステム入門：スタイルガイド作成">デザインシステム入門：スタイルガイド作成</option>
                                <option value="UI改善：既存画面の“微調整”で化けさせる">UI改善：既存画面の“微調整”で化けさせる</option>
                                <option value="アクセシビリティ基礎：読みやすさ・操作しやすさ">アクセシビリティ基礎：読みやすさ・操作しやすさ</option>
                                <option value="スマホ最適化：レスポンシブの考え方">スマホ最適化：レスポンシブの考え方</option>
                                <option value="プロトタイピング：動くモックで確認する">プロトタイピング：動くモックで確認する</option>
                            </optgroup>

                            <optgroup label="仕事編｜“価値”にする">
                                <option value="Webデザイン：ファーストビュー設計（視線誘導）">Webデザイン：ファーストビュー設計（視線誘導）</option>
                                <option value="LP構成：成約に繋げるストーリー設計">LP構成：成約に繋げるストーリー設計</option>
                                <option value="バナー制作：クリックされる訴求の作り方">バナー制作：クリックされる訴求の作り方</option>
                                <option value="SNS画像：テンプレ化して量産する方法">SNS画像：テンプレ化して量産する方法</option>
                                <option value="ブランド基礎：ロゴ・トーン＆マナーの整え方">ブランド基礎：ロゴ・トーン＆マナーの整え方</option>
                                <option value="コピー×デザイン：伝わる見出しと余白の関係">コピー×デザイン：伝わる見出しと余白の関係</option>
                                <option value="実案件想定：要望が曖昧でも進める整理術">実案件想定：要望が曖昧でも進める整理術</option>
                                <option value="ポートフォリオ制作：魅せる構成と文章テンプレ">ポートフォリオ制作：魅せる構成と文章テンプレ</option>
                                <option value="案件獲得・単価設計：提案文／見積もり／継続導線">案件獲得・単価設計：提案文／見積もり／継続導線</option>
                            </optgroup>
                        </select>
                    </div>

                    <div class="form_goal element">
                        <p class="sub_title">③来週のオリジナル目標</p>
                        <textarea id="original_goal" name="original_goal"></textarea>
                    </div>

                    <div class="form_consultation element">
                        <p class="sub_title">④メンターに相談したいことが何かあれば記載：</p>
                        <textarea id="mentor_consultation" name="mentor_consultation"></textarea>
                    </div>
                </div>

                <!-- 送信ボタン -->
                <input type="submit" value="送信" class="btn">
            </form>
        </div>
    </div>
    <?php
    // ★共通レイアウト終了
    require_once APP_ROOT . '/parts/layout_end.php';
    ?>

</body>

<!-- JQueryが先 -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

<!-- main.jsが後 -->
<script src="js/main.js"></script>

</html>