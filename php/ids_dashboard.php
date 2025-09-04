<?php
// ids_dashboard.php の更新部分
// 既存コードの該当箇所を以下のように変更してください

require_once __DIR__ . '/common_init.php';
require 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: list.php');
    exit;
}

// NTP改ざんの状態を確認
$ntp_tampering_active = (!empty($_SESSION['ntp_tampering_enabled']) && 
                         $_SESSION['ntp_attack_status'] === 'compromised' && 
                         isset($_SESSION['ntp_time_offset']));
$time_offset = $_SESSION['ntp_time_offset'] ?? 0;

// SQLクエリを更新してdetected_atカラムを使用
$stmt = $pdo->query("SELECT id, detected_at, attack_type, ip_address, status_code FROM attack_logs ORDER BY detected_at DESC");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>IDS - 攻撃検知ダッシュボード</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .row-accent-danger { position:relative; }
        .row-accent-danger::before { content:''; position:absolute; left:0; top:0; width:6px; height:100%; background:#ef4444; border-top-left-radius:6px; border-bottom-left-radius:6px; }
        .row-accent-ransomware { position:relative; }
        .row-accent-ransomware::before { content:''; position:absolute; left:0; top:0; width:6px; height:100%; background:#dc2626; border-top-left-radius:6px; border-bottom-left-radius:6px; }
        .badge{display:inline-block;padding:.125rem .5rem;border-radius:.375rem;font-size:.75rem;line-height:1rem;font-weight:600;white-space:nowrap;}
        .badge-gray{background:#f3f4f6;color:#1f2937;}
        .badge-green{background:#d1fae5;color:#065f46;}
        .badge-red{background:#fee2e2;color:#991b1b;}
        .badge-amber{background:#fef3c7;color:#92400e;}
        .badge-purple{background:#f3e8ff;color:#6b21a8;}
        .badge-orange{background:#fed7aa;color:#9a3412;}
        .chip-legend{display:inline-flex;align-items:center;gap:.5rem;padding:.25rem .5rem;border-radius:.5rem;background:#f9fafb;border:1px solid #e5e7eb;}
        .legend-dot{width:.6rem;height:.6rem;border-radius:9999px;display:inline-block;}
        .dot-green{background:#10b981;}
        .dot-red{background:#ef4444;}
        .dot-amber{background:#f59e0b;}
        .dot-purple{background:#8b5cf6;}
        .dot-orange{background:#f97316;}
        .time-compromised {
            color: #dc2626 !important;
            font-weight: bold;
            background: #fef2f2;
            padding: 2px 4px;
            border-radius: 4px;
        }
    </style>
</head>
<body class="bg-gray-100">
<?php include 'header.php'; ?>
<div class="container mx-auto mt-10 p-4">
    <!-- NTP改ざん警告 -->
    <?php if ($ntp_tampering_active): ?>
        <div class="bg-red-600 text-white p-4 rounded-lg mb-6 shadow-lg">
            <div class="flex items-center">
                <div class="text-2xl mr-3">⚠️</div>
                <div>
                    <div class="font-bold text-lg">NTP改ざん攻撃が検出されています</div>
                    <div class="text-sm">
                        システム時刻が <?= sprintf('%+d秒', $time_offset) ?> 改ざんされており、
                        以下のログタイムスタンプは信頼できません。実際の時刻と異なる可能性があります。
                    </div>
                    <div class="text-xs mt-1 opacity-90">
                        現在の実時刻: <?= date('Y-m-d H:i:s') ?> | 
                        改ざん後時刻: <?= date('Y-m-d H:i:s', time() + $time_offset) ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-3xl font-bold text-gray-800">IDS - 攻撃検知ダッシュボード</h1>
        <div class="flex items-center gap-2 text-sm">
            <span class="chip-legend"><span class="legend-dot dot-red"></span>IPブロック（403）</span>
            <span class="chip-legend"><span class="legend-dot dot-amber"></span>IP検知（モニタ）</span>
            <span class="chip-legend"><span class="legend-dot dot-purple"></span>ランサムウェア検知</span>
            <span class="chip-legend"><span class="legend-dot dot-green"></span>許可IPからのadminバイパス</span>
            <span class="chip-legend"><span class="legend-dot dot-orange"></span>時刻改ざん検知</span>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="min-w-full leading-normal">
            <thead>
                <tr>
                    <th class="px-5 py-3 border-b-2 bg-gray-100 text-left text-xs font-semibold uppercase">ID</th>
                    <th class="px-5 py-3 border-b-2 bg-gray-100 text-left text-xs font-semibold uppercase">
                        検知日時
                        <?php if ($ntp_tampering_active): ?>
                            <span class="text-red-600">⚠️</span>
                        <?php endif; ?>
                    </th>
                    <th class="px-5 py-3 border-b-2 bg-gray-100 text-left text-xs font-semibold uppercase">レスポンス</th>
                    <th class="px-5 py-3 border-b-2 bg-gray-100 text-left text-xs font-semibold uppercase">攻撃タイプ</th>
                    <th class="px-5 py-3 border-b-2 bg-gray-100 text-left text-xs font-semibold uppercase">送信元IP</th>
                    <th class="px-5 py-3 border-b-2 bg-gray-100 text-left text-xs font-semibold uppercase">分類</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <?php
                        $attack_type = (string)$log['attack_type'];
                        $status      = (int)$log['status_code'];
                        
                        // detected_atカラムがある場合はそれを使用、なければ現在時刻
                        if (!empty($log['detected_at'])) {
                            $detected_at = $log['detected_at'];
                            $is_timestamp_compromised = (strpos($attack_type, '[Time Compromised]') !== false);
                        } else {
                            $detected_at = date('Y-m-d H:i:s');
                            $is_timestamp_compromised = false;
                        }

                        $status_color = 'bg-gray-200 text-gray-800';
                        if ($status === 403) $status_color = 'bg-red-200 text-red-800';
                        if ($status === 404) $status_color = 'bg-yellow-200 text-yellow-800';
                        if ($status === 500) $status_color = 'bg-purple-200 text-purple-800';

                        $badge_cls = 'badge badge-gray';
                        $row_class = '';

                        $is_ntp_attack = (stripos($attack_type, 'NTP') !== false || stripos($attack_type, 'Time') !== false);
                        if ($is_ntp_attack) {
                            $badge_cls = 'badge badge-orange';
                            if ($status === 200) $status_color = 'bg-orange-200 text-orange-800';
                        }

                        $is_ransomware = (stripos($attack_type, 'Ransomware') !== false);
                        if ($is_ransomware) {
                            $badge_cls = 'badge badge-purple';
                            $row_class = '';
                            if ($status === 200) $status_color = 'bg-purple-200 text-purple-800';
                        }

                        $is_backdoor = ($attack_type === 'Backdoor Login' || $attack_type === 'Trusted IP Admin Bypass Login');
                        if ($is_backdoor && $status === 200) {
                            $badge_cls = 'badge badge-green';
                            $status_color = 'bg-green-200 text-green-800';
                        }

                        if ($attack_type === 'Keylogger Capture') {
                            $badge_cls = 'badge badge-amber';
                            if ($status === 200) $status_color = 'bg-yellow-200 text-yellow-800';
                        }

                        $is_ip_block = (stripos($attack_type, 'IPS') !== false && stripos($attack_type, 'Block') !== false) ||
                                       (stripos($attack_type, 'IP') !== false && $status === 403);
                        if ($is_ip_block && !$is_ransomware && !$is_ntp_attack) {
                            $badge_cls = 'badge badge-red';
                            $row_class = 'row-accent-danger';
                            $status_color = 'bg-red-200 text-red-800';
                        }

                        $is_ip_monitor = (stripos($attack_type, 'IPS') !== false && stripos($attack_type, 'Monitor') !== false) ||
                                         (stripos($attack_type, 'IP') !== false && $status !== 403);
                        if (!$is_ip_block && $is_ip_monitor && !$is_ransomware && !$is_ntp_attack) {
                            $badge_cls = 'badge badge-amber';
                            if ($status === 200) $status_color = 'bg-yellow-200 text-yellow-800';
                        }
                    ?>
                    <tr class="hover:bg-gray-50 <?= $row_class ?>">
                        <!-- ID -->
                        <td class="pl-3 pr-5 py-5 border-b text-sm">
                            <a href="log_detail.php?id=<?= (int)$log['id'] ?>" class="text-blue-600 hover:underline font-bold">#<?= (int)$log['id'] ?></a>
                        </td>
                        <!-- 検知日時（改ざん検知対応） -->
                        <td class="px-5 py-5 border-b text-sm text-gray-600 whitespace-nowrap">
                            <span class="<?= ($is_timestamp_compromised || ($ntp_tampering_active && $is_ntp_attack)) ? 'time-compromised' : '' ?>">
                                <?= htmlspecialchars($detected_at) ?>
                            </span>
                            <?php if ($is_timestamp_compromised): ?>
                                <div class="text-xs text-red-500">改ざん時刻</div>
                            <?php endif; ?>
                        </td>
                        <!-- レスポンス（ステータス） -->
                        <td class="px-5 py-5 border-b text-sm">
                            <span class="px-2 py-1 font-semibold rounded-full <?= $status_color ?>">
                                <?= htmlspecialchars((string)$status) ?>
                            </span>
                        </td>
                        <!-- 攻撃タイプ -->
                        <td class="px-5 py-5 border-b text-sm">
                            <?= htmlspecialchars($attack_type) ?>
                        </td>
                        <!-- 送信元IP -->
                        <td class="px-5 py-5 border-b text-sm">
                            <?= htmlspecialchars((string)$log['ip_address']) ?>
                        </td>
                        <!-- 分類 -->
                        <td class="px-5 py-5 border-b text-sm">
                            <span class="<?= $badge_cls ?>">
                                <?php if ($is_ntp_attack): ?>
                                    時刻改ざん攻撃
                                <?php elseif ($is_ransomware): ?>
                                    ランサムウェア検知
                                <?php elseif ($is_ip_block): ?>
                                    IPブロック
                                <?php elseif ($is_ip_monitor): ?>
                                    IP検知（モニタ）
                                <?php elseif ($is_backdoor): ?>
                                    許可IPからのadminバイパス
                                <?php else: ?>
                                    その他
                                <?php endif; ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-gray-500">ログはまだありません。</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>