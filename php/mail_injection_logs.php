<?php
require_once __DIR__ . '/common_init.php';
require 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: list.php');
    exit;
}

// 模擬メール履歴の取得
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

try {
    // 総数取得
    $count_sql = "SELECT COUNT(*) FROM simulated_emails";
    $total_count = $pdo->query($count_sql)->fetchColumn();
    $total_pages = ceil($total_count / $per_page);
    
    // データ取得
    $sql = "
        SELECT se.*, u.username 
        FROM simulated_emails se 
        LEFT JOIN users u ON se.sender_user_id = u.id 
        ORDER BY se.created_at DESC 
        LIMIT ? OFFSET ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$per_page, $offset]);
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $emails = [];
    $total_count = 0;
    $total_pages = 1;
}

// 統計情報
$stats = [
    'total' => $total_count,
    'today' => 0,
    'by_type' => []
];

try {
    $today_sql = "SELECT COUNT(*) FROM simulated_emails WHERE DATE(created_at) = CURDATE()";
    $stats['today'] = $pdo->query($today_sql)->fetchColumn();
    
    $type_sql = "SELECT injection_type, COUNT(*) as count FROM simulated_emails GROUP BY injection_type";
    $type_result = $pdo->query($type_sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($type_result as $row) {
        $stats['by_type'][$row['injection_type']] = $row['count'];
    }
} catch (PDOException $e) {
    // テーブルが存在しない場合は無視
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>メールインジェクション履歴</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<?php include 'header.php'; ?>

<div class="container mx-auto mt-10 p-4">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-gray-800">メールインジェクション履歴</h1>
        <div class="flex gap-2">
            <a href="mail_contact.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">フォームに戻る</a>
            <a href="ids_dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">IDSダッシュボード</a>
        </div>
    </div>

    <!-- 統計カード -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow border">
            <div class="text-sm text-gray-500">総送信数</div>
            <div class="text-2xl font-bold text-red-600"><?= number_format($stats['total']) ?></div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow border">
            <div class="text-sm text-gray-500">今日の送信</div>
            <div class="text-2xl font-bold text-orange-600"><?= number_format($stats['today']) ?></div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow border">
            <div class="text-sm text-gray-500">CC/BCC</div>
            <div class="text-2xl font-bold text-purple-600"><?= number_format(($stats['by_type']['CC'] ?? 0) + ($stats['by_type']['BCC'] ?? 0)) ?></div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow border">
            <div class="text-sm text-gray-500">ヘッダー改ざん</div>
            <div class="text-2xl font-bold text-blue-600"><?= number_format($stats['by_type']['HEADER_INJECTION'] ?? 0) ?></div>
        </div>
    </div>

    <!-- メール履歴テーブル -->
    <div class="bg-white rounded-lg shadow border overflow-hidden">
        <div class="p-4 border-b">
            <h2 class="text-lg font-semibold">送信されたメール一覧（模擬）</h2>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">送信日時</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">送信者</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">宛先</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">件名</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">インジェクション手法</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">経由フィールド</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($emails)): ?>
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">まだメールインジェクションは実行されていません</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($emails as $email): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-4 py-2 text-sm font-mono"><?= htmlspecialchars($email['created_at']) ?></td>
                                <td class="px-4 py-2 text-sm"><?= htmlspecialchars($email['username'] ?? 'Unknown') ?></td>
                                <td class="px-4 py-2 text-sm font-mono"><?= htmlspecialchars($email['recipient_email']) ?></td>
                                <td class="px-4 py-2 text-sm max-w-xs truncate"><?= htmlspecialchars($email['subject']) ?></td>
                                <td class="px-4 py-2">
                                    <?php
                                    $type_colors = [
                                        'CC' => 'bg-purple-100 text-purple-800',
                                        'BCC' => 'bg-purple-100 text-purple-800',
                                        'SUBJECT_INJECTION' => 'bg-red-100 text-red-800',
                                        'HEADER_INJECTION' => 'bg-blue-100 text-blue-800'
                                    ];
                                    $color_class = $type_colors[$email['injection_type']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="px-2 py-1 rounded text-xs font-medium <?= $color_class ?>">
                                        <?= htmlspecialchars($email['injection_type']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-sm"><?= htmlspecialchars($email['injected_via']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- ページネーション -->
        <?php if ($total_pages > 1): ?>
            <div class="px-4 py-3 border-t flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    <?= number_format($offset + 1) ?> - <?= number_format(min($offset + $per_page, $total_count)) ?> / <?= number_format($total_count) ?>
                </div>
                <div class="flex space-x-1">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" class="px-3 py-1 border rounded text-sm hover:bg-gray-100">前へ</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?= $i ?>" 
                           class="px-3 py-1 border rounded text-sm <?= $i === $page ? 'bg-blue-600 text-white' : 'hover:bg-gray-100' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>" class="px-3 py-1 border rounded text-sm hover:bg-gray-100">次へ</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- 教育用説明 -->
    <div class="mt-8 bg-white rounded-lg shadow border p-6">
        <h2 class="text-lg font-semibold mb-4">メールインジェクション攻撃について</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm text-gray-700">
            <div>
                <h3 class="font-semibold text-blue-700 mb-2">攻撃手法</h3>
                <ul class="list-disc list-inside space-y-1">
                    <li>メールヘッダーへの改行文字挿入</li>
                    <li>CC/BCC フィールドの不正追加</li>
                    <li>Subject フィールドの改ざん</li>
                    <li>追加ヘッダーの挿入</li>
                    <li>スパムメールの送信踏み台化</li>
                </ul>
            </div>
            <div>
                <h3 class="font-semibold text-green-700 mb-2">対策</h3>
                <ul class="list-disc list-inside space-y-1">
                    <li>入力値の改行文字除去・エスケープ</li>
                    <li>メールアドレスの厳格な検証</li>
                    <li>ヘッダー文字列の検査</li>
                    <li>送信メール数の制限</li>
                    <li>送信ログの監視</li>
                </ul>
            </div>
        </div>
    </div>
</div>
</body>
</html>