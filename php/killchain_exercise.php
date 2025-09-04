<?php
require_once __DIR__ . '/common_init.php';
require 'db.php';

// 管理者権限チェック
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: list.php');
    exit;
}

// サイバーキルチェーン演習が有効化されているかチェック
if (empty($_SESSION['killchain_attack_enabled'])) {
    header('Location: simulation_tools.php?error=' . urlencode('サイバーキルチェーン演習が有効化されていません。'));
    exit;
}

// AJAX リクエスト処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    
    $action = $_POST['action'] ?? '';
    
    // IDS通知
    if ($action === 'log_attack') {
        $phase = $_POST['phase'] ?? '';
        $detail = $_POST['detail'] ?? '';
        $status = (int)($_POST['status'] ?? 200);
        
        try {
            if (function_exists('log_attack')) {
                log_attack($pdo, 'Cyber Kill Chain: ' . $phase, $detail, 'killchain_exercise', $status);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'log_attack function not available']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    // キルチェーン段階実行
    if ($action === 'execute_phase') {
        $phase = $_POST['phase'] ?? '';
        $target = $_POST['target'] ?? '';
        $method = $_POST['method'] ?? '';
        
        // 各段階の成功率を設定
        $success_rates = [
            'reconnaissance' => 1,
            'weaponization' => 1,
            'delivery' => 1,
            'exploitation' => 1,
            'installation' => 1,
            'command_control' => 1,
            'actions_objectives' => 1
        ];
        
        $success_rate = $success_rates[$phase] ?? 0.50;
        $success = (rand(1, 100) / 100) <= $success_rate;
        
        // 段階別の詳細結果を生成
        $results = generatePhaseResults($phase, $target, $method, $success);
        
        echo json_encode([
            'success' => true,
            'phase_success' => $success,
            'results' => $results,
            'next_phase' => getNextPhase($phase)
        ]);
        exit;
    }
}

function generatePhaseResults($phase, $target, $method, $success) {
    $base_results = [
        'phase' => $phase,
        'target' => $target,
        'method' => $method,
        'success' => $success,
        'timestamp' => date('Y-m-d H:i:s'),
        'details' => [],
        'artifacts' => [],
        'next_steps' => []
    ];
    
    switch ($phase) {
        case 'reconnaissance':
            $base_results['details'] = [
                'target_ip' => '192.168.1.' . rand(10, 254),
                'open_ports' => $success ? ['22/ssh', '80/http', '443/https', '3389/rdp'] : ['443/https'],
                'os_detection' => $success ? 'Ubuntu 18.04.6 LTS' : 'OS detection failed',
                'email_addresses' => $success ? ['admin@target.com', 'support@target.com'] : [],
                'subdomains' => $success ? ['mail.target.com', 'ftp.target.com'] : []
            ];
            $base_results['artifacts'] = ['nmap_scan.txt', 'whois_info.txt'];
            break;
            
        case 'weaponization':
            $base_results['details'] = [
                'payload_type' => $method,
                'target_vulnerability' => $success ? 'CVE-2021-44228 (Log4Shell)' : 'No suitable vulnerability found',
                'payload_size' => $success ? rand(2048, 8192) . ' bytes' : 'N/A',
                'encoding' => $success ? 'Base64 + XOR' : 'Failed',
                'evasion_techniques' => $success ? ['Polymorphic code', 'Anti-debugging', 'Delayed execution'] : []
            ];
            $base_results['artifacts'] = $success ? ['malicious_payload.exe', 'dropper.js'] : [];
            break;
            
        case 'delivery':
            $base_results['details'] = [
                'delivery_method' => $method,
                'success_rate' => $success ? rand(15, 35) . '%' : '0%',
                'targets_reached' => $success ? rand(50, 200) : 0,
                'clicked_count' => $success ? rand(5, 30) : 0,
                'av_detection' => $success ? (rand(1, 10) <= 3 ? 'Detected by 2/10 engines' : 'Clean') : 'Blocked'
            ];
            $base_results['artifacts'] = $success ? ['phishing_email.eml', 'landing_page.html'] : [];
            break;
            
        case 'exploitation':
            $base_results['details'] = [
                'exploit_method' => $method,
                'vulnerability' => $success ? 'Buffer overflow in web service' : 'Exploitation failed',
                'privileges_gained' => $success ? 'SYSTEM/root' : 'None',
                'shell_type' => $success ? 'Reverse TCP shell' : 'N/A',
                'persistence_method' => $success ? 'Registry modification' : 'N/A'
            ];
            $base_results['artifacts'] = $success ? ['exploit_code.py', 'shell_commands.log'] : [];
            break;
            
        case 'installation':
            $base_results['details'] = [
                'malware_family' => $success ? 'Custom RAT' : 'Installation failed',
                'installation_path' => $success ? 'C:\\Windows\\System32\\svchost.exe' : 'N/A',
                'persistence_mechanisms' => $success ? ['Registry Run key', 'Scheduled task', 'Service installation'] : [],
                'hiding_techniques' => $success ? ['Process hollowing', 'Rootkit techniques'] : [],
                'communication_protocol' => $success ? 'HTTPS over port 443' : 'N/A'
            ];
            $base_results['artifacts'] = $success ? ['rat_client.exe', 'config.encrypted'] : [];
            break;
            
        case 'command_control':
            $base_results['details'] = [
                'c2_server' => $success ? 'https://legitimate-site.com/api/' : 'Connection failed',
                'communication_frequency' => $success ? 'Every 5 minutes' : 'N/A',
                'data_exfiltration_ready' => $success ? 'Yes' : 'No',
                'additional_tools_deployed' => $success ? ['Keylogger', 'Screenshot tool', 'Network scanner'] : [],
                'lateral_movement_ready' => $success ? 'Yes' : 'No'
            ];
            $base_results['artifacts'] = $success ? ['c2_traffic.pcap', 'beacon_config.json'] : [];
            break;
            
        case 'actions_objectives':
            $base_results['details'] = [
                'objective_type' => $method,
                'data_collected' => $success ? rand(1024, 10240) . ' MB' : '0 MB',
                'files_accessed' => $success ? ['customer_db.sql', 'financial_reports.xlsx', 'employee_data.csv'] : [],
                'systems_compromised' => $success ? rand(5, 25) : 0,
                'mission_success' => $success ? 'Complete' : 'Failed'
            ];
            $base_results['artifacts'] = $success ? ['exfiltrated_data.zip', 'access_log.txt'] : [];
            break;
    }
    
    return $base_results;
}

function getNextPhase($current_phase) {
    $phases = [
        'reconnaissance' => 'weaponization',
        'weaponization' => 'delivery',
        'delivery' => 'exploitation',
        'exploitation' => 'installation',
        'installation' => 'command_control',
        'command_control' => 'actions_objectives',
        'actions_objectives' => null
    ];
    
    return $phases[$current_phase] ?? null;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>サイバーキルチェーン演習</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .killchain-phase {
            transition: all 0.3s ease-in-out;
            position: relative;
        }
        .phase-pending {
            background: #f8fafc;
            border-color: #e2e8f0;
            opacity: 0.7;
        }
        .phase-active {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            border-color: #3b82f6;
            color: white;
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.4);
        }
        .phase-success {
            background: linear-gradient(135deg, #059669, #10b981);
            border-color: #10b981;
            color: white;
        }
        .phase-failed {
            background: linear-gradient(135deg, #dc2626, #ef4444);
            border-color: #ef4444;
            color: white;
        }
        .killchain-arrow {
            position: absolute;
            right: -20px;
            top: 50%;
            transform: translateY(-50%);
            width: 0;
            height: 0;
            border-top: 15px solid transparent;
            border-bottom: 15px solid transparent;
            border-left: 20px solid #e2e8f0;
            z-index: 10;
        }
        .phase-success .killchain-arrow {
            border-left-color: #10b981;
        }
        .phase-failed .killchain-arrow {
            border-left-color: #ef4444;
        }
        .phase-active .killchain-arrow {
            border-left-color: #3b82f6;
        }
        .target-network {
            background: radial-gradient(circle, #1e3a8a, #1e40af);
            color: white;
        }
        .attack-log {
            background: #000;
            color: #00ff00;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            max-height: 400px;
            overflow-y: auto;
            border-radius: 8px;
            padding: 16px;
        }
        .phase-details {
            display: none;
        }
        .phase-details.active {
            display: block;
        }
        .artifact-item {
            background: #1f2937;
            color: #e5e7eb;
            padding: 0.5rem;
            border-radius: 0.375rem;
            font-family: monospace;
            font-size: 0.875rem;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
<?php include 'header.php'; ?>

<div class="container mx-auto mt-10 p-4">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">サイバーキルチェーン演習</h1>
            <p class="text-gray-600">Lockheed Martinのキルチェーンモデルに基づく7段階の系統的攻撃シミュレーション</p>
        </div>
        <div class="text-right">
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-3 rounded">
                <p class="font-bold">⚠️ 教育目的の演習</p>
                <p class="text-sm">実際の攻撃は行いません</p>
            </div>
        </div>
    </div>

    <!-- 攻撃設定パネル -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h2 class="text-xl font-semibold mb-4">🎯 攻撃設定</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="target-organization" class="block text-sm font-medium text-gray-700 mb-1">標的組織</label>
                <select id="target-organization" class="w-full border rounded-lg px-3 py-2">
                    <option value="financial">大手銀行</option>
                    <option value="government">政府機関</option>
                    <option value="healthcare">大手病院</option>
                    <option value="manufacturing">製造企業</option>
                    <option value="retail">小売チェーン</option>
                </select>
            </div>
            <div>
                <label for="attacker-profile" class="block text-sm font-medium text-gray-700 mb-1">攻撃者プロファイル</label>
                <select id="attacker-profile" class="w-full border rounded-lg px-3 py-2">
                    <option value="apt">国家支援型APT</option>
                    <option value="cybercrime">サイバー犯罪組織</option>
                    <option value="hacktivist">ハクティビスト</option>
                    <option value="insider">内部脅威</option>
                </select>
            </div>
            <div>
                <label for="attack-sophistication" class="block text-sm font-medium text-gray-700 mb-1">攻撃レベル</label>
                <select id="attack-sophistication" class="w-full border rounded-lg px-3 py-2">
                    <option value="basic">基本レベル</option>
                    <option value="intermediate" selected>中級レベル</option>
                    <option value="advanced">上級レベル</option>
                    <option value="expert">専門レベル</option>
                </select>
            </div>
        </div>
        <div class="mt-4 flex gap-4">
            <button id="start-killchain-btn" class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg font-semibold">
                キルチェーン攻撃開始
            </button>
            <button id="reset-killchain-btn" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg font-semibold">
                リセット
            </button>
            <button id="auto-execute-btn" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-lg font-semibold">
                自動実行モード
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- 左側：キルチェーン段階 -->
        <div class="lg:col-span-2 space-y-4">
            <h2 class="text-xl font-semibold">📋 サイバーキルチェーン段階</h2>
            
            <!-- 段階1: 偵察 (Reconnaissance) -->
            <div class="killchain-phase phase-pending border-2 p-4 rounded-lg relative" data-phase="reconnaissance">
                <div class="killchain-arrow"></div>
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-bold text-lg">🔍 1. 偵察 (Reconnaissance)</h3>
                    <div class="flex gap-2">
                        <span class="phase-status text-sm px-2 py-1 rounded bg-gray-200">待機中</span>
                        <button class="execute-phase-btn bg-blue-500 text-white px-3 py-1 rounded text-sm" data-phase="reconnaissance">
                            実行
                        </button>
                    </div>
                </div>
                <p class="text-sm mb-3">標的の情報収集と脆弱性調査</p>
                <div class="mb-2">
                    <label class="block text-xs font-medium mb-1">偵察手法:</label>
                    <select class="phase-method w-full text-sm border rounded px-2 py-1" data-phase="reconnaissance">
                        <option value="passive_osint">パッシブOSINT</option>
                        <option value="active_scanning">アクティブスキャン</option>
                        <option value="social_engineering">ソーシャルエンジニアリング</option>
                        <option value="insider_info">内部情報活用</option>
                    </select>
                </div>
                <div class="phase-details">
                    <div class="text-xs space-y-1" id="reconnaissance-details"></div>
                </div>
            </div>

            <!-- 段階2: 武器化 (Weaponization) -->
            <div class="killchain-phase phase-pending border-2 p-4 rounded-lg relative" data-phase="weaponization">
                <div class="killchain-arrow"></div>
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-bold text-lg">⚔️ 2. 武器化 (Weaponization)</h3>
                    <div class="flex gap-2">
                        <span class="phase-status text-sm px-2 py-1 rounded bg-gray-200">待機中</span>
                        <button class="execute-phase-btn bg-blue-500 text-white px-3 py-1 rounded text-sm" data-phase="weaponization" disabled>
                            実行
                        </button>
                    </div>
                </div>
                <p class="text-sm mb-3">悪意のあるペイロードの作成と準備</p>
                <div class="mb-2">
                    <label class="block text-xs font-medium mb-1">武器化手法:</label>
                    <select class="phase-method w-full text-sm border rounded px-2 py-1" data-phase="weaponization">
                        <option value="malware_creation">カスタムマルウェア作成</option>
                        <option value="exploit_kit">エクスプロイトキット使用</option>
                        <option value="document_weaponization">文書ファイル武器化</option>
                        <option value="supply_chain">サプライチェーン侵害</option>
                    </select>
                </div>
                <div class="phase-details">
                    <div class="text-xs space-y-1" id="weaponization-details"></div>
                </div>
            </div>

            <!-- 段階3: 配送 (Delivery) -->
            <div class="killchain-phase phase-pending border-2 p-4 rounded-lg relative" data-phase="delivery">
                <div class="killchain-arrow"></div>
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-bold text-lg">📧 3. 配送 (Delivery)</h3>
                    <div class="flex gap-2">
                        <span class="phase-status text-sm px-2 py-1 rounded bg-gray-200">待機中</span>
                        <button class="execute-phase-btn bg-blue-500 text-white px-3 py-1 rounded text-sm" data-phase="delivery" disabled>
                            実行
                        </button>
                    </div>
                </div>
                <p class="text-sm mb-3">標的への武器化されたペイロードの送達</p>
                <div class="mb-2">
                    <label class="block text-xs font-medium mb-1">配送手法:</label>
                    <select class="phase-method w-full text-sm border rounded px-2 py-1" data-phase="delivery">
                        <option value="spear_phishing">スピアフィッシング</option>
                        <option value="watering_hole">水飲み場攻撃</option>
                        <option value="usb_drop">USB配置攻撃</option>
                        <option value="compromised_website">侵害サイト経由</option>
                    </select>
                </div>
                <div class="phase-details">
                    <div class="text-xs space-y-1" id="delivery-details"></div>
                </div>
            </div>

            <!-- 段階4: 悪用 (Exploitation) -->
            <div class="killchain-phase phase-pending border-2 p-4 rounded-lg relative" data-phase="exploitation">
                <div class="killchain-arrow"></div>
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-bold text-lg">💥 4. 悪用 (Exploitation)</h3>
                    <div class="flex gap-2">
                        <span class="phase-status text-sm px-2 py-1 rounded bg-gray-200">待機中</span>
                        <button class="execute-phase-btn bg-blue-500 text-white px-3 py-1 rounded text-sm" data-phase="exploitation" disabled>
                            実行
                        </button>
                    </div>
                </div>
                <p class="text-sm mb-3">脆弱性を悪用した初期侵入の実行</p>
                <div class="mb-2">
                    <label class="block text-xs font-medium mb-1">悪用手法:</label>
                    <select class="phase-method w-full text-sm border rounded px-2 py-1" data-phase="exploitation">
                        <option value="buffer_overflow">バッファオーバーフロー</option>
                        <option value="zero_day">ゼロデイ脆弱性</option>
                        <option value="known_vulnerability">既知脆弱性</option>
                        <option value="social_exploitation">ソーシャル悪用</option>
                    </select>
                </div>
                <div class="phase-details">
                    <div class="text-xs space-y-1" id="exploitation-details"></div>
                </div>
            </div>

            <!-- 段階5: 設置 (Installation) -->
            <div class="killchain-phase phase-pending border-2 p-4 rounded-lg relative" data-phase="installation">
                <div class="killchain-arrow"></div>
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-bold text-lg">⚙️ 5. 設置 (Installation)</h3>
                    <div class="flex gap-2">
                        <span class="phase-status text-sm px-2 py-1 rounded bg-gray-200">待機中</span>
                        <button class="execute-phase-btn bg-blue-500 text-white px-3 py-1 rounded text-sm" data-phase="installation" disabled>
                            実行
                        </button>
                    </div>
                </div>
                <p class="text-sm mb-3">永続的なアクセスのためのマルウェア設置</p>
                <div class="mb-2">
                    <label class="block text-xs font-medium mb-1">設置手法:</label>
                    <select class="phase-method w-full text-sm border rounded px-2 py-1" data-phase="installation">
                        <option value="backdoor_installation">バックドア設置</option>
                        <option value="rootkit_deployment">ルートキット配備</option>
                        <option value="service_installation">サービス設置</option>
                        <option value="registry_persistence">レジストリ永続化</option>
                    </select>
                </div>
                <div class="phase-details">
                    <div class="text-xs space-y-1" id="installation-details"></div>
                </div>
            </div>

            <!-- 段階6: 指令制御 (Command & Control) -->
            <div class="killchain-phase phase-pending border-2 p-4 rounded-lg relative" data-phase="command_control">
                <div class="killchain-arrow"></div>
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-bold text-lg">🎮 6. 指令制御 (C&C)</h3>
                    <div class="flex gap-2">
                        <span class="phase-status text-sm px-2 py-1 rounded bg-gray-200">待機中</span>
                        <button class="execute-phase-btn bg-blue-500 text-white px-3 py-1 rounded text-sm" data-phase="command_control" disabled>
                            実行
                        </button>
                    </div>
                </div>
                <p class="text-sm mb-3">外部サーバーとの通信チャネル確立</p>
                <div class="mb-2">
                    <label class="block text-xs font-medium mb-1">C&C手法:</label>
                    <select class="phase-method w-full text-sm border rounded px-2 py-1" data-phase="command_control">
                        <option value="https_beacon">HTTPS ビーコン</option>
                        <option value="dns_tunneling">DNS トンネリング</option>
                        <option value="social_media">SNS経由通信</option>
                        <option value="p2p_network">P2Pネットワーク</option>
                    </select>
                </div>
                <div class="phase-details">
                    <div class="text-xs space-y-1" id="command_control-details"></div>
                </div>
            </div>

            <!-- 段階7: 目的達成 (Actions on Objectives) -->
            <div class="killchain-phase phase-pending border-2 p-4 rounded-lg relative" data-phase="actions_objectives">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-bold text-lg">🎯 7. 目的達成 (Actions on Objectives)</h3>
                    <div class="flex gap-2">
                        <span class="phase-status text-sm px-2 py-1 rounded bg-gray-200">待機中</span>
                        <button class="execute-phase-btn bg-blue-500 text-white px-3 py-1 rounded text-sm" data-phase="actions_objectives" disabled>
                            実行
                        </button>
                    </div>
                </div>
                <p class="text-sm mb-3">最終的な攻撃目標の実行</p>
                <div class="mb-2">
                    <label class="block text-xs font-medium mb-1">目的:</label>
                    <select class="phase-method w-full text-sm border rounded px-2 py-1" data-phase="actions_objectives">
                        <option value="data_exfiltration">データ窃取</option>
                        <option value="system_destruction">システム破壊</option>
                        <option value="ransomware_deployment">ランサムウェア展開</option>
                        <option value="espionage">長期スパイ活動</option>
                    </select>
                </div>
                <div class="phase-details">
                    <div class="text-xs space-y-1" id="actions_objectives-details"></div>
                </div>
            </div>
        </div>

        <!-- 右側：モニタリングとログ -->
        <div class="space-y-6">
            <!-- 攻撃統計 -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">📊 攻撃統計</h2>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span>完了段階:</span>
                        <span id="completed-phases" class="font-bold">0 / 7</span>
                    </div>
                    <div class="flex justify-between">
                        <span>成功率:</span>
                        <span id="success-rate" class="font-bold text-green-600">0%</span>
                    </div>
                    <div class="flex justify-between">
                        <span>経過時間:</span>
                        <span id="elapsed-time" class="font-bold">00:00</span>
                    </div>
                    <div class="flex justify-between">
                        <span>検知回数:</span>
                        <span id="detection-count" class="font-bold text-red-600">0</span>
                    </div>
                </div>
            </div>

            <!-- 標的ネットワーク状況 -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">🏢 標的ネットワーク状況</h2>
                <div class="target-network p-4 rounded-lg">
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div class="flex items-center">
                            <span class="w-3 h-3 bg-green-500 rounded-full mr-2" id="firewall-status"></span>
                            <span>ファイアウォール</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-3 h-3 bg-green-500 rounded-full mr-2" id="ids-status"></span>
                            <span>IDS/IPS</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-3 h-3 bg-green-500 rounded-full mr-2" id="av-status"></span>
                            <span>アンチウイルス</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-3 h-3 bg-green-500 rounded-full mr-2" id="email-security"></span>
                            <span>メールセキュリティ</span>
                        </div>
                    </div>
                    <div class="mt-3 text-xs">
                        <div>侵害レベル: <span id="compromise-level" class="font-bold">0%</span></div>
                        <div>アラート数: <span id="alert-count" class="font-bold">0</span></div>
                    </div>
                </div>
            </div>

            <!-- 実行ログ -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">🔍 実行ログ</h2>
                <div id="attack-log" class="attack-log">
                    <div>[SYSTEM] サイバーキルチェーン演習システム起動</div>
                    <div>[INFO] 攻撃設定を行い、実行ボタンを押してください</div>
                </div>
            </div>

            <!-- 収集した成果物 -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">📦 収集した成果物</h2>
                <div id="artifacts-list" class="space-y-2">
                    <p class="text-gray-500 text-sm">攻撃段階を実行すると成果物が表示されます</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
class KillChainSimulator {
    constructor() {
        this.isRunning = false;
        this.currentPhase = 0;
        this.completedPhases = 0;
        this.successfulPhases = 0;
        this.detectionCount = 0;
        this.startTime = null;
        this.phases = [
            'reconnaissance', 'weaponization', 'delivery', 'exploitation',
            'installation', 'command_control', 'actions_objectives'
        ];
        
        this.attackLog = document.getElementById('attack-log');
        this.completedPhasesElement = document.getElementById('completed-phases');
        this.successRateElement = document.getElementById('success-rate');
        this.elapsedTimeElement = document.getElementById('elapsed-time');
        this.detectionCountElement = document.getElementById('detection-count');
        this.artifactsListElement = document.getElementById('artifacts-list');
        
        this.bindEvents();
        this.updateTimer();
    }
    
    bindEvents() {
        document.getElementById('start-killchain-btn').addEventListener('click', () => {
            this.startKillChain();
        });
        
        document.getElementById('reset-killchain-btn').addEventListener('click', () => {
            this.resetKillChain();
        });
        
        document.getElementById('auto-execute-btn').addEventListener('click', () => {
            this.autoExecuteMode();
        });
        
        // 各段階の実行ボタン
        document.querySelectorAll('.execute-phase-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const phase = e.target.dataset.phase;
                this.executePhase(phase);
            });
        });
    }
    
    async startKillChain() {
        if (this.isRunning) return;
        
        this.startTime = Date.now();
        const target = document.getElementById('target-organization').value;
        const attacker = document.getElementById('attacker-profile').value;
        const level = document.getElementById('attack-sophistication').value;
        
        this.log(`[START] サイバーキルチェーン攻撃開始`, 'system');
        this.log(`[CONFIG] 標的: ${this.getTargetName(target)}, 攻撃者: ${this.getAttackerName(attacker)}, レベル: ${this.getLevelName(level)}`, 'info');
        
        // 最初の段階を有効化
        this.enablePhase('reconnaissance');
        
        await this.sendIDSAlert('Kill Chain Attack Start', `target=${target}, attacker=${attacker}, level=${level}`);
    }
    
    async executePhase(phaseName) {
        const phaseElement = document.querySelector(`[data-phase="${phaseName}"]`);
        const statusElement = phaseElement.querySelector('.phase-status');
        const executeBtn = phaseElement.querySelector('.execute-phase-btn');
        const detailsElement = phaseElement.querySelector('.phase-details');
        const methodSelect = phaseElement.querySelector('.phase-method');
        
        // 段階開始
        phaseElement.classList.remove('phase-pending');
        phaseElement.classList.add('phase-active');
        statusElement.textContent = '実行中';
        statusElement.className = 'phase-status text-sm px-2 py-1 rounded bg-blue-500 text-white';
        executeBtn.disabled = true;
        executeBtn.textContent = '実行中...';
        
        const method = methodSelect.value;
        const target = document.getElementById('target-organization').value;
        
        this.log(`[${phaseName.toUpperCase()}] ${this.getPhaseName(phaseName)} 開始`, 'phase');
        
        try {
            const response = await fetch(window.location.pathname, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `action=execute_phase&phase=${phaseName}&target=${target}&method=${method}`
            });
            
            const result = await response.json();
            
            if (result.success) {
                const phaseSuccess = result.phase_success;
                
                // 段階完了
                phaseElement.classList.remove('phase-active');
                if (phaseSuccess) {
                    phaseElement.classList.add('phase-success');
                    statusElement.textContent = '成功';
                    statusElement.className = 'phase-status text-sm px-2 py-1 rounded bg-green-500 text-white';
                    this.successfulPhases++;
                    this.log(`[SUCCESS] ${this.getPhaseName(phaseName)} 成功`, 'success');
                } else {
                    phaseElement.classList.add('phase-failed');
                    statusElement.textContent = '失敗';
                    statusElement.className = 'phase-status text-sm px-2 py-1 rounded bg-red-500 text-white';
                    this.detectionCount++;
                    this.log(`[FAILED] ${this.getPhaseName(phaseName)} 失敗 - 検知またはブロック`, 'error');
                }
                
                this.completedPhases++;
                this.displayPhaseResults(phaseName, result.results);
                this.updateStats();
                
                // 次の段階を有効化（成功時のみ）
                if (phaseSuccess && result.next_phase) {
                    this.enablePhase(result.next_phase);
                }
                
                // 成果物を表示
                if (result.results.artifacts && result.results.artifacts.length > 0) {
                    this.addArtifacts(result.results.artifacts);
                }
                
                // ネットワーク状況更新
                this.updateNetworkStatus(phaseName, phaseSuccess);
                
                // IDS通知
                await this.sendIDSAlert(
                    `${this.getPhaseName(phaseName)} ${phaseSuccess ? 'Success' : 'Failed'}`,
                    `method=${method}, result=${phaseSuccess ? 'success' : 'failed'}`
                );
                
            } else {
                this.log(`[ERROR] 段階実行エラー: ${result.message}`, 'error');
            }
            
        } catch (error) {
            this.log(`[ERROR] 通信エラー: ${error.message}`, 'error');
        } finally {
            executeBtn.disabled = false;
            executeBtn.textContent = '実行';
        }
    }
    
    async autoExecuteMode() {
        if (this.isRunning) return;
        
        this.isRunning = true;
        this.log(`[AUTO] 自動実行モード開始`, 'system');
        
        for (const phase of this.phases) {
            if (!this.isRunning) break;
            
            await this.executePhase(phase);
            
            // 失敗した場合は停止
            const phaseElement = document.querySelector(`[data-phase="${phase}"]`);
            if (phaseElement.classList.contains('phase-failed')) {
                this.log(`[AUTO] 自動実行停止 - ${this.getPhaseName(phase)} で失敗`, 'warning');
                break;
            }
            
            // 次の段階まで待機
            await this.sleep(2000);
        }
        
        this.isRunning = false;
        if (this.completedPhases === 7) {
            this.log(`[COMPLETE] サイバーキルチェーン攻撃完了`, 'success');
        }
    }
    
    resetKillChain() {
        this.isRunning = false;
        this.currentPhase = 0;
        this.completedPhases = 0;
        this.successfulPhases = 0;
        this.detectionCount = 0;
        this.startTime = null;
        
        // すべての段階をリセット
        document.querySelectorAll('.killchain-phase').forEach(phase => {
            phase.classList.remove('phase-active', 'phase-success', 'phase-failed');
            phase.classList.add('phase-pending');
            
            const status = phase.querySelector('.phase-status');
            status.textContent = '待機中';
            status.className = 'phase-status text-sm px-2 py-1 rounded bg-gray-200';
            
            const btn = phase.querySelector('.execute-phase-btn');
            btn.disabled = true;
            btn.textContent = '実行';
            
            const details = phase.querySelector('.phase-details');
            details.classList.remove('active');
            details.innerHTML = '';
        });
        
        // ネットワーク状況リセット
        ['firewall-status', 'ids-status', 'av-status', 'email-security'].forEach(id => {
            document.getElementById(id).className = 'w-3 h-3 bg-green-500 rounded-full mr-2';
        });
        document.getElementById('compromise-level').textContent = '0%';
        document.getElementById('alert-count').textContent = '0';
        
        // ログとアーティファクトクリア
        this.attackLog.innerHTML = `
            <div>[SYSTEM] システムリセット完了</div>
            <div>[INFO] 攻撃設定を行い、実行ボタンを押してください</div>
        `;
        this.artifactsListElement.innerHTML = '<p class="text-gray-500 text-sm">攻撃段階を実行すると成果物が表示されます</p>';
        
        this.updateStats();
    }
    
    enablePhase(phaseName) {
        const phaseElement = document.querySelector(`[data-phase="${phaseName}"]`);
        const executeBtn = phaseElement.querySelector('.execute-phase-btn');
        executeBtn.disabled = false;
        
        this.log(`[READY] ${this.getPhaseName(phaseName)} 実行可能`, 'info');
    }
    
    displayPhaseResults(phaseName, results) {
        const detailsElement = document.getElementById(`${phaseName}-details`);
        detailsElement.innerHTML = '';
        
        if (results.details) {
            Object.entries(results.details).forEach(([key, value]) => {
                const div = document.createElement('div');
                div.innerHTML = `<strong>${key}:</strong> ${Array.isArray(value) ? value.join(', ') : value}`;
                detailsElement.appendChild(div);
            });
            
            detailsElement.parentElement.classList.add('active');
        }
    }
    
    addArtifacts(artifacts) {
        if (this.artifactsListElement.innerHTML.includes('攻撃段階を実行すると')) {
            this.artifactsListElement.innerHTML = '';
        }
        
        artifacts.forEach(artifact => {
            const div = document.createElement('div');
            div.className = 'artifact-item';
            div.textContent = `📄 ${artifact}`;
            this.artifactsListElement.appendChild(div);
        });
    }
    
    updateNetworkStatus(phaseName, success) {
        if (!success) return;
        
        const compromiseLevel = Math.min(100, (this.successfulPhases / 7) * 100);
        document.getElementById('compromise-level').textContent = `${Math.round(compromiseLevel)}%`;
        
        const alertCount = parseInt(document.getElementById('alert-count').textContent) + (success ? 1 : 0);
        document.getElementById('alert-count').textContent = alertCount;
        
        // セキュリティシステムの状態変更
        const systems = ['firewall-status', 'ids-status', 'av-status', 'email-security'];
        const systemIndex = Math.floor(this.successfulPhases / 2);
        
        if (systemIndex < systems.length) {
            const statusElement = document.getElementById(systems[systemIndex]);
            statusElement.className = 'w-3 h-3 bg-red-500 rounded-full mr-2';
        }
    }
    
    updateStats() {
        this.completedPhasesElement.textContent = `${this.completedPhases} / 7`;
        
        const successRate = this.completedPhases > 0 ? Math.round((this.successfulPhases / this.completedPhases) * 100) : 0;
        this.successRateElement.textContent = `${successRate}%`;
        
        this.detectionCountElement.textContent = this.detectionCount;
    }
    
    updateTimer() {
        if (this.startTime) {
            const elapsed = Math.floor((Date.now() - this.startTime) / 1000);
            const minutes = Math.floor(elapsed / 60);
            const seconds = elapsed % 60;
            this.elapsedTimeElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
        
        setTimeout(() => this.updateTimer(), 1000);
    }
    
    async sendIDSAlert(phase, detail) {
        try {
            await fetch(window.location.pathname, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `action=log_attack&phase=${encodeURIComponent(phase)}&detail=${encodeURIComponent(detail)}&status=200`
            });
        } catch (error) {
            console.warn('IDS通知送信失敗:', error);
        }
    }
    
    log(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString();
        const colors = {
            'system': '#00ffff',
            'phase': '#ffff00',
            'info': '#00ff00',
            'success': '#00ff00',
            'warning': '#ffa500',
            'error': '#ff0000'
        };
        
        const div = document.createElement('div');
        div.style.color = colors[type] || colors.info;
        div.textContent = `[${timestamp}] ${message}`;
        
        this.attackLog.appendChild(div);
        this.attackLog.scrollTop = this.attackLog.scrollHeight;
        
        // ログ行数制限
        while (this.attackLog.children.length > 100) {
            this.attackLog.removeChild(this.attackLog.firstChild);
        }
    }
    
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
    
    getTargetName(type) {
        const names = {
            'financial': '大手銀行',
            'government': '政府機関',
            'healthcare': '大手病院',
            'manufacturing': '製造企業',
            'retail': '小売チェーン'
        };
        return names[type] || type;
    }
    
    getAttackerName(type) {
        const names = {
            'apt': '国家支援型APT',
            'cybercrime': 'サイバー犯罪組織',
            'hacktivist': 'ハクティビスト',
            'insider': '内部脅威'
        };
        return names[type] || type;
    }
    
    getLevelName(level) {
        const names = {
            'basic': '基本レベル',
            'intermediate': '中級レベル',
            'advanced': '上級レベル',
            'expert': '専門レベル'
        };
        return names[level] || level;
    }
    
    getPhaseName(phase) {
        const names = {
            'reconnaissance': '偵察',
            'weaponization': '武器化',
            'delivery': '配送',
            'exploitation': '悪用',
            'installation': '設置',
            'command_control': '指令制御',
            'actions_objectives': '目的達成'
        };
        return names[phase] || phase;
    }
}

// 初期化
document.addEventListener('DOMContentLoaded', function() {
    new KillChainSimulator();
});
</script>
</body>
</html>