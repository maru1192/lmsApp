<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/login.css">
    <link rel="stylesheet" href="event/css/reset.css">
    <link rel="icon" type="image/png" href="/shared/img/fabicon.png">

    <title>LearningApp</title>
</head>

<body>
    <!-- ID/Pass入力欄 -->
    <div class="login">
        <div class="login_inner">
            <p class="login_text">ログイン</p>

            <form id="loginForm" class="login_form" name="login_form" action="login_act.php" method="post"> 
                <div class="loginForm_inner">
                    <input id="email" type="email" placeholder="メールアドレス" name="lid" class="input_area" required />
                    <input id="password" type="password" placeholder="パスワード" name="lpw" class="input_area" required />
                </div>
                <input type="submit" class="login-btn" value="ログイン" />

                <p id="loginError" class="login_error" style="display:none;"></p>
            </form>

            <!-- 区切り線 -->
            <div class="divider">
                <span class="divider__text">OR</span>
            </div>

            <!-- 新規登録エリア -->
            <div class="new_registration">
                <p class="registration_text">アカウントをお持ちでない方は<a href="signup.php">こちら</a></p>
            </div>
        </div>
    </div>


    <!-- JQueryが先 -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

    <!-- main.jsが後 -->
    <script type="module" src="js/main.js"></script>
</body>

</html>