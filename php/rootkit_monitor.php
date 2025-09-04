<?php
// rootkit_monitor.php — ルートキット演習の可視化（擬似）
session_start();
$is_admin = (($_SESSION['role'] ?? '') === 'admin');
$cli_enable = !empty($_SESSION['cli_attack_mode_enabled']);
if (!$is_admin || !$cli_enable) { http_response_code(403); exit('forbidden'); }

$st = $_SESSION['rootkit_state'] ?? [
  'installed'=>false, 'installed_at'=>null,
  'hidden'=>['pids'=>[], 'files'=>[], 'ports'=>[]]
];
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>Rootkit 演習モニタ（擬似）</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100">
<div class="max-w-5xl mx-auto py-8 px-4 space-y-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold">🛡 Rootkit 演習モニタ（擬似）</h1>
    <div class="space-x-2">
      <a class="px-3 py-2 bg-slate-700 text-white rounded" href="cli_console.php">攻撃コンソール →</a>
      <a class="px-3 py-2 bg-indigo-600 text-white rounded" href="ids_dashboard.php">防御モニタ →</a>
    </div>
  </div>

  <div class="bg-white rounded-xl shadow p-4">
    <h2 class="font-semibold mb-2">現在の状態</h2>
    <div class="grid md:grid-cols-2 gap-4">
      <div class="p-3 border rounded">
        <div>installed: <span class="font-mono"><?= $st['installed'] ? 'yes' : 'no' ?></span></div>
        <div>installed_at: <span class="font-mono"><?= htmlspecialchars($st['installed_at'] ?? '-') ?></span></div>
      </div>
      <div class="p-3 border rounded">
        <div class="font-semibold mb-1">hidden（擬似）</div>
        <div class="text-sm">pids: <span class="font-mono"><?= htmlspecialchars(implode(', ', $st['hidden']['pids'])) ?></span></div>
        <div class="text-sm">files: <span class="font-mono"><?= htmlspecialchars(implode(', ', $st['hidden']['files'])) ?></span></div>
        <div class="text-sm">ports: <span class="font-mono"><?= htmlspecialchars(implode(', ', $st['hidden']['ports'])) ?></span></div>
      </div>
    </div>
    <p class="text-xs text-gray-500 mt-3">※ 実際の OS には一切変更を加えません。教育用の可視化です。</p>
  </div>

  <div class="bg-white rounded-xl shadow p-4">
    <h2 class="font-semibold mb-2">最近の rootkit 関連イベント</h2>
    <div id="feed" class="space-y-2 max-h-[420px] overflow-y-auto"></div>
  </div>
</div>

<script>
let since = 0;
const feed = document.getElementById('feed');

function add(ev){
  // rootkit 関連だけ表示（event_type に 'rootkit' が含まれる）
  if (!/rootkit/i.test(ev.event_type||'')) return;
  const div = document.createElement('div');
  div.className = 'border rounded px-3 py-2 text-sm';
  div.innerHTML =
    `<div class="flex justify-between">
       <div class="font-mono text-slate-700">${ev.event_type}</div>
       <div class="text-xs text-slate-500">${ev.created_at} / ${ev.ip||'-'}</div>
     </div>
     <div class="text-slate-500 break-words">${(ev.meta||'').replaceAll('<','&lt;')}</div>`;
  feed.appendChild(div);
  feed.scrollTop = feed.scrollHeight;
}

async function tick(){
  try{
    const res = await fetch('cli_events_fetch.php?since_id='+since);
    const js  = await res.json();
    if (js.ok){
      (js.events||[]).forEach(e => { add(e); since = Math.max(since, e.id); });
    }
  }catch(e){}
}
tick();
setInterval(tick, 2000);
</script>
</body>
</html>
