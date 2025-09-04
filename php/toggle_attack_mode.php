<?php
require_once __DIR__ . '/common_init.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: list.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attack_type'])) {
    $attack_type = $_POST['attack_type'];

    $all_attack_modes = [
        'dictionary_attack_enabled', 
        'bruteforce_enabled',
        'keylogger_enabled',
        'trusted_admin_bypass_enabled',
        'ransomware_enabled',
        'tamper_enabled',
        'reverse_bruteforce_enabled',
        'joe_account_attack_enabled',
        'cli_attack_mode_enabled',  // 正しい変数名
        'buffer_overflow_enabled',
        'stepping_stone_enabled',
        'apt_attack_enabled',
        'mail_attack_enabled',
        'killchain_attack_enabled',
        'ntp_tampering_enabled',
        'dns_attack_enabled',
        'csrf_enabled' 
    ];

    if ($attack_type === 'all_enable') {
        foreach ($all_attack_modes as $mode) {
            $_SESSION[$mode] = true;
        }
        // CLI用のトークンも自動発行
        $_SESSION['cli_attack_api_token'] = bin2hex(random_bytes(16));
        $_SESSION['force_hamburger'] = true;

    } elseif ($attack_type === 'all_disable') {
        foreach ($all_attack_modes as $mode) {
            unset($_SESSION[$mode]);
        }
        // CLIトークンも削除
        unset($_SESSION['cli_attack_api_token']);
        $_SESSION['force_hamburger'] = false;

    } else {
        $session_key = $attack_type . '_enabled';
        $is_enabled = $_SESSION[$session_key] ?? false;
        $_SESSION[$session_key] = !$is_enabled;
        
        // CLI特有の処理
        if ($attack_type === 'cli_attack_mode' && !$is_enabled) {
            $_SESSION['cli_attack_api_token'] = bin2hex(random_bytes(16));
        } elseif ($attack_type === 'cli_attack_mode' && $is_enabled) {
            unset($_SESSION['cli_attack_api_token']);
        }
    }
}

header('Location: simulation_tools.php');
exit;