<?php
require_once __DIR__ . '/common_init.php';
require 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$action = $_POST['action'] ?? '';

// 簡易的な防御チェック
$csrf_protection = $_SESSION['csrf_protection_enabled'] ?? false;
$submitted_token = $_POST['csrf_token'] ?? '';
$session_token = $_SESSION['csrf_token'] ?? '';

if ($csrf_protection && (!$submitted_token || !hash_equals($session_token, $submitted_token))) {
    echo json_encode([
        'success' => false, 
        'protected' => true,
        'message' => 'CSRF token validation failed'
    ]);
    exit;
}

// 攻撃成功のシミュレーション
switch ($action) {
    case 'change_password':
        $new_password = $_POST['new_password'] ?? '';
        $message = "パスワードが '{$new_password}' に変更されました（演習用）";
        break;
    case 'delete_account':
        $message = "アカウントが削除されました（演習用）";
        break;
    default:
        $message = "不明なアクション: {$action}";
}

// IDSログ記録
if (function_exists('log_attack')) {
    log_attack($pdo, "CSRF Attack Success", $action, 'csrf_target.php', 200);
}

echo json_encode([
    'success' => true,
    'protected' => false,
    'message' => $message
]);
?>