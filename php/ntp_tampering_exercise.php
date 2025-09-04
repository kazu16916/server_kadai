<?php
// ntp_tampering_exercise.php
require_once __DIR__ . '/common_init.php';
require 'db.php';

// 管理者のみ許可
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: list.php');
    exit;
}

// NTP改ざん攻撃演習が無効の場合は戻す
if (empty($_SESSION['ntp_tampering_enabled'])) {
    header('Location: simulation_tools.php?error=' . urlencode('NTP改ざん攻撃演習を先に有効化してください。'));
    exit;
}

// 現在のNTP攻撃状態を取得
$ntp_status = $_SESSION['ntp_attack_status'] ?? 'preparing';
$time_offset = $_SESSION['ntp_time_offset'] ?? 0;

// POSTリクエスト処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'scan_ntp_servers':
            // NTPサーバスキャンのシミュレーション
            $_SESSION['ntp_attack_status'] = 'scanning';
            $attack_detail = 'NTP server discovery scan initiated';
            if (function_exists('log_attack')) {
                log_attack($pdo, 'NTP Tampering: Server Discovery', $attack_detail, 'ntp_tampering_exercise.php', 200);
            }
            header('Location: ntp_tampering_exercise.php?success=' . urlencode('NTPサーバスキャンを開始しました'));
            exit;
            
        case 'exploit_ntp_server':
            // NTPサーバ攻撃のシミュレーション
            $_SESSION['ntp_attack_status'] = 'attacking';
            $attack_detail = 'NTP server exploitation attempt';
            if (function_exists('log_attack')) {
                log_attack($pdo, 'NTP Tampering: Server Exploitation', $attack_detail, 'ntp_tampering_exercise.php', 200);
            }
            header('Location: ntp_tampering_exercise.php?success=' . urlencode('NTPサーバ攻撃を実行しました'));
            exit;
            
        case 'compromise_ntp':
            // NTPサーバ改ざん成功のシミュレーション
            $_SESSION['ntp_attack_status'] = 'compromised';
            $attack_detail = 'NTP server successfully compromised - time manipulation enabled';
            if (function_exists('log_attack')) {
                log_attack($pdo, 'NTP Tampering: Server Compromised', $attack_detail, 'ntp_tampering_exercise.php', 200);
            }
            header('Location: ntp_tampering_exercise.php?success=' . urlencode('NTPサーバの改ざんに成功しました！時刻操作が可能になりました'));
            exit;
            
        case 'set_time_offset':
            // 時刻オフセットの設定
            $offset_hours = (int)($_POST['offset_hours'] ?? 0);
            $offset_minutes = (int)($_POST['offset_minutes'] ?? 0);
            $direction = $_POST['direction'] ?? 'future';
            
            $total_offset_seconds = ($offset_hours * 3600) + ($offset_minutes * 60);
            if ($direction === 'past') {
                $total_offset_seconds = -$total_offset_seconds;
            }
            
            $_SESSION['ntp_time_offset'] = $total_offset_seconds;
            
            $attack_detail = sprintf('Time offset set to %+d seconds (%s)',
                $total_offset_seconds,
                $direction === 'future' ? 'future' : 'past'
            );
            
            if (function_exists('log_attack')) {
                log_attack($pdo, 'NTP Tampering: Time Offset Applied', $attack_detail, 'ntp_tampering_exercise.php', 200);
            }
            
            header('Location: ntp_tampering_exercise.php?success=' . urlencode(sprintf('時刻オフセットを%+d秒に設定しました', $total_offset_seconds)));
            exit;
            
        case 'reset_attack':
            // 攻撃状態をリセット
            $_SESSION['ntp_attack_status'] = 'preparing';
            $_SESSION['ntp_time_offset'] = 0;
            header('Location: ntp_tampering_exercise.php?success=' . urlencode('NTP攻撃状態をリセットしました'));
            exit;
    }
}

// 現在の改ざん時刻を計算
$current_real_time = time();
$tampered_time = $current_real_time + $time_offset;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>NTP改ざん攻撃演習</title>
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
        .status-attacking { background: #fed7d7; color: #c53030; }
        .status-compromised { background: #d1fae5; color: #065f46; }
        .time-display {
            font-family: 'Courier New', monospace;
            font-size: 1.2em;
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-gray-100">
<?php include 'header.php'; ?>

<div class="container mx-auto mt-6 p-4">
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
        <p class="font-bold">⚠️ 教育演習モード</p>
        <p>これはNTP改ざん攻撃の教育演習です。実際のNTPサーバは改ざんされません。</p>
    </div>

    <h1 class="text-3xl font-bold text-gray-800 mb-6">🕐 NTP改ざん攻撃演習</h1>

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
                    'attacking' => 'status-attacking',
                    'compromised' => 'status-compromised'
                ];
                $status_labels = [
                    'preparing' => '準備中',
                    'scanning' => 'NTPサーバスキャン中',
                    'attacking' => 'NTPサーバ攻撃中',
                    'compromised' => 'NTPサーバ改ざん完了'
                ];
                ?>
                
                <div class="<?= $status_classes[$ntp_status] ?> p-3 rounded-lg mb-4">
                    <strong>現在の状態: <?= $status_labels[$ntp_status] ?></strong>
                </div>
                
                <!-- 時刻情報 -->
                <div class="grid grid-cols-1 gap-4">
                    <div class="bg-gray-50 p-3 rounded">
                        <div class="text-sm text-gray-600">実際の時刻</div>
                        <div class="time-display text-blue-600">
                            <?= date('Y-m-d H:i:s', $current_real_time) ?>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-3 rounded">
                        <div class="text-sm text-gray-600">改ざん後の時刻</div>
                        <div class="time-display <?= $time_offset !== 0 ? 'text-red-600' : 'text-gray-600' ?>">
                            <?= date('Y-m-d H:i:s', $tampered_time) ?>
                            <?php if ($time_offset !== 0): ?>
                                <span class="text-sm">(<?= sprintf('%+d秒', $time_offset) ?>)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 攻撃フェーズ制御 -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">攻撃フェーズ</h2>
                
                <div class="space-y-3">
                    <?php if ($ntp_status === 'preparing'): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="scan_ntp_servers">
                            <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700">
                                フェーズ 1: NTPサーバを発見
                            </button>
                        </form>
                    <?php elseif ($ntp_status === 'scanning'): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="exploit_ntp_server">
                            <button type="submit" class="w-full bg-orange-600 text-white py-2 px-4 rounded hover:bg-orange-700">
                                フェーズ 2: NTPサーバを攻撃
                            </button>
                        </form>
                    <?php elseif ($ntp_status === 'attacking'): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="compromise_ntp">
                            <button type="submit" class="w-full bg-red-600 text-white py-2 px-4 rounded hover:bg-red-700">
                                フェーズ 3: NTPサーバを改ざん
                            </button>
                        </form>
                    <?php elseif ($ntp_status === 'compromised'): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 p-3 rounded">
                            ✅ NTPサーバの改ざんに成功しました！<br>
                            時刻操作が可能になりました。
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

            <!-- 時刻操作パネル -->
            <?php if ($ntp_status === 'compromised'): ?>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">時刻操作</h2>
                
                <form method="POST">
                    <input type="hidden" name="action" value="set_time_offset">
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">時間</label>
                            <select name="offset_hours" class="w-full border rounded px-3 py-2">
                                <?php for ($h = 0; $h <= 23; $h++): ?>
                                <option value="<?= $h ?>"><?= $h ?>時間</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">分</label>
                            <select name="offset_minutes" class="w-full border rounded px-3 py-2">
                                <?php for ($m = 0; $m <= 59; $m += 5): ?>
                                <option value="<?= $m ?>"><?= $m ?>分</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">方向</label>
                        <div class="flex space-x-4">
                            <label class="flex items-center">
                                <input type="radio" name="direction" value="future" checked class="mr-2">
                                未来に進める
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="direction" value="past" class="mr-2">
                                過去に戻す
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full bg-red-600 text-white py-2 px-4 rounded hover:bg-red-700">
                        時刻オフセットを適用
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <!-- 右カラム: ログと情報 -->
        <div class="space-y-6">
            
            <!-- ターミナル出力 -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">攻撃ログ</h2>
                
                <div class="terminal" id="terminal-output">
                    <div>[SYSTEM] NTP改ざん攻撃演習が開始されました</div>
                    <?php if ($ntp_status !== 'preparing'): ?>
                    <div>[SCAN] NTPサーバのスキャンを実行中...</div>
                    <div>[SCAN] 192.168.1.1:123 - NTP server detected</div>
                    <div>[SCAN] 10.0.0.1:123 - NTP server detected</div>
                    <div>[SCAN] 172.16.0.1:123 - NTP server detected</div>
                    <?php endif; ?>
                    
                    <?php if (in_array($ntp_status, ['attacking', 'compromised'])): ?>
                    <div>[EXPLOIT] NTPサーバの脆弱性をスキャン中...</div>
                    <div>[EXPLOIT] CVE-2023-xxxx vulnerability found!</div>
                    <div>[EXPLOIT] Attempting buffer overflow...</div>
                    <div>[EXPLOIT] Root access gained to NTP server!</div>
                    <?php endif; ?>
                    
                    <?php if ($ntp_status === 'compromised'): ?>
                    <div style="color: #ff6b6b;">[COMPROMISE] NTPサーバの時刻設定を改ざん中...</div>
                    <div style="color: #ff6b6b;">[COMPROMISE] Time synchronization disabled</div>
                    <div style="color: #ff6b6b;">[COMPROMISE] Custom time offset applied</div>
                    <div style="color: #00ff00;">[SUCCESS] NTP server successfully compromised!</div>
                    <div style="color: #00ff00;">[SUCCESS] Time manipulation is now active</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- NTP攻撃の影響 -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">攻撃の影響</h2>
                
                <div class="space-y-3">
                    <div class="flex items-center">
                        <div class="w-4 h-4 rounded-full mr-3 <?= $ntp_status === 'compromised' ? 'bg-red-500' : 'bg-gray-300' ?>"></div>
                        <span class="<?= $ntp_status === 'compromised' ? 'text-red-600 font-semibold' : 'text-gray-500' ?>">
                            IDSログの時刻が改ざん
                        </span>
                    </div>
                    
                    <div class="flex items-center">
                        <div class="w-4 h-4 rounded-full mr-3 <?= $ntp_status === 'compromised' ? 'bg-red-500' : 'bg-gray-300' ?>"></div>
                        <span class="<?= $ntp_status === 'compromised' ? 'text-red-600 font-semibold' : 'text-gray-500' ?>">
                            ログ解析の妨害
                        </span>
                    </div>
                    
                    <div class="flex items-center">
                        <div class="w-4 h-4 rounded-full mr-3 <?= $ntp_status === 'compromised' ? 'bg-red-500' : 'bg-gray-300' ?>"></div>
                        <span class="<?= $ntp_status === 'compromised' ? 'text-red-600 font-semibold' : 'text-gray-500' ?>">
                            タイムライン追跡の困難化
                        </span>
                    </div>
                    
                    <div class="flex items-center">
                        <div class="w-4 h-4 rounded-full mr-3 <?= $ntp_status === 'compromised' ? 'bg-red-500' : 'bg-gray-300' ?>"></div>
                        <span class="<?= $ntp_status === 'compromised' ? 'text-red-600 font-semibold' : 'text-gray-500' ?>">
                            証拠保全の阻害
                        </span>
                    </div>
                </div>
            </div>

            <!-- 検証ツール -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">検証ツール</h2>
                
                <div class="space-y-3">
                    <a href="ids_dashboard.php" class="block w-full bg-blue-600 text-white text-center py-2 px-4 rounded hover:bg-blue-700">
                        IDSダッシュボードで時刻を確認
                    </a>
                    
                    <button onclick="createTestLog()" class="w-full bg-green-600 text-white py-2 px-4 rounded hover:bg-green-700">
                        テストログを生成
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// テストログ生成
async function createTestLog() {
    try {
        await fetch('ids_event.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                attack_type: 'NTP Tampering Test',
                detail: 'Test log entry to verify time tampering',
                status_code: 200
            })
        });
        alert('テストログを生成しました。IDSダッシュボードで確認してください。');
    } catch (e) {
        alert('テストログの生成に失敗しました: ' + e.message);
    }
}

// リアルタイム時刻更新（5秒ごと）
setInterval(() => {
    location.reload();
}, 5000);
</script>

</body>
</html>