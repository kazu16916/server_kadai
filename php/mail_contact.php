<?php
require_once __DIR__ . '/common_init.php';
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$sent = false;
$injection_detected = false;
$injection_details = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // メールインジェクション検知パターン
    $injection_patterns = [
        'cc:' => '/cc\s*:/i',
        'bcc:' => '/bcc\s*:/i',
        'to:' => '/to\s*:/i',
        'from:' => '/from\s*:/i',
        'subject:' => '/subject\s*:/i',
        'newline_injection' => '/[\r\n]/i',
        'content_type' => '/content-type\s*:/i',
        'mime_version' => '/mime-version\s*:/i'
    ];
    
    // 各フィールドでインジェクション検知
    foreach ([$name => 'name', $email => 'email', $subject => 'subject', $message => 'message'] as $value => $field) {
        foreach ($injection_patterns as $pattern_name => $pattern) {
            if (preg_match($pattern, $value)) {
                $injection_detected = true;
                $injection_details[] = [
                    'field' => $field,
                    'pattern' => $pattern_name,
                    'content' => substr($value, 0, 100) . (strlen($value) > 100 ? '...' : '')
                ];
            }
        }
    }
    
    // 模擬メール送信処理
    if ($injection_detected) {
        // インジェクション成功として記録
        $injection_summary = json_encode($injection_details, JSON_UNESCAPED_UNICODE);
        
        if (function_exists('log_attack')) {
            log_attack($pdo, 'Mail Injection Success', 
                'Detected patterns: ' . $injection_summary, 
                'mail_contact.php', 200);
        }
        
        // 模擬的に追加のメール送信をシミュレート
        simulate_mail_injection($pdo, $injection_details, $name, $email, $subject, $message);
    } else {
        // 正常なメール送信として記録
        if (function_exists('log_attack')) {
            log_attack($pdo, 'Mail Form Submission', 
                'Normal mail form submission from: ' . $email, 
                'mail_contact.php', 200);
        }
    }
    
    $sent = true;
}

function simulate_mail_injection($pdo, $injection_details, $name, $email, $subject, $message) {
    // 模擬的に複数のメール送信をシミュレート
    $simulated_emails = [];
    
    foreach ($injection_details as $detail) {
        switch ($detail['pattern']) {
            case 'cc':
            case 'bcc':
                // CC/BCC インジェクションの模擬
                $extracted_emails = extract_emails_from_injection($detail['content']);
                foreach ($extracted_emails as $target_email) {
                    $simulated_emails[] = [
                        'type' => strtoupper($detail['pattern']),
                        'to' => $target_email,
                        'subject' => $subject,
                        'injected_via' => $detail['field']
                    ];
                }
                break;
                
            case 'subject':
                // Subject インジェクション
                $simulated_emails[] = [
                    'type' => 'SUBJECT_INJECTION',
                    'to' => $email,
                    'subject' => 'INJECTED: ' . $subject,
                    'injected_via' => $detail['field']
                ];
                break;
                
            case 'newline_injection':
                // 改行インジェクション
                $simulated_emails[] = [
                    'type' => 'HEADER_INJECTION',
                    'to' => $email,
                    'subject' => $subject,
                    'additional_headers' => 'X-Injected: true',
                    'injected_via' => $detail['field']
                ];
                break;
        }
    }
    
    // 模擬メールログをデータベースに保存
    foreach ($simulated_emails as $mail) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO simulated_emails (sender_user_id, injection_type, recipient_email, subject, injected_via, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $mail['type'],
                $mail['to'],
                $mail['subject'],
                $mail['injected_via']
            ]);
        } catch (PDOException $e) {
            // テーブルが存在しない場合は作成
            create_simulated_emails_table($pdo);
            // 再試行
            try {
                $stmt->execute([
                    $_SESSION['user_id'],
                    $mail['type'],
                    $mail['to'],
                    $mail['subject'],
                    $mail['injected_via']
                ]);
            } catch (PDOException $e2) {
                error_log("Failed to log simulated email: " . $e2->getMessage());
            }
        }
    }
}

function extract_emails_from_injection($content) {
    // メールアドレス抽出の簡易実装
    preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $content, $matches);
    return array_unique($matches[0]);
}

function create_simulated_emails_table($pdo) {
    $sql = "
    CREATE TABLE IF NOT EXISTS simulated_emails (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_user_id INT,
        injection_type VARCHAR(50),
        recipient_email VARCHAR(255),
        subject TEXT,
        injected_via VARCHAR(50),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX(sender_user_id),
        INDEX(created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $pdo->exec($sql);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>お問い合わせフォーム</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .injection-highlight { background: linear-gradient(45deg, #fef3c7, #fbbf24); animation: pulse 2s infinite; }
    </style>
</head>
<body class="bg-gray-100">
<?php include 'header.php'; ?>

<div class="container mx-auto mt-10 p-4 max-w-2xl">
    <div class="bg-white p-8 rounded-lg shadow-md">
        <h1 class="text-2xl font-bold mb-6">お問い合わせフォーム</h1>
        
        <?php if ($sent): ?>
            <div class="mb-6">
                <?php if ($injection_detected): ?>
                    <!-- インジェクション成功の視覚的フィードバック -->
                    <div class="injection-highlight border border-orange-300 text-orange-900 p-4 rounded-lg mb-4">
                        <div class="flex items-center mb-2">
                            <svg class="w-5 h-5 mr-2 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            <strong>メールインジェクション攻撃成功！</strong>
                        </div>
                        <p class="text-sm mb-3">検知されたインジェクションパターン:</p>
                        <div class="space-y-2">
                            <?php foreach ($injection_details as $detail): ?>
                                <div class="bg-white bg-opacity-50 p-2 rounded text-sm">
                                    <strong>フィールド:</strong> <?= htmlspecialchars($detail['field']) ?> | 
                                    <strong>パターン:</strong> <?= htmlspecialchars($detail['pattern']) ?>
                                    <br><strong>内容:</strong> <code class="bg-gray-200 px-1 rounded"><?= htmlspecialchars($detail['content']) ?></code>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="text-sm mt-3 font-semibold">模擬的に追加のメールが送信されました。IDSダッシュボードで詳細を確認してください。</p>
                    </div>
                    
                    <div class="bg-red-100 border border-red-300 text-red-700 p-3 rounded mb-4">
                        <strong>攻撃者視点:</strong> メールヘッダーインジェクションにより、意図しない追加のメールが送信されました。
                    </div>
                <?php else: ?>
                    <!-- 正常送信 -->
                    <div class="bg-green-100 border border-green-300 text-green-700 p-3 rounded">
                        お問い合わせを受け付けました。ありがとうございます。
                    </div>
                <?php endif; ?>
                
                <div class="mt-4 flex gap-3">
                    <a href="mail_contact.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">新しいメッセージ</a>
                    <a href="ids_dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">IDSログを確認</a>
                    <?php if ($_SESSION['role'] === 'admin' && $injection_detected): ?>
                        <a href="mail_injection_logs.php" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">送信メール履歴</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- フォーム表示 -->
            <form method="POST" class="space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">お名前 *</label>
                    <input type="text" id="name" name="name" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="山田太郎">
                </div>
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">メールアドレス *</label>
                    <input type="email" id="email" name="email" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="yamada@example.com">
                </div>
                
                <div>
                    <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">件名 *</label>
                    <input type="text" id="subject" name="subject" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="お問い合わせの件">
                </div>
                
                <div>
                    <label for="message" class="block text-sm font-medium text-gray-700 mb-1">メッセージ *</label>
                    <textarea id="message" name="message" rows="6" required 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="お問い合わせ内容をご記入ください"></textarea>
                </div>
                
                <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    送信する
                </button>
            </form>
        <?php endif; ?>
    </div>
    
    <!-- 演習用説明（管理者のみ） -->
    <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
        <div class="mt-6 bg-blue-50 border border-blue-200 p-4 rounded-lg">
            <h3 class="font-bold text-blue-800 mb-2">メールインジェクション演習</h3>
            <p class="text-sm text-blue-700 mb-2">以下のパターンでメールインジェクション攻撃を試行できます：</p>
            <div class="space-y-1 text-sm font-mono text-blue-600">
                <div>CC追加: <code>件名フィールドに "テスト\nCC: victim@example.com"</code></div>
                <div>BCC追加: <code>名前フィールドに "攻撃者\nBCC: spam@evil.com"</code></div>
                <div>ヘッダー追加: <code>メッセージに "内容\nX-Priority: 1"</code></div>
                <div>Subject変更: <code>メールアドレスに "test@example.com\nSubject: 改ざんされた件名"</code></div>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>