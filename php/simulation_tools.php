<?php
require_once __DIR__ . '/common_init.php';
require 'db.php';

// --- 権限 ---
$is_admin = (($_SESSION['role'] ?? '') === 'admin');

// 未ログインは一覧へ
if (!isset($_SESSION['role']) && ($current ?? '') !== 'logout.php') {
    header('Location: list.php');
    exit;
}

/* =========================
 *  各種演習の有効/無効トグル
 *  - CLI/Buffer/NTP/DNS…などと同じパターンで
 *    keylogger / ransomware / tamper を enable/disable 追加
 *  - 「全体有効化/無効化」は従来どおり toggle_attack_mode.php を使用
 * ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $t = $_POST['attack_type'] ?? '';

    // --- CLI 演習 ---
    if ($t === 'cli_enable') {
        $_SESSION['cli_attack_mode_enabled'] = true;
        $_SESSION['cli_attack_api_token']    = bin2hex(random_bytes(16)); // 自動発行
        $_SESSION['flash_cli_enabled'] = true;
        header('Location: simulation_tools.php');
        exit;

    } elseif ($t === 'cli_disable') {
        unset($_SESSION['cli_attack_mode_enabled'], $_SESSION['cli_attack_api_token']);
        $_SESSION['flash_cli_disabled'] = true;
        header('Location: simulation_tools.php');
        exit;
    }

    // --- Rootkit 演習（擬似）---
    if ($t === 'rootkit_enable') {
        $_SESSION['rootkit_enabled'] = true;
        // 擬似状態の初期化（実際のOSには触れません）
        $_SESSION['rootkit_state'] = [
            'installed'    => false,
            'installed_at' => null,
            'hidden'       => ['pids'=>[], 'files'=>[], 'ports'=>[]],
        ];
        $_SESSION['flash_rootkit_enabled'] = true;
        header('Location: simulation_tools.php');
        exit;

    } elseif ($t === 'rootkit_disable') {
        unset($_SESSION['rootkit_enabled'], $_SESSION['rootkit_state']);
        $_SESSION['flash_rootkit_disabled'] = true;
        header('Location: simulation_tools.php');
        exit;
    }

    // --- バッファオーバーフロー演習 ---
    if ($t === 'buffer_overflow_enable') {
        $_SESSION['buffer_overflow_enabled'] = true;
        $_SESSION['flash_buffer_overflow_enabled'] = true;
        header('Location: simulation_tools.php');
        exit;

    } elseif ($t === 'buffer_overflow_disable') {
        unset($_SESSION['buffer_overflow_enabled']);
        $_SESSION['flash_buffer_overflow_disabled'] = true;
        header('Location: simulation_tools.php');
        exit;
    }

    // --- 踏み台攻撃演習 ---
    if ($t === 'stepping_stone_enable') {
        $_SESSION['stepping_stone_enabled'] = true;
        $_SESSION['flash_stepping_stone_enabled'] = true;
        header('Location: simulation_tools.php');
        exit;

    } elseif ($t === 'stepping_stone_disable') {
        unset($_SESSION['stepping_stone_enabled']);
        $_SESSION['flash_stepping_stone_disabled'] = true;
        header('Location: simulation_tools.php');
        exit;
    }

    // --- APT 演習 ---
    if ($t === 'apt_attack_enable') {
        $_SESSION['apt_attack_enabled'] = true;
        $_SESSION['flash_apt_enabled'] = true;
        header('Location: simulation_tools.php');
        exit;

    } elseif ($t === 'apt_attack_disable') {
        unset($_SESSION['apt_attack_enabled']);
        $_SESSION['flash_apt_disabled'] = true;
        header('Location: simulation_tools.php');
        exit;
    }

    // --- メール攻撃演習 ---
    if ($t === 'mail_attack_enable') {
        $_SESSION['mail_attack_enabled'] = true;
        $_SESSION['flash_mail_enabled'] = true;
        header('Location: simulation_tools.php');
        exit;

    } elseif ($t === 'mail_attack_disable') {
        unset($_SESSION['mail_attack_enabled']);
        $_SESSION['flash_mail_disabled'] = true;
        header('Location: simulation_tools.php');
        exit;
    }

    // --- キルチェーン演習 ---
    if ($t === 'killchain_attack_enable') {
        $_SESSION['killchain_attack_enabled'] = true;
        $_SESSION['flash_killchain_enabled'] = true;
        header('Location: simulation_tools.php');
        exit;

    } elseif ($t === 'killchain_attack_disable') {
        unset($_SESSION['killchain_attack_enabled']);
        $_SESSION['flash_killchain_disabled'] = true;
        header('Location: simulation_tools.php');
        exit;
    }

    // --- NTP 改ざん演習 ---
    if ($t === 'ntp_tampering_enable') {
        $_SESSION['ntp_tampering_enabled'] = true;
        // 時刻改ざんの基準時間を設定（現在時刻からのオフセット）
        $_SESSION['ntp_time_offset'] = 0; // 初期値は0秒オフセット
        $_SESSION['ntp_attack_status'] = 'preparing'; // preparing, attacking, compromised
        $_SESSION['flash_ntp_enabled'] = true;
        header('Location: simulation_tools.php');
        exit;

    } elseif ($t === 'ntp_tampering_disable') {
        unset($_SESSION['ntp_tampering_enabled'], $_SESSION['ntp_time_offset'], $_SESSION['ntp_attack_status']);
        $_SESSION['flash_ntp_disabled'] = true;
        header('Location: simulation_tools.php');
        exit;
    }

    // --- DNS 攻撃演習 ---
    if ($t === 'dns_attack_enable') {
        $_SESSION['dns_attack_enabled'] = true;
        // DNS攻撃の状態管理
        $_SESSION['dns_attack_status'] = 'preparing'; // preparing, scanning, exploiting, compromised
        $_SESSION['dns_target_domain'] = 'login.php'; // 標的ドメイン
        $_SESSION['dns_fake_server'] = '127.0.0.1:8088'; // 偽サーバのアドレス
        $_SESSION['flash_dns_enabled'] = true;
        header('Location: simulation_tools.php');
        exit;

    } elseif ($t === 'dns_attack_disable') {
        unset($_SESSION['dns_attack_enabled'], $_SESSION['dns_attack_status'],
              $_SESSION['dns_target_domain'], $_SESSION['dns_fake_server']);
        $_SESSION['flash_dns_disabled'] = true;
        header('Location: simulation_tools.php');
        exit;
    }

    /* =============================
     * ★ 追加：3演習の個別 enable/disable
     *  - Keylogger
     *  - Ransomware
     *  - Tamper（改ざん検知）
     * ============================= */
    if ($t === 'keylogger_enable') {
        $_SESSION['keylogger_enabled'] = true;
        $_SESSION['flash_keylogger_enabled'] = true;
        header('Location: simulation_tools.php');
        exit;

    } elseif ($t === 'keylogger_disable') {
        unset($_SESSION['keylogger_enabled']);
        $_SESSION['flash_keylogger_disabled'] = true;
        header('Location: simulation_tools.php');
        exit;
    }

    if ($t === 'ransomware_enable') {
        $_SESSION['ransomware_enabled'] = true;
        $_SESSION['flash_ransomware_enabled'] = true;
        header('Location: simulation_tools.php');
        exit;

    } elseif ($t === 'ransomware_disable') {
        unset($_SESSION['ransomware_enabled']);
        $_SESSION['flash_ransomware_disabled'] = true;
        header('Location: simulation_tools.php');
        exit;
    }

    if ($t === 'tamper_enable') {
        $_SESSION['tamper_enabled'] = true;
        $_SESSION['flash_tamper_enabled'] = true;
        header('Location: simulation_tools.php');
        exit;

    } elseif ($t === 'tamper_disable') {
        unset($_SESSION['tamper_enabled']);
        $_SESSION['flash_tamper_disabled'] = true;
        header('Location: simulation_tools.php');
        exit;
    }
    if ($t === 'csrf_enable') {
        $_SESSION['csrf_enabled'] = true;
        $_SESSION['flash_csrf_enabled'] = true;
        header('Location: simulation_tools.php');
        exit;

    } elseif ($t === 'csrf_disable') {
        unset($_SESSION['csrf_enabled'], $_SESSION['csrf_protection_enabled'], 
            $_SESSION['referer_check_enabled'], $_SESSION['origin_check_enabled'], 
            $_SESSION['samesite_cookie_enabled'], $_SESSION['csrf_token']);
        $_SESSION['flash_csrf_disabled'] = true;
        header('Location: simulation_tools.php');
        exit;
    }
}

// 既存演習トグル状態
$dictionary_attack_enabled    = $_SESSION['dictionary_attack_enabled']    ?? false;
$bruteforce_enabled           = $_SESSION['bruteforce_enabled']           ?? false;
$trusted_admin_bypass_enabled = $_SESSION['trusted_admin_bypass_enabled'] ?? false;
$keylogger_enabled            = $_SESSION['keylogger_enabled']            ?? false;
$ransomware_enabled           = $_SESSION['ransomware_enabled']           ?? false;
$tamper_enabled               = $_SESSION['tamper_enabled']               ?? false;
$reverse_bruteforce_enabled   = $_SESSION['reverse_bruteforce_enabled']   ?? false;
$joe_account_attack_enabled   = $_SESSION['joe_account_attack_enabled']   ?? false;
$buffer_overflow_enabled      = $_SESSION['buffer_overflow_enabled']      ?? false;
$stepping_stone_enabled       = $_SESSION['stepping_stone_enabled']       ?? false;
$apt_attack_enabled           = $_SESSION['apt_attack_enabled']           ?? false;
$mail_attack_enabled          = $_SESSION['mail_attack_enabled']          ?? false;
$killchain_attack_enabled     = $_SESSION['killchain_attack_enabled']     ?? false;
$ntp_tampering_enabled        = $_SESSION['ntp_tampering_enabled']        ?? false;
$dns_attack_enabled           = $_SESSION['dns_attack_enabled']           ?? false;
$csrf_enabled                 = $_SESSION['csrf_enabled']                 ?? false;

// CLI / Rootkit 演習状態
$cli_enabled     = !empty($_SESSION['cli_attack_mode_enabled']);
$rootkit_enabled = !empty($_SESSION['rootkit_enabled']);

// サンプル攻撃者プロファイル
$attackers = [
    ['name'=>'アメリカのWebサーバー','ip'=>'204.79.197.200','type'=>'Datacenter (US)','user_agent'=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36'],
    ['name'=>'ヨーロッパのVPNサービス','ip'=>'89.187.167.53','type'=>'VPN (EU)','user_agent'=>'Mozilla/5.0 (Windows NT 10.0; rv:102.0) Gecko/20100101 Firefox/102.0'],
    ['name'=>'Tor匿名ネットワーク','ip'=>'185.220.101.30','type'=>'Tor Exit Node','user_agent'=>'Mozilla/5.0 (Windows NT 10.0; rv:102.0) Gecko/20100101 Firefox/102.0'],
    ['name'=>'アジアのボットネット','ip'=>'103.137.186.25','type'=>'Botnet (Asia)','user_agent'=>'curl/7.68.0'],
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>攻撃シミュレーションツール</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<?php include 'header.php'; ?>

<div class="container mx-auto mt-10 p-4">
  <h1 class="text-3xl font-bold text-gray-800 mb-2">攻撃シミュレーションツール</h1>
  <p class="text-gray-600 mb-8">IPアドレスの偽装や、特定の攻撃演習を有効化できます。</p>

  <!-- IPシミュレーション -->
  <div class="bg-white p-8 rounded-lg shadow-md mb-8">
    <h2 class="text-2xl font-bold text-center mb-6">IPアドレス シミュレーション</h2>
    <?php if (isset($_SESSION['simulated_ip'])): ?>
      <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
        <p class="font-bold">シミュレーション実行中:</p>
        <p>現在、あなたは <strong><?= htmlspecialchars($_SESSION['simulated_ip']) ?> (<?= htmlspecialchars($_SESSION['simulated_type']) ?>)</strong> として記録されています。</p>
      </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      <?php foreach ($attackers as $a): ?>
        <div class="bg-white p-6 rounded-lg border">
          <h3 class="text-lg font-semibold"><?= htmlspecialchars($a['name']) ?></h3>
          <code class="block bg-gray-100 p-2 rounded mt-2 text-sm"><?= htmlspecialchars($a['ip']) ?></code>
          <form action="set_simulation_ip.php" method="POST" class="mt-4">
            <input type="hidden" name="ip" value="<?= htmlspecialchars($a['ip']) ?>">
            <input type="hidden" name="type" value="<?= htmlspecialchars($a['type']) ?>">
            <input type="hidden" name="user_agent" value="<?= htmlspecialchars($a['user_agent']) ?>">
            <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 text-sm">この攻撃者になる</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="mt-6 text-center">
      <form action="set_simulation_ip.php" method="POST">
        <input type="hidden" name="stop" value="true">
        <button type="submit" class="text-gray-600 hover:underline">IPシミュレーションを停止</button>
      </form>
    </div>
  </div>

  <!-- 高度な攻撃演習の有効化 -->
  <div class="bg-white p-8 rounded-lg shadow-md">
    <h2 class="text-2xl font-bold text-center mb-6">高度な攻撃演習の有効化</h2>

    <div class="mb-6 text-center">
      <form action="toggle_attack_mode.php" method="POST" class="inline-block">
        <input type="hidden" name="attack_type" value="all_enable">
        <button type="submit" class="bg-green-600 text-white py-2 px-6 rounded-lg hover:bg-green-700">全て有効化</button>
      </form>
      <form action="toggle_attack_mode.php" method="POST" class="inline-block">
        <input type="hidden" name="attack_type" value="all_disable">
        <button type="submit" class="bg-gray-600 text-white py-2 px-6 rounded-lg hover:bg-gray-700">全て無効化</button>
      </form>
    </div>

    <div class="space-y-4 border-t pt-6">
      <!-- 辞書攻撃（従来どおり一発トグル：toggle_attack_mode.php を使用） -->
      <div class="flex justify-between items-center p-4 border rounded-lg">
        <div>
          <h3 class="font-semibold text-lg">辞書攻撃</h3>
          <p class="text-sm text-gray-600">ログインページに辞書攻撃ボタンを表示します。</p>
        </div>
        <form action="toggle_attack_mode.php" method="POST">
          <input type="hidden" name="attack_type" value="dictionary_attack">
          <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold <?= $dictionary_attack_enabled ? 'bg-gray-500 text-white' : 'bg-red-500 text-white' ?>">
            <?= $dictionary_attack_enabled ? '無効化' : '有効化' ?>
          </button>
        </form>
      </div>

      <!-- 総当たり攻撃（従来どおり） -->
      <div class="flex justify-between items-center p-4 border rounded-lg">
        <div>
          <h3 class="font-semibold text-lg">総当たり攻撃 (ビジュアル)</h3>
          <p class="text-sm text-gray-600">ログインページに総当たり攻撃のシミュレーターを表示します。</p>
        </div>
        <form action="toggle_attack_mode.php" method="POST">
          <input type="hidden" name="attack_type" value="bruteforce">
          <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold <?= $bruteforce_enabled ? 'bg-gray-500 text-white' : 'bg-red-500 text-white' ?>">
            <?= $bruteforce_enabled ? '無効化' : '有効化' ?>
          </button>
        </form>
      </div>

      <!-- 逆ブルートフォース（従来どおり） -->
      <div class="flex justify-between items-center p-4 border rounded-lg">
        <div>
          <h3 class="font-semibold text-lg">逆総当たり攻撃</h3>
          <p class="text-sm text-gray-600">1つのパスワードに対して複数ユーザー名を試行（演習）。</p>
        </div>
        <form action="toggle_attack_mode.php" method="POST">
          <input type="hidden" name="attack_type" value="reverse_bruteforce">
          <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold <?= $reverse_bruteforce_enabled ? 'bg-gray-500 text-white' : 'bg-red-600 text-white' ?>">
            <?= $reverse_bruteforce_enabled ? '無効化' : '有効化' ?>
          </button>
        </form>
      </div>

      <!-- ジョーアカウント攻撃（従来どおり） -->
      <div class="flex justify-between items-center p-4 border rounded-lg">
        <div>
          <h3 class="font-semibold text-lg">ジョーアカウント攻撃（スプレー）</h3>
          <p class="text-sm text-gray-600">既定名/指定パターンに対し、よくあるPWを薄く広く試行（演習）。</p>
        </div>
        <form action="toggle_attack_mode.php" method="POST">
          <input type="hidden" name="attack_type" value="joe_account_attack">
          <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold <?= $joe_account_attack_enabled ? 'bg-gray-500 text-white' : 'bg-red-600 text-white' ?>">
            <?= $joe_account_attack_enabled ? '無効化' : '有効化' ?>
          </button>
        </form>
      </div>

      <!-- バックドア（従来どおり） -->
      <div class="flex justify-between items-center p-4 border rounded-lg">
        <div>
          <h3 class="font-semibold text-lg">バックドア</h3>
          <p class="text-sm text-gray-600">信頼IPから admin をパスワードレス許可（演習）。</p>
        </div>
        <form action="toggle_attack_mode.php" method="POST">
          <input type="hidden" name="attack_type" value="trusted_admin_bypass">
          <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold <?= $trusted_admin_bypass_enabled ? 'bg-gray-500 text-white' : 'bg-red-500 text-white' ?>">
            <?= $trusted_admin_bypass_enabled ? '無効化' : '有効化' ?>
          </button>
        </form>
      </div>

      <!-- ★ キーロガー（UIをCLIパターンへ統一） -->
      <div class="flex justify-between items-center p-4 border rounded-lg">
        <div>
          <h3 class="font-semibold text-lg">キーロガー（演習）</h3>
          <p class="text-sm text-gray-600">ログイン画面のキー入力を記録（擬似）し、防御モニタに通知します。</p>
        </div>

        <?php if (!$keylogger_enabled): ?>
          <!-- 無効 → 有効化ボタンのみ -->
          <form action="simulation_tools.php" method="POST">
            <input type="hidden" name="attack_type" value="keylogger_enable">
            <button type="submit"
              class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white hover:bg-red-700">
              有効化
            </button>
          </form>
        <?php else: ?>
          <!-- 有効化済み → 演習画面リンク + 無効化ボタン -->
          <div class="flex items-center gap-2">
            <a href="attacker_console.php"
               class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white hover:bg-red-700">
               キーロガー演習ページへ
            </a>
            <form action="simulation_tools.php" method="POST">
              <input type="hidden" name="attack_type" value="keylogger_disable">
              <button class="px-4 py-2 rounded-lg text-sm font-semibold bg-gray-500 text-white hover:bg-gray-600">
                無効化
              </button>
            </form>
          </div>
        <?php endif; ?>
      </div>

      <!-- ★ ランサムウェア演習（UIをCLIパターンへ統一） -->
      <div class="flex justify-between items-center p-4 border rounded-lg">
        <div>
          <h3 class="font-semibold text-lg">ランサムウェア演習</h3>
          <p class="text-sm text-gray-600">疑似ランサム表示＆検知（演習）。実ファイルは暗号化しません。</p>
        </div>

        <?php if (!$ransomware_enabled): ?>
          <form action="simulation_tools.php" method="POST">
            <input type="hidden" name="attack_type" value="ransomware_enable">
            <button type="submit"
              class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white">
              有効化
            </button>
          </form>
        <?php else: ?>
          <div class="flex items-center gap-2">
            <a href="enhanced_ransomware_exercise.php"
               class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white hover:bg-red-700">
               ランサムウェア演習ページへ
            </a>
            <form action="simulation_tools.php" method="POST">
              <input type="hidden" name="attack_type" value="ransomware_disable">
              <button class="px-4 py-2 rounded-lg text-sm font-semibold bg-gray-500 text-white hover:bg-gray-600">
                無効化
              </button>
            </form>
          </div>
        <?php endif; ?>
      </div>

      <!-- ★ 改ざん検知演習（UIをCLIパターンへ統一） -->
      <div class="flex justify-between items-center p-4 border rounded-lg">
        <div>
          <h3 class="font-semibold text-lg">改ざん攻撃（演習）</h3>
          <p class="text-sm text-gray-600"><code>simulation_files</code> 内で模擬改ざん＆ハッシュ検証を行います。</p>
        </div>

        <?php if (!$tamper_enabled): ?>
          <form action="simulation_tools.php" method="POST">
            <input type="hidden" name="attack_type" value="tamper_enable">
            <button type="submit"
              class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white">
              有効化
            </button>
          </form>
        <?php else: ?>
          <div class="flex items-center gap-2">
            <a href="tamper_attack.php"
               class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white hover:bg-red-700">
               改ざん検知演習ページへ
            </a>
            <form action="simulation_tools.php" method="POST">
              <input type="hidden" name="attack_type" value="tamper_disable">
              <button class="px-4 py-2 rounded-lg text-sm font-semibold bg-gray-500 text-white hover:bg-gray-600">
                無効化
              </button>
            </form>
          </div>
        <?php endif; ?>
      </div>

      <!-- ★ CLI攻撃演習（擬似）— 統一パターン -->
      <div class="flex justify-between items-center p-4 border rounded-lg">
          <div>
              <h3 class="font-semibold text-lg">CLI攻撃演習（擬似）</h3>
              <p class="text-sm text-gray-600">
                  ポートスキャン/総当たり/SQLi などを擬似実行し、防御モニタに通知します。
              </p>
          </div>

          <?php if (!$cli_enabled): ?>
              <!-- 無効 → 有効化ボタンのみ -->
              <form action="simulation_tools.php" method="POST">
                  <input type="hidden" name="attack_type" value="cli_enable">
                  <button type="submit"
                      class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white hover:bg-red-700">
                      有効化
                  </button>
              </form>
          <?php else: ?>
              <!-- 有効化済み → 演習画面リンク + 無効化ボタン -->
              <div class="flex items-center gap-2">
                <a href="cli_console.php" 
                    class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white hover:bg-red-700">
                    CLI攻撃演習ページへ
                </a>
                  <form action="simulation_tools.php" method="POST">
                      <input type="hidden" name="attack_type" value="cli_disable">
                      <button class="px-4 py-2 rounded-lg text-sm font-semibold bg-gray-500 text-white hover:bg-gray-600">
                          無効化
                      </button>
                  </form>
              </div>
          <?php endif; ?>
      </div>

      <!-- バッファオーバーフロー演習（模擬） -->
      <div class="flex justify-between items-center p-4 border rounded-lg">
          <div>
              <h3 class="font-semibold text-lg">バッファオーバーフロー演習（模擬）</h3>
              <p class="text-sm text-gray-600">
                  メモリ破壊攻撃の視覚的シミュレーションを実行します。実際のメモリ破壊は発生しません。
              </p>
          </div>

          <?php if (!$buffer_overflow_enabled): ?>
              <!-- 無効 → 有効化ボタンのみ -->
              <form action="simulation_tools.php" method="POST">
                  <input type="hidden" name="attack_type" value="buffer_overflow_enable">
                  <button type="submit"
                      class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white">
                      有効化
                  </button>
              </form>
          <?php else: ?>
              <!-- 有効化済み → 演習画面リンク + 無効化ボタン -->
              <div class="flex items-center gap-2">
                <a href="buffer_overflow_exercise.php" 
                    class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white hover:bg-red-700">
                    バッファオーバーフロー攻撃演習ページへ
                </a>
                  <form action="simulation_tools.php" method="POST">
                      <input type="hidden" name="attack_type" value="buffer_overflow_disable">
                      <button class="px-4 py-2 rounded-lg text-sm font-semibold bg-gray-500 text-white hover:bg-gray-600">
                          無効化
                      </button>
                  </form>
              </div>
          <?php endif; ?>
      </div>

      <!-- 踏み台攻撃演習（模擬） -->
      <div class="flex justify-between items-center p-4 border rounded-lg">
          <div>
              <h3 class="font-semibold text-lg">踏み台攻撃演習（模擬）</h3>
              <p class="text-sm text-gray-600">
                  複数ホストを経由する多段階攻撃の視覚的シミュレーション。実際の侵入は発生しません。
              </p>
          </div>

          <?php if (!$stepping_stone_enabled): ?>
              <form action="simulation_tools.php" method="POST">
                  <input type="hidden" name="attack_type" value="stepping_stone_enable">
                  <button type="submit"
                      class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white">
                      有効化
                  </button>
              </form>
          <?php else: ?>
              <div class="flex items-center gap-2">
                <a href="stepping_stone_exercise.php" 
                    class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white hover:bg-red-700">
                    踏み台攻撃演習ページへ
                </a>
                  <form action="simulation_tools.php" method="POST">
                      <input type="hidden" name="attack_type" value="stepping_stone_disable">
                      <button class="px-4 py-2 rounded-lg text-sm font-semibold bg-gray-500 text-white hover:bg-gray-600">
                          無効化
                      </button>
                  </form>
              </div>
          <?php endif; ?>
      </div>

      <!-- 標的型攻撃演習（APT） -->
      <div class="flex justify-between items-center p-4 border rounded-lg">
          <div>
              <h3 class="font-semibold text-lg">標的型攻撃演習（APT）</h3>
              <p class="text-sm text-gray-600">
                  高度で持続的な標的型攻撃の段階的シミュレーション。偵察から潜伏、横展開まで視覚化します。
              </p>
          </div>

          <?php if (!$apt_attack_enabled): ?>
              <form action="simulation_tools.php" method="POST">
                  <input type="hidden" name="attack_type" value="apt_attack_enable">
                  <button type="submit"
                      class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white">
                      有効化
                  </button>
              </form>
          <?php else: ?>
              <div class="flex items-center gap-2">
                <a href="apt_attack_exercise.php" 
                    class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white hover:bg-red-700">
                    標的型攻撃演習ページへ
                </a>
                  <form action="simulation_tools.php" method="POST">
                      <input type="hidden" name="attack_type" value="apt_attack_disable">
                      <button class="px-4 py-2 rounded-lg text-sm font-semibold bg-gray-500 text-white hover:bg-gray-600">
                          無効化
                      </button>
                  </form>
              </div>
          <?php endif; ?>
      </div>

      <!-- メール攻撃演習 -->
      <div class="flex justify-between items-center p-4 border rounded-lg">
          <div>
              <h3 class="font-semibold text-lg">メール攻撃演習（フィッシング・インジェクション）</h3>
              <p class="text-sm text-gray-600">
                  フィッシングメール作成、メールインジェクション、SPAMリレーなどの模擬演習。
              </p>
          </div>

          <?php if (!$mail_attack_enabled): ?>
              <form action="simulation_tools.php" method="POST">
                  <input type="hidden" name="attack_type" value="mail_attack_enable">
                  <button type="submit"
                      class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white">
                      有効化
                  </button>
              </form>
          <?php else: ?>
              <div class="flex items-center gap-2">
                <a href="mail_attack_exercise.php" 
                    class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white hover:bg-red-700">
                    メールサーバ攻撃演習ページへ
                </a>
                  <form action="simulation_tools.php" method="POST">
                      <input type="hidden" name="attack_type" value="mail_attack_disable">
                      <button class="px-4 py-2 rounded-lg text-sm font-semibold bg-gray-500 text-white hover:bg-gray-600">
                          無効化
                      </button>
                  </form>
              </div>
          <?php endif; ?>
      </div>

      <!-- サイバーキルチェーン演習 -->
      <div class="flex justify-between items-center p-4 border rounded-lg">
            <div>
                <h3 class="font-semibold text-lg">サイバーキルチェーン演習</h3>
                <p class="text-sm text-gray-600">
                    7段階のサイバーキルチェーンモデルに基づく系統的攻撃演習。偵察から目的達成まで段階的に実行します。
                </p>
            </div>

            <?php if (!$killchain_attack_enabled): ?>
                <form action="simulation_tools.php" method="POST">
                    <input type="hidden" name="attack_type" value="killchain_attack_enable">
                    <button type="submit"
                        class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white">
                        有効化
                    </button>
                </form>
            <?php else: ?>
                <div class="flex items-center gap-2">
                    <a href="killchain_exercise.php" 
                        class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white hover:bg-red-700">
                        サイバーキルチェーン攻撃演習ページへ
                    </a>
                    <form action="simulation_tools.php" method="POST">
                        <input type="hidden" name="attack_type" value="killchain_attack_disable">
                        <button class="px-4 py-2 rounded-lg text-sm font-semibold bg-gray-500 text-white hover:bg-gray-600">
                            無効化
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- NTP改ざん攻撃演習 -->
        <div class="flex justify-between items-center p-4 border rounded-lg">
            <div>
                <h3 class="font-semibold text-lg">NTP改ざん攻撃演習（時刻操作）</h3>
                <p class="text-sm text-gray-600">
                    NTPサーバ改ざんによる時刻操作攻撃をシミュレーションし、IDSログの時刻を意図的に改ざんします。ログ解析妨害の演習用です。
                </p>
            </div>

            <?php if (!$ntp_tampering_enabled): ?>
                <form action="simulation_tools.php" method="POST">
                    <input type="hidden" name="attack_type" value="ntp_tampering_enable">
                    <button type="submit"
                        class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white">
                        有効化
                    </button>
                </form>
            <?php else: ?>
                <div class="flex items-center gap-2">
                    <a href="ntp_tampering_exercise.php" 
                        class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white hover:bg-red-700">
                        NTPサーバ攻撃演習ページへ
                    </a>
                    <form action="simulation_tools.php" method="POST">
                        <input type="hidden" name="attack_type" value="ntp_tampering_disable">
                        <button class="px-4 py-2 rounded-lg text-sm font-semibold bg-gray-500 text-white hover:bg-gray-600">
                            無効化
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- DNS攻撃演習 -->
        <div class="flex justify-between items-center p-4 border rounded-lg">
            <div>
                <h3 class="font-semibold text-lg">DNS攻撃演習（DNS改ざん・フィッシング）</h3>
                <p class="text-sm text-gray-600">
                    DNSサーバへの攻撃によるドメイン改ざんをシミュレーションし、偽のログインページへ誘導します。フィッシング攻撃の演習用です。
                </p>
            </div>

            <?php if (!$dns_attack_enabled): ?>
                <form action="simulation_tools.php" method="POST">
                    <input type="hidden" name="attack_type" value="dns_attack_enable">
                    <button type="submit"
                        class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white">
                        有効化
                    </button>
                </form>
            <?php else: ?>
                <div class="flex items-center gap-2">
                    <a href="dns_attack_exercise.php" 
                        class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white hover:bg-red-700">
                        DNSサーバ攻撃演習ページへ
                    </a>
                    <form action="simulation_tools.php" method="POST">
                        <input type="hidden" name="attack_type" value="dns_attack_disable">
                        <button class="px-4 py-2 rounded-lg text-sm font-semibold bg-gray-500 text-white hover:bg-gray-600">
                            無効化
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <div class="flex justify-between items-center p-4 border rounded-lg">
            <div>
                <h3 class="font-semibold text-lg">CSRF攻撃演習</h3>
                <p class="text-sm text-gray-600">
                    クロスサイトリクエストフォージェリ攻撃の実行と防御策の効果を体験できます。
                    CSRFトークン、Refererチェック、SameSite Cookie等の防御機能を学習します。
                </p>
            </div>

            <?php if (!$csrf_enabled): ?>
                <form action="simulation_tools.php" method="POST">
                    <input type="hidden" name="attack_type" value="csrf_enable">
                    <button type="submit"
                        class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white hover:bg-red-700">
                        有効化
                    </button>
                </form>
            <?php else: ?>
                <div class="flex items-center gap-2">
                    <a href="csrf_exercise.php"
                    class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white hover:bg-red-700">
                    CSRF攻撃演習ページへ
                    </a>
                    <form action="simulation_tools.php" method="POST">
                        <input type="hidden" name="attack_type" value="csrf_disable">
                        <button class="px-4 py-2 rounded-lg text-sm font-semibold bg-gray-500 text-white hover:bg-gray-600">
                            無効化
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
  </div>
</div>
</body>
</html>
