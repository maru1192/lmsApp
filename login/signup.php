<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/login.css">
    <link rel="stylesheet" href="event/css/reset.css">

    <title>LearningApp</title>
</head>

<body>
    <div class="personal_setting">
        <div class="personal_setting_inner">
            <h3 class="form_title">登録内容を入力してください</h3>

            <form method="post" action="signup_act.php" class="personal_setting_form" id="personalForm">

                <!-- 名前 -->
                <div class="form-group">
                    <label>お名前：</label>
                    <div class="name-row">
                        <div class="name-field">
                            <input type="text" id="lastName" name="lastName" placeholder="姓" required />
                        </div>
                        <div class="name-field">
                            <input type="text" id="firstName" name="firstName" placeholder="名" required />
                        </div>
                    </div>

                    <!-- 性別 -->
                    <div class="gender_row">
                        <label>性別：</label>
                        <select id="gender" name="gender" required>
                            <option value="">選択してください</option>
                            <option value="男性">男性</option>
                            <option value="女性">女性</option>
                            <option value="その他">その他</option>
                        </select>
                    </div>

                    <!-- 生年月日 -->
                    <div class="birthdate_row">
                        <label>生年月日：</label>
                        <input type="date" id="birthdate" name="birthdate" required />
                    </div>

                    <!-- メールアドレス -->
                    <div class="mail_row">
                        <label>メールアドレス：</label>
                        <input type="email" id="email" name="email" placeholder="△△△@gmail.com" required />
                    </div>

                    <!-- パスワード -->
                    <div class="password_row">
                        <label>パスワード：</label>
                        <input type="password" id="password" name="password" required />
                    </div>

                    <!-- パスワード確認 -->
                    <div class="password_row">
                        <label>パスワード（確認）：</label>
                        <input type="password" id="passwordConfirm" required />
                    </div>

                </div>
                <button type="submit" class="btn-primary">登録</button>

            </form>
        </div>
    </div>


    <!-- JQueryが先 -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

    <!-- main.jsが後 -->
    <script type="module" src="js/main.js"></script>
</body>

</html>