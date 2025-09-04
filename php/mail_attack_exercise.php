<?php
require_once __DIR__ . '/common_init.php';
require 'db.php';

// 管理者権限チェック
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: list.php');
    exit;
}

// メール攻撃演習が有効化されているかチェック
if (empty($_SESSION['mail_attack_enabled'])) {
    header('Location: simulation_tools.php?error=' . urlencode('メール攻撃演習が有効化されていません。'));
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
                log_attack($pdo, 'Mail Attack: ' . $phase, $detail, 'mail_exercise', $status);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'log_attack function not available']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    // フィッシングメール送信シミュレーション
    if ($action === 'send_phishing') {
        $template = $_POST['template'] ?? 'generic';
        $target_count = (int)($_POST['target_count'] ?? 1);
        $delay = (int)($_POST['delay'] ?? 1000);
        
        // 模擬送信結果を生成
        $results = [];
        $templates = [
            'generic' => [
                'subject' => '重要：アカウント確認が必要です',
                'success_rate' => 0.15
            ],
            'banking' => [
                'subject' => '【緊急】銀行口座の不正アクセス検知',
                'success_rate' => 0.25
            ],
            'social' => [
                'subject' => 'SNSアカウントに新しいログインがありました',
                'success_rate' => 0.30
            ],
            'shipping' => [
                'subject' => '配送業者：再配達のお知らせ',
                'success_rate' => 0.35
            ]
        ];
        
        $template_info = $templates[$template] ?? $templates['generic'];
        
        for ($i = 0; $i < $target_count; $i++) {
            $email = "user" . ($i + 1) . "@target-company.com";
            $clicked = (rand(1, 100) / 100) <= $template_info['success_rate'];
            
            $results[] = [
                'email' => $email,
                'sent' => true,
                'clicked' => $clicked,
                'timestamp' => date('H:i:s'),
                'delay' => $delay
            ];
        }
        
        echo json_encode([
            'success' => true,
            'results' => $results,
            'template' => $template_info,
            'total_sent' => count($results),
            'total_clicked' => count(array_filter($results, fn($r) => $r['clicked']))
        ]);
        exit;
    }
    
    // メールインジェクション攻撃
    if ($action === 'mail_injection') {
        $injection_type = $_POST['injection_type'] ?? 'header';
        $payload = $_POST['payload'] ?? '';
        
        // 検知シミュレーション
        $detected = false;
        $detection_patterns = [
            'cc:', 'bcc:', 'to:', 'from:', 'subject:',
            '%0a', '%0d', '\r\n', '\n'
        ];
        
        foreach ($detection_patterns as $pattern) {
            if (stripos($payload, $pattern) !== false) {
                $detected = true;
                break;
            }
        }
        
        echo json_encode([
            'success' => true,
            'detected' => $detected,
            'payload' => $payload,
            'injection_type' => $injection_type,
            'result' => $detected ? 'ブロックされました' : '攻撃が成功しました'
        ]);
        exit;
    }
    
    // SPAMリレー攻撃
    if ($action === 'spam_relay') {
        $target_count = (int)($_POST['target_count'] ?? 10);
        $spam_type = $_POST['spam_type'] ?? 'advertisement';
        
        // SPAM送信シミュレーション
        $relay_success = rand(1, 100) <= 30; // 30%の確率で成功
        $sent_count = $relay_success ? $target_count : 0;
        
        echo json_encode([
            'success' => true,
            'relay_success' => $relay_success,
            'sent_count' => $sent_count,
            'target_count' => $target_count,
            'spam_type' => $spam_type,
            'blocked_by_relay' => !$relay_success
        ]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>メール攻撃演習</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .attack-panel {
            transition: all 0.3s ease-in-out;
        }
        .panel-active {
            background: linear-gradient(135deg, #1f2937, #374151);
            border-color: #3b82f6;
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.3);
        }
        .mail-preview {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            font-family: Arial, sans-serif;
            max-width: 600px;
        }
        .mail-header {
            background: #e2e8f0;
            padding: 12px;
            border-bottom: 1px solid #cbd5e1;
        }
        .mail-body {
            padding: 20px;
            line-height: 1.6;
        }
        .phishing-link {
            color: #3b82f6;
            text-decoration: underline;
            cursor: pointer;
        }
        .phishing-link:hover {
            background: #fef3c7;
        }
        .terminal-log {
            background: #000;
            color: #00ff00;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            max-height: 300px;
            overflow-y: auto;
            border-radius: 8px;
            padding: 16px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
        }
        .stat-card {
            background: #1f2937;
            color: white;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .success-rate {
            color: #10b981;
        }
        .detection-rate {
            color: #ef4444;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
<?php include 'header.php'; ?>

<div class="container mx-auto mt-10 p-4">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">メール攻撃演習</h1>
            <p class="text-gray-600">フィッシング、メールインジェクション、SPAMリレーなどの模擬演習</p>
        </div>
        <div class="text-right">
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-3 rounded">
                <p class="font-bold">⚠️ 教育目的の演習</p>
                <p class="text-sm">実際のメール送信は行いません</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- 左側：攻撃パネル -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- フィッシングメール攻撃 -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">🎣 フィッシングメール攻撃</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">テンプレート選択</label>
                        <select id="phishing-template" class="w-full border rounded-lg px-3 py-2">
                            <option value="generic">一般的なアカウント確認</option>
                            <option value="banking">銀行・金融機関</option>
                            <option value="social">SNS・ソーシャルメディア</option>
                            <option value="shipping">配送業者</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">送信対象数</label>
                        <select id="target-count" class="w-full border rounded-lg px-3 py-2">
                            <option value="10">10名</option>
                            <option value="50">50名</option>
                            <option value="100" selected>100名</option>
                            <option value="500">500名</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">送信間隔</label>
                    <select id="send-delay" class="w-full border rounded-lg px-3 py-2">
                        <option value="100">高速（100ms間隔）</option>
                        <option value="500">通常（500ms間隔）</option>
                        <option value="1000" selected>低速（1秒間隔）</option>
                        <option value="2000">ステルス（2秒間隔）</option>
                    </select>
                </div>
                
                <button id="start-phishing-btn" class="w-full bg-red-600 hover:bg-red-700 text-white py-3 rounded-lg font-semibold mb-4">
                    フィッシングキャンペーン開始
                </button>
                
                <!-- メールプレビュー -->
                <div id="mail-preview" class="mail-preview hidden">
                    <div class="mail-header">
                        <div class="text-sm"><strong>From:</strong> <span id="preview-from"></span></div>
                        <div class="text-sm"><strong>To:</strong> <span id="preview-to"></span></div>
                        <div class="text-sm"><strong>Subject:</strong> <span id="preview-subject"></span></div>
                    </div>
                    <div class="mail-body" id="preview-body"></div>
                </div>
            </div>

            <!-- メールインジェクション攻撃 -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">💉 メールインジェクション攻撃</h2>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">インジェクションタイプ</label>
                    <select id="injection-type" class="w-full border rounded-lg px-3 py-2">
                        <option value="header">ヘッダーインジェクション</option>
                        <option value="content">コンテンツインジェクション</option>
                        <option value="recipient">受信者インジェクション</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">攻撃ペイロード</label>
                    <textarea id="injection-payload" rows="4" class="w-full border rounded-lg px-3 py-2" 
                              placeholder="例: %0Acc:attacker@evil.com%0Abcc:spam@evil.com%0A%0AThis is injected content"></textarea>
                    <div class="mt-2 text-xs text-gray-500">
                        <p><strong>サンプルペイロード:</strong></p>
                        <code class="bg-gray-100 p-1 rounded">%0Acc:attacker@evil.com%0A</code> - CC追加<br>
                        <code class="bg-gray-100 p-1 rounded">\nBcc:spam-list@evil.com\n</code> - BCC追加<br>
                        <code class="bg-gray-100 p-1 rounded">%0d%0aSubject:Hijacked!</code> - 件名改ざん
                    </div>
                </div>
                
                <button id="test-injection-btn" class="w-full bg-orange-600 hover:bg-orange-700 text-white py-2 rounded-lg font-semibold">
                    インジェクション攻撃テスト
                </button>
            </div>

            <!-- SPAMリレー攻撃 -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">📧 SPAMリレー攻撃</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">SPAM タイプ</label>
                        <select id="spam-type" class="w-full border rounded-lg px-3 py-2">
                            <option value="advertisement">広告・宣伝</option>
                            <option value="scam">詐欺・偽情報</option>
                            <option value="malware">マルウェア配布</option>
                            <option value="cryptocurrency">仮想通貨詐欺</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">送信対象数</label>
                        <input type="number" id="spam-target-count" class="w-full border rounded-lg px-3 py-2" 
                               value="1000" min="10" max="10000">
                    </div>
                </div>
                
                <button id="test-spam-relay-btn" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-2 rounded-lg font-semibold">
                    SPAMリレー攻撃テスト
                </button>
            </div>
        </div>

        <!-- 右側：ログとモニタリング -->
        <div class="space-y-6">
            <!-- 攻撃統計 -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">📊 攻撃統計</h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value" id="total-sent">0</div>
                        <div class="text-sm">送信数</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value success-rate" id="click-rate">0%</div>
                        <div class="text-sm">クリック率</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value detection-rate" id="detection-rate">0%</div>
                        <div class="text-sm">検知率</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="campaigns-run">0</div>
                        <div class="text-sm">実行回数</div>
                    </div>
                </div>
            </div>

            <!-- 攻撃ログ -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">🔍 攻撃ログ</h2>
                <div id="attack-log" class="terminal-log">
                    <div>[SYSTEM] メール攻撃演習システム起動</div>
                    <div>[INFO] 攻撃を開始してください</div>
                </div>
            </div>

            <!-- リアルタイム検知状況 -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">🚨 リアルタイム検知</h2>
                <div id="detection-status" class="space-y-2">
                    <div class="flex items-center text-sm">
                        <span class="w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                        <span>メールフィルタ: 待機中</span>
                    </div>
                    <div class="flex items-center text-sm">
                        <span class="w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                        <span>SPAMフィルタ: 待機中</span>
                    </div>
                    <div class="flex items-center text-sm">
                        <span class="w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                        <span>インジェクション検知: 待機中</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
class MailAttackSimulator {
    constructor() {
        this.isRunning = false;
        this.totalSent = 0;
        this.totalClicks = 0;
        this.totalDetections = 0;
        this.campaignsRun = 0;
        
        // DOM要素
        this.attackLog = document.getElementById('attack-log');
        this.detectionStatus = document.getElementById('detection-status');
        
        // 統計要素
        this.totalSentElement = document.getElementById('total-sent');
        this.clickRateElement = document.getElementById('click-rate');
        this.detectionRateElement = document.getElementById('detection-rate');
        this.campaignsElement = document.getElementById('campaigns-run');
        
        // メールテンプレート
        this.templates = {
            generic: {
                from: 'security@service-center.com',
                subject: '重要：アカウント確認が必要です',
                body: `
                    <p>お客様各位</p>
                    <p>システムメンテナンスのため、アカウントの確認が必要です。</p>
                    <p>以下のリンクから確認してください：</p>
                    <p><a href="#" class="phishing-link">https://account-verification.service-center.com/verify</a></p>
                    <p>24時間以内に確認されない場合、アカウントが一時停止される可能性があります。</p>
                    <p>サービスセンター</p>
                `
            },
            banking: {
                from: 'security@bank-notice.com',
                subject: '【緊急】銀行口座の不正アクセス検知',
                body: `
                    <p>お客様の口座で不正なアクセスが検知されました。</p>
                    <p>セキュリティ確保のため、直ちに確認してください：</p>
                    <p><a href="#" class="phishing-link">https://secure-banking.verification-center.com/urgent</a></p>
                    <p><strong>時間: 2025/09/01 14:23</strong></p>
                    <p><strong>アクセス元: 不明な場所</strong></p>
                    <p>銀行セキュリティセンター</p>
                `
            },
            social: {
                from: 'security@social-platform.com',
                subject: 'SNSアカウントに新しいログインがありました',
                body: `
                    <p>あなたのアカウントに新しいデバイスからのログインがありました。</p>
                    <p><strong>日時:</strong> 2025年9月1日 14:30</p>
                    <p><strong>デバイス:</strong> 不明なデバイス</p>
                    <p><strong>場所:</strong> 東京都以外</p>
                    <p>心当たりがない場合は、直ちに確認してください：</p>
                    <p><a href="#" class="phishing-link">https://security.social-platform.com/check-login</a></p>
                `
            },
            shipping: {
                from: 'delivery@shipping-company.com',
                subject: '配送業者：再配達のお知らせ',
                body: `
                    <p>荷物をお届けに伺いましたが、不在のため持ち戻りました。</p>
                    <p><strong>荷物番号:</strong> JP1234567890</p>
                    <p><strong>差出人:</strong> Amazon.co.jp</p>
                    <p>再配達をご希望の場合は、以下から手続きしてください：</p>
                    <p><a href="#" class="phishing-link">https://redelivery.shipping-company.com/schedule</a></p>
                    <p>配送センター</p>
                `
            }
        };
        
        this.bindEvents();
    }
    
    bindEvents() {
        // フィッシング攻撃開始
        document.getElementById('start-phishing-btn').addEventListener('click', () => {
            this.startPhishingCampaign();
        });
        
        // メールインジェクション攻撃
        document.getElementById('test-injection-btn').addEventListener('click', () => {
            this.testMailInjection();
        });
        
        // SPAMリレー攻撃
        document.getElementById('test-spam-relay-btn').addEventListener('click', () => {
            this.testSpamRelay();
        });
        
        // テンプレート変更時のプレビュー更新
        document.getElementById('phishing-template').addEventListener('change', () => {
            this.updateMailPreview();
        });
        
        // 初期プレビュー表示
        this.updateMailPreview();
    }
    
    updateMailPreview() {
        const templateType = document.getElementById('phishing-template').value;
        const template = this.templates[templateType];
        
        if (template) {
            document.getElementById('preview-from').textContent = template.from;
            document.getElementById('preview-to').textContent = 'target@company.com';
            document.getElementById('preview-subject').textContent = template.subject;
            document.getElementById('preview-body').innerHTML = template.body;
            document.getElementById('mail-preview').classList.remove('hidden');
        }
    }
    
    async startPhishingCampaign() {
        if (this.isRunning) return;
        
        this.isRunning = true;
        const button = document.getElementById('start-phishing-btn');
        button.disabled = true;
        button.textContent = 'キャンペーン実行中...';
        
        const template = document.getElementById('phishing-template').value;
        const targetCount = parseInt(document.getElementById('target-count').value);
        const delay = parseInt(document.getElementById('send-delay').value);
        
        this.log(`[START] フィッシングキャンペーン開始`, 'info');
        this.log(`[CONFIG] テンプレート: ${template}, 対象: ${targetCount}名, 間隔: ${delay}ms`, 'info');
        
        // IDS通知
        await this.sendIDSAlert('Phishing Campaign Start', `template=${template}, targets=${targetCount}`);
        
        try {
            const response = await fetch(window.location.pathname, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `action=send_phishing&template=${template}&target_count=${targetCount}&delay=${delay}`
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.log(`[SUCCESS] ${result.total_sent}通のメールを送信完了`, 'success');
                this.log(`[STATS] クリック数: ${result.total_clicked}/${result.total_sent} (${Math.round((result.total_clicked/result.total_sent)*100)}%)`, 'info');
                
                // 送信結果をシミュレート表示
                for (let i = 0; i < result.results.length; i++) {
                    if (!this.isRunning) break;
                    
                    const mail = result.results[i];
                    this.log(`[SEND] ${mail.email} - ${mail.clicked ? 'クリック' : '未開封'}`, mail.clicked ? 'success' : 'info');
                    
                    if (i % 10 === 0) {
                        await this.sleep(delay / 10);
                    }
                }
                
                this.totalSent += result.total_sent;
                this.totalClicks += result.total_clicked;
                this.campaignsRun++;
                this.updateStats();
                
                // 検知状況の更新
                this.updateDetectionStatus('mail-filter', result.total_clicked > 0 ? 'alert' : 'normal');
                
            } else {
                this.log(`[ERROR] フィッシング攻撃失敗: ${result.message}`, 'error');
            }
            
        } catch (error) {
            this.log(`[ERROR] 通信エラー: ${error.message}`, 'error');
        } finally {
            this.isRunning = false;
            button.disabled = false;
            button.textContent = 'フィッシングキャンペーン開始';
        }
    }
    
    async testMailInjection() {
        const injectionType = document.getElementById('injection-type').value;
        const payload = document.getElementById('injection-payload').value.trim();
        
        if (!payload) {
            alert('攻撃ペイロードを入力してください。');
            return;
        }
        
        this.log(`[INJECTION] メールインジェクション攻撃開始`, 'info');
        this.log(`[PAYLOAD] ${payload}`, 'info');
        
        try {
            const response = await fetch(window.location.pathname, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `action=mail_injection&injection_type=${injectionType}&payload=${encodeURIComponent(payload)}`
            });
            
            const result = await response.json();
            
            if (result.success) {
                if (result.detected) {
                    this.log(`[BLOCKED] インジェクション攻撃が検知・ブロックされました`, 'warning');
                    this.totalDetections++;
                    this.updateDetectionStatus('injection-detection', 'alert');
                    await this.sendIDSAlert('Mail Injection Blocked', payload);
                } else {
                    this.log(`[SUCCESS] インジェクション攻撃が成功しました`, 'success');
                    this.updateDetectionStatus('injection-detection', 'compromised');
                    await this.sendIDSAlert('Mail Injection Success', payload);
                }
                
                this.updateStats();
            }
            
        } catch (error) {
            this.log(`[ERROR] インジェクション攻撃エラー: ${error.message}`, 'error');
        }
    }
    
    async testSpamRelay() {
        const spamType = document.getElementById('spam-type').value;
        const targetCount = parseInt(document.getElementById('spam-target-count').value);
        
        this.log(`[SPAM] SPAMリレー攻撃開始`, 'info');
        this.log(`[CONFIG] タイプ: ${spamType}, 対象: ${targetCount}通`, 'info');
        
        try {
            const response = await fetch(window.location.pathname, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `action=spam_relay&spam_type=${spamType}&target_count=${targetCount}`
            });
            
            const result = await response.json();
            
            if (result.success) {
                if (result.relay_success) {
                    this.log(`[SUCCESS] SPAMリレー成功: ${result.sent_count}通送信`, 'success');
                    this.totalSent += result.sent_count;
                    this.updateDetectionStatus('spam-filter', 'compromised');
                    await this.sendIDSAlert('SPAM Relay Success', `sent=${result.sent_count}, type=${spamType}`);
                } else {
                    this.log(`[BLOCKED] SPAMリレーがブロックされました`, 'warning');
                    this.totalDetections++;
                    this.updateDetectionStatus('spam-filter', 'alert');
                    await this.sendIDSAlert('SPAM Relay Blocked', `type=${spamType}, attempted=${targetCount}`);
                }
                
                this.updateStats();
            }
            
        } catch (error) {
            this.log(`[ERROR] SPAMリレー攻撃エラー: ${error.message}`, 'error');
        }
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
    
    updateDetectionStatus(systemType, status) {
        const statusMap = {
            'mail-filter': 'メールフィルタ',
            'spam-filter': 'SPAMフィルタ',
            'injection-detection': 'インジェクション検知'
        };
        
        const statusColors = {
            'normal': 'bg-green-500',
            'alert': 'bg-yellow-500',
            'compromised': 'bg-red-500'
        };
        
        const statusTexts = {
            'normal': '正常',
            'alert': '検知中',
            'compromised': '侵害'
        };
        
        const statusElements = document.querySelectorAll('#detection-status div');
        statusElements.forEach(element => {
            const text = element.textContent;
            if (text.includes(statusMap[systemType])) {
                const indicator = element.querySelector('span');
                indicator.className = `w-3 h-3 ${statusColors[status]} rounded-full mr-2`;
                element.innerHTML = `<span class="w-3 h-3 ${statusColors[status]} rounded-full mr-2"></span><span>${statusMap[systemType]}: ${statusTexts[status]}</span>`;
            }
        });
    }
    
    updateStats() {
        this.totalSentElement.textContent = this.totalSent;
        this.campaignsElement.textContent = this.campaignsRun;
        
        const clickRate = this.totalSent > 0 ? Math.round((this.totalClicks / this.totalSent) * 100) : 0;
        const detectionRate = this.totalSent > 0 ? Math.round((this.totalDetections / this.totalSent) * 100) : 0;
        
        this.clickRateElement.textContent = `${clickRate}%`;
        this.detectionRateElement.textContent = `${detectionRate}%`;
    }
    
    log(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString();
        const colors = {
            'info': '#00ff00',
            'success': '#00ff00',
            'warning': '#ffff00',
            'error': '#ff0000',
            'system': '#00ffff'
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
}

// フィッシングリンククリックシミュレーション
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('phishing-link')) {
        e.preventDefault();
        
        // クリック効果
        e.target.style.background = '#fef3c7';
        setTimeout(() => {
            e.target.style.background = '';
        }, 500);
        
        // 模擬的な「危険サイト」警告
        setTimeout(() => {
            alert('⚠️ 警告: これはフィッシングリンクです！\n\n実際の攻撃では、このリンクから認証情報が盗まれる可能性があります。\n\n（これは教育目的の演習です）');
        }, 500);
    }
});

// 初期化
document.addEventListener('DOMContentLoaded', function() {
    new MailAttackSimulator();
});
</script>
</body>
</html>