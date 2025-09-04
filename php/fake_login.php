<?php
// fake_login.php - DNS攻撃演習用の偽ログインページ
session_start();

// DNS攻撃演習が有効でない場合は本物のログインページへリダイレクト


// POSTリクエストの処理（認証情報の盗取）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $source_ip = $_SESSION['simulated_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    
    // 認証情報をデータベースに保存（演習用）
    try {
        require_once __DIR__ . '/db.php';
        
        // 盗取データ保存用テーブルの自動作成
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS dns_phishing_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(255) NOT NULL,
                password VARCHAR(255) NOT NULL,
                source_ip VARCHAR(45) NOT NULL,
                user_agent TEXT,
                captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                session_data TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        
        // 盗取した認証情報を保存
        $stmt = $pdo->prepare("
            INSERT INTO dns_phishing_logs (username, password, source_ip, user_agent, session_data) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $username,
            $password,
            $source_ip,
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            json_encode($_SESSION)
        ]);
        
        // IDS/IPSログにも記録
        if (function_exists('log_attack')) {
            log_attack($pdo, 'DNS Phishing: Credentials Captured', 
                "username={$username}, password_length=" . strlen($password), 
                'fake_login.php', 200);
        }
        
    } catch (Throwable $e) {
        error_log('DNS Phishing log failed: ' . $e->getMessage());
    }
    
    // 攻撃成功ページを表示
    showSuccessPage($username, $password, $source_ip);
    exit;
}

function showSuccessPage($username, $password, $source_ip) {
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>攻撃成功 - DNS フィッシング演習</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in { animation: fadeIn 0.6s ease-out; }
        .terminal {
            background: #0a0a0a;
            color: #00ff00;
            font-family: 'Courier New', monospace;
            padding: 20px;
            border-radius: 8px;
        }
    </style>
</head>
<body class="bg-red-900 text-white min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- 攻撃成功ヘッダー -->
            <div class="text-center mb-8 fade-in">
                <h1 class="text-4xl font-bold text-red-400 mb-4">🎯 DNS フィッシング攻撃成功！</h1>
                <p class="text-xl text-red-200">ユーザー名とパスワードが攻撃者に送信されました</p>
            </div>
            
            <!-- 盗取された情報の表示 -->
            <div class="bg-red-800 rounded-lg p-6 mb-6 fade-in">
                <h2 class="text-2xl font-bold mb-4 text-red-300">💀 盗取された認証情報</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-red-700 p-4 rounded">
                        <div class="text-red-300 text-sm">ユーザー名</div>
                        <div class="text-white font-mono text-lg"><?= htmlspecialchars($username) ?></div>
                    </div>
                    <div class="bg-red-700 p-4 rounded">
                        <div class="text-red-300 text-sm">パスワード</div>
                        <div class="text-white font-mono text-lg"><?= htmlspecialchars($password) ?></div>
                    </div>
                    <div class="bg-red-700 p-4 rounded">
                        <div class="text-red-300 text-sm">送信元IP</div>
                        <div class="text-white font-mono text-lg"><?= htmlspecialchars($source_ip) ?></div>
                    </div>
                    <div class="bg-red-700 p-4 rounded">
                        <div class="text-red-300 text-sm">盗取時刻</div>
                        <div class="text-white font-mono text-lg"><?= date('Y-m-d H:i:s') ?></div>
                    </div>
                </div>
            </div>
            
            <!-- ターミナル風ログ -->
            <div class="terminal mb-6 fade-in">
                <div>[<?= date('H:i:s') ?>] DNS フィッシング攻撃が成功しました</div>
                <div>[<?= date('H:i:s') ?>] ターゲット: <?= htmlspecialchars($username) ?></div>
                <div>[<?= date('H:i:s') ?>] パスワード長: <?= strlen($password) ?> 文字</div>
                <div>[<?= date('H:i:s') ?>] データベースに保存完了</div>
                <div>[<?= date('H:i:s') ?>] C2サーバーへ送信完了</div>
                <div style="color: #ff6b6b;">[<?= date('H:i:s') ?>] ミッション完了 - 認証情報取得成功</div>
            </div>
            
            <!-- 攻撃分析 -->
            <div class="bg-gray-800 rounded-lg p-6 mb-6 fade-in">
                <h2 class="text-xl font-bold mb-4 text-yellow-400">⚡ 攻撃分析</h2>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-300">攻撃手法:</span>
                        <span class="text-white">DNS キャッシュポイズニング + フィッシング</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-300">被害者の認識:</span>
                        <span class="text-white">正規ログインページと誤認</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-300">データ送信先:</span>
                        <span class="text-white">攻撃者制御サーバー (127.0.0.1:8088)</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-300">SSL証明書:</span>
                        <span class="text-red-400">なし（HTTP接続）</span>
                    </div>
                </div>
            </div>
            
            <!-- 教育メッセージ -->
            <div class="bg-blue-800 rounded-lg p-6 fade-in">
                <h2 class="text-xl font-bold mb-4 text-blue-300">🎓 セキュリティ教育</h2>
                <div class="space-y-2 text-sm text-blue-100">
                    <p><strong>この攻撃の特徴:</strong></p>
                    <ul class="list-disc list-inside space-y-1 ml-4">
                        <li>DNS改ざんにより被害者は偽サイトと気づかない</li>
                        <li>見た目は正規サイトと同じため判別困難</li>
                        <li>HTTPS証明書がない場合の警告に注意が必要</li>
                        <li>URLバーの確認が重要（ドメイン・プロトコル）</li>
                    </ul>
                    <p class="mt-3"><strong>対策:</strong></p>
                    <ul class="list-disc list-inside space-y-1 ml-4">
                        <li>SSL証明書の確認（緑色の鍵マーク）</li>
                        <li>ブックマークからのアクセス</li>
                        <li>多要素認証の導入</li>
                        <li>DNS over HTTPS (DoH) の使用</li>
                    </ul>
                </div>
            </div>
            
            <!-- 戻るボタン -->
            <div class="text-center mt-8">
                <a href="dns_attack_exercise.php" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-lg">
                    DNS攻撃演習に戻る
                </a>
            </div>
        </div>
    </div>
</body>
</html>
<?php
    return;
}

// 偽ログインフォーム（本物そっくりに作成）
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ログイン</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* 本物のlogin.phpと同じスタイルを適用 */
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
    </style>
</head>
<body class="bg-gray-100">
<div class="container mx-auto mt-10 p-4 max-w-[1024px]">
    <div class="bg-white p-8 rounded-lg shadow-md">
        <h1 class="text-2xl font-bold text-center mb-6">ログイン</h1>

        <!-- 偽の成功メッセージ（もしあれば） -->
        <div id="message-area" class="text-center mb-4">
            <div class="bg-red-50 border border-red-300 text-red-800 p-3 rounded mb-4 text-sm">
                <strong>⚠️ 注意：</strong> これは DNS フィッシング攻撃の演習用偽サイトです。<br>
                入力された情報は攻撃者によって盗取されます。
            </div>
        </div>

        <!-- 偽ログインフォーム -->
        <form method="POST" action="fake_login.php">
            <div class="mb-4">
                <label for="username" class="block text-gray-700">ユーザー名</label>
                <input type="text" name="username" id="username" class="w-full px-3 py-2 border rounded-lg" 
                       required placeholder="ユーザー名を入力" value="admin">
            </div>
            <div class="mb-6">
                <label for="password" class="block text-gray-700">パスワード</label>
                <input type="password" name="password" id="password" class="w-full px-3 py-2 border rounded-lg"
                       placeholder="パスワードを入力" value="administrator">
                <p class="text-xs text-gray-500 mt-1">
                    ※ このページは攻撃者が作成した偽サイトです
                </p>
            </div>
            <button type="submit" class="w-full bg-green-500 text-white py-2 rounded-lg hover:bg-green-600">
                ログイン
            </button>
        </form>

        <!-- 偽サイトであることを示すマーカー（教育目的） -->
        <div class="mt-6 p-4 bg-yellow-50 border border-yellow-300 rounded">
            <h3 class="text-lg font-bold text-yellow-800 mb-2">🚨 演習説明</h3>
            <div class="text-sm text-yellow-700">
                <p><strong>このページは教育演習用の偽サイトです。</strong></p>
                <ul class="list-disc list-inside mt-2 space-y-1">
                    <li>DNS攻撃により正規サイトに偽装しています</li>
                    <li>デフォルト値（admin/administrator）で送信してください</li>
                    <li>入力情報は攻撃者に送信される様子を確認できます</li>
                    <li>実際の攻撃では被害者は偽サイトと気づきません</li>
                </ul>
            </div>
        </div>

        <p class="text-center mt-4">
            <a href="register.php" class="text-blue-500">新規登録</a> |
            <a href="dns_attack_exercise.php" class="text-gray-500">演習に戻る</a>
        </p>
    </div>
</div>

<script>
// URLバーの警告表示（教育目的）
if (window.location.href.includes('fake_login.php')) {
    setTimeout(() => {
        if (!document.getElementById('url-warning')) {
            const warning = document.createElement('div');
            warning.id = 'url-warning';
            warning.className = 'fixed top-0 left-0 w-full bg-red-600 text-white text-center py-2 text-sm z-50';
            warning.innerHTML = '⚠️ 偽サイト検知: URLが fake_login.php になっています（通常は login.php）';
            document.body.insertBefore(warning, document.body.firstChild);
        }
    }, 2000);
}

// SSL証明書の欠如を警告
if (window.location.protocol === 'http:') {
    setTimeout(() => {
        alert('⚠️ セキュリティ警告: このサイトはHTTPS接続ではありません。\n正規サイトは通常HTTPS (🔒) を使用します。');
    }, 3000);
}
</script>
</body>
</html>