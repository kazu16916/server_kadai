<?php
// view_captured_credentials.php - 盗取された認証情報を表示
session_start();
require_once __DIR__ . '/db.php';

// 管理者のみ許可
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// DNS攻撃演習が有効でない場合は空の結果を返す
if (empty($_SESSION['dns_attack_enabled'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'credentials' => []]);
    exit;
}

try {
    // 盗取された認証情報を取得
    $stmt = $pdo->query("
        SELECT username, password, source_ip, captured_at, user_agent
        FROM dns_phishing_logs 
        ORDER BY captured_at DESC 
        LIMIT 50
    ");
    $credentials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 時刻フォーマットの調整
    foreach ($credentials as &$cred) {
        $cred['captured_at'] = date('Y-m-d H:i:s', strtotime($cred['captured_at']));
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'credentials' => $credentials,
        'count' => count($credentials)
    ]);
    
} catch (PDOException $e) {
    // テーブルが存在しない場合は空の結果を返す
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'credentials' => []]);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>