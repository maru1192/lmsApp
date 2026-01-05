<?php
declare(strict_types=1);

session_start();

// プロジェクトの物理パス（/Applications/.../htdocs/gs_code/1229_event）
define('APP_ROOT', __DIR__);

// URL上のプロジェクト基準パス（/gs_code/1229_event）
$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$appUrl  = str_replace($docRoot, '', APP_ROOT);
define('APP_URL', rtrim($appUrl, '/'));

// 共通関数
require_once APP_ROOT . '/event/func.php';
