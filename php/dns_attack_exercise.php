<?php
// dns_attack_exercise.php
require_once __DIR__ . '/common_init.php';
require 'db.php';

// 管理者のみ許可
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: list.php');
    exit;
}

// DNS攻撃演習が無効の場合は戻す
if (empty($_SESSION['dns_attack_enabled'])) {
    header('Location: simulation_tools.php?error=' . urlencode('DNS攻撃演習を先に有効化してください。'));
    exit;
}

// 現在のDNS攻撃状態を取得
$dns_status = $_SESSION['dns_attack_status'] ?? 'preparing';
$target_domain = $_SESSION['dns_target_domain'] ?? 'login.php';
$fake_server = $_SESSION['dns_fake_server'] ?? '127.0.0.1:8088';

// POSTリクエスト処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'scan_dns_servers':
            // DNSサーバスキャンのシミュレーション
            $_SESSION['dns_attack_status'] = 'scanning';
            $attack_detail = 'DNS server discovery and vulnerability scan initiated';
            if (function_exists('log_attack')) {
                log_attack($pdo, 'DNS Attack: Server Discovery', $attack_detail, 'dns_attack_exercise.php', 200);
            }
            header('Location: dns_attack_exercise.php?success=' . urlencode('DNSサーバスキャンを開始しました'));
            exit;
            
        case 'exploit_dns_server':
            // DNSサーバ攻撃のシミュレーション
            $_SESSION['dns_attack_status'] = 'exploiting';
            $attack_detail = 'DNS server exploitation and cache poisoning attempt';
            if (function_exists('log_attack')) {
                log_attack($pdo, 'DNS Attack: Cache Poisoning', $attack_detail, 'dns_attack_exercise.php', 200);
            }
            header('Location: dns_attack_exercise.php?success=' . urlencode('DNSキャッシュポイズニング攻撃を実行しました'));
            exit;
            
        case 'compromise_dns':
            // DNS改ざん成功のシミュレーション
            $_SESSION['dns_attack_status'] = 'compromised';
            $attack_detail = sprintf('DNS successfully compromised - %s redirected to %s', $target_domain, $fake_server);
            if (function_exists('log_attack')) {
                log_attack($pdo, 'DNS Attack: Domain Hijacked', $attack_detail, 'dns_attack_exercise.php', 200);
            }
            header('Location: dns_attack_exercise.php?success=' . urlencode('DNS改ざんに成功しました！偽サイトへのリダイレクトが有効になりました'));
            exit;
            
        case 'reset_attack':
            // 攻撃状態をリセット
            $_SESSION['dns_attack_status'] = 'preparing';
            header('Location: dns_attack_exercise.php?success=' . urlencode('DNS攻撃状態をリセットしました'));
            exit;
            
        case 'set_target_domain':
            // 標的ドメインの設定
            $new_domain = trim($_POST['domain'] ?? '');
            if (!empty($new_domain)) {
                $_SESSION['dns_target_domain'] = $new_domain;
                header('Location: dns_attack_exercise.php?success=' . urlencode("標的ドメインを {$new_domain} に設定しました"));
            } else {
                header('Location: dns_attack_exercise.php?error=' . urlencode('有効なドメイン名を入力してください'));
            }
            exit;
    }
}

// 偽サイトのURL生成
$fake_login_url = "http://{$fake_server}/fake_login.php";
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>DNS攻撃演習</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .terminal {
            background: #0a0a0a;
            color: #00ff00;
            font-family: 'Courier New', monospace;
            padding: 20px;
            border-radius: 8px;
            max-height: 300px;
            overflow-y: auto;
        }
        .status-preparing { background: #fef3c7; color: #92400e; }
        .status-scanning { background: #dbeafe; color: #1e40af; }
        .status-exploiting { background: #fed7d7; color: #c53030; }
        .status-compromised { background: #d1fae5; color: #065f46; }
        .dns-record {
            font-family: 'Courier New', monospace;
            background: #f3f4f6;
            padding: 8px;
            border-radius: 4px;
            margin: 4px 0;
        }
    </style>
</head>
<body class="bg-gray-100">
<?php include 'header.php'; ?>

<div class="container mx-auto mt-6 p-4">
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
        <p class="font-bold">⚠️ 教育演習モード</p>
        <p>これはDNS攻撃の教育演習です。実際のDNSサーバは改ざんされません。</p>
    </div>

    <h1 class="text-3xl font-bold text-gray-800 mb-6">🌐 DNS攻撃演習</h1>

    <!-- 成功・エラーメッセージ -->
    <?php if (isset($_GET['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($_GET['success']) ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($_GET['error']) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- 左カラム: 攻撃制御パネル -->
        <div class="space-y-6">
            
            <!-- 現在の状態表示 -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">攻撃状態</h2>
                
                <?php
                $status_classes = [
                    'preparing' => 'status-preparing',
                    'scanning' => 'status-scanning', 
                    'exploiting' => 'status-exploiting',
                    'compromised' => 'status-compromised'
                ];
                $status_labels = [
                    'preparing' => '準備中',
                    'scanning' => 'DNSサーバスキャン中',
                    'exploiting' => 'DNS攻撃実行中',
                    'compromised' => 'DNS改ざん完了'
                ];
                ?>
                
                <div class="<?= $status_classes[$dns_status] ?> p-3 rounded-lg mb-4">
                    <strong>現在の状態: <?= $status_labels[$dns_status] ?></strong>
                </div>
                
                <!-- DNS情報 -->
                <div class="space-y-3">
                    <div class="dns-record">
                        <div class="text-sm text-gray-600">標的ドメイン</div>
                        <div class="font-bold text-blue-600"><?= htmlspecialchars($target_domain) ?></div>
                    </div>
                    
                    <div class="dns-record">
                        <div class="text-sm text-gray-600">偽サーバアドレス</div>
                        <div class="font-bold <?= $dns_status === 'compromised' ? 'text-red-600' : 'text-gray-600' ?>">
                            <?= htmlspecialchars($fake_server) ?>
                        </div>
                    </div>
                    
                    <?php if ($dns_status === 'compromised'): ?>
                    <div class="dns-record bg-red-50 border border-red-200">
                        <div class="text-sm text-red-600">改ざん状態</div>
                        <div class="font-bold text-red-700">
                            <?= htmlspecialchars($target_domain) ?> → <?= htmlspecialchars($fake_server) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 攻撃フェーズ制御 -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">攻撃フェーズ</h2>
                
                <div class="space-y-3">
                    <?php if ($dns_status === 'preparing'): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="scan_dns_servers">
                            <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700">
                                フェーズ 1: DNSサーバを発見・スキャン
                            </button>
                        </form>
                    <?php elseif ($dns_status === 'scanning'): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="exploit_dns_server">
                            <button type="submit" class="w-full bg-orange-600 text-white py-2 px-4 rounded hover:bg-orange-700">
                                フェーズ 2: DNSキャッシュポイズニング
                            </button>
                        </form>
                    <?php elseif ($dns_status === 'exploiting'): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="compromise_dns">
                            <button type="submit" class="w-full bg-red-600 text-white py-2 px-4 rounded hover:bg-red-700">
                                フェーズ 3: DNS改ざん実行
                            </button>
                        </form>
                    <?php elseif ($dns_status === 'compromised'): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 p-3 rounded">
                            ✅ DNS改ざんに成功しました！<br>
                            偽サイトへのリダイレクトが有効です。
                        </div>
                    <?php endif; ?>
                    
                    <!-- リセットボタン -->
                    <form method="POST">
                        <input type="hidden" name="action" value="reset_attack">
                        <button type="submit" class="w-full bg-gray-500 text-white py-2 px-4 rounded hover:bg-gray-600">
                            攻撃をリセット
                        </button>
                    </form>
                </div>
            </div>

            <!-- 標的ドメイン設定 -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">標的設定</h2>
                
                <form method="POST">
                    <input type="hidden" name="action" value="set_target_domain">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">標的ドメイン</label>
                        <input type="text" name="domain" value="<?= htmlspecialchars($target_domain) ?>" 
                               class="w-full border rounded px-3 py-2">
                    </div>
                    
                    <button type="submit" class="w-full bg-purple-600 text-white py-2 px-4 rounded hover:bg-purple-700">
                        標的を更新
                    </button>
                </form>
            </div>
        </div>

        <!-- 右カラム: ログと情報 -->
        <div class="space-y-6">
            
            <!-- ターミナル出力 -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">攻撃ログ</h2>
                
                <div class="terminal" id="terminal-output">
                    <div>[SYSTEM] DNS攻撃演習が開始されました</div>
                    <?php if ($dns_status !== 'preparing'): ?>
                    <div>[SCAN] DNSサーバのスキャンを実行中...</div>
                    <div>[SCAN] 8.8.8.8:53 - Google DNS detected</div>
                    <div>[SCAN] 1.1.1.1:53 - Cloudflare DNS detected</div>
                    <div>[SCAN] 192.168.1.1:53 - Local DNS server detected</div>
                    <div>[VULN] DNS cache poisoning vulnerability found!</div>
                    <?php endif; ?>
                    
                    <?php if (in_array($dns_status, ['exploiting', 'compromised'])): ?>
                    <div>[EXPLOIT] DNSキャッシュポイズニング攻撃を実行中...</div>
                    <div>[EXPLOIT] Spoofed DNS response packets sent</div>
                    <div>[EXPLOIT] Cache poisoning attempt #1...</div>
                    <div>[EXPLOIT] Cache poisoning attempt #2...</div>
                    <div>[EXPLOIT] DNS cache successfully poisoned!</div>
                    <?php endif; ?>
                    
                    <?php if ($dns_status === 'compromised'): ?>
                    <div style="color: #ff6b6b;">[COMPROMISE] DNS記録を改ざん中...</div>
                    <div style="color: #ff6b6b;">[COMPROMISE] <?= htmlspecialchars($target_domain) ?> A record modified</div>
                    <div style="color: #ff6b6b;">[COMPROMISE] Redirecting to <?= htmlspecialchars($fake_server) ?></div>
                    <div style="color: #00ff00;">[SUCCESS] DNS攻撃が成功しました！</div>
                    <div style="color: #00ff00;">[SUCCESS] 偽サイトへのリダイレクトが有効です</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- DNS攻撃の影響 -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">攻撃の影響</h2>
                
                <div class="space-y-3">
                    <div class="flex items-center">
                        <div class="w-4 h-4 rounded-full mr-3 <?= $dns_status === 'compromised' ? 'bg-red-500' : 'bg-gray-300' ?>"></div>
                        <span class="<?= $dns_status === 'compromised' ? 'text-red-600 font-semibold' : 'text-gray-500' ?>">
                            正規サイトへの偽装アクセス
                        </span>
                    </div>
                    
                    <div class="flex items-center">
                        <div class="w-4 h-4 rounded-full mr-3 <?= $dns_status === 'compromised' ? 'bg-red-500' : 'bg-gray-300' ?>"></div>
                        <span class="<?= $dns_status === 'compromised' ? 'text-red-600 font-semibold' : 'text-gray-500' ?>">
                            認証情報の窃取
                        </span>
                    </div>
                    
                    <div class="flex items-center">
                        <div class="w-4 h-4 rounded-full mr-3 <?= $dns_status === 'compromised' ? 'bg-red-500' : 'bg-gray-300' ?>"></div>
                        <span class="<?= $dns_status === 'compromised' ? 'text-red-600 font-semibold' : 'text-gray-500' ?>">
                            フィッシング攻撃の実現
                        </span>
                    </div>
                    
                    <div class="flex items-center">
                        <div class="w-4 h-4 rounded-full mr-3 <?= $dns_status === 'compromised' ? 'bg-red-500' : 'bg-gray-300' ?>"></div>
                        <span class="<?= $dns_status === 'compromised' ? 'text-red-600 font-semibold' : 'text-gray-500' ?>">
                            マルウェア配布の可能性
                        </span>
                    </div>
                </div>
            </div>

            <!-- 偽サイトテスト -->
            <?php if ($dns_status === 'compromised'): ?>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">攻撃テスト</h2>
                
                <div class="space-y-3">
                    <p class="text-sm text-gray-600">
                        DNS改ざんが有効になりました。以下のリンクで偽サイトをテストできます：
                    </p>
                    
                    <a href="<?= htmlspecialchars($fake_login_url) ?>" target="_blank" 
                       class="block w-full bg-red-600 text-white text-center py-2 px-4 rounded hover:bg-red-700">
                        偽ログインページを開く
                    </a>
                    
                    <div class="text-xs text-gray-500">
                        ※ 新しいタブで開きます。実際のDNS改ざんではユーザーは気づかずに偽サイトにアクセスします。
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- 検証ツール -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">検証ツール</h2>
                
                <div class="space-y-3">
                    <a href="ids_dashboard.php" class="block w-full bg-blue-600 text-white text-center py-2 px-4 rounded hover:bg-blue-700">
                        IDSダッシュボードで攻撃を確認
                    </a>
                    
                    <button onclick="viewCapturedCredentials()" class="w-full bg-green-600 text-white py-2 px-4 rounded hover:bg-green-700">
                        盗取された認証情報を表示
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 盗取された認証情報を表示
async function viewCapturedCredentials() {
    try {
        const response = await fetch('view_captured_credentials.php');
        const result = await response.json();
        
        if (result.success && result.credentials.length > 0) {
            let message = '盗取された認証情報:\n\n';
            result.credentials.forEach((cred, index) => {
                message += `${index + 1}. ユーザー名: ${cred.username}\n`;
                message += `   パスワード: ${cred.password}\n`;
                message += `   盗取時刻: ${cred.captured_at}\n`;
                message += `   送信元IP: ${cred.source_ip}\n\n`;
            });
            alert(message);
        } else {
            alert('盗取された認証情報はありません。');
        }
    } catch (e) {
        alert('認証情報の取得に失敗しました: ' + e.message);
    }
}

// IDSテストログ生成
async function createDnsTestLog() {
    try {
        await fetch('ids_event.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                attack_type: 'DNS Attack Test',
                detail: 'Test log entry to verify DNS attack detection',
                status_code: 200
            })
        });
        alert('DNSテストログを生成しました。IDSダッシュボードで確認してください。');
    } catch (e) {
        alert('テストログの生成に失敗しました: ' + e.message);
    }
}

// リアルタイム状態更新（10秒ごと）
setInterval(() => {
    if ('<?= $dns_status ?>' === 'compromised') {
        // DNS攻撃が完了している場合のみリロード
        const lastReload = sessionStorage.getItem('lastReload');
        const now = Date.now();
        if (!lastReload || (now - parseInt(lastReload)) > 30000) { // 30秒に1回のみ
            sessionStorage.setItem('lastReload', now.toString());
            location.reload();
        }
    }
}, 10000);
</script>

</body>
</html>