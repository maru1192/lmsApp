document.addEventListener('DOMContentLoaded', () => {
    const buttons = document.querySelectorAll('.nav-icon-btn[data-menu]');
    const panels = document.querySelectorAll('.subpanel[data-menu]');

    const initial =
        document.body.dataset.activeMenu ||
        localStorage.getItem('activeMenu') ||
        (buttons[0] ? buttons[0].dataset.menu : 'home');

    setActive(initial);

    buttons.forEach(btn => {
        btn.addEventListener('click', () => {
            const key = btn.dataset.menu;
            const href = btn.dataset.href;
            
            // hrefがある場合はページ遷移
            if (href) {
                window.location.href = href;
                return;
            }
            
            setActive(key);
            localStorage.setItem('activeMenu', key);
        });
    });

    function setActive(key) {
        // ボタンactive
        buttons.forEach(b => b.classList.toggle('is-active', b.dataset.menu === key));
        // パネル切替
        panels.forEach(p => p.classList.toggle('is-active', p.dataset.menu === key));


        // home はサブ無し → body[data-has-sub] を切替（CSSが幅調整する）
        const hasSub = (key !== 'home');
        document.body.dataset.hasSub = hasSub ? '1' : '0';
        document.body.dataset.activeMenu = key;
    }

    // ユーザーメニュードロップダウン
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userDropdown = document.getElementById('userDropdown');

    if (userMenuBtn && userDropdown) {
        userMenuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            userDropdown.classList.toggle('is-open');
        });

        // 外側クリックで閉じる
        document.addEventListener('click', () => {
            userDropdown.classList.remove('is-open');
        });

        // ドロップダウン内クリックで伝播を止める
        userDropdown.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }
});
