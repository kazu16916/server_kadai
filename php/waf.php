<?php
// waf.php
// 簡易的な IDS/IPS（侵入検知・防御）
// 追加：IP アドレス単位の IPS ブロック（正確一致 / ワイルドカード / CIDR 対応）
// 追加：ランサムウェア関連シグネチャの検知
// 追加：メールインジェクション検知
// 追加：標的型攻撃（APT）検知
// 追加：メール攻撃演習検知
// 追加：サイバーキルチェーン演習検知

// グローバル変数で"検知のみ（block 以外）"だったイベントを終了時に記録するため保持
global $waf_detected_info;
$waf_detected_info = null;

/**
 * クライアントIPを取得（模擬IPがあれば最優先）
 */
function waf_client_ip(): string {
    return $_SESSION['simulated_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

/**
 * IPパターンとのマッチ判定
 * サポート：
 *  - 正確一致（例：203.0.113.5）
 *  - ワイルドカード（例：203.0.113.* / 203.0.*.*）
 *  - CIDR（例：203.0.113.0/24, 2001:db8::/32）
 */
function waf_ip_matches(string $ip, string $pattern): bool {
    $pattern = trim($pattern);
    if ($pattern === '') return false;

    // CIDR
    if (strpos($pattern, '/') !== false) {
        return waf_ip_in_cidr($ip, $pattern);
    }

    // ワイルドカード
    if (strpos($pattern, '*') !== false) {
        // IPv4のみ簡易対応（演習用途）
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }
        // パターンを正規表現化
        $re = '/^' . str_replace(['.','*'], ['\.','[0-9]{1,3}'], $pattern) . '$/';
        return (bool)preg_match($re, $ip);
    }

    // 正確一致
    return hash_equals($pattern, $ip);
}

/**
 * IPがCIDRに含まれるか（IPv4/IPv6対応）
 */
function waf_ip_in_cidr(string $ip, string $cidr): bool {
    [$subnet, $mask] = explode('/', $cidr, 2) + [null, null];
    if ($subnet === null || $mask === null) return false;

    // inet_pton でバイナリ化
    $ip_bin     = @inet_pton($ip);
    $subnet_bin = @inet_pton($subnet);
    if ($ip_bin === false || $subnet_bin === false) return false;

    $len = strlen($ip_bin); // 4 or 16
    $mask = (int)$mask;

    // IPv4/IPv6 の bit 長整合チェック
    if (($len === 4 && $mask > 32) || ($len === 16 && $mask > 128)) return false;
    if (strlen($subnet_bin) !== $len) return false;

    $bytes = intdiv($mask, 8);
    $remainder = $mask % 8;

    // 先頭の完全一致バイトを比較
    if ($bytes && substr($ip_bin, 0, $bytes) !== substr($subnet_bin, 0, $bytes)) {
        return false;
    }
    if ($remainder === 0) return true;

    // 次の1バイトをマスクして比較
    $maskByte = ~((1 << (8 - $remainder)) - 1) & 0xFF;
    return (ord($ip_bin[$bytes]) & $maskByte) === (ord($subnet_bin[$bytes]) & $maskByte);
}

/**
 * ランサムウェア演習が有効な場合にデフォルトシグネチャを動的に追加
 */
function add_ransomware_signatures_if_enabled($pdo): void {
    // ランサムウェア演習が無効の場合は何もしない
    if (empty($_SESSION['ransomware_enabled'])) {
        return;
    }

    $ransomware_signatures = [
        ['.locky', 'Ransomware: Locky encryption pattern detected'],
        ['encryption started', 'Ransomware: Encryption process detected'],
        ['send bitcoin', 'Ransomware: Cryptocurrency ransom demand'],
        ['unlock your files', 'Ransomware: File unlock demand'],
        ['RSA-2048', 'Ransomware: Strong encryption algorithm reference'],
        ['all files encrypted', 'Ransomware: Mass encryption claim'],
        ['spreading to network', 'Ransomware: Network propagation attempt'],
        ['scanning for *.doc', 'Ransomware: File type scanning pattern'],
        ['scanning for *.pdf', 'Ransomware: Document scanning pattern'],
        ['scanning for *.jpg', 'Ransomware: Image file scanning pattern'],
        ['via SMB', 'Ransomware: SMB-based network spread'],
        ['network shares', 'Ransomware: Network share targeting']
    ];

    try {
        // 既存のランサムウェア関連ルールをクリア（重複防止）
        $pdo->exec("DELETE FROM waf_blacklist WHERE is_custom = FALSE AND description LIKE '%Ransomware:%'");

        // 新しいシグネチャを追加
        $stmt = $pdo->prepare("
            INSERT INTO waf_blacklist (pattern, description, action, is_custom) 
            VALUES (?, ?, 'detect', FALSE)
        ");

        foreach ($ransomware_signatures as [$pattern, $description]) {
            $stmt->execute([$pattern, $description]);
        }

    } catch (Throwable $e) {
        error_log("Failed to add ransomware signatures: " . $e->getMessage());
    }
}

/**
 * メールインジェクション検知シグネチャを動的に追加
 */
function add_mail_injection_signatures_if_enabled($pdo): void {
    // メールインジェクション演習のデフォルトシグネチャ
    $mail_injection_signatures = [
        ['\ncc:', 'Mail Injection: CC header injection detected'],
        ['\nbcc:', 'Mail Injection: BCC header injection detected'],  
        ['\nto:', 'Mail Injection: TO header injection detected'],
        ['\nfrom:', 'Mail Injection: FROM header injection detected'],
        ['\nsubject:', 'Mail Injection: SUBJECT header injection detected'],
        ['content-type:', 'Mail Injection: Content-Type header injection detected'],
        ['mime-version:', 'Mail Injection: MIME-Version header injection detected'],
        ['\nx-priority:', 'Mail Injection: X-Priority header injection detected'],
        ['\nx-mailer:', 'Mail Injection: X-Mailer header injection detected'],
        ['\nreply-to:', 'Mail Injection: Reply-To header injection detected'],
        ['\nreturn-path:', 'Mail Injection: Return-Path header injection detected'],
        ['\nmessage-id:', 'Mail Injection: Message-ID header injection detected'],
        ['\ndate:', 'Mail Injection: Date header injection detected'],
        ['%0a', 'Mail Injection: URL encoded newline (LF) detected'],
        ['%0d', 'Mail Injection: URL encoded carriage return (CR) detected'],
        ['%0d%0a', 'Mail Injection: URL encoded CRLF sequence detected'],
        ['\r\n', 'Mail Injection: CRLF sequence detected'],
        ['\n\r', 'Mail Injection: LFCR sequence detected']
    ];

    try {
        // 既存のメールインジェクション関連ルールをクリア（重複防止）
        $pdo->exec("DELETE FROM waf_blacklist WHERE is_custom = FALSE AND description LIKE '%Mail Injection:%'");

        // 新しいシグネチャを追加
        $stmt = $pdo->prepare("
            INSERT INTO waf_blacklist (pattern, description, action, is_custom) 
            VALUES (?, ?, 'detect', FALSE)
        ");

        foreach ($mail_injection_signatures as [$pattern, $description]) {
            $stmt->execute([$pattern, $description]);
        }

    } catch (Throwable $e) {
        error_log("Failed to add mail injection signatures: " . $e->getMessage());
    }
}

/**
 * 標的型攻撃演習が有効な場合にデフォルトシグネチャを動的に追加
 */
function add_apt_attack_signatures_if_enabled($pdo): void {
    // 標的型攻撃演習が無効の場合は何もしない
    if (empty($_SESSION['apt_attack_enabled'])) {
        return;
    }

    $apt_signatures = [
        // 偵察フェーズ
        ['nmap -sS', 'APT: Network scanning detected'],
        ['whois', 'APT: Domain reconnaissance detected'],
        ['theHarvester', 'APT: Email harvesting tool detected'],
        ['maltego', 'APT: OSINT gathering tool detected'],
        ['shodan search', 'APT: Internet-wide scanning detected'],
        
        // 初期侵入フェーズ
        ['msfvenom', 'APT: Payload generation detected'],
        ['meterpreter', 'APT: Meterpreter framework detected'],
        ['reverse_https', 'APT: Reverse HTTPS connection detected'],
        ['sendEmail', 'APT: Spear phishing email detected'],
        
        // 実行・永続化フェーズ
        ['persistence -A', 'APT: Persistence mechanism detected'],
        ['CurrentVersion\\Run', 'APT: Registry persistence detected'],
        ['SecurityUpdate', 'APT: Disguised persistence service detected'],
        
        // 権限昇格フェーズ
        ['bypassuac', 'APT: UAC bypass attempt detected'],
        ['getsystem', 'APT: System privilege escalation detected'],
        ['hashdump', 'APT: Password hash dumping detected'],
        ['local_exploit_suggester', 'APT: Local exploit enumeration detected'],
        
        // 防御回避フェーズ
        ['migrate -N explorer.exe', 'APT: Process migration detected'],
        ['clearev', 'APT: Event log clearing detected'],
        ['timestomp', 'APT: File timestamp manipulation detected'],
        ['process hollowing', 'APT: Process hollowing technique detected'],
        
        // 認証情報取得フェーズ
        ['mimikatz', 'APT: Credential dumping tool detected'],
        ['sekurlsa::logonpasswords', 'APT: Memory credential extraction detected'],
        ['wdigest', 'APT: WDigest credential extraction detected'],
        ['kerberos', 'APT: Kerberos ticket extraction detected'],
        
        // 発見・横展開フェーズ
        ['enum_domain', 'APT: Domain enumeration detected'],
        ['portfwd add', 'APT: Port forwarding setup detected'],
        ['proxychains', 'APT: Proxy chain usage detected'],
        ['psexec.py', 'APT: Lateral movement via PsExec detected'],
        
        // 収集・外部送信フェーズ
        ['search -f *.docx', 'APT: Document file search detected'],
        ['search -f *.xlsx', 'APT: Spreadsheet file search detected'],
        ['7z a -p', 'APT: Data compression with password detected'],
        ['curl -X POST -F "file=@', 'APT: Data exfiltration detected'],
        
        // MITRE ATT&CK技術
        ['T1595.002', 'APT: Active scanning technique detected'],
        ['T1590.001', 'APT: Domain properties gathering detected'],
        ['T1566.001', 'APT: Spear phishing attachment detected'],
        ['T1204.002', 'APT: Malicious file execution detected'],
        ['T1547.001', 'APT: Registry run keys persistence detected'],
        ['T1053.005', 'APT: Scheduled task persistence detected'],
        ['T1548.002', 'APT: UAC bypass technique detected'],
        ['T1134.001', 'APT: Token impersonation detected'],
        ['T1055', 'APT: Process injection detected'],
        ['T1070.001', 'APT: Event log clearing detected'],
        ['T1070.006', 'APT: Timestamp manipulation detected'],
        ['T1003.001', 'APT: LSASS memory dumping detected'],
        ['T1558.003', 'APT: Kerberoasting detected'],
        ['T1021.002', 'APT: SMB/Windows Admin Shares detected'],
        ['T1018', 'APT: Remote system discovery detected'],
        ['T1005', 'APT: Data from local system detected'],
        ['T1041', 'APT: Exfiltration over C2 channel detected'],
        
        // 追加の標的型攻撃パターン
        ['Empire', 'APT: PowerShell Empire framework detected'],
        ['Cobalt Strike', 'APT: Cobalt Strike framework detected'],
        ['powershell.exe -ExecutionPolicy Bypass', 'APT: PowerShell execution policy bypass detected'],
        ['certutil -decode', 'APT: Base64 decoding with certutil detected'],
        ['bitsadmin /transfer', 'APT: File download via bitsadmin detected'],
        ['wmic process call create', 'APT: Remote process execution via WMI detected'],
        ['schtasks /create', 'APT: Scheduled task creation detected'],
        ['net user /add', 'APT: User account creation detected'],
        ['net localgroup administrators', 'APT: Administrator group manipulation detected'],
        ['reg add HKLM', 'APT: Registry modification detected'],
        ['vssadmin delete shadows', 'APT: Shadow copy deletion detected'],
        ['wbadmin delete catalog', 'APT: Backup catalog deletion detected'],
        ['bcdedit /set', 'APT: Boot configuration modification detected'],
        ['netsh firewall set', 'APT: Firewall configuration change detected'],
        ['sc create', 'APT: Service creation detected'],
        ['rundll32.exe', 'APT: DLL execution via rundll32 detected'],
        ['regsvr32.exe /s /n /u /i:', 'APT: Squiblydoo technique detected'],
        ['mshta.exe', 'APT: HTML application execution detected'],
        ['cscript.exe', 'APT: Script execution via cscript detected'],
        ['wscript.exe', 'APT: Script execution via wscript detected']
    ];

    try {
        // 既存の標的型攻撃関連ルールをクリア（重複防止）
        $pdo->exec("DELETE FROM waf_blacklist WHERE is_custom = FALSE AND description LIKE '%APT:%'");

        // 新しいシグネチャを追加
        $stmt = $pdo->prepare("
            INSERT INTO waf_blacklist (pattern, description, action, is_custom) 
            VALUES (?, ?, 'detect', FALSE)
        ");

        foreach ($apt_signatures as [$pattern, $description]) {
            $stmt->execute([$pattern, $description]);
        }

    } catch (Throwable $e) {
        error_log("Failed to add APT attack signatures: " . $e->getMessage());
    }
}

/**
 * メール攻撃演習が有効な場合にデフォルトシグネチャを動的に追加
 */
function add_mail_attack_signatures_if_enabled($pdo): void {
    // メール攻撃演習が無効の場合は何もしない
    if (empty($_SESSION['mail_attack_enabled'])) {
        return;
    }

    $mail_attack_signatures = [
        // フィッシング関連
        ['account-verification.', 'Mail Attack: Phishing domain pattern detected'],
        ['urgent-security.', 'Mail Attack: Urgent security phishing detected'],
        ['bank-notice.com', 'Mail Attack: Banking phishing domain detected'],
        ['social-platform.com', 'Mail Attack: Social media phishing detected'],
        ['shipping-company.com', 'Mail Attack: Delivery phishing domain detected'],
        ['security@service-center', 'Mail Attack: Fake security sender detected'],
        ['delivery@shipping', 'Mail Attack: Fake delivery sender detected'],
        ['重要：アカウント確認', 'Mail Attack: Japanese phishing subject detected'],
        ['緊急】銀行口座', 'Mail Attack: Banking urgency phishing detected'],
        ['新しいログインがありました', 'Mail Attack: Login notification phishing detected'],
        ['再配達のお知らせ', 'Mail Attack: Redelivery phishing detected'],
        ['24時間以内に確認', 'Mail Attack: Time pressure phishing detected'],
        ['アカウントが一時停止', 'Mail Attack: Account suspension threat detected'],
        ['不正なアクセス', 'Mail Attack: Security breach claim detected'],
        ['直ちに確認', 'Mail Attack: Immediate action request detected'],
        
        // メールインジェクション関連（強化版）
        ['%0acc:', 'Mail Attack: URL encoded CC injection detected'],
        ['%0abcc:', 'Mail Attack: URL encoded BCC injection detected'],
        ['%0asubject:', 'Mail Attack: URL encoded subject injection detected'],
        ['%0afrom:', 'Mail Attack: URL encoded from injection detected'],
        ['%0d%0aContent-Type:', 'Mail Attack: Content-Type header injection detected'],
        ['\nContent-Transfer-Encoding:', 'Mail Attack: Encoding header injection detected'],
        ['boundary=', 'Mail Attack: MIME boundary injection detected'],
        
        // SPAMリレー関連
        ['RCPT TO:', 'Mail Attack: SMTP RCPT command detected'],
        ['MAIL FROM:', 'Mail Attack: SMTP MAIL FROM command detected'],
        ['DATA\r\n', 'Mail Attack: SMTP DATA command detected'],
        ['HELO spam', 'Mail Attack: SPAM HELO command detected'],
        ['EHLO spam', 'Mail Attack: SPAM EHLO command detected'],
        ['X-Spam-Level:', 'Mail Attack: Spam level header detected'],
        ['X-Spam-Status:', 'Mail Attack: Spam status header detected'],
        ['bulk-email', 'Mail Attack: Bulk email pattern detected'],
        ['mass-mailer', 'Mail Attack: Mass mailer pattern detected'],
        
        // メールボム/DoS関連
        ['for i in range(1000)', 'Mail Attack: Mail bombing script detected'],
        ['send_bulk_email', 'Mail Attack: Bulk email function detected'],
        ['mail_flood', 'Mail Attack: Mail flooding attempt detected'],
        
        // 標的型メール攻撃関連
        ['spear-phishing', 'Mail Attack: Spear phishing technique detected'],
        ['social-engineering', 'Mail Attack: Social engineering attempt detected'],
        ['credential-harvesting', 'Mail Attack: Credential harvesting detected'],
        ['business-email-compromise', 'Mail Attack: BEC attempt detected'],
        ['CEO-fraud', 'Mail Attack: CEO fraud attempt detected'],
        ['invoice-fraud', 'Mail Attack: Invoice fraud detected'],
        
        // メールワーム関連
        ['self-replicating', 'Mail Attack: Self-replicating malware detected'],
        ['address-book-access', 'Mail Attack: Address book access attempt detected'],
        ['auto-forward', 'Mail Attack: Auto-forwarding rule detected'],
        
        // メールサーバー攻撃関連
        ['smtp-auth-bypass', 'Mail Attack: SMTP authentication bypass detected'],
        ['mail-relay-abuse', 'Mail Attack: Mail relay abuse detected'],
        ['imap-injection', 'Mail Attack: IMAP injection detected'],
        ['pop3-overflow', 'Mail Attack: POP3 buffer overflow detected'],
        
        // メール爆弾・大量送信関連
        ['X-Priority: 1', 'Mail Attack: High priority spam detected'],
        ['Precedence: bulk', 'Mail Attack: Bulk mail precedence detected'],
        ['List-Unsubscribe:', 'Mail Attack: Mass mailing list detected'],
        
        // 悪意のある添付ファイル関連
        ['.exe', 'Mail Attack: Executable attachment detected'],
        ['.scr', 'Mail Attack: Screen saver executable detected'],
        ['.bat', 'Mail Attack: Batch file attachment detected'],
        ['.com', 'Mail Attack: Command file attachment detected'],
        ['.pif', 'Mail Attack: Program information file detected'],
        ['.vbs', 'Mail Attack: VBScript attachment detected'],
        ['.js', 'Mail Attack: JavaScript attachment detected'],
        
        // フィッシングURL関連
        ['bit.ly/', 'Mail Attack: Shortened URL detected'],
        ['tinyurl.com/', 'Mail Attack: URL shortener detected'],
        ['goo.gl/', 'Mail Attack: Google URL shortener detected'],
        ['t.co/', 'Mail Attack: Twitter URL shortener detected'],
        
        // 偽装ドメイン関連
        ['payp4l.com', 'Mail Attack: PayPal typosquatting detected'],
        ['g00gle.com', 'Mail Attack: Google typosquatting detected'],
        ['micr0soft.com', 'Mail Attack: Microsoft typosquatting detected'],
        ['bank0famerica.com', 'Mail Attack: Bank typosquatting detected'],
        
        // メールヘッダ偽装関連
        ['X-Mailer: The Bat!', 'Mail Attack: Suspicious mailer detected'],
        ['X-Originating-IP:', 'Mail Attack: IP origin spoofing detected'],
        ['Received: from unknown', 'Mail Attack: Unknown sender route detected']
    ];

    try {
        // 既存のメール攻撃関連ルールをクリア（重複防止）
        $pdo->exec("DELETE FROM waf_blacklist WHERE is_custom = FALSE AND description LIKE '%Mail Attack:%'");

        // 新しいシグネチャを追加
        $stmt = $pdo->prepare("
            INSERT INTO waf_blacklist (pattern, description, action, is_custom) 
            VALUES (?, ?, 'detect', FALSE)
        ");

        foreach ($mail_attack_signatures as [$pattern, $description]) {
            $stmt->execute([$pattern, $description]);
        }

    } catch (Throwable $e) {
        error_log("Failed to add mail attack signatures: " . $e->getMessage());
    }
}

/**
 * サイバーキルチェーン演習が有効な場合にデフォルトシグネチャを動的に追加
 */
function add_killchain_attack_signatures_if_enabled($pdo): void {
    // サイバーキルチェーン演習が無効の場合は何もしない
    if (empty($_SESSION['killchain_attack_enabled'])) {
        return;
    }

    $killchain_signatures = [
        // 段階1: 偵察 (Reconnaissance)
        ['passive_osint', 'Kill Chain: Passive OSINT reconnaissance detected'],
        ['active_scanning', 'Kill Chain: Active network scanning detected'],
        ['social_engineering', 'Kill Chain: Social engineering reconnaissance detected'],
        ['insider_info', 'Kill Chain: Insider information gathering detected'],
        ['domain reconnaissance', 'Kill Chain: Domain reconnaissance activity detected'],
        ['email harvesting', 'Kill Chain: Email harvesting detected'],
        ['subdomain enumeration', 'Kill Chain: Subdomain enumeration detected'],
        ['port scanning', 'Kill Chain: Port scanning activity detected'],
        ['OS fingerprinting', 'Kill Chain: Operating system fingerprinting detected'],
        ['vulnerability scanning', 'Kill Chain: Vulnerability scanning detected'],
        
        // 段階2: 武器化 (Weaponization)
        ['malware_creation', 'Kill Chain: Custom malware creation detected'],
        ['exploit_kit', 'Kill Chain: Exploit kit usage detected'],
        ['document_weaponization', 'Kill Chain: Document weaponization detected'],
        ['supply_chain', 'Kill Chain: Supply chain compromise attempt detected'],
        ['payload generation', 'Kill Chain: Malicious payload generation detected'],
        ['polymorphic code', 'Kill Chain: Polymorphic code generation detected'],
        ['anti-debugging', 'Kill Chain: Anti-debugging technique detected'],
        ['evasion technique', 'Kill Chain: Evasion technique implementation detected'],
        ['RAT compilation', 'Kill Chain: Remote Access Trojan compilation detected'],
        ['backdoor creation', 'Kill Chain: Backdoor creation detected'],
        
        // 段階3: 配送 (Delivery)
        ['spear_phishing', 'Kill Chain: Spear phishing delivery detected'],
        ['watering_hole', 'Kill Chain: Watering hole attack detected'],
        ['usb_drop', 'Kill Chain: USB drop attack detected'],
        ['compromised_website', 'Kill Chain: Compromised website delivery detected'],
        ['email attachment', 'Kill Chain: Malicious email attachment detected'],
        ['drive-by download', 'Kill Chain: Drive-by download detected'],
        ['social media delivery', 'Kill Chain: Social media delivery method detected'],
        ['instant messaging', 'Kill Chain: Instant messaging delivery detected'],
        ['file sharing', 'Kill Chain: File sharing delivery detected'],
        ['removable media', 'Kill Chain: Removable media delivery detected'],
        
        // 段階4: 悪用 (Exploitation)
        ['buffer_overflow', 'Kill Chain: Buffer overflow exploitation detected'],
        ['zero_day', 'Kill Chain: Zero-day vulnerability exploitation detected'],
        ['known_vulnerability', 'Kill Chain: Known vulnerability exploitation detected'],
        ['social_exploitation', 'Kill Chain: Social exploitation detected'],
        ['code injection', 'Kill Chain: Code injection attack detected'],
        ['privilege escalation', 'Kill Chain: Privilege escalation attempt detected'],
        ['memory corruption', 'Kill Chain: Memory corruption exploit detected'],
        ['format string', 'Kill Chain: Format string vulnerability exploitation detected'],
        ['SQL injection', 'Kill Chain: SQL injection exploitation detected'],
        ['XSS exploitation', 'Kill Chain: Cross-site scripting exploitation detected'],
        
        // 段階5: 設置 (Installation)
        ['backdoor_installation', 'Kill Chain: Backdoor installation detected'],
        ['rootkit_deployment', 'Kill Chain: Rootkit deployment detected'],
        ['service_installation', 'Kill Chain: Malicious service installation detected'],
        ['registry_persistence', 'Kill Chain: Registry persistence mechanism detected'],
        ['autostart modification', 'Kill Chain: Autostart entry modification detected'],
        ['scheduled task', 'Kill Chain: Malicious scheduled task creation detected'],
        ['DLL hijacking', 'Kill Chain: DLL hijacking detected'],
        ['process hollowing', 'Kill Chain: Process hollowing installation detected'],
        ['bootkit installation', 'Kill Chain: Bootkit installation detected'],
        ['firmware modification', 'Kill Chain: Firmware modification detected'],
        
        // 段階6: 指令制御 (Command & Control)
        ['https_beacon', 'Kill Chain: HTTPS C2 beacon detected'],
        ['dns_tunneling', 'Kill Chain: DNS tunneling C2 detected'],
        ['social_media', 'Kill Chain: Social media C2 channel detected'],
        ['p2p_network', 'Kill Chain: P2P network C2 detected'],
        ['IRC channel', 'Kill Chain: IRC C2 channel detected'],
        ['HTTP POST', 'Kill Chain: HTTP POST C2 communication detected'],
        ['encrypted channel', 'Kill Chain: Encrypted C2 channel detected'],
        ['steganography', 'Kill Chain: Steganographic C2 detected'],
        ['cloud service', 'Kill Chain: Cloud service C2 detected'],
        ['domain fronting', 'Kill Chain: Domain fronting C2 detected'],
        
        // 段階7: 目的達成 (Actions on Objectives)
        ['data_exfiltration', 'Kill Chain: Data exfiltration detected'],
        ['system_destruction', 'Kill Chain: System destruction attempt detected'],
        ['ransomware_deployment', 'Kill Chain: Ransomware deployment detected'],
        ['espionage', 'Kill Chain: Long-term espionage activity detected'],
        ['intellectual property', 'Kill Chain: Intellectual property theft detected'],
        ['financial fraud', 'Kill Chain: Financial fraud activity detected'],
        ['sabotage operation', 'Kill Chain: Sabotage operation detected'],
        ['data manipulation', 'Kill Chain: Data manipulation detected'],
        ['credential harvesting', 'Kill Chain: Credential harvesting detected'],
        ['lateral movement', 'Kill Chain: Lateral movement detected'],
        
        // キルチェーン横断的パターン
        ['multi-stage payload', 'Kill Chain: Multi-stage payload detected'],
        ['attack framework', 'Kill Chain: Attack framework usage detected'],
        ['TTPs combination', 'Kill Chain: Multiple TTPs combination detected'],
        ['kill chain progression', 'Kill Chain: Sequential attack progression detected'],
        ['persistent threat', 'Kill Chain: Persistent threat behavior detected'],
        ['advanced technique', 'Kill Chain: Advanced attack technique detected'],
        ['coordinated attack', 'Kill Chain: Coordinated attack detected'],
        ['multi-vector attack', 'Kill Chain: Multi-vector attack detected'],
        ['sophisticated campaign', 'Kill Chain: Sophisticated campaign detected'],
        ['targeted operation', 'Kill Chain: Targeted operation detected']
    ];

    try {
        // 既存のサイバーキルチェーン関連ルールをクリア（重複防止）
        $pdo->exec("DELETE FROM waf_blacklist WHERE is_custom = FALSE AND description LIKE '%Kill Chain:%'");

        // 新しいシグネチャを追加
        $stmt = $pdo->prepare("
            INSERT INTO waf_blacklist (pattern, description, action, is_custom) 
            VALUES (?, ?, 'detect', FALSE)
        ");

        foreach ($killchain_signatures as [$pattern, $description]) {
            $stmt->execute([$pattern, $description]);
        }

    } catch (Throwable $e) {
        error_log("Failed to add kill chain attack signatures: " . $e->getMessage());
    }
}

/**
 * メイン：WAF/IPS 実行
 */
function run_waf($pdo) {
    global $waf_detected_info;

    // ランサムウェア演習が有効な場合、動的にシグネチャを追加
    add_ransomware_signatures_if_enabled($pdo);
    
    // メールインジェクション検知シグネチャを追加（常時有効）
    add_mail_injection_signatures_if_enabled($pdo);
    
    // 標的型攻撃演習が有効な場合、動的にシグネチャを追加
    add_apt_attack_signatures_if_enabled($pdo);
    
    // メール攻撃演習が有効な場合、動的にシグネチャを追加
    add_mail_attack_signatures_if_enabled($pdo);
    
    // サイバーキルチェーン演習が有効な場合、動的にシグネチャを追加
    add_killchain_attack_signatures_if_enabled($pdo);

    // ===== 1) まずは IP ブロックリスト（IPS）を評価 =====
    try {
        // テーブルが無くても動くように例外を握りつぶす
        $stmt = $pdo->query("SELECT ip_pattern, action, description FROM waf_ip_blocklist");
        $ip_rules = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        $ip_rules = [];
    }

    if (!empty($ip_rules)) {
        $client_ip = waf_client_ip();

        foreach ($ip_rules as $r) {
            $pattern = (string)($r['ip_pattern'] ?? '');
            $action  = (string)($r['action'] ?? 'block');       // 'block' or 'monitor'
            $desc    = (string)($r['description'] ?? 'IPS: IP Rule Matched');

            if ($pattern !== '' && waf_ip_matches($client_ip, $pattern)) {
                if ($action === 'block') {
                    // 即時ブロック＆ログ
                    log_attack($pdo, $desc, 'IPS: IP Blocked', $pattern, 403);
                    http_response_code(403);
                    echo "<!DOCTYPE html><html><head><title>Forbidden</title></head><body><h1>403 Forbidden</h1><p>このIPアドレスからのアクセスはブロックされています。</p></body></html>";
                    die();
                } else {
                    // monitor：通すが、検知情報として終了時に記録
                    $waf_detected_info = [
                        'pdo'              => $pdo,
                        'attack_type'      => $desc,
                        'malicious_input'  => 'IPS: IP Matched (monitor)',
                        'detected_pattern' => $pattern
                    ];
                    // 以降のコンテンツ検査も実行（早期returnしない）
                }
            }
        }
    }

    // ===== 2) コンテンツ検査（WAF ブラックリスト） =====
    try {
        $stmt = $pdo->query("SELECT pattern, action, description FROM waf_blacklist");
        $rules = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (PDOException $e) {
        error_log("WAF Error: Could not fetch rules. " . $e->getMessage());
        return;
    }

    if (empty($rules)) return;

    // チェック対象を集約
    $strings_to_check = [];
    $strings_to_check[] = $_SERVER['REQUEST_URI'] ?? '';

    foreach ([ $_GET, $_POST, $_COOKIE ] as $global_array) {
        array_walk_recursive($global_array, function($value) use (&$strings_to_check) {
            if (is_string($value)) $strings_to_check[] = $value;
        });
    }

    // JSON ボディ
    if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        $json_input = file_get_contents('php://input');
        $decoded_data = json_decode($json_input, true);
        if (is_array($decoded_data)) {
            array_walk_recursive($decoded_data, function($value) use (&$strings_to_check) {
                if (is_string($value)) $strings_to_check[] = $value;
            });
        }
    }

    // アップロードファイル
    if (!empty($_FILES)) {
        foreach ($_FILES as $file) {
            if (isset($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) {
                $content = @file_get_contents($file['tmp_name']);
                if (is_string($content)) $strings_to_check[] = $content;
            }
        }
    }

    // ★ detect は「保存だけして継続」、block はその場で 403
    foreach ($strings_to_check as $value) {
        $raw     = (string)$value;
        $decoded = urldecode($raw);

        foreach ($rules as $rule) {
            $pattern     = (string)($rule['pattern'] ?? '');
            $action      = (string)($rule['action'] ?? 'detect');   // 既定 detect（DB側も DEFAULT 'detect'）
            $description = (string)($rule['description'] ?? 'WAF Rule Matched');

            if ($pattern === '') continue;

            // URLデコード前後の両方で部分一致を判定
            $hit = (stripos($decoded, $pattern) !== false) || (stripos($raw, $pattern) !== false);
            if (!$hit) continue;

            if ($action === 'block') {
                // 即時ブロック
                log_attack($pdo, $description, $raw, $pattern, 403);
                http_response_code(403);
                echo "<!DOCTYPE html><html><head><title>Forbidden</title></head><body><h1>403 Forbidden</h1><p>不正な入力が検知されたため、リクエストはブロックされました。</p></body></html>";
                die();
            } else {
                // 検知のみ：終了時にまとめて記録（最初の1件だけ保持）
                if ($waf_detected_info === null) {
                    $waf_detected_info = [
                        'pdo'              => $pdo,
                        'attack_type'      => $description,
                        'malicious_input'  => $raw,
                        'detected_pattern' => $pattern
                    ];
                }
                // ★ ここで return しない！ 続けて block ルールがないか評価する
            }
        }
    }
}

/**
 * スクリプト終了時に実行される最終ログ記録関数
 * （block 以外の"検知のみ"を1件まとめて書く）
 */
function final_log_handler() {
    global $waf_detected_info;

    if ($waf_detected_info === null) return;

    $status_code = http_response_code();
    log_attack(
        $waf_detected_info['pdo'],
        $waf_detected_info['attack_type'],
        $waf_detected_info['malicious_input'],
        $waf_detected_info['detected_pattern'],
        $status_code
    );
}
register_shutdown_function('final_log_handler');

/**
 * IDS/IPS ログ記録
 */
function log_attack($pdo, $attack_type, $malicious_input, $detected_pattern, $status_code) {
    // シミュレーション中のIP/UserAgentがあれば優先
    $ip_address = $_SESSION['simulated_ip']         ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $user_agent = $_SESSION['simulated_user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A');
    $source_type = $_SESSION['simulated_type']      ?? 'Direct';

    // ★ NTP改ざん攻撃演習による時刻操作
    $timestamp = time(); // デフォルトは現在時刻
    
    // NTP改ざん攻撃演習が有効で、時刻オフセットが設定されている場合
    if (!empty($_SESSION['ntp_tampering_enabled']) && 
        $_SESSION['ntp_attack_status'] === 'compromised' && 
        isset($_SESSION['ntp_time_offset'])) {
        
        $time_offset = (int)$_SESSION['ntp_time_offset'];
        $timestamp += $time_offset;
        
        // 改ざんされた時刻であることを attack_type に記録（デバッグ用）
        if (strpos($attack_type, 'NTP Tampering') === false) {
            $attack_type .= ' [Time Compromised]';
        }
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO attack_logs (ip_address, user_id, attack_type, malicious_input, request_uri, user_agent, status_code, source_type, detected_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?))"
        );
        $log_message = "Input: " . (string)$malicious_input . " | Pattern: " . (string)$detected_pattern;
        $stmt->execute([
            $ip_address,
            $_SESSION['user_id'] ?? null,
            (string)$attack_type,
            $log_message,
            $_SERVER['REQUEST_URI'] ?? '',
            (string)$user_agent,
            (int)$status_code,
            (string)$source_type,
            $timestamp  // ★ 改ざんされた時刻またはnoamal時刻
        ]);
    } catch (PDOException $e) {
        error_log("Failed to log attack: " . $e->getMessage());
    }
}

function add_dns_attack_signatures_if_enabled($pdo): void {
    // DNS攻撃演習が無効の場合は何もしない
    if (empty($_SESSION['dns_attack_enabled'])) {
        return;
    }

    $dns_attack_signatures = [
        // DNS攻撃関連の基本シグネチャ
        ['dns poisoning', 'DNS Attack: Cache poisoning detected'],
        ['dns spoofing', 'DNS Attack: DNS response spoofing detected'],
        ['dns hijacking', 'DNS Attack: Domain hijacking attempt detected'],
        ['dns pharming', 'DNS Attack: Pharming attack detected'],
        ['dns tunneling', 'DNS Attack: DNS tunneling detected'],
        
        // DNS攻撃ツール・コマンド
        ['nslookup', 'DNS Attack: DNS reconnaissance tool usage'],
        ['dig @', 'DNS Attack: DNS query tool detected'],
        ['dnsrecon', 'DNS Attack: DNS reconnaissance tool detected'],
        ['dnsmap', 'DNS Attack: DNS mapping tool detected'],
        ['fierce.pl', 'DNS Attack: Domain scanner detected'],
        ['dnsenum', 'DNS Attack: DNS enumeration tool detected'],
        
        // DNS プロトコル攻撃
        ['dns amplification', 'DNS Attack: DNS amplification attack'],
        ['dns reflection', 'DNS Attack: DNS reflection attack'],
        ['dns flood', 'DNS Attack: DNS flood attack detected'],
        ['recursive dns', 'DNS Attack: Recursive DNS abuse detected'],
        
        // 偽装・フィッシング関連
        ['fake dns', 'DNS Attack: Fake DNS server detected'],
        ['rogue dns', 'DNS Attack: Rogue DNS server detected'],
        ['dns redirect', 'DNS Attack: Malicious DNS redirection'],
        ['phishing dns', 'DNS Attack: DNS-based phishing detected'],
        
        // DNS over HTTPSの悪用
        ['doh malware', 'DNS Attack: DNS over HTTPS malware communication'],
        ['dns over https', 'DNS Attack: Suspicious DoH usage detected'],
        ['cloudflare-dns.com', 'DNS Attack: Potential DoH abuse via Cloudflare'],
        
        // DNS設定の改ざん
        ['hosts file', 'DNS Attack: Hosts file modification detected'],
        ['resolv.conf', 'DNS Attack: DNS resolver configuration tampering'],
        ['dns settings', 'DNS Attack: DNS configuration modification'],
        
        // DNS ベースのマルウェア通信
        ['dns beacon', 'DNS Attack: DNS beaconing detected'],
        ['dns exfiltration', 'DNS Attack: Data exfiltration via DNS'],
        ['dns c2', 'DNS Attack: DNS-based C2 communication'],
        ['dns covert channel', 'DNS Attack: DNS covert channel detected'],
        
        // 特定のDNS攻撃パターン
        ['kaminsky attack', 'DNS Attack: Kaminsky DNS attack pattern'],
        ['dns rebinding', 'DNS Attack: DNS rebinding attack detected'],
        ['subdomain takeover', 'DNS Attack: Subdomain takeover attempt'],
        ['domain shadowing', 'DNS Attack: Domain shadowing detected'],
        ['fast flux', 'DNS Attack: Fast flux network detected'],
        ['domain generation algorithm', 'DNS Attack: DGA domain detected'],
        ['dga', 'DNS Attack: Domain Generation Algorithm usage'],
        
        // DNS サーバへの直接攻撃
        ['bind exploit', 'DNS Attack: BIND DNS server exploit'],
        ['dns server overflow', 'DNS Attack: DNS server buffer overflow'],
        ['dns zone transfer', 'DNS Attack: Unauthorized zone transfer'],
        ['axfr', 'DNS Attack: Zone transfer attempt (AXFR)'],
        ['ixfr', 'DNS Attack: Incremental zone transfer (IXFR)'],
        
        // 新しいDNS攻撃手法
        ['dns water torture', 'DNS Attack: DNS water torture attack'],
        ['nxdomain attack', 'DNS Attack: NXDOMAIN flooding attack'],
        ['dns random subdomain', 'DNS Attack: Random subdomain attack'],
        ['dns wildcard abuse', 'DNS Attack: DNS wildcard abuse detected'],
        
        // IPv6 DNS攻撃
        ['ipv6 dns', 'DNS Attack: IPv6 DNS manipulation'],
        ['aaaa record poison', 'DNS Attack: IPv6 AAAA record poisoning'],
        
        // DNS セキュリティ機能の迂回
        ['dnssec bypass', 'DNS Attack: DNSSEC bypass attempt'],
        ['dns filtering bypass', 'DNS Attack: DNS filtering bypass detected'],
        
        // モバイル DNS攻撃
        ['mobile dns hijack', 'DNS Attack: Mobile DNS hijacking'],
        ['wifi dns poison', 'DNS Attack: WiFi DNS poisoning detected'],
        
        // IoT DNS攻撃
        ['iot dns attack', 'DNS Attack: IoT device DNS attack'],
        ['router dns change', 'DNS Attack: Router DNS settings modification'],
        
        // DNS-based 情報収集
        ['dns fingerprinting', 'DNS Attack: DNS server fingerprinting'],
        ['dns information gathering', 'DNS Attack: DNS information gathering'],
        ['dns reconnaissance', 'DNS Attack: DNS reconnaissance activity']
    ];

    try {
        // 既存のDNS攻撃関連ルールをクリア（重複防止）
        $pdo->exec("DELETE FROM waf_blacklist WHERE is_custom = FALSE AND description LIKE '%DNS Attack:%'");

        // 新しいシグネチャを追加
        $stmt = $pdo->prepare("
            INSERT INTO waf_blacklist (pattern, description, action, is_custom) 
            VALUES (?, ?, 'detect', FALSE)
        ");

        foreach ($dns_attack_signatures as [$pattern, $description]) {
            $stmt->execute([$pattern, $description]);
        }

    } catch (Throwable $e) {
        error_log("Failed to add DNS attack signatures: " . $e->getMessage());
    }
}

function add_csrf_attack_signatures_if_enabled($pdo): void {
    // CSRF攻撃演習が無効の場合は何もしない
    if (empty($_SESSION['csrf_enabled'])) {
        return;
    }

    $csrf_signatures = [
        // 基本的なCSRF攻撃パターン
        ['action=change_password', 'CSRF Attack: Password change attempt detected'],
        ['action=delete_account', 'CSRF Attack: Account deletion attempt detected'],
        ['action=transfer_funds', 'CSRF Attack: Funds transfer attempt detected'],
        ['action=change_email', 'CSRF Attack: Email change attempt detected'],
        ['action=change_settings', 'CSRF Attack: Settings change attempt detected'],
        ['action=add_user', 'CSRF Attack: User addition attempt detected'],
        ['action=delete_user', 'CSRF Attack: User deletion attempt detected'],
        ['action=modify_permissions', 'CSRF Attack: Permission modification detected'],
        
        // よくあるCSRF攻撃フォームフィールド
        ['new_password=', 'CSRF Attack: Password field detected'],
        ['confirm_password=', 'CSRF Attack: Password confirmation field detected'],
        ['transfer_to=', 'CSRF Attack: Transfer destination field detected'],
        ['amount=', 'CSRF Attack: Amount field detected'],
        ['recipient=', 'CSRF Attack: Recipient field detected'],
        ['delete_confirm=', 'CSRF Attack: Delete confirmation detected'],
        
        // 悪意のあるパラメータ値
        ['hacked123', 'CSRF Attack: Suspicious password value detected'],
        ['attacker@', 'CSRF Attack: Suspicious email pattern detected'],
        ['evil.com', 'CSRF Attack: Suspicious domain detected'],
        ['pwned', 'CSRF Attack: Malicious content detected'],
        ['backdoor', 'CSRF Attack: Backdoor attempt detected'],
        
        // CSRFトークンの欠如を示すパターン
        ['action=', 'CSRF Attack: Action without token detected'],
        
        // 自動実行を示すスクリプトパターン
        ['submit()', 'CSRF Attack: Auto-submit script detected'],
        ['form.submit', 'CSRF Attack: Form auto-submission detected'],
        ['setTimeout', 'CSRF Attack: Delayed execution script detected'],
        
        // 偽装サイトのパターン
        ['gift', 'CSRF Attack: Social engineering gift scam detected'],
        ['winner', 'CSRF Attack: Social engineering winner scam detected'],
        ['congratulations', 'CSRF Attack: Social engineering congratulations scam detected'],
        ['prize', 'CSRF Attack: Social engineering prize scam detected'],
        ['free', 'CSRF Attack: Social engineering free offer detected'],
        
        // リファラーヘッダの異常
        ['evil-site', 'CSRF Attack: Malicious referer detected'],
        ['attacker', 'CSRF Attack: Attacker domain in referer detected'],
        
        // HTMLインジェクションパターン（CSRF攻撃フォーム）
        ['<form', 'CSRF Attack: HTML form injection detected'],
        ['method="post"', 'CSRF Attack: POST form injection detected'],
        ['hidden', 'CSRF Attack: Hidden field injection detected'],
        ['autosubmit', 'CSRF Attack: Auto-submit form detected'],
        
        // クリックジャッキングとの組み合わせ
        ['iframe', 'CSRF Attack: Iframe-based attack detected'],
        ['opacity:0', 'CSRF Attack: Invisible element attack detected'],
        ['position:absolute', 'CSRF Attack: Overlay attack detected'],
        
        // XMLHttpRequest によるCSRF
        ['XMLHttpRequest', 'CSRF Attack: AJAX-based CSRF detected'],
        ['fetch(', 'CSRF Attack: Fetch API CSRF detected'],
        ['cors', 'CSRF Attack: CORS bypass attempt detected'],
        
        // 画像/リソースによるCSRF
        ['img src=', 'CSRF Attack: Image-based CSRF detected'],
        ['onerror=', 'CSRF Attack: Error handler CSRF detected'],
        ['onload=', 'CSRF Attack: Load handler CSRF detected'],
        
        // ソーシャルエンジニアリング要素
        ['urgent', 'CSRF Attack: Urgency social engineering detected'],
        ['expire', 'CSRF Attack: Expiration pressure detected'],
        ['limited time', 'CSRF Attack: Time pressure tactic detected'],
        ['verify account', 'CSRF Attack: Account verification phishing detected'],
        ['security alert', 'CSRF Attack: Security alert phishing detected'],
        
        // 管理機能への攻撃
        ['admin', 'CSRF Attack: Admin function targeting detected'],
        ['privilege', 'CSRF Attack: Privilege escalation attempt detected'],
        ['role=admin', 'CSRF Attack: Admin role assignment detected'],
        ['permissions', 'CSRF Attack: Permission modification detected'],
        
        // APIエンドポイントへの攻撃
        ['/api/', 'CSRF Attack: API endpoint targeting detected'],
        ['application/json', 'CSRF Attack: JSON API CSRF detected'],
        ['PUT ', 'CSRF Attack: PUT method CSRF detected'],
        ['DELETE ', 'CSRF Attack: DELETE method CSRF detected'],
        ['PATCH ', 'CSRF Attack: PATCH method CSRF detected']
    ];

    try {
        // 既存のCSRF関連ルールをクリア（重複防止）
        $pdo->exec("DELETE FROM waf_blacklist WHERE is_custom = FALSE AND description LIKE '%CSRF Attack:%'");

        // 新しいシグネチャを追加
        $stmt = $pdo->prepare("
            INSERT INTO waf_blacklist (pattern, description, action, is_custom) 
            VALUES (?, ?, 'detect', FALSE)
        ");

        foreach ($csrf_signatures as [$pattern, $description]) {
            $stmt->execute([$pattern, $description]);
        }

    } catch (Throwable $e) {
        error_log("Failed to add CSRF attack signatures: " . $e->getMessage());
    }
}

// PDO があれば即実行
if (isset($pdo)) {
    run_waf($pdo);
}