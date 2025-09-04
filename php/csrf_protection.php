<?php
require_once __DIR__ . '/common_init.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'update_protection') {
    $_SESSION['csrf_protection_enabled'] = $input['csrf_token_protection'] ?? false;
    $_SESSION['referer_check_enabled'] = $input['referer_check'] ?? false;
    $_SESSION['origin_check_enabled'] = $input['origin_check'] ?? false;
    $_SESSION['samesite_cookie_enabled'] = $input['same_site_cookie'] ?? false;
    
    // CSRFトークン生成
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    echo json_encode([
        'success' => true,
        'csrf_token' => $_SESSION['csrf_token'],
        'message' => 'Protection updated'
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
2. simulation_tools.php の修正
CSRF機能を有効化する部分を追加：
php// 既存の攻撃タイプ処理の後に追加
if ($t === 'csrf_enable') {
    $_SESSION['csrf_enabled'] = true;
    $_SESSION['flash_csrf_enabled'] = true;
    header('Location: simulation_tools.php');
    exit;
} elseif ($t === 'csrf_disable') {
    unset($_SESSION['csrf_enabled']);
    $_SESSION['flash_csrf_disabled'] = true;
    header('Location: simulation_tools.php');
    exit;
}
そして、HTMLの攻撃演習リスト部分に追加：
php<!-- CSRF攻撃演習 -->
<div class="flex justify-between items-center p-4 border rounded-lg">
    <div>
        <h3 class="font-semibold text-lg">CSRF攻撃演習</h3>
        <p class="text-sm text-gray-600">クロスサイトリクエストフォージェリ攻撃の実行と防御を体験</p>
    </div>
    <?php if (!($csrf_enabled ?? false)): ?>
        <form action="simulation_tools.php" method="POST">
            <input type="hidden" name="attack_type" value="csrf_enable">
            <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white">
                有効化
            </button>
        </form>
    <?php else: ?>
        <div class="flex items-center gap-2">
            <a href="csrf_exercise.php" class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white">
                CSRF攻撃演習ページへ
            </a>
            <form action="simulation_tools.php" method="POST">
                <input type="hidden" name="attack_type" value="csrf_disable">
                <button class="px-4 py-2 rounded-lg text-sm font-semibold bg-gray-500 text-white">
                    無効化
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>