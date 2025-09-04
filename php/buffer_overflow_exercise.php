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

// バッファオーバーフロー演習が有効でない場合は利用不可
if (empty($_SESSION['buffer_overflow_enabled'])) {
    header('Location: simulation_tools.php?error=' . urlencode('バッファオーバーフロー演習が無効です。simulation_toolsで有効化してください。'));
    exit;
}

$attack_executed = false;
$overflow_result = [];
$memory_corrupted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_data = $_POST['input_data'] ?? '';
    $buffer_size = (int)($_POST['buffer_size'] ?? 64);
    $target_program = $_POST['target_program'] ?? 'vulnerable_service';
    
    // 模擬バッファオーバーフローの判定
    $input_length = strlen($input_data);
    $attack_executed = true;
    
    // IDSログに記録
    if (function_exists('log_attack')) {
        log_attack($pdo, 'Buffer Overflow Simulation', 
            "Input length: {$input_length}, Buffer size: {$buffer_size}, Target: {$target_program}", 
            'buffer_overflow_exercise.php', 200);
    }
    
    if ($input_length > $buffer_size) {
        $memory_corrupted = true;
        $overflow_amount = $input_length - $buffer_size;
        
        // 模擬的な攻撃結果を生成
        $overflow_result = [
            'buffer_size' => $buffer_size,
            'input_length' => $input_length,
            'overflow_bytes' => $overflow_amount,
            'corrupted_memory' => simulate_memory_corruption($input_data, $buffer_size),
            'exploit_success' => ($overflow_amount >= 16), // 最低16バイトでRIP制御可能と仮定
            'shellcode_detected' => detect_shellcode_patterns($input_data),
            'return_address_overwrite' => ($overflow_amount >= 8)
        ];
        
        // 攻撃成功をログに記録
        if (function_exists('log_attack')) {
            $exploit_type = $overflow_result['exploit_success'] ? 'Successful Buffer Overflow' : 'Buffer Overflow Attempt';
            log_attack($pdo, $exploit_type, 
                "Overflow: {$overflow_amount} bytes, Shellcode: " . ($overflow_result['shellcode_detected'] ? 'Yes' : 'No'), 
                'buffer_overflow_exercise.php', $overflow_result['exploit_success'] ? 200 : 400);
        }
    }
}

function simulate_memory_corruption($input, $buffer_size) {
    $buffer_content = substr($input, 0, $buffer_size);
    $overflow_content = substr($input, $buffer_size);
    
    // 模擬メモリレイアウト
    $memory_layout = [
        'buffer' => str_pad($buffer_content, $buffer_size, "\x00"),
        'saved_rbp' => $overflow_content ? substr($overflow_content . str_repeat("\x41", 8), 0, 8) : "AAAAAAAA",
        'return_addr' => $overflow_content ? substr($overflow_content . str_repeat("\x42", 8), 8, 8) : "BBBBBBBB",
        'corrupted_data' => strlen($overflow_content) > 16 ? substr($overflow_content, 16) : ''
    ];
    
    return $memory_layout;
}

function detect_shellcode_patterns($input) {
    $shellcode_patterns = [
        '\x90', // NOP sled
        '\x31\xc0', // xor eax, eax
        '\x50', // push eax
        '\x68', // push immediate
        '\xb8', // mov eax, immediate
        '/bin/sh',
        'AAAA', // Typical buffer overflow padding
        'BBBB', // Return address overwrite pattern
        '\xcc', // int3 (breakpoint)
        '\xcd\x80' // int 0x80 (system call)
    ];
    
    foreach ($shellcode_patterns as $pattern) {
        if (strpos($input, $pattern) !== false) {
            return true;
        }
    }
    
    return false;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>バッファオーバーフロー攻撃演習</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .memory-cell { 
            font-family: 'Courier New', monospace; 
            font-size: 12px; 
            width: 40px; 
            height: 30px; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            margin: 1px;
            border: 1px solid #ccc;
        }
        .buffer-zone { background: #e6f3ff; }
        .overflow-zone { background: #ffe6e6; }
        .critical-zone { background: #ff9999; animation: pulse 2s infinite; }
        .shellcode-pattern { background: #ffff99; font-weight: bold; }
        
        @keyframes overflow-animation {
            0% { transform: translateX(-100%); opacity: 0; }
            50% { transform: translateX(0%); opacity: 1; }
            100% { transform: translateX(0%); opacity: 1; }
        }
        
        .overflow-visual {
            animation: overflow-animation 2s ease-out;
        }
    </style>
</head>
<body class="bg-gray-900 text-white">
<?php include 'header.php'; ?>

<div class="container mx-auto mt-10 p-4">
    <!-- 警告ヘッダー -->
    <div class="bg-gradient-to-r from-red-600 to-red-700 p-4 rounded-lg mb-8 text-center">
        <h1 class="text-3xl font-bold mb-2">⚠️ バッファオーバーフロー攻撃演習 ⚠️</h1>
        <p class="text-lg">これは教育目的の模擬演習です。実際のメモリ破壊は発生しません。</p>
    </div>

    <?php if ($attack_executed && $memory_corrupted): ?>
        <!-- 攻撃成功結果の表示 -->
        <div class="bg-red-900 border border-red-700 p-6 rounded-lg mb-8 overflow-visual">
            <div class="flex items-center mb-4">
                <div class="text-red-400 mr-3 text-2xl">💥</div>
                <h2 class="text-xl font-bold text-red-300">バッファオーバーフロー攻撃<?= $overflow_result['exploit_success'] ? '成功' : '検出' ?>！</h2>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-3">
                    <div class="text-sm">
                        <span class="font-semibold">バッファサイズ:</span> <?= $overflow_result['buffer_size'] ?> バイト
                    </div>
                    <div class="text-sm">
                        <span class="font-semibold">入力データ長:</span> <?= $overflow_result['input_length'] ?> バイト
                    </div>
                    <div class="text-sm">
                        <span class="font-semibold text-red-300">オーバーフロー:</span> <?= $overflow_result['overflow_bytes'] ?> バイト
                    </div>
                    <div class="text-sm">
                        <span class="font-semibold">リターンアドレス制御:</span> 
                        <span class="<?= $overflow_result['return_address_overwrite'] ? 'text-red-300' : 'text-gray-400' ?>">
                            <?= $overflow_result['return_address_overwrite'] ? '可能' : '不可' ?>
                        </span>
                    </div>
                    <div class="text-sm">
                        <span class="font-semibold">シェルコード検出:</span> 
                        <span class="<?= $overflow_result['shellcode_detected'] ? 'text-yellow-300' : 'text-gray-400' ?>">
                            <?= $overflow_result['shellcode_detected'] ? 'あり' : 'なし' ?>
                        </span>
                    </div>
                </div>
                
                <!-- メモリレイアウト視覚化 -->
                <div>
                    <h3 class="font-semibold mb-2">模擬メモリレイアウト:</h3>
                    <div class="bg-black p-4 rounded font-mono text-xs">
                        <div class="mb-2">
                            <span class="text-blue-300">バッファ領域:</span>
                            <div class="flex flex-wrap">
                                <?php 
                                $buffer = $overflow_result['corrupted_memory']['buffer'];
                                for ($i = 0; $i < strlen($buffer); $i++): 
                                    $byte = ord($buffer[$i]);
                                ?>
                                    <div class="memory-cell buffer-zone">
                                        <?= sprintf('%02X', $byte) ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <div class="mb-2">
                            <span class="text-red-300">保存されたRBP:</span>
                            <div class="flex">
                                <?php 
                                $rbp = $overflow_result['corrupted_memory']['saved_rbp'];
                                for ($i = 0; $i < 8; $i++): 
                                    $byte = isset($rbp[$i]) ? ord($rbp[$i]) : 0;
                                ?>
                                    <div class="memory-cell overflow-zone">
                                        <?= sprintf('%02X', $byte) ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <div class="mb-2">
                            <span class="text-red-300 font-bold">リターンアドレス:</span>
                            <div class="flex">
                                <?php 
                                $ret_addr = $overflow_result['corrupted_memory']['return_addr'];
                                for ($i = 0; $i < 8; $i++): 
                                    $byte = isset($ret_addr[$i]) ? ord($ret_addr[$i]) : 0;
                                ?>
                                    <div class="memory-cell critical-zone">
                                        <?= sprintf('%02X', $byte) ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($overflow_result['exploit_success']): ?>
                <div class="mt-4 p-3 bg-red-800 rounded">
                    <p class="text-red-200 font-semibold">🚨 エクスプロイト成功: リターンアドレスの制御が可能です</p>
                    <p class="text-xs text-red-300 mt-1">実際の攻撃では任意のコード実行が可能になります</p>
                </div>
            <?php endif; ?>
        </div>
        
    <?php elseif ($attack_executed && !$memory_corrupted): ?>
        <!-- 攻撃失敗 -->
        <div class="bg-green-900 border border-green-700 p-4 rounded-lg mb-8">
            <h2 class="text-lg font-bold text-green-300">攻撃失敗: バッファサイズ内に収まっています</h2>
            <p class="text-sm text-green-200">入力長: <?= strlen($input_data) ?> バイト ≤ バッファサイズ: <?= $buffer_size ?> バイト</p>
        </div>
    <?php endif; ?>

    <!-- 攻撃フォーム -->
    <div class="bg-gray-800 p-8 rounded-lg shadow-lg mb-8">
        <h2 class="text-2xl font-bold mb-6 text-center text-orange-400">模擬バッファオーバーフロー攻撃</h2>
        
        <form method="POST" class="space-y-6">
            <div>
                <label for="target_program" class="block text-sm font-medium mb-2">攻撃対象プログラム</label>
                <select id="target_program" name="target_program" class="w-full bg-gray-700 border border-gray-600 rounded-md px-3 py-2">
                    <option value="vulnerable_service">脆弱なサービス (C言語)</option>
                    <option value="legacy_daemon">レガシーデーモン</option>
                    <option value="network_service">ネットワークサービス</option>
                </select>
            </div>
            
            <div>
                <label for="buffer_size" class="block text-sm font-medium mb-2">バッファサイズ (バイト)</label>
                <select id="buffer_size" name="buffer_size" class="w-full bg-gray-700 border border-gray-600 rounded-md px-3 py-2">
                    <option value="32">32 バイト</option>
                    <option value="64" selected>64 バイト</option>
                    <option value="128">128 バイト</option>
                    <option value="256">256 バイト</option>
                </select>
            </div>
            
            <div>
                <label for="input_data" class="block text-sm font-medium mb-2">攻撃ペイロード</label>
                <textarea id="input_data" name="input_data" rows="6" 
                          class="w-full bg-gray-700 border border-gray-600 rounded-md px-3 py-2 font-mono text-sm"
                          placeholder="攻撃データを入力してください (例: AAAA...)"><?= htmlspecialchars($_POST['input_data'] ?? '') ?></textarea>
                <div class="mt-2 text-xs text-gray-400">
                    現在の入力長: <span id="input-length">0</span> バイト
                </div>
            </div>
            
            <button type="submit" class="w-full bg-red-600 text-white py-3 rounded-md hover:bg-red-700 font-semibold text-lg">
                🔴 バッファオーバーフロー攻撃を実行
            </button>
        </form>
    </div>

    <!-- 攻撃パターン例 -->
    <div class="bg-gray-800 p-6 rounded-lg mb-8">
        <h3 class="text-lg font-bold mb-4 text-blue-400">攻撃パターン例</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <h4 class="font-semibold text-white mb-2">基本的なオーバーフロー (80バイト)</h4>
                <button onclick="setPayload('<?= str_repeat('A', 80) ?>')" 
                        class="w-full text-left bg-gray-700 p-3 rounded text-sm font-mono hover:bg-gray-600">
                    <?= str_repeat('A', 40) ?>...(80文字)
                </button>
            </div>
            
            <div>
                <h4 class="font-semibold text-white mb-2">NOPスレッド + シェルコード (120バイト)</h4>
                <button onclick="setPayload('<?= str_repeat('\x90', 60) . '\x31\xc0\x50\x68//sh\x68/bin\x89\xe3\x50\x53\x89\xe1\xb0\x0b\xcd\x80' . str_repeat('B', 40) ?>')" 
                        class="w-full text-left bg-gray-700 p-3 rounded text-sm font-mono hover:bg-gray-600">
                    \x90\x90...\x31\xc0\x50...(120文字)
                </button>
            </div>
            
            <div>
                <h4 class="font-semibold text-white mb-2">リターンアドレス制御 (72バイト)</h4>
                <button onclick="setPayload('<?= str_repeat('A', 64) . 'BBBBBBBB' ?>')" 
                        class="w-full text-left bg-gray-700 p-3 rounded text-sm font-mono hover:bg-gray-600">
                    <?= str_repeat('A', 32) ?>...BBBBBBBB(72文字)
                </button>
            </div>
            
            <div>
                <h4 class="font-semibold text-white mb-2">大量データ攻撃 (1000バイト)</h4>
                <button onclick="setPayload('<?= str_repeat('X', 1000) ?>')" 
                        class="w-full text-left bg-gray-700 p-3 rounded text-sm font-mono hover:bg-gray-600">
                    <?= str_repeat('X', 40) ?>...(1000文字)
                </button>
            </div>
        </div>
    </div>

    <!-- 教育的説明 -->
    <div class="bg-gray-800 p-6 rounded-lg">
        <h3 class="text-lg font-bold mb-4 text-green-400">バッファオーバーフロー攻撃について</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm text-gray-300">
            <div>
                <h4 class="font-semibold text-white mb-2">攻撃の仕組み</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>固定サイズのバッファに想定以上のデータを送信</li>
                    <li>隣接するメモリ領域を上書き</li>
                    <li>リターンアドレスを攻撃者の制御下に置く</li>
                    <li>任意のコード実行を達成</li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold text-white mb-2">対策技術</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>スタックカナリア (Stack Canaries)</li>
                    <li>ASLR (Address Space Layout Randomization)</li>
                    <li>NX bit (No-Execute)</li>
                    <li>安全な関数の使用 (strcpy → strncpy)</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
function setPayload(payload) {
    document.getElementById('input_data').value = payload;
    updateInputLength();
}

function updateInputLength() {
    const input = document.getElementById('input_data');
    const lengthDisplay = document.getElementById('input-length');
    lengthDisplay.textContent = input.value.length;
    
    // バッファサイズとの比較で色を変更
    const bufferSize = parseInt(document.getElementById('buffer_size').value);
    if (input.value.length > bufferSize) {
        lengthDisplay.className = 'text-red-400 font-bold';
    } else {
        lengthDisplay.className = 'text-green-400';
    }
}

document.getElementById('input_data').addEventListener('input', updateInputLength);
document.getElementById('buffer_size').addEventListener('change', updateInputLength);

// 初期化
updateInputLength();

// 攻撃実行前の確認
document.querySelector('form').addEventListener('submit', function(e) {
    const inputLength = document.getElementById('input_data').value.length;
    const bufferSize = parseInt(document.getElementById('buffer_size').value);
    
    if (inputLength > bufferSize) {
        const confirmed = confirm(
            `⚠️ バッファオーバーフロー攻撃を実行します\n\n` +
            `バッファサイズ: ${bufferSize} バイト\n` +
            `入力データ: ${inputLength} バイト\n` +
            `オーバーフロー: ${inputLength - bufferSize} バイト\n\n` +
            `実行しますか？（教育演習）`
        );
        if (!confirmed) {
            e.preventDefault();
        }
    }
});
</script>

</body>
</html>