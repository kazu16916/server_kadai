<?php
require_once __DIR__ . '/common_init.php';
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 管理者のみアクセス可能
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: list.php');
    exit;
}

// 踏み台攻撃演習が有効でない場合は利用不可
if (empty($_SESSION['stepping_stone_enabled'])) {
    header('Location: simulation_tools.php?error=' . urlencode('踏み台攻撃演習が無効です。simulation_toolsで有効化してください。'));
    exit;
}

$attack_executed = false;
$attack_chain = [];
$final_target_compromised = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attack_type = $_POST['attack_type'] ?? '';
    $entry_point = $_POST['entry_point'] ?? '';
    $final_target = $_POST['final_target'] ?? '';
    $attack_method = $_POST['attack_method'] ?? '';
    
    $attack_executed = true;
    
    // 攻撃チェーンの構築
    $attack_chain = build_attack_chain($entry_point, $final_target, $attack_method);
    
    // 最終目標の侵害判定
    $final_target_compromised = evaluate_attack_success($attack_chain);
    
    // IDSログに記録
    if (function_exists('log_attack')) {
        $chain_summary = implode(' -> ', array_column($attack_chain, 'host'));
        log_attack($pdo, 'Stepping Stone Attack', 
            "Chain: {$chain_summary}, Method: {$attack_method}, Success: " . ($final_target_compromised ? 'Yes' : 'No'), 
            'stepping_stone_exercise.php', $final_target_compromised ? 200 : 403);
    }
}

function build_attack_chain($entry, $target, $method) {
    // 模擬ネットワーク環境
    $network_hosts = [
        'compromised_web_server' => [
            'ip' => '192.168.1.50',
            'hostname' => 'web01.company.com',
            'os' => 'Ubuntu 20.04',
            'services' => ['HTTP', 'SSH', 'MySQL'],
            'vulnerability' => 'Unpatched Apache',
            'access_level' => 'www-data'
        ],
        'internal_workstation' => [
            'ip' => '192.168.1.100',
            'hostname' => 'ws-accounting',
            'os' => 'Windows 10',
            'services' => ['RDP', 'SMB', 'WinRM'],
            'vulnerability' => 'Weak RDP credentials',
            'access_level' => 'user'
        ],
        'database_server' => [
            'ip' => '192.168.1.200',
            'hostname' => 'db-primary',
            'os' => 'CentOS 7',
            'services' => ['MySQL', 'SSH'],
            'vulnerability' => 'Default MySQL credentials',
            'access_level' => 'mysql'
        ],
        'domain_controller' => [
            'ip' => '192.168.1.10',
            'hostname' => 'dc01.company.local',
            'os' => 'Windows Server 2019',
            'services' => ['LDAP', 'Kerberos', 'DNS'],
            'vulnerability' => 'Kerberos ticket attack',
            'access_level' => 'domain_admin'
        ],
        'file_server' => [
            'ip' => '192.168.1.150',
            'hostname' => 'fileserver',
            'os' => 'Windows Server 2016',
            'services' => ['SMB', 'FTP'],
            'vulnerability' => 'SMB misconfiguration',
            'access_level' => 'administrator'
        ]
    ];
    
    $chain = [];
    $current_host = $entry;
    
    // エントリーポイント
    if (isset($network_hosts[$current_host])) {
        $host_info = $network_hosts[$current_host];
        $chain[] = [
            'step' => 1,
            'host' => $host_info['hostname'],
            'ip' => $host_info['ip'],
            'method' => 'Initial compromise',
            'vulnerability' => $host_info['vulnerability'],
            'success' => true,
            'access_gained' => $host_info['access_level'],
            'timestamp' => date('H:i:s'),
            'duration' => rand(30, 120) . 's'
        ];
    }
    
    // 中間ホップの生成（攻撃手法に応じて）
    $intermediate_hops = generate_intermediate_hops($entry, $target, $method, $network_hosts);
    
    foreach ($intermediate_hops as $hop) {
        $chain[] = $hop;
    }
    
    // 最終ターゲット
    if (isset($network_hosts[$target]) && $target !== $current_host) {
        $target_info = $network_hosts[$target];
        $success = calculate_final_success($chain, $method);
        
        $chain[] = [
            'step' => count($chain) + 1,
            'host' => $target_info['hostname'],
            'ip' => $target_info['ip'],
            'method' => get_final_attack_method($method),
            'vulnerability' => $target_info['vulnerability'],
            'success' => $success,
            'access_gained' => $success ? $target_info['access_level'] : 'none',
            'timestamp' => date('H:i:s', time() + count($chain) * 30),
            'duration' => rand(60, 300) . 's',
            'is_final_target' => true
        ];
    }
    
    return $chain;
}

function generate_intermediate_hops($entry, $target, $method, $hosts) {
    $hops = [];
    $step = 2;
    
    // 攻撃手法に応じた中間ステップ
    switch ($method) {
        case 'lateral_movement':
            if ($entry === 'compromised_web_server' && $target === 'domain_controller') {
                $hops[] = [
                    'step' => $step++,
                    'host' => 'ws-accounting',
                    'ip' => '192.168.1.100',
                    'method' => 'RDP brute force',
                    'vulnerability' => 'Weak credentials',
                    'success' => true,
                    'access_gained' => 'user',
                    'timestamp' => date('H:i:s', time() + 60),
                    'duration' => '45s'
                ];
                
                $hops[] = [
                    'step' => $step++,
                    'host' => 'ws-accounting',
                    'ip' => '192.168.1.100',
                    'method' => 'Privilege escalation',
                    'vulnerability' => 'Windows UAC bypass',
                    'success' => true,
                    'access_gained' => 'administrator',
                    'timestamp' => date('H:i:s', time() + 120),
                    'duration' => '90s'
                ];
            }
            break;
            
        case 'pivot_attack':
            $hops[] = [
                'step' => $step++,
                'host' => 'Internal proxy setup',
                'ip' => $hosts[$entry]['ip'],
                'method' => 'SOCKS proxy establishment',
                'vulnerability' => 'Open network access',
                'success' => true,
                'access_gained' => 'network_access',
                'timestamp' => date('H:i:s', time() + 30),
                'duration' => '15s'
            ];
            break;
            
        case 'port_forwarding':
            $hops[] = [
                'step' => $step++,
                'host' => 'Tunnel establishment',
                'ip' => $hosts[$entry]['ip'],
                'method' => 'SSH port forwarding',
                'vulnerability' => 'SSH access available',
                'success' => true,
                'access_gained' => 'tunnel_access',
                'timestamp' => date('H:i:s', time() + 45),
                'duration' => '20s'
            ];
            break;
    }
    
    return $hops;
}

function get_final_attack_method($method) {
    $methods = [
        'lateral_movement' => 'Domain admin credential dump',
        'pivot_attack' => 'Direct service exploitation',
        'port_forwarding' => 'Tunneled attack',
        'credential_stuffing' => 'Credential reuse attack'
    ];
    
    return $methods[$method] ?? 'Direct compromise attempt';
}

function calculate_final_success($chain, $method) {
    $success_rates = [
        'lateral_movement' => 0.85,
        'pivot_attack' => 0.70,
        'port_forwarding' => 0.75,
        'credential_stuffing' => 0.60
    ];
    
    $base_rate = $success_rates[$method] ?? 0.50;
    $chain_bonus = min(0.2, count($chain) * 0.05); // 踏み台が多いほど成功率向上
    
    return (mt_rand() / mt_getrandmax()) < ($base_rate + $chain_bonus);
}

function evaluate_attack_success($chain) {
    if (empty($chain)) return false;
    
    $final_step = end($chain);
    return $final_step['success'] ?? false;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>踏み台攻撃演習</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .network-node {
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        .node-compromised {
            border-color: #ef4444;
            background: linear-gradient(45deg, #fee2e2, #fecaca);
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.3);
            animation: pulse-red 2s infinite;
        }
        .node-accessing {
            border-color: #f59e0b;
            background: linear-gradient(45deg, #fef3c7, #fed7aa);
            animation: pulse-amber 1s infinite;
        }
        .attack-arrow {
            stroke: #ef4444;
            stroke-width: 3;
            marker-end: url(#arrowhead);
            animation: dash 2s linear infinite;
        }
        .attack-arrow-success {
            stroke: #10b981;
            animation: none;
        }
        .attack-arrow-failed {
            stroke: #6b7280;
            animation: none;
            opacity: 0.5;
        }
        @keyframes pulse-red {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        @keyframes pulse-amber {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        @keyframes dash {
            to { stroke-dashoffset: -20; }
        }
        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .step-animation {
            animation: slideInUp 0.5s ease-out;
        }
    </style>
</head>
<body class="bg-gray-900 text-white">
<?php include 'header.php'; ?>

<div class="container mx-auto mt-10 p-4">
    <!-- 警告ヘッダー -->
    <div class="bg-gradient-to-r from-orange-600 to-red-700 p-4 rounded-lg mb-8 text-center">
        <h1 class="text-3xl font-bold mb-2">🔗 踏み台攻撃演習 🔗</h1>
        <p class="text-lg">これは教育目的の模擬演習です。実際のネットワーク侵入は発生しません。</p>
    </div>

    <?php if ($attack_executed): ?>
        <!-- 攻撃結果の表示 -->
        <div class="bg-gray-800 border border-gray-700 p-6 rounded-lg mb-8">
            <div class="flex items-center mb-4">
                <div class="text-orange-400 mr-3 text-2xl">🎯</div>
                <h2 class="text-xl font-bold text-orange-300">
                    踏み台攻撃シミュレーション <?= $final_target_compromised ? '成功' : '失敗' ?>！
                </h2>
            </div>
            
            <!-- 攻撃チェーン可視化 -->
            <div class="bg-black p-6 rounded-lg mb-6">
                <h3 class="text-lg font-semibold mb-4 text-blue-300">攻撃経路の可視化</h3>
                <div class="space-y-4">
                    <?php foreach ($attack_chain as $i => $step): ?>
                        <div class="step-animation flex items-center" style="animation-delay: <?= $i * 0.5 ?>s">
                            <!-- ステップ番号 -->
                            <div class="flex-shrink-0 w-8 h-8 rounded-full <?= $step['success'] ? 'bg-green-600' : 'bg-red-600' ?> flex items-center justify-center text-sm font-bold mr-4">
                                <?= $step['step'] ?>
                            </div>
                            
                            <!-- ホスト情報 -->
                            <div class="flex-grow bg-gray-900 p-4 rounded border <?= $step['success'] ? 'border-green-500' : 'border-red-500' ?>">
                                <div class="flex justify-between items-start">
                                    <div class="flex-grow">
                                        <div class="flex items-center mb-2">
                                            <span class="font-bold text-white"><?= htmlspecialchars($step['host']) ?></span>
                                            <span class="ml-3 text-gray-400"><?= htmlspecialchars($step['ip']) ?></span>
                                            <?php if (!empty($step['is_final_target'])): ?>
                                                <span class="ml-3 px-2 py-1 bg-purple-600 text-xs rounded">最終目標</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-sm text-gray-300 mb-1">
                                            <strong>攻撃手法:</strong> <?= htmlspecialchars($step['method']) ?>
                                        </div>
                                        <div class="text-sm text-gray-300">
                                            <strong>脆弱性:</strong> <?= htmlspecialchars($step['vulnerability']) ?>
                                        </div>
                                    </div>
                                    <div class="text-right text-sm">
                                        <div class="text-gray-400"><?= htmlspecialchars($step['timestamp']) ?></div>
                                        <div class="text-gray-400"><?= htmlspecialchars($step['duration']) ?></div>
                                        <div class="mt-1">
                                            <?php if ($step['success']): ?>
                                                <span class="px-2 py-1 bg-green-600 text-xs rounded">成功</span>
                                                <div class="text-xs text-green-300 mt-1">権限: <?= htmlspecialchars($step['access_gained']) ?></div>
                                            <?php else: ?>
                                                <span class="px-2 py-1 bg-red-600 text-xs rounded">失敗</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 矢印（最後以外） -->
                        <?php if ($i < count($attack_chain) - 1): ?>
                            <div class="flex justify-center py-2">
                                <svg width="30" height="30" class="text-gray-500">
                                    <defs>
                                        <marker id="arrowhead" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">
                                            <polygon points="0 0, 10 3.5, 0 7" fill="currentColor" />
                                        </marker>
                                    </defs>
                                    <line x1="15" y1="5" x2="15" y2="25" stroke="currentColor" stroke-width="2" marker-end="url(#arrowhead)" />
                                </svg>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- 攻撃サマリー -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-gray-900 p-4 rounded border">
                    <div class="text-sm text-gray-400">総ステップ数</div>
                    <div class="text-2xl font-bold"><?= count($attack_chain) ?></div>
                </div>
                <div class="bg-gray-900 p-4 rounded border">
                    <div class="text-sm text-gray-400">成功ステップ</div>
                    <div class="text-2xl font-bold text-green-400">
                        <?= count(array_filter($attack_chain, fn($s) => $s['success'])) ?>
                    </div>
                </div>
                <div class="bg-gray-900 p-4 rounded border">
                    <div class="text-sm text-gray-400">最終結果</div>
                    <div class="text-2xl font-bold <?= $final_target_compromised ? 'text-red-400' : 'text-green-400' ?>">
                        <?= $final_target_compromised ? '侵害成功' : '防御成功' ?>
                    </div>
                </div>
            </div>
            
            <div class="mt-4 flex gap-3">
                <a href="stepping_stone_exercise.php" class="bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700">新しい攻撃</a>
                <a href="ids_dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">IDSログを確認</a>
            </div>
        </div>
    <?php endif; ?>

    <!-- 攻撃設定フォーム -->
    <div class="bg-gray-800 p-8 rounded-lg shadow-lg">
        <h2 class="text-2xl font-bold mb-6 text-center text-orange-400">踏み台攻撃設定</h2>
        
        <form method="POST" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="entry_point" class="block text-sm font-medium mb-2">エントリーポイント（最初の侵害対象）</label>
                    <select id="entry_point" name="entry_point" class="w-full bg-gray-700 border border-gray-600 rounded-md px-3 py-2">
                        <option value="compromised_web_server">侵害されたWebサーバー (192.168.1.50)</option>
                        <option value="internal_workstation">内部ワークステーション (192.168.1.100)</option>
                        <option value="database_server">データベースサーバー (192.168.1.200)</option>
                    </select>
                </div>
                
                <div>
                    <label for="final_target" class="block text-sm font-medium mb-2">最終目標</label>
                    <select id="final_target" name="final_target" class="w-full bg-gray-700 border border-gray-600 rounded-md px-3 py-2">
                        <option value="domain_controller">ドメインコントローラー (192.168.1.10)</option>
                        <option value="file_server">ファイルサーバー (192.168.1.150)</option>
                        <option value="database_server">機密データベース (192.168.1.200)</option>
                    </select>
                </div>
            </div>
            
            <div>
                <label for="attack_method" class="block text-sm font-medium mb-2">攻撃手法</label>
                <select id="attack_method" name="attack_method" class="w-full bg-gray-700 border border-gray-600 rounded-md px-3 py-2">
                    <option value="lateral_movement">水平展開 (Lateral Movement)</option>
                    <option value="pivot_attack">ピボット攻撃</option>
                    <option value="port_forwarding">ポートフォワーディング</option>
                    <option value="credential_stuffing">認証情報の使いまわし</option>
                </select>
            </div>
            
            <button type="submit" name="attack_type" value="stepping_stone" 
                    class="w-full bg-orange-600 text-white py-3 rounded-md hover:bg-orange-700 font-semibold text-lg">
                🚀 踏み台攻撃を実行
            </button>
        </form>
    </div>

    <!-- 教育的説明 -->
    <div class="bg-gray-800 p-6 rounded-lg mt-8">
        <h3 class="text-lg font-bold mb-4 text-blue-400">踏み台攻撃について</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm text-gray-300">
            <div>
                <h4 class="font-semibold text-white mb-2">攻撃の特徴</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>複数のホストを経由して最終目標に到達</li>
                    <li>直接アクセスできない内部システムへの侵入</li>
                    <li>各ステップで権限昇格や横展開を実行</li>
                    <li>検知を困難にする多段階攻撃</li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold text-white mb-2">対策技術</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>ネットワークセグメンテーション</li>
                    <li>最小権限の原則</li>
                    <li>横展開検知システム</li>
                    <li>多層防御アーキテクチャ</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
// フォーム送信前の確認
document.querySelector('form').addEventListener('submit', function(e) {
    const entryPoint = document.getElementById('entry_point').selectedOptions[0].text;
    const finalTarget = document.getElementById('final_target').selectedOptions[0].text;
    const attackMethod = document.getElementById('attack_method').selectedOptions[0].text;
    
    const confirmed = confirm(
        `踏み台攻撃を実行しますか？\n\n` +
        `エントリーポイント: ${entryPoint}\n` +
        `最終目標: ${finalTarget}\n` +
        `攻撃手法: ${attackMethod}\n\n` +
        `この攻撃は教育演習用の模擬実行です。`
    );
    
    if (!confirmed) {
        e.preventDefault();
    }
});
</script>

</body>
</html>