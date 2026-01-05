<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once APP_ROOT . '/event/func.php';
sschk();

$ACTIVE_MENU = $ACTIVE_MENU ?? 'home'; // home / event / form
$ACTIVE_SUB  = $ACTIVE_SUB  ?? '';

$pageTitle = $pageTitle ?? 'My Page';
$pageCss   = $pageCss   ?? '';



$userName = $_SESSION['name_sei'] . ' ' . $_SESSION['name_mei'];

// ナビ定義（指定の順番）
$NAV = [
    'home' => [
        'icon'  => 'fas fa-home',
        'label' => 'HOME',
        'href'  => APP_URL . '/home.php',
        'items' => [], // ← 表示なし
    ],
    'event' => [
        'icon'  => 'far fa-calendar-alt', 
        'label' => 'イベント',
        'items' => [
            ['key' => 'event_list',   'label' => 'イベント一覧',           'href' => APP_URL . '/event/event_list.php',      'icon' => 'far fa-list-alt'],
            ['key' => 'event_join',   'label' => '参加イベント一覧', 'href' => APP_URL . '/event/event_join_list.php', 'icon' => 'far fa-check-circle'],
            ['key' => 'event_create', 'label' => 'イベント登録',           'href' => APP_URL . '/event/form_append.php',     'icon' => 'far fa-plus-square'],
        ],
    ],
    'form' => [
        'icon'  => 'far fa-edit', // フォームっぽい
        'label' => 'フォーム',
        'items' => [
            ['key' => 'review_list', 'label' => '週次レビュー一覧',        'href' => APP_URL . '/review/result.php',       'icon' => 'far fa-clipboard'],
            ['key' => 'review_new',  'label' => '週次登録フォーム', 'href' => APP_URL . '/review/index.php',      'icon' => 'far fa-file-alt'],
        ],
    ],
    'user' => [
        'icon'  => 'far fa-user',
        'label' => 'ユーザー',
        'items' => [
            ['key' => 'user_info',   'label' => 'ユーザー登録情報',   'href' => APP_URL . '/user/user_info.php',   'icon' => 'far fa-id-card'],
            ['key' => 'survey',      'label' => 'アンケート回答内容', 'href' => APP_URL . '/user/survey.php',      'icon' => 'far fa-comment-dots'],
        ],
    ],
];

// HOME時はサブメニューを出さない（=グリーンを隠す）
$hasSub = !empty($NAV[$ACTIVE_MENU]['items']);
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title><?= h($pageTitle) ?></title>

    <link rel="stylesheet" href="<?= h(APP_URL . '/assets/css/layout.css') ?>">
    <?php if ($pageCss): ?>
        <link rel="stylesheet" href="<?= h($pageCss) ?>">
    <?php endif; ?>

    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css">
</head>

<body data-active-menu="<?= h($ACTIVE_MENU) ?>" data-has-sub="<?= $hasSub ? '1' : '0' ?>">
    <div class="app-shell">

        <!-- 赤エリア（左上） -->
        <header class="nav-top">
            <div class="brand">
                <div class="brand-mark"></div>
            </div>
        </header>

        <!-- 右上（任意） -->
        <header class="main-top">
            <div class="user-menu">
                <button type="button" class="main-top-right" id="userMenuBtn">
                    <i class="far fa-user-circle"></i>
                    <span><?= h($userName) ?></span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="user-dropdown" id="userDropdown">
                    <a href="<?= h(APP_URL . '/login/logout.php') ?>" class="logout-link">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>ログアウト</span>
                    </a>
                </div>
            </div>
        </header>

        <!-- 左ナビ（オレンジ＋グリーン） -->
        <nav class="nav-area">

            <!-- オレンジ：アイコン -->
            <div class="nav-icons">
                <?php foreach ($NAV as $key => $sec): ?>
                    <button
                        type="button"
                        class="nav-icon-btn <?= ($ACTIVE_MENU === $key) ? 'is-active' : '' ?>"
                        data-menu="<?= h($key) ?>"
                        <?php if (!empty($sec['href'])): ?>data-href="<?= h($sec['href']) ?>"<?php endif; ?>
                        aria-label="<?= h($sec['label']) ?>"
                        title="<?= h($sec['label']) ?>">
                        <i class="<?= h($sec['icon']) ?>"></i>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- グリーン：サブメニュー -->
            <div class="nav-sub">
                <?php foreach ($NAV as $key => $sec): ?>
                    <div class="subpanel <?= ($ACTIVE_MENU === $key) ? 'is-active' : '' ?>" data-menu="<?= h($key) ?>">
                        <?php if (!empty($sec['items'])): ?>
                            <div class="sub-title">
                                <i class="<?= h($sec['icon']) ?>"></i>
                                <span><?= h($sec['label']) ?></span>
                            </div>

                            <div class="sub-links">
                                <?php foreach ($sec['items'] as $item): ?>
                                    <a
                                        class="sub-link <?= ($ACTIVE_SUB === $item['key']) ? 'is-current' : '' ?>"
                                        href="<?= h($item['href']) ?>">
                                        <i class="<?= h($item['icon']) ?>"></i>
                                        <span><?= h($item['label']) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

        </nav>

        <!-- メイン本文 -->
        <main class="app-main">
            <div class="app-content">