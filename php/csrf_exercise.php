<?php
require_once __DIR__ . '/common_init.php';
require 'db.php';

// CSRF演習が有効でない場合はリダイレクト
if (empty($_SESSION['csrf_enabled'])) {
    header('Location: simulation_tools.php?error=' . urlencode('CSRF攻撃演習が有効ではありません。'));
    exit;
}

// 管理者権限チェック
$is_admin = (($_SESSION['role'] ?? '') === 'admin');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>CSRF攻撃演習</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .attack-frame { border: 3px solid #dc2626; background: #fef2f2; }
        .defense-frame { border: 3px solid #059669; background: #f0fdf4; }
        .vulnerable-indicator { color: #dc2626; font-weight: bold; }
        .protected-indicator { color: #059669; font-weight: bold; }
    </style>
</head>
<body class="bg-gray-100">
<?php include 'header.php'; ?>

<div class="container mx-auto mt-10 p-4">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-gray-800">CSRF攻撃演習</h1>
        <div class="flex items-center gap-4">
            <div class="text-sm">
                <span class="vulnerable-indicator">🔴 脆弱</span> /
                <span class="protected-indicator">🟢 保護済み</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- 左側：攻撃者画面 -->
        <div class="attack-frame rounded-lg p-6">
            <h2 class="text-xl font-bold text-red-600 mb-4">🔴 攻撃者画面</h2>
            
            <!-- CSRF攻撃フォーム作成 -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-3">悪意のあるサイトを作成</h3>
                <div class="bg-white p-4 rounded border-2 border-red-300">
                    <label for="target-action" class="block text-sm font-medium mb-2">攻撃対象のアクション</label>
                    <select id="target-action" class="w-full px-3 py-2 border rounded-lg mb-3">
                        <option value="change_password">パスワード変更</option>
                        <option value="delete_account">アカウント削除</option>
                        <option value="transfer_funds">資金移動</option>
                        <option value="change_email">メールアドレス変更</option>
                    </select>
                    
                    <div id="attack-params" class="space-y-2">
                        <!-- 動的に生成される攻撃パラメータ -->
                    </div>
                    
                    <button id="generate-attack" class="w-full bg-red-600 text-white py-2 rounded-lg hover:bg-red-700 mt-3">
                        悪意のあるページを生成
                    </button>
                </div>
            </div>

            <!-- 生成された攻撃コード -->
            <div id="attack-code-container" class="mb-6 hidden">
                <h3 class="text-lg font-semibold mb-3">生成された攻撃コード</h3>
                <div class="bg-gray-900 text-green-400 p-4 rounded text-sm overflow-auto max-h-64">
                    <pre id="attack-code"></pre>
                </div>
                <button id="deploy-attack" class="w-full bg-red-600 text-white py-2 rounded-lg hover:bg-red-700 mt-3">
                    攻撃を実行
                </button>
            </div>

            <!-- 攻撃履歴 -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-3">攻撃履歴</h3>
                <div id="attack-history" class="bg-white p-4 rounded border-2 border-red-300 max-h-48 overflow-auto">
                    <div class="text-gray-500 text-sm">攻撃履歴はここに表示されます</div>
                </div>
            </div>
        </div>

        <!-- 右側：防御者画面 -->
        <div class="defense-frame rounded-lg p-6">
            <h2 class="text-xl font-bold text-green-600 mb-4">🟢 防御者画面</h2>
            
            <!-- システム状態表示 -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-3">システム状態</h3>
                <div id="system-status" class="bg-white p-4 rounded border-2 border-green-300">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="font-medium">防御レベル:</span>
                            <span id="defense-level" class="ml-2 px-2 py-1 rounded text-xs font-bold bg-red-100 text-red-800">脆弱</span>
                        </div>
                        <div>
                            <span class="font-medium">攻撃検知:</span>
                            <span id="attack-detected" class="ml-2 px-2 py-1 rounded text-xs font-bold bg-gray-100 text-gray-800">なし</span>
                        </div>
                        <div>
                            <span class="font-medium">被害状況:</span>
                            <span id="damage-status" class="ml-2 px-2 py-1 rounded text-xs font-bold bg-green-100 text-green-800">正常</span>
                        </div>
                        <div>
                            <span class="font-medium">最終攻撃:</span>
                            <span id="last-attack" class="ml-2 text-xs text-gray-600">-</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- アラート・通知パネル -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-3">セキュリティアラート</h3>
                <div id="security-alerts" class="bg-white p-4 rounded border-2 border-green-300 max-h-32 overflow-auto">
                    <div class="text-gray-500 text-sm">アラートはここに表示されます</div>
                </div>
            </div>
            
            <!-- CSRF防御設定 -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-3">CSRF防御設定</h3>
                <div class="bg-white p-4 rounded border-2 border-green-300">
                    <label class="flex items-center mb-3">
                        <input type="checkbox" id="csrf-token-protection" class="mr-2">
                        <span>CSRFトークン保護を有効にする</span>
                        <span id="csrf-status" class="ml-auto text-xs px-2 py-1 rounded bg-red-100 text-red-800">無効</span>
                    </label>
                    
                    <label class="flex items-center mb-3">
                        <input type="checkbox" id="referer-check" class="mr-2">
                        <span>Refererヘッダーチェック</span>
                        <span id="referer-status" class="ml-auto text-xs px-2 py-1 rounded bg-red-100 text-red-800">無効</span>
                    </label>
                    
                    <label class="flex items-center mb-3">
                        <input type="checkbox" id="same-site-cookie" class="mr-2">
                        <span>SameSite Cookie属性</span>
                        <span id="samesite-status" class="ml-auto text-xs px-2 py-1 rounded bg-red-100 text-red-800">無効</span>
                    </label>
                    
                    <label class="flex items-center mb-3">
                        <input type="checkbox" id="origin-check" class="mr-2">
                        <span>Origin ヘッダーチェック</span>
                        <span id="origin-status" class="ml-auto text-xs px-2 py-1 rounded bg-red-100 text-red-800">無効</span>
                    </label>
                    
                    <button id="update-protection" class="w-full bg-green-600 text-white py-2 rounded-lg hover:bg-green-700">
                        防御設定を更新
                    </button>
                </div>
            </div>

            <!-- リアルタイム防御効果 -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-3">防御効果の可視化</h3>
                <div class="bg-white p-4 rounded border-2 border-green-300">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm">攻撃ブロック率:</span>
                            <div class="flex items-center">
                                <div class="w-24 bg-gray-200 rounded-full h-2 mr-2">
                                    <div id="block-rate-bar" class="bg-red-600 h-2 rounded-full" style="width: 0%"></div>
                                </div>
                                <span id="block-rate-text" class="text-xs font-bold">0%</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm">攻撃試行回数:</span>
                            <span id="attack-attempts" class="text-sm font-bold">0</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm">ブロック成功:</span>
                            <span id="blocked-attacks" class="text-sm font-bold text-green-600">0</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm">攻撃成功:</span>
                            <span id="successful-attacks" class="text-sm font-bold text-red-600">0</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 被害詳細可視化 -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-3">被害詳細可視化</h3>
                <div class="bg-white p-4 rounded border-2 border-green-300">
                    <button id="show-damage-details" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 mb-3">
                        攻撃後の被害状況を表示
                    </button>
                    <div id="damage-details" class="hidden">
                        <div class="space-y-4">
                            <div>
                                <h4 class="font-semibold text-sm mb-2">攻撃で送信されたHTMLコード:</h4>
                                <div id="malicious-html" class="bg-gray-100 p-3 rounded text-xs font-mono overflow-auto max-h-32 border">
                                    <div class="text-gray-500">攻撃実行後に表示されます</div>
                                </div>
                            </div>
                            <div>
                                <h4 class="font-semibold text-sm mb-2">被害の詳細:</h4>
                                <div id="damage-report" class="bg-red-50 p-3 rounded text-sm border-l-4 border-red-400">
                                    <div class="text-gray-500">攻撃成功時に詳細が表示されます</div>
                                </div>
                            </div>
                            <div>
                                <h4 class="font-semibold text-sm mb-2">影響を受けたシステム部分:</h4>
                                <div id="affected-systems" class="space-y-2">
                                    <div class="text-gray-500 text-sm">攻撃後に表示されます</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 脆弱性スキャン結果 -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-3">脆弱性スキャン結果</h3>
                <div id="vulnerability-scan" class="bg-white p-4 rounded border-2 border-green-300">
                    <button id="run-scan" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 mb-3">
                        脆弱性スキャンを実行
                    </button>
                    <div id="scan-results" class="text-sm">
                        <div class="text-gray-500">スキャン結果はここに表示されます</div>
                    </div>
                </div>
            </div>

            <!-- 攻撃検知ログ -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-3">攻撃検知ログ</h3>
                <div id="detection-log" class="bg-white p-4 rounded border-2 border-green-300 max-h-48 overflow-auto">
                    <div class="text-gray-500 text-sm">検知ログはここに表示されます</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 模擬的な脆弱なアプリケーション -->
    <div class="mt-8 bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-bold mb-4">模擬的な脆弱なアプリケーション</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- パスワード変更フォーム -->
            <div class="border p-4 rounded">
                <h3 class="font-semibold mb-3">パスワード変更</h3>
                <form id="password-change-form" method="POST" action="csrf_target.php">
                    <input type="hidden" name="action" value="change_password">
                    <div class="csrf-token-field" style="display: none;">
                        <input type="hidden" name="csrf_token" id="password-csrf-token">
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium mb-1">新しいパスワード</label>
                        <input type="password" name="new_password" class="w-full px-3 py-2 border rounded">
                    </div>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        パスワード変更
                    </button>
                    <div class="vulnerability-status mt-2 text-sm">
                        <span class="vulnerable-indicator">🔴 CSRF攻撃に対して脆弱です</span>
                    </div>
                </form>
            </div>

            <!-- アカウント削除フォーム -->
            <div class="border p-4 rounded">
                <h3 class="font-semibold mb-3">アカウント削除</h3>
                <form id="delete-account-form" method="POST" action="csrf_target.php">
                    <input type="hidden" name="action" value="delete_account">
                    <div class="csrf-token-field" style="display: none;">
                        <input type="hidden" name="csrf_token" id="delete-csrf-token">
                    </div>
                    <p class="text-sm text-gray-600 mb-3">アカウントを削除すると元に戻せません。</p>
                    <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                        アカウントを削除
                    </button>
                    <div class="vulnerability-status mt-2 text-sm">
                        <span class="vulnerable-indicator">🔴 CSRF攻撃に対して脆弱です</span>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
class CSRFExercise {
    constructor() {
        this.protectionEnabled = false;
        this.currentTarget = null;
        this.attackHistory = [];
        this.detectionLog = [];
        this.securityAlerts = [];
        
        // 統計データ
        this.stats = {
            totalAttempts: 0,
            blockedAttacks: 0,
            successfulAttacks: 0
        };
        
        this.initializeEventListeners();
        this.generateCSRFToken();
        this.updateSystemStatus();
        this.initializeDefenseStatusIndicators();
    }
    
    initializeEventListeners() {
        // 攻撃対象変更（初期化時に実行）
        this.updateAttackParameters();
        
        // 攻撃コード生成
        document.getElementById('generate-attack').addEventListener('click', () => {
            this.generateAttackCode();
        });
        
        // 攻撃実行
        document.getElementById('deploy-attack').addEventListener('click', () => {
            this.deployAttack();
        });
        
        // 防御設定更新
        document.getElementById('update-protection').addEventListener('click', async () => {
            await this.updateProtection();
        });
        
        // 脆弱性スキャン
        document.getElementById('run-scan').addEventListener('click', () => {
            this.runVulnerabilityScan();
        });
        
        // 攻撃対象変更
        document.getElementById('target-action').addEventListener('change', () => {
            this.updateAttackParameters();
        });
        
        // 被害詳細表示
        document.getElementById('show-damage-details').addEventListener('click', () => {
            this.showDamageDetails();
        });
    }
    
    generateCSRFToken() {
        // 簡易的なCSRFトークン生成
        this.csrfToken = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
    }
    
    updateAttackParameters() {
        const action = document.getElementById('target-action').value;
        const container = document.getElementById('attack-params');
        
        let html = '';
        switch(action) {
            case 'change_password':
                html = `
                    <label class="block text-sm font-medium mb-1">新しいパスワード</label>
                    <input type="text" id="attack-password" placeholder="攻撃者が設定するパスワード" class="w-full px-3 py-2 border rounded">
                `;
                break;
            case 'change_email':
                html = `
                    <label class="block text-sm font-medium mb-1">新しいメールアドレス</label>
                    <input type="email" id="attack-email" placeholder="attacker@evil.com" class="w-full px-3 py-2 border rounded">
                `;
                break;
            case 'transfer_funds':
                html = `
                    <label class="block text-sm font-medium mb-1">送金先</label>
                    <input type="text" id="attack-account" placeholder="攻撃者の口座" class="w-full px-3 py-2 border rounded mb-2">
                    <label class="block text-sm font-medium mb-1">金額</label>
                    <input type="number" id="attack-amount" placeholder="1000000" class="w-full px-3 py-2 border rounded">
                `;
                break;
        }
        container.innerHTML = html;
    }
    
    generateAttackCode() {
        const action = document.getElementById('target-action').value;
        let attackCode = '';
        let params = '';
        
        // パラメータを収集
        switch(action) {
            case 'change_password':
                const password = document.getElementById('attack-password')?.value || 'hacked123';
                params = `<input type="hidden" name="new_password" value="${password}">`;
                break;
            case 'change_email':
                const email = document.getElementById('attack-email')?.value || 'attacker@evil.com';
                params = `<input type="hidden" name="new_email" value="${email}">`;
                break;
            case 'transfer_funds':
                const account = document.getElementById('attack-account')?.value || 'attacker-account';
                const amount = document.getElementById('attack-amount')?.value || '1000000';
                params = `
                    <input type="hidden" name="transfer_to" value="${account}">
                    <input type="hidden" name="amount" value="${amount}">`;
                break;
            case 'delete_account':
                params = '';
                break;
        }
        
        attackCode = `<!DOCTYPE html>
<html>
<head>
    <title>無料ギフト！</title>
</head>
<body>
    <h1>おめでとうございます！</h1>
    <p>あなたは100万円のギフト券に当選しました！</p>
    <p>以下のボタンをクリックして受け取ってください：</p>
    
    <form id="malicious-form" method="POST" action="http://localhost:8088/csrf_target.php" style="display:none;">
        <input type="hidden" name="action" value="${action}">
        ${params}
    </form>
    
    <button onclick="document.getElementById('malicious-form').submit();" style="background:gold; padding:10px 20px; border:none; border-radius:5px; cursor:pointer;">
        ギフト券を受け取る
    </button>
    
    <script type="text/javascript">
    // 自動実行版（ページを開いただけで攻撃実行）
    // setTimeout(function() {
    //     document.getElementById('malicious-form').submit();
    // }, 2000);
    <\/script>
</body>
</html>`;

        document.getElementById('attack-code').textContent = attackCode;
        document.getElementById('attack-code-container').classList.remove('hidden');
        
        this.logAttack('攻撃コード生成', `${action} に対する CSRF 攻撃コードを生成しました`);
    }
    
    async deployAttack() {
        const action = document.getElementById('target-action').value;
        this.stats.totalAttempts++;
        
        // 攻撃をIDSに記録
        await this.sendIDSEvent('CSRF Attack Attempt', `Target: ${action}`);
        
        // 現在の保護状態をチェック
        const isProtected = document.getElementById('csrf-token-protection').checked;
        
        if (isProtected) {
            this.stats.blockedAttacks++;
            this.logDetection('CSRF攻撃をブロック', `${action} への CSRF 攻撃が CSRFトークンにより阻止されました`);
            this.addSecurityAlert('success', '攻撃ブロック', `${action} への CSRF 攻撃をブロックしました`);
            this.updateDamageStatus('protected');
            this.updateDamageVisualization(action, false);
            alert('攻撃は防御されました！CSRFトークンが正しくありません。');
        } else {
            this.stats.successfulAttacks++;
            this.logAttack('CSRF攻撃成功', `${action} への CSRF 攻撃が成功しました`);
            this.logDetection('CSRF攻撃成功', `防御機能が無効のため ${action} への攻撃が成功しました`);
            this.addSecurityAlert('danger', '攻撃成功', `${action} への CSRF 攻撃が成功しました - システムが侵害されました`);
            this.updateDamageStatus('compromised');
            this.updateDamageVisualization(action, true);
            alert('攻撃が成功しました！被害者のアカウントで不正な操作が実行されました。');
        }
        
        this.updateVulnerabilityStatus();
        this.updateSystemStatus();
        this.updateDefenseMetrics();
    }
    
    async updateProtection() {
        const csrfProtection = document.getElementById('csrf-token-protection').checked;
        const refererCheck = document.getElementById('referer-check').checked;
        const sameSiteCookie = document.getElementById('same-site-cookie').checked;
        const originCheck = document.getElementById('origin-check').checked;
        
        this.protectionEnabled = csrfProtection || refererCheck || sameSiteCookie || originCheck;
        
        try {
            // サーバー側に防御設定を送信
            const response = await fetch('csrf_protection.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_protection',
                    csrf_token_protection: csrfProtection,
                    referer_check: refererCheck,
                    same_site_cookie: sameSiteCookie,
                    origin_check: originCheck
                })
            });
            
            const result = await response.json();
            
            if (result.success && result.csrf_token) {
                this.csrfToken = result.csrf_token;
            }
        } catch (e) {
            console.warn('Protection update failed:', e);
        }
        
        // CSRFトークンフィールドの表示/非表示
        const tokenFields = document.querySelectorAll('.csrf-token-field');
        tokenFields.forEach(field => {
            if (csrfProtection) {
                field.style.display = 'block';
                const tokenInput = field.querySelector('input[name="csrf_token"]');
                if (tokenInput) {
                    tokenInput.value = this.csrfToken;
                }
            } else {
                field.style.display = 'none';
            }
        });
        
        this.updateVulnerabilityStatus();
        this.updateSystemStatus();
        this.updateDefenseStatusIndicators();
        this.updateDefenseMetrics();
        
        // 防御設定変更のアラート
        if (this.protectionEnabled) {
            this.addSecurityAlert('success', '防御強化', 'CSRF防御機能が有効になりました');
            this.logDetection('防御設定更新', `CSRF防御機能: 有効 - 保護レベルが向上しました`);
        } else {
            this.addSecurityAlert('warning', '防御無効', 'すべての防御機能が無効になりました - システムが脆弱な状態です');
            this.logDetection('防御設定更新', `CSRF防御機能: 無効 - システムが脆弱な状態です`);
        }
        
        alert('防御設定を更新しました。');
    }
    
    showDamageDetails() {
        const detailsContainer = document.getElementById('damage-details');
        if (detailsContainer.classList.contains('hidden')) {
            detailsContainer.classList.remove('hidden');
            document.getElementById('show-damage-details').textContent = '被害状況を隠す';
        } else {
            detailsContainer.classList.add('hidden');
            document.getElementById('show-damage-details').textContent = '攻撃後の被害状況を表示';
        }
    }
    async sendIDSEvent(attack_type, detail) {
        try {
            await fetch('ids_event.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ attack_type, detail, status_code: 200 })
            });
        } catch (e) {
            console.warn('IDS event send failed:', e);
        }
    }
    
    updateDamageVisualization(action, attackSuccessful) {
        const htmlContainer = document.getElementById('malicious-html');
        const reportContainer = document.getElementById('damage-report');
        const systemsContainer = document.getElementById('affected-systems');
        
        // 攻撃に使用されたHTMLコードを表示
        const action_elem = document.getElementById('target-action').value;
        let params = '';
        
        switch(action_elem) {
            case 'change_password':
                const password = document.getElementById('attack-password')?.value || 'hacked123';
                params = `        <input type="hidden" name="new_password" value="${password}">`;
                break;
            case 'change_email':
                const email = document.getElementById('attack-email')?.value || 'attacker@evil.com';
                params = `        <input type="hidden" name="new_email" value="${email}">`;
                break;
            case 'transfer_funds':
                const account = document.getElementById('attack-account')?.value || 'attacker-account';
                const amount = document.getElementById('attack-amount')?.value || '1000000';
                params = `        <input type="hidden" name="transfer_to" value="${account}">
        <input type="hidden" name="amount" value="${amount}">`;
                break;
        }
        
        const maliciousHtml = `<!DOCTYPE html>
<html>
<head><title>無料ギフト！</title></head>
<body>
    <h1>おめでとうございます！</h1>
    <p>あなたは100万円のギフト券に当選しました！</p>
    
    <form method="POST" action="http://localhost:8088/csrf_target.php" style="display:none;">
        <input type="hidden" name="action" value="${action_elem}">
${params}
    </form>
    
    <button onclick="document.forms[0].submit();">
        ギフト券を受け取る
    </button>
</body>
</html>`;
        
        htmlContainer.innerHTML = `<pre>${maliciousHtml}</pre>`;
        
        // 被害レポートの生成
        if (attackSuccessful) {
            let damageReport = '';
            let affectedSystems = [];
            
            switch(action) {
                case 'change_password':
                    const newPassword = document.getElementById('attack-password')?.value || 'hacked123';
                    damageReport = `
                        <div class="font-bold text-red-600 mb-2">重大な被害が発生しました</div>
                        <div class="text-sm space-y-1">
                            <div>• ユーザーのパスワードが無断で変更されました</div>
                            <div>• 新しいパスワード: <code class="bg-red-100 px-1 rounded">${newPassword}</code></div>
                            <div>• アカウント乗っ取りのリスクが発生</div>
                            <div>• ユーザーは正当なパスワードでログインできません</div>
                        </div>
                    `;
                    affectedSystems = [
                        { name: 'ユーザー認証システム', status: 'compromised', impact: '高' },
                        { name: 'パスワードデータベース', status: 'modified', impact: '高' },
                        { name: 'セッション管理', status: 'at-risk', impact: '中' }
                    ];
                    break;
                    
                case 'delete_account':
                    damageReport = `
                        <div class="font-bold text-red-600 mb-2">破滅的な被害が発生しました</div>
                        <div class="text-sm space-y-1">
                            <div>• ユーザーアカウントが完全に削除されました</div>
                            <div>• すべてのユーザーデータが失われました</div>
                            <div>• 復旧は不可能です</div>
                            <div>• サービスへの永続的なアクセス拒否</div>
                        </div>
                    `;
                    affectedSystems = [
                        { name: 'ユーザーデータベース', status: 'destroyed', impact: '最高' },
                        { name: 'バックアップシステム', status: 'at-risk', impact: '高' },
                        { name: 'アクセスログ', status: 'compromised', impact: '中' }
                    ];
                    break;
                    
                case 'transfer_funds':
                    const transferTo = document.getElementById('attack-account')?.value || 'attacker-account';
                    const transferAmount = document.getElementById('attack-amount')?.value || '1000000';
                    damageReport = `
                        <div class="font-bold text-red-600 mb-2">金銭的被害が発生しました</div>
                        <div class="text-sm space-y-1">
                            <div>• 不正な資金移動が実行されました</div>
                            <div>• 送金先: <code class="bg-red-100 px-1 rounded">${transferTo}</code></div>
                            <div>• 送金額: <code class="bg-red-100 px-1 rounded">${transferAmount}円</code></div>
                            <div>• 金融取引の信頼性が損なわれました</div>
                        </div>
                    `;
                    affectedSystems = [
                        { name: '決済システム', status: 'compromised', impact: '最高' },
                        { name: '財務データベース', status: 'modified', impact: '最高' },
                        { name: '取引ログ', status: 'corrupted', impact: '高' }
                    ];
                    break;
                    
                case 'change_email':
                    const newEmail = document.getElementById('attack-email')?.value || 'attacker@evil.com';
                    damageReport = `
                        <div class="font-bold text-red-600 mb-2">アカウント乗っ取りリスクが発生しました</div>
                        <div class="text-sm space-y-1">
                            <div>• メールアドレスが無断で変更されました</div>
                            <div>• 新しいメール: <code class="bg-red-100 px-1 rounded">${newEmail}</code></div>
                            <div>• パスワードリセット機能の悪用リスク</div>
                            <div>• アカウント復旧が困難になりました</div>
                        </div>
                    `;
                    affectedSystems = [
                        { name: 'ユーザープロファイル', status: 'modified', impact: '高' },
                        { name: 'メール通知システム', status: 'redirected', impact: '高' },
                        { name: 'パスワードリセット', status: 'at-risk', impact: '最高' }
                    ];
                    break;
            }
            
            reportContainer.innerHTML = damageReport;
            
            // 影響を受けたシステムの表示
            systemsContainer.innerHTML = affectedSystems.map(system => {
                const statusColor = system.status === 'destroyed' || system.status === 'compromised' ? 'bg-red-100 text-red-800' :
                                   system.status === 'modified' || system.status === 'corrupted' ? 'bg-orange-100 text-orange-800' :
                                   'bg-yellow-100 text-yellow-800';
                const impactColor = system.impact === '最高' ? 'bg-red-500' :
                                   system.impact === '高' ? 'bg-orange-500' : 'bg-yellow-500';
                
                return `<div class="flex items-center justify-between p-2 border rounded">
                    <div>
                        <span class="font-medium text-sm">${system.name}</span>
                        <span class="ml-2 px-2 py-1 rounded text-xs ${statusColor}">${system.status}</span>
                    </div>
                    <div class="flex items-center">
                        <span class="text-xs mr-2">影響度:</span>
                        <span class="w-2 h-2 rounded-full ${impactColor}"></span>
                        <span class="text-xs ml-1">${system.impact}</span>
                    </div>
                </div>`;
            }).join('');
            
        } else {
            reportContainer.innerHTML = `
                <div class="font-bold text-green-600 mb-2">攻撃は阻止されました</div>
                <div class="text-sm space-y-1">
                    <div>• CSRF防御機能により攻撃がブロックされました</div>
                    <div>• システムへの被害はありません</div>
                    <div>• ユーザーデータは保護されています</div>
                </div>
            `;
            
            systemsContainer.innerHTML = `
                <div class="flex items-center justify-between p-2 border rounded bg-green-50">
                    <div>
                        <span class="font-medium text-sm">全システム</span>
                        <span class="ml-2 px-2 py-1 rounded text-xs bg-green-100 text-green-800">保護済み</span>
                    </div>
                    <div class="flex items-center">
                        <span class="text-xs mr-2">セキュリティ:</span>
                        <span class="w-2 h-2 rounded-full bg-green-500"></span>
                        <span class="text-xs ml-1">正常</span>
                    </div>
                </div>
            `;
        }
    }
    
    updateVulnerabilityStatus() {
        const statusElements = document.querySelectorAll('.vulnerability-status');
        statusElements.forEach(element => {
            if (this.protectionEnabled) {
                element.innerHTML = '<span class="protected-indicator">🟢 CSRF攻撃から保護されています</span>';
            } else {
                element.innerHTML = '<span class="vulnerable-indicator">🔴 CSRF攻撃に対して脆弱です</span>';
            }
        });
    }
    
    runVulnerabilityScan() {
        const results = document.getElementById('scan-results');
        results.innerHTML = '<div class="text-blue-600">スキャン中...</div>';
        
        setTimeout(() => {
            let scanHTML = '<div class="space-y-2">';
            
            // CSRF トークンチェック
            if (document.getElementById('csrf-token-protection').checked) {
                scanHTML += '<div class="text-green-600">✓ CSRFトークン保護: 有効</div>';
            } else {
                scanHTML += '<div class="text-red-600">✗ CSRFトークン保護: 無効 (高リスク)</div>';
            }
            
            // その他のチェック
            if (document.getElementById('referer-check').checked) {
                scanHTML += '<div class="text-green-600">✓ Refererチェック: 有効</div>';
            } else {
                scanHTML += '<div class="text-yellow-600">! Refererチェック: 無効 (中リスク)</div>';
            }
            
            if (document.getElementById('same-site-cookie').checked) {
                scanHTML += '<div class="text-green-600">✓ SameSite Cookie: 有効</div>';
            } else {
                scanHTML += '<div class="text-yellow-600">! SameSite Cookie: 無効 (中リスク)</div>';
            }
            
            const riskLevel = this.protectionEnabled ? '低' : '高';
            const riskColor = this.protectionEnabled ? 'green' : 'red';
            
            scanHTML += `<div class="mt-3 p-2 border rounded bg-gray-50">
                <strong class="text-${riskColor}-600">総合リスク評価: ${riskLevel}</strong>
            </div>`;
            
            scanHTML += '</div>';
            results.innerHTML = scanHTML;
            
            this.logDetection('脆弱性スキャン実行', `総合リスク評価: ${riskLevel}`);
        }, 1500);
    }
    
    logAttack(action, description) {
        const timestamp = new Date().toLocaleTimeString();
        this.attackHistory.push({ timestamp, action, description });
        
        const historyContainer = document.getElementById('attack-history');
        historyContainer.innerHTML = this.attackHistory.map(item => 
            `<div class="border-b pb-2 mb-2">
                <div class="text-sm text-gray-500">${item.timestamp}</div>
                <div class="font-semibold text-red-600">${item.action}</div>
                <div class="text-sm">${item.description}</div>
            </div>`
        ).join('');
    }
    
    logDetection(event, description) {
        const timestamp = new Date().toLocaleTimeString();
        this.detectionLog.push({ timestamp, event, description });
        
        const logContainer = document.getElementById('detection-log');
        logContainer.innerHTML = this.detectionLog.map(item => 
            `<div class="border-b pb-2 mb-2">
                <div class="text-sm text-gray-500">${item.timestamp}</div>
                <div class="font-semibold text-green-600">${item.event}</div>
                <div class="text-sm">${item.description}</div>
            </div>`
        ).join('');
    }
    
    async handleFormSubmit(e, action) {
        e.preventDefault();
        
        const form = e.target;
        const formData = new FormData(form);
        
        try {
            const response = await fetch('csrf_target.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.protected) {
                this.logDetection('CSRF攻撃をブロック', `${action} への攻撃がブロックされました: ${result.message}`);
                alert('操作が拒否されました: ' + result.message);
            } else if (result.success) {
                this.logDetection('操作実行', result.message);
                alert('操作が完了しました: ' + result.message);
            } else {
                this.logDetection('操作エラー', result.message);
                alert('エラー: ' + result.message);
            }
        } catch (error) {
            console.error('Form submit error:', error);
            alert('通信エラーが発生しました');
        }
    }
    
    initializeDefenseStatusIndicators() {
        // 初期状態設定
        this.updateDefenseStatusIndicators();
    }
    
    updateDefenseStatusIndicators() {
        const csrf = document.getElementById('csrf-token-protection').checked;
        const referer = document.getElementById('referer-check').checked;
        const samesite = document.getElementById('same-site-cookie').checked;
        const origin = document.getElementById('origin-check').checked;
        
        // 各防御機能のステータス表示
        document.getElementById('csrf-status').className = csrf ? 
            'ml-auto text-xs px-2 py-1 rounded bg-green-100 text-green-800' : 
            'ml-auto text-xs px-2 py-1 rounded bg-red-100 text-red-800';
        document.getElementById('csrf-status').textContent = csrf ? '有効' : '無効';
        
        document.getElementById('referer-status').className = referer ? 
            'ml-auto text-xs px-2 py-1 rounded bg-green-100 text-green-800' : 
            'ml-auto text-xs px-2 py-1 rounded bg-red-100 text-red-800';
        document.getElementById('referer-status').textContent = referer ? '有効' : '無効';
        
        document.getElementById('samesite-status').className = samesite ? 
            'ml-auto text-xs px-2 py-1 rounded bg-green-100 text-green-800' : 
            'ml-auto text-xs px-2 py-1 rounded bg-red-100 text-red-800';
        document.getElementById('samesite-status').textContent = samesite ? '有効' : '無効';
        
        document.getElementById('origin-status').className = origin ? 
            'ml-auto text-xs px-2 py-1 rounded bg-green-100 text-green-800' : 
            'ml-auto text-xs px-2 py-1 rounded bg-red-100 text-red-800';
        document.getElementById('origin-status').textContent = origin ? '有効' : '無効';
    }
    
    updateSystemStatus() {
        const defenseLevel = document.getElementById('defense-level');
        const attackDetected = document.getElementById('attack-detected');
        const lastAttack = document.getElementById('last-attack');
        
        // 防御レベル表示
        if (this.protectionEnabled) {
            defenseLevel.className = 'ml-2 px-2 py-1 rounded text-xs font-bold bg-green-100 text-green-800';
            defenseLevel.textContent = '保護中';
        } else {
            defenseLevel.className = 'ml-2 px-2 py-1 rounded text-xs font-bold bg-red-100 text-red-800';
            defenseLevel.textContent = '脆弱';
        }
        
        // 攻撃検知状況
        if (this.stats.totalAttempts > 0) {
            attackDetected.className = 'ml-2 px-2 py-1 rounded text-xs font-bold bg-yellow-100 text-yellow-800';
            attackDetected.textContent = `${this.stats.totalAttempts}件検知`;
        } else {
            attackDetected.className = 'ml-2 px-2 py-1 rounded text-xs font-bold bg-gray-100 text-gray-800';
            attackDetected.textContent = 'なし';
        }
        
        // 最終攻撃時刻
        if (this.attackHistory.length > 0) {
            lastAttack.textContent = this.attackHistory[this.attackHistory.length - 1].timestamp;
        }
    }
    
    updateDamageStatus(status) {
        const damageStatus = document.getElementById('damage-status');
        
        switch (status) {
            case 'protected':
                damageStatus.className = 'ml-2 px-2 py-1 rounded text-xs font-bold bg-green-100 text-green-800';
                damageStatus.textContent = '正常';
                break;
            case 'compromised':
                damageStatus.className = 'ml-2 px-2 py-1 rounded text-xs font-bold bg-red-100 text-red-800';
                damageStatus.textContent = '侵害発生';
                break;
            default:
                damageStatus.className = 'ml-2 px-2 py-1 rounded text-xs font-bold bg-green-100 text-green-800';
                damageStatus.textContent = '正常';
        }
    }
    
    updateDefenseMetrics() {
        const blockRate = this.stats.totalAttempts > 0 ? 
            Math.round((this.stats.blockedAttacks / this.stats.totalAttempts) * 100) : 0;
        
        // プログレスバー更新
        document.getElementById('block-rate-bar').style.width = `${blockRate}%`;
        document.getElementById('block-rate-bar').className = blockRate >= 80 ? 
            'bg-green-600 h-2 rounded-full' : 
            blockRate >= 50 ? 'bg-yellow-600 h-2 rounded-full' : 'bg-red-600 h-2 rounded-full';
        
        // テキスト更新
        document.getElementById('block-rate-text').textContent = `${blockRate}%`;
        document.getElementById('attack-attempts').textContent = this.stats.totalAttempts;
        document.getElementById('blocked-attacks').textContent = this.stats.blockedAttacks;
        document.getElementById('successful-attacks').textContent = this.stats.successfulAttacks;
    }
    
    addSecurityAlert(type, title, message) {
        const timestamp = new Date().toLocaleTimeString();
        const alert = { timestamp, type, title, message };
        this.securityAlerts.unshift(alert); // 新しいアラートを先頭に
        
        // 最大10件まで保持
        if (this.securityAlerts.length > 10) {
            this.securityAlerts = this.securityAlerts.slice(0, 10);
        }
        
        const container = document.getElementById('security-alerts');
        container.innerHTML = this.securityAlerts.map(alert => {
            const colorClass = alert.type === 'success' ? 'border-green-200 bg-green-50' : 
                              alert.type === 'warning' ? 'border-yellow-200 bg-yellow-50' : 
                              'border-red-200 bg-red-50';
            const iconClass = alert.type === 'success' ? '🛡️' : 
                             alert.type === 'warning' ? '⚠️' : '🚨';
            
            return `<div class="border-l-4 ${colorClass} p-2 mb-2">
                <div class="flex items-center">
                    <span class="mr-2">${iconClass}</span>
                    <div class="flex-1">
                        <div class="text-sm font-semibold">${alert.title}</div>
                        <div class="text-xs">${alert.message}</div>
                    </div>
                    <div class="text-xs text-gray-500">${alert.timestamp}</div>
                </div>
            </div>`;
        }).join('');
    }
}

// 初期化
document.addEventListener('DOMContentLoaded', () => {
    new CSRFExercise();
});
</script>
</body>
</html>