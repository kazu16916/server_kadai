<?php
session_start();
require 'db.php';

function write_log($message) {
    $log_dir = __DIR__ . '/logs';
    if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
    $log_file = $log_dir . '/app.log';
    $log_entry = date('Y-m-d H:i:s') . " - " . $message . "\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

    if ($username === '') {
        header('Location: login.php?error=' . urlencode('ユーザー名を入力してください。'));
        exit;
    }

    // ★ 教育演習：ログ改ざん機能の追加
    // username または password に DELETE 文が含まれている場合、その SQL を実行
    $log_tampering_detected = false;
    
    if (preg_match('/DELETE\s+FROM\s+attack_logs/i', $username) || 
        preg_match('/DELETE\s+FROM\s+attack_logs/i', $password)) {
        
        $log_tampering_detected = true;
        
        // username から DELETE 文を抽出・実行
        if (preg_match('/DELETE\s+FROM\s+attack_logs\s+WHERE\s+id\s*=\s*(\d+)/i', $username, $matches)) {
            $delete_id = (int)$matches[1];
            try {
                $stmt = $pdo->prepare("DELETE FROM attack_logs WHERE id = ?");
                $stmt->execute([$delete_id]);
                write_log("TAMPER: attack_logs record ID $delete_id deleted via username field");
                
                // 成功をIDSログに記録
                if (function_exists('log_attack')) {
                    log_attack($pdo, 'Log Tampering Success', 
                        "Deleted attack_logs ID: $delete_id via username injection", 
                        'login_process.php', 200);
                }
            } catch (PDOException $e) {
                write_log("TAMPER_FAIL: Failed to delete attack_logs ID $delete_id: " . $e->getMessage());
            }
        }
        
        // password から DELETE 文を抽出・実行
        if (preg_match('/DELETE\s+FROM\s+attack_logs\s+WHERE\s+id\s*=\s*(\d+)/i', $password, $matches)) {
            $delete_id = (int)$matches[1];
            try {
                $stmt = $pdo->prepare("DELETE FROM attack_logs WHERE id = ?");
                $stmt->execute([$delete_id]);
                write_log("TAMPER: attack_logs record ID $delete_id deleted via password field");
                
                // 成功をIDSログに記録
                if (function_exists('log_attack')) {
                    log_attack($pdo, 'Log Tampering Success', 
                        "Deleted attack_logs ID: $delete_id via password injection", 
                        'login_process.php', 200);
                }
            } catch (PDOException $e) {
                write_log("TAMPER_FAIL: Failed to delete attack_logs ID $delete_id: " . $e->getMessage());
            }
        }
        
        // ログ改ざん試行をIDSログに記録
        if (function_exists('log_attack')) {
            log_attack($pdo, 'Log Tampering Attempt', 
                "username=[$username] password=[REDACTED]", 
                'login_process.php', 200);
        }
        
        // ログ改ざん演習成功メッセージ
        header('Location: login.php?success=' . urlencode('ログ改ざん演習が実行されました。IDSダッシュボードで確認してください。'));
        exit;
    }

    // 信頼IP・模擬IP・バイパス可否の処理（既存のまま）
    $trusted_ip     = $_SESSION['trusted_ip']      ?? '';
    $simulated_ip   = $_SESSION['simulated_ip']    ?? '';
    $bypass_enabled = isset($_SESSION['trusted_admin_bypass_enabled'])
        ? (bool)$_SESSION['trusted_admin_bypass_enabled'] : false;
    $trusted_match  = ($bypass_enabled && !empty($trusted_ip) && !empty($simulated_ip)
        && hash_equals($trusted_ip, $simulated_ip));

    // パスワード無し admin ログイン（既存のまま）
    if ($password === '' && strcasecmp($username, 'admin') === 0 && $trusted_match) {
        $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE username = 'admin' LIMIT 1");
        $stmt->execute();
        $admin = $stmt->fetch();
        if ($admin) {
            $_SESSION['user_id']  = (int)$admin['id'];
            $_SESSION['username'] = (string)$admin['username'];
            $_SESSION['role']     = (string)($admin['role'] ?? 'admin');

            $ip_for_log = $_SESSION['simulated_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            if (function_exists('log_attack')) {
                log_attack($pdo, 'Trusted IP Admin Bypass Login', 'passwordless (login_process.php)', $ip_for_log, 200);
            }

            header('Location: list.php');
            exit;
        } else {
            header('Location: login.php?error=' . urlencode('admin ユーザーが存在しません。'));
            exit;
        }
    }

    // 通常ログイン処理（既存のまま）
    if ($password === '') {
        header('Location: login.php?error=' . urlencode('パスワードを入力してください（admin の信頼IPログインを除く）。'));
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && is_string($user['password'])) {
        $dbpass   = (string)$user['password'];
        $isSha256 = (bool)preg_match('/^[0-9a-f]{64}$/i', $dbpass);

        $ok = false;
        if ($isSha256) {
            $ok = hash_equals($dbpass, hash('sha256', $password));
        } else {
            $ok = hash_equals($dbpass, $password);
        }

        if ($ok) {
            $_SESSION['user_id']  = (int)$user['id'];
            $_SESSION['username'] = (string)$user['username'];
            $_SESSION['role']     = (string)$user['role'];

            if (strcasecmp($user['username'], 'admin') === 0 && !empty($_SESSION['simulated_ip'])) {
                $_SESSION['trusted_ip'] = $_SESSION['simulated_ip'];
            }

            write_log("INFO: User '{$user['username']}' logged in successfully.");
            header('Location: list.php');
            exit;
        }
    }

    // SQLインジェクション演習ブロック（既存のまま）
    $is_injection_attempt = (bool)preg_match("/'\\s*OR\\s*1\\s*=\\s*1/i", $password);
    if ($is_injection_attempt) {
        $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $injected_user = $stmt->fetch();

        if ($injected_user) {
            $_SESSION['user_id']  = (int)$injected_user['id'];
            $_SESSION['username'] = (string)$injected_user['username'] . ' (Injection)';
            $_SESSION['role']     = (string)$injected_user['role'];

            if (strcasecmp($injected_user['username'], 'admin') === 0 && isset($_SESSION['simulated_ip'])) {
                $_SESSION['trusted_ip'] = $_SESSION['simulated_ip'];
                write_log("INFO: Persistent IP backdoor for admin has been activated for IP: " . $_SESSION['simulated_ip']);
            }

            if (function_exists('log_attack')) {
                log_attack($pdo, 'Successful SQLi (Simulated - OR 1=1)', $password, 'login_process.php', 200);
            }
            write_log("WARN: User '{$injected_user['username']}' logged in via simulated SQL Injection.");
            header('Location: list.php');
            exit;
        }
    }

    // 失敗
    write_log("INFO: Failed login attempt for username '{$username}'.");
    header('Location: login.php?error=' . urlencode('ユーザー名またはパスワードが間違っています。'));
    exit;
}

header('Location: login.php');
exit;