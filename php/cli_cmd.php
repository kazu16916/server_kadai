<?php
require_once __DIR__ . '/common_init.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','0');
ini_set('log_errors','1');
ob_start();

register_shutdown_function(function(){
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR])) {
        while (ob_get_level()) ob_end_clean();
        echo json_encode(['ok'=>false,'error'=>'fatal','msg'=>'内部エラー（ログ参照）'], JSON_UNESCAPED_UNICODE);
    }
});

set_exception_handler(function($ex){
    error_log('[cli_cmd] '.$ex->getMessage());
    while (ob_get_level()) ob_end_clean();
    echo json_encode(['ok'=>false,'error'=>'exception','msg'=>'内部例外発生'], JSON_UNESCAPED_UNICODE);
    exit;
});

// 権限・有効化チェック（リダイレクト禁止）
if (($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['ok'=>false,'error'=>'perm','msg'=>'adminのみ利用可'], JSON_UNESCAPED_UNICODE); 
    exit;
}
if (empty($_SESSION['cli_attack_mode_enabled'])) {
    echo json_encode(['ok'=>false,'error'=>'disabled','msg'=>'CLI演習は無効です'], JSON_UNESCAPED_UNICODE); 
    exit;
}

// トークン
$token_client = $_SERVER['HTTP_X_CLI_TOKEN'] ?? ($_POST['token'] ?? '');
$token_server = $_SESSION['cli_attack_api_token'] ?? '';
if (!$token_client || !$token_server || !hash_equals($token_server, $token_client)) {
    echo json_encode(['ok'=>false,'error'=>'token','msg'=>'トークン不一致/未設定'], JSON_UNESCAPED_UNICODE); 
    exit;
}

// 入力
$raw = file_get_contents('php://input');
$in  = json_decode($raw ?: '[]', true);
$cmdline = trim((string)($in['cmd'] ?? ''));

function out($lines){ 
    echo json_encode(['ok'=>true,'lines'=>$lines], JSON_UNESCAPED_UNICODE); 
    exit; 
}

// 任意: CLIイベントを別テーブルに記録（存在しない環境では自動で無視）
function log_cli_event($pdo, $type, $meta){
    try{
        $stmt = $pdo->prepare("INSERT INTO cli_events (event_type, meta, ip) VALUES (?, ?, ?)");
        $ip = $_SESSION['simulated_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? '-');
        $stmt->execute([$type, $meta, $ip]);
    } catch(Throwable $e) { 
        /* noop */ 
    }
}

if ($cmdline === '') {
    out(['コマンドが空です。help を参照してください。']);
}
$parts = preg_split('/\s+/', $cmdline);
$cmd   = strtolower($parts[0] ?? '');

switch ($cmd) {
    case 'help':
        out([
            '*** 模擬 CLI ヘルプ ***',
            'scan <port|start-end> [--tool nmap|zmap]   仮想ポートスキャン通知',
            'bruteforce <username> <length>            総当たり (擬似)',
            'spray <pattern> [--pw CSV]                パスワードスプレー (擬似)',
            'sqlinj <target>                           SQLi 試行 (擬似)',
            'rootkit <install|hide|show|remove> [...]  ルートキット演習 (擬似)',
            'echo <text>                               エコー',
            'clear                                     画面クリア'
        ]);
        break;

    case 'scan':
        $detail = 'CLI scan: ' . $cmdline;
        if (function_exists('log_attack')) {
            log_attack($pdo, 'CLI Scan', $detail, ($_SESSION['simulated_ip'] ?? 'cli'), 200);
        }
        log_cli_event($pdo, 'scan', $detail);
        out(["[OK] 仮想スキャンイベントを記録: $cmdline"]);
        break;

    case 'bruteforce':
        // 形式: bruteforce <username> <length
        $username = $parts[1] ?? '';
        $length   = isset($parts[2]) && ctype_digit($parts[2]) ? (int)$parts[2] : null;
        if ($username === '') {
            out(['使い方: bruteforce <username> <length?>']);
        }

        // admin だけ特別に「模擬クラック完了」を出す
        if (strtolower($username) === 'admin') {
            $pw = 'administrator';
            $len = $length ?: strlen($pw);

            $lines = [
                "🎰 ターゲット: {$username} / 想定桁数: {$len}",
                "… 一文字ずつ推測モードで解析を開始",
                "位置 1: 'a' が確定",
                "位置 2: 'd' が確定",
                "位置 3: 'm' が確定",
                "位置 4: 'i' が確定",
                "位置 5: 'n' が確定",
                "位置 6: 'i' が確定",
                "位置 7: 's' が確定",
                "位置 8: 't' が確定",
                "位置 9: 'r' が確定",
                "位置 10: 'a' が確定",
                "位置 11: 't' が確定",
                "位置 12: 'o' が確定",
                "位置 13: 'r' が確定",
                "✅ 完全なパスワード発見: {$pw}（user: {$username}）",
            ];

            // IDS / モニタへ「成功」を記録
            $detail = "CLI Bruteforce Success: user={$username}, password={$pw}";
            if (function_exists('log_attack')) {
                log_attack($pdo, 'CLI Bruteforce Success', $detail, ($_SESSION['simulated_ip'] ?? 'cli'), 200);
            }
            log_cli_event($pdo, 'bruteforce', $detail);

            out($lines);
            break;
        }

        // admin 以外は従来どおりの簡易ログのみ
        if (function_exists('log_attack')) {
            log_attack($pdo, 'CLI Bruteforce', $cmdline, ($_SESSION['simulated_ip'] ?? 'cli'), 200);
        }
        log_cli_event($pdo, 'bruteforce', $cmdline);
        out(['[OK] 総当たり(擬似)イベントを記録しました。']);
        break;

    case 'spray':
        if (function_exists('log_attack')) {
            log_attack($pdo, 'CLI Spray', $cmdline, ($_SESSION['simulated_ip'] ?? 'cli'), 200);
        }
        log_cli_event($pdo, 'spray', $cmdline);
        out(['[OK] スプレー(擬似)イベントを記録しました。']);
        break;

    case 'sqlinj':
        if (function_exists('log_attack')) {
            log_attack($pdo, 'CLI SQLi', $cmdline, ($_SESSION['simulated_ip'] ?? 'cli'), 200);
        }
        log_cli_event($pdo, 'sqli', $cmdline);
        out(['[OK] SQLi(擬似)イベントを記録しました。']);
        break;

    case 'echo':
        out([substr($cmdline, 5)]);
        break;

    case 'clear':
        out(['__CLEAR__']);
        break;

        case 'rootkit':
        {
        // ルートキットの「状態」をセッションに保持（擬似）
        if (!isset($_SESSION['rootkit_state'])) {
            $_SESSION['rootkit_state'] = [
            'installed' => false,
            'hidden' => ['pids'=>[], 'files'=>[], 'ports'=>[]],
            'installed_at' => null,
            ];
        }
        $st = &$_SESSION['rootkit_state'];

        $sub = strtolower($parts[1] ?? '');
        if ($sub === '' || $sub === 'help') {
            out([
            '使い方: rootkit install',
            '      : rootkit hide pid <PID>',
            '      : rootkit hide file </path/to/file>',
            '      : rootkit hide port <PORT>',
            '      : rootkit show',
            '      : rootkit remove',
            ]);
        }

        // ---- install（擬似インストール）----
        if ($sub === 'install') {
            if ($st['installed']) {
            out(['[INFO] 擬似ルートキットは既に導入済みです。', 'status: installed']);
            }
            $st['installed'] = true;
            $st['installed_at'] = date('c');

            $detail = 'Rootkit installed (simulated)';
            if (function_exists('log_attack')) {
            log_attack($pdo, 'CLI Rootkit Install', $detail, ($_SESSION['simulated_ip'] ?? 'cli'), 200);
            }
            log_cli_event($pdo, 'rootkit', $detail);

            out([
            '🧩 擬似ルートキットを導入しました（実際の変更は行いません）',
            'status: installed',
            "installed_at: {$st['installed_at']}",
            ]);
        }

        // ---- hide（擬似隠蔽）----
        if ($sub === 'hide') {
            if (!$st['installed']) {
            out(['[WARN] 先に rootkit install を実行してください。']);
            }
            $kind = strtolower($parts[2] ?? '');
            $val  = trim($parts[3] ?? '');

            if (!in_array($kind, ['pid','file','port'], true) || $val==='') {
            out(['使い方: rootkit hide pid <PID> | rootkit hide file <FILE> | rootkit hide port <PORT>']);
            }
            if ($kind === 'pid') {
            if (!ctype_digit($val)) out(['PID は数値で指定してください。']);
            $st['hidden']['pids'][] = (int)$val;
            } elseif ($kind === 'file') {
            $st['hidden']['files'][] = $val;
            } else { // port
            if (!ctype_digit($val)) out(['PORT は数値で指定してください。']);
            $st['hidden']['ports'][] = (int)$val;
            }

            $detail = "Rootkit hide {$kind}={$val} (simulated)";
            if (function_exists('log_attack')) {
            log_attack($pdo, 'CLI Rootkit Hide', $detail, ($_SESSION['simulated_ip'] ?? 'cli'), 200);
            }
            log_cli_event($pdo, 'rootkit', $detail);

            out([
            "🔒 {$kind}={$val} を隠蔽リストに追加しました（擬似）",
            'hidden_summary: ' . json_encode($st['hidden'], JSON_UNESCAPED_UNICODE),
            ]);
        }

        // ---- show（状態表示）----
        if ($sub === 'show') {
            $lines = [
            '=== ルートキット状態（擬似）===',
            'installed: ' . ($st['installed'] ? 'yes' : 'no'),
            'installed_at: ' . ($st['installed_at'] ?? '-'),
            'hidden.pids: '  . implode(', ', $st['hidden']['pids']),
            'hidden.files: ' . implode(', ', $st['hidden']['files']),
            'hidden.ports: ' . implode(', ', $st['hidden']['ports']),
            ];
            out($lines);
        }

        // ---- remove（擬似削除）----
        if ($sub === 'remove') {
            $st = [
            'installed' => false,
            'hidden' => ['pids'=>[], 'files'=>[], 'ports'=>[]],
            'installed_at' => null,
            ];
            $_SESSION['rootkit_state'] = $st;

            $detail = "Rootkit removed (simulated)";
            if (function_exists('log_attack')) {
            log_attack($pdo, 'CLI Rootkit Remove', $detail, ($_SESSION['simulated_ip'] ?? 'cli'), 200);
            }
            log_cli_event($pdo, 'rootkit', $detail);

            out(['🧹 擬似ルートキットを削除しました。', 'status: removed']);
        }

        // 未対応サブコマンド
        out(['未知の rootkit サブコマンドです。help を参照してください。']);
        break;
        }
    default:
        out(["未知のコマンドです: $cmd"]);
        break;
}