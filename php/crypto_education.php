<?php
// crypto_education.php - 暗号方式教育演習システム
require_once __DIR__ . '/common_init.php';
require_once __DIR__ . '/db.php';

// 暗号化処理用のディレクトリ作成
$crypto_dir = __DIR__ . '/crypto_exercise';
$keys_dir = $crypto_dir . '/keys';
$files_dir = $crypto_dir . '/files';

foreach ([$crypto_dir, $keys_dir, $files_dir] as $dir) {
   if (!is_dir($dir)) {
       mkdir($dir, 0755, true);
   }
}

// 暗号化クラス
class CryptoEducation {
   private $keys_dir;
   private $files_dir;
   
   public function __construct($keys_dir, $files_dir) {
       $this->keys_dir = $keys_dir;
       $this->files_dir = $files_dir;
   }
   
   // 共通鍵暗号（AES-256-CBC）
   public function symmetricEncrypt($plaintext, $password) {
       $method = 'AES-256-CBC';
       $key = hash('sha256', $password, true);
       $iv = random_bytes(16);
       
       $encrypted = openssl_encrypt($plaintext, $method, $key, 0, $iv);
       $result = base64_encode($iv . $encrypted);
       
       return [
           'encrypted' => $result,
           'method' => $method,
           'key_length' => strlen($key) * 8 . ' bits',
           'iv_length' => strlen($iv) * 8 . ' bits'
       ];
   }
   
   public function symmetricDecrypt($encrypted_data, $password) {
       $method = 'AES-256-CBC';
       $key = hash('sha256', $password, true);
       $data = base64_decode($encrypted_data);
       
       $iv = substr($data, 0, 16);
       $encrypted = substr($data, 16);
       
       $decrypted = openssl_decrypt($encrypted, $method, $key, 0, $iv);
       return $decrypted !== false ? $decrypted : null;
   }
   
   // RSA公開鍵暗号
   public function generateRSAKeyPair($bits = 2048) {
       $config = [
           "digest_alg" => "sha256",
           "private_key_bits" => $bits,
           "private_key_type" => OPENSSL_KEYTYPE_RSA,
       ];
       
       $res = openssl_pkey_new($config);
       if (!$res) return false;
       
       openssl_pkey_export($res, $private_key);
       $public_key = openssl_pkey_get_details($res)['key'];
       
       return [
           'private_key' => $private_key,
           'public_key' => $public_key,
           'bits' => $bits
       ];
   }
   
   public function rsaEncrypt($plaintext, $public_key) {
       $encrypted = '';
       $chunk_size = 245; // RSA 2048ビットでの最大平文サイズ（PKCS1パディング考慮）
       
       $chunks = str_split($plaintext, $chunk_size);
       $encrypted_chunks = [];
       
       foreach ($chunks as $chunk) {
           $encrypted_chunk = '';
           if (openssl_public_encrypt($chunk, $encrypted_chunk, $public_key)) {
               $encrypted_chunks[] = base64_encode($encrypted_chunk);
           } else {
               return false;
           }
       }
       
       return implode('|', $encrypted_chunks);
   }
   
   public function rsaDecrypt($encrypted_data, $private_key) {
       $chunks = explode('|', $encrypted_data);
       $decrypted_chunks = [];
       
       foreach ($chunks as $chunk) {
           $decrypted_chunk = '';
           if (openssl_private_decrypt(base64_decode($chunk), $decrypted_chunk, $private_key)) {
               $decrypted_chunks[] = $decrypted_chunk;
           } else {
               return false;
           }
       }
       
       return implode('', $decrypted_chunks);
   }
   
   // デジタル署名
   public function createDigitalSignature($data, $private_key) {
       $signature = '';
       if (openssl_sign($data, $signature, $private_key, OPENSSL_ALGO_SHA256)) {
           return base64_encode($signature);
       }
       return false;
   }
   
   public function verifyDigitalSignature($data, $signature, $public_key) {
       $result = openssl_verify($data, base64_decode($signature), $public_key, OPENSSL_ALGO_SHA256);
       return $result === 1;
   }
   
   // ハイブリッド暗号
   public function hybridEncrypt($plaintext, $public_key) {
       // 1. ランダムなAESキーを生成
       $aes_key = random_bytes(32); // 256ビット
       $aes_password = base64_encode($aes_key);
       
       // 2. AESでデータを暗号化
       $aes_result = $this->symmetricEncrypt($plaintext, $aes_password);
       
       // 3. AESキーをRSAで暗号化
       $encrypted_key = '';
       if (!openssl_public_encrypt($aes_key, $encrypted_key, $public_key)) {
           return false;
       }
       
       return [
           'encrypted_data' => $aes_result['encrypted'],
           'encrypted_key' => base64_encode($encrypted_key),
           'method' => 'Hybrid (RSA + AES-256-CBC)'
       ];
   }
   
   public function hybridDecrypt($encrypted_data, $encrypted_key, $private_key) {
       // 1. RSAで暗号化されたAESキーを復号
       $aes_key = '';
       if (!openssl_private_decrypt(base64_decode($encrypted_key), $aes_key, $private_key)) {
           return false;
       }
       
       // 2. AESでデータを復号
       $aes_password = base64_encode($aes_key);
       return $this->symmetricDecrypt($encrypted_data, $aes_password);
   }
}

$crypto = new CryptoEducation($keys_dir, $files_dir);

// POST処理
$result = [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   $action = $_POST['action'] ?? '';
   
   try {
       switch ($action) {
           case 'symmetric_encrypt':
               $plaintext = $_POST['plaintext'] ?? '';
               $password = $_POST['password'] ?? '';
               
               if (empty($plaintext) || empty($password)) {
                   throw new Exception('平文とパスワードを入力してください');
               }
               
               $result = $crypto->symmetricEncrypt($plaintext, $password);
               $result['type'] = 'symmetric_encrypt';
               $result['original_text'] = $plaintext;
               $result['password'] = $password;
               break;
               
           case 'symmetric_decrypt':
               $encrypted_data = $_POST['encrypted_data'] ?? '';
               $password = $_POST['decrypt_password'] ?? '';
               
               if (empty($encrypted_data) || empty($password)) {
                   throw new Exception('暗号化データとパスワードを入力してください');
               }
               
               $decrypted = $crypto->symmetricDecrypt($encrypted_data, $password);
               if ($decrypted === null) {
                   throw new Exception('復号に失敗しました。パスワードが間違っている可能性があります。');
               }
               
               $result = [
                   'type' => 'symmetric_decrypt',
                   'decrypted_text' => $decrypted,
                   'password' => $password
               ];
               break;
               
           case 'generate_rsa_keys':
               $bits = (int)($_POST['key_size'] ?? 2048);
               $keypair = $crypto->generateRSAKeyPair($bits);
               
               if (!$keypair) {
                   throw new Exception('RSA鍵ペアの生成に失敗しました');
               }
               
               // 鍵をファイルに保存
               $timestamp = date('Y-m-d_H-i-s');
               $private_key_file = $keys_dir . "/rsa_private_{$timestamp}.pem";
               $public_key_file = $keys_dir . "/rsa_public_{$timestamp}.pem";
               
               file_put_contents($private_key_file, $keypair['private_key']);
               file_put_contents($public_key_file, $keypair['public_key']);
               
               $result = [
                   'type' => 'generate_rsa_keys',
                   'private_key' => $keypair['private_key'],
                   'public_key' => $keypair['public_key'],
                   'bits' => $keypair['bits'],
                   'private_key_file' => basename($private_key_file),
                   'public_key_file' => basename($public_key_file)
               ];
               break;
               
           case 'rsa_encrypt':
               $plaintext = $_POST['rsa_plaintext'] ?? '';
               $public_key = $_POST['public_key'] ?? '';
               
               if (empty($plaintext) || empty($public_key)) {
                   throw new Exception('平文と公開鍵を入力してください');
               }
               
               $encrypted = $crypto->rsaEncrypt($plaintext, $public_key);
               if ($encrypted === false) {
                   throw new Exception('RSA暗号化に失敗しました');
               }
               
               $result = [
                   'type' => 'rsa_encrypt',
                   'original_text' => $plaintext,
                   'encrypted' => $encrypted,
                   'public_key_preview' => substr($public_key, 0, 100) . '...'
               ];
               break;
               
           case 'rsa_decrypt':
               $encrypted_data = $_POST['rsa_encrypted_data'] ?? '';
               $private_key = $_POST['private_key'] ?? '';
               
               if (empty($encrypted_data) || empty($private_key)) {
                   throw new Exception('暗号化データと秘密鍵を入力してください');
               }
               
               $decrypted = $crypto->rsaDecrypt($encrypted_data, $private_key);
               if ($decrypted === false) {
                   throw new Exception('RSA復号に失敗しました');
               }
               
               $result = [
                   'type' => 'rsa_decrypt',
                   'decrypted_text' => $decrypted,
                   'private_key_preview' => substr($private_key, 0, 100) . '...'
               ];
               break;
               
           case 'create_signature':
               $data = $_POST['signature_data'] ?? '';
               $private_key = $_POST['signature_private_key'] ?? '';
               
               if (empty($data) || empty($private_key)) {
                   throw new Exception('署名対象データと秘密鍵を入力してください');
               }
               
               $signature = $crypto->createDigitalSignature($data, $private_key);
               if ($signature === false) {
                   throw new Exception('デジタル署名の作成に失敗しました');
               }
               
               $result = [
                   'type' => 'create_signature',
                   'original_data' => $data,
                   'signature' => $signature,
                   'hash' => hash('sha256', $data)
               ];
               break;
               
           case 'verify_signature':
               $data = $_POST['verify_data'] ?? '';
               $signature = $_POST['signature'] ?? '';
               $public_key = $_POST['verify_public_key'] ?? '';
               
               if (empty($data) || empty($signature) || empty($public_key)) {
                   throw new Exception('データ、署名、公開鍵をすべて入力してください');
               }
               
               $is_valid = $crypto->verifyDigitalSignature($data, $signature, $public_key);
               
               $result = [
                   'type' => 'verify_signature',
                   'data' => $data,
                   'signature' => $signature,
                   'is_valid' => $is_valid,
                   'hash' => hash('sha256', $data)
               ];
               break;
               
           case 'hybrid_encrypt':
               $plaintext = $_POST['hybrid_plaintext'] ?? '';
               $public_key = $_POST['hybrid_public_key'] ?? '';
               
               if (empty($plaintext) || empty($public_key)) {
                   throw new Exception('平文と公開鍵を入力してください');
               }
               
               $encrypted = $crypto->hybridEncrypt($plaintext, $public_key);
               if ($encrypted === false) {
                   throw new Exception('ハイブリッド暗号化に失敗しました');
               }
               
               $result = [
                   'type' => 'hybrid_encrypt',
                   'original_text' => $plaintext,
                   'encrypted_data' => $encrypted['encrypted_data'],
                   'encrypted_key' => $encrypted['encrypted_key'],
                   'method' => $encrypted['method']
               ];
               break;
               
           case 'hybrid_decrypt':
               $encrypted_data = $_POST['hybrid_encrypted_data'] ?? '';
               $encrypted_key = $_POST['hybrid_encrypted_key'] ?? '';
               $private_key = $_POST['hybrid_private_key'] ?? '';
               
               if (empty($encrypted_data) || empty($encrypted_key) || empty($private_key)) {
                   throw new Exception('暗号化データ、暗号化鍵、秘密鍵をすべて入力してください');
               }
               
               $decrypted = $crypto->hybridDecrypt($encrypted_data, $encrypted_key, $private_key);
               if ($decrypted === false) {
                   throw new Exception('ハイブリッド復号に失敗しました');
               }
               
               $result = [
                   'type' => 'hybrid_decrypt',
                   'decrypted_text' => $decrypted
               ];
               break;
       }
   } catch (Exception $e) {
       $error = $e->getMessage();
   }
}

// 保存されている鍵ファイル一覧を取得
$saved_keys = [];
if (is_dir($keys_dir)) {
   $files = glob($keys_dir . '/*.pem');
   foreach ($files as $file) {
       $content = file_get_contents($file);
       $saved_keys[] = [
           'filename' => basename($file),
           'type' => strpos($file, 'private') !== false ? 'private' : 'public',
           'content' => $content,
           'size' => strlen($content)
       ];
   }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
   <meta charset="UTF-8">
   <title>暗号方式教育演習システム</title>
   <script src="https://cdn.tailwindcss.com"></script>
   <style>
       .crypto-section {
           border-left: 4px solid;
           padding-left: 1rem;
       }
       .symmetric { border-color: #3b82f6; }
       .asymmetric { border-color: #ef4444; }
       .hybrid { border-color: #10b981; }
       .signature { border-color: #f59e0b; }
       
       .key-display {
           font-family: 'Courier New', monospace;
           font-size: 0.8rem;
           background: #f8fafc;
           border: 1px solid #e2e8f0;
           border-radius: 0.5rem;
           padding: 0.75rem;
           max-height: 200px;
           overflow-y: auto;
           white-space: pre-wrap;
           word-break: break-all;
       }
       
       .result-box {
           background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
           color: white;
           border-radius: 0.75rem;
           padding: 1.5rem;
           margin: 1rem 0;
       }
       
       .step-indicator {
           display: flex;
           align-items: center;
           margin: 1rem 0;
       }
       
       .step-number {
           background: #4f46e5;
           color: white;
           width: 2rem;
           height: 2rem;
           border-radius: 50%;
           display: flex;
           align-items: center;
           justify-content: center;
           font-weight: bold;
           margin-right: 0.75rem;
       }
       
       .explanation-box {
           background: #f0f9ff;
           border: 1px solid #0ea5e9;
           border-radius: 0.5rem;
           padding: 1rem;
           margin: 1rem 0;
       }
   </style>
</head>
<body class="bg-gray-50">

<?php include 'header.php'; ?>

<div class="container mx-auto mt-6 p-4">
   <h1 class="text-4xl font-bold text-center text-gray-800 mb-8">暗号方式教育演習システム</h1>
   
   <div class="text-center mb-8">
       <p class="text-lg text-gray-600">
           実際の鍵とファイルを使って、共通鍵暗号・公開鍵暗号・ハイブリッド暗号・デジタル署名を体験的に学習できます。
       </p>
   </div>

   <?php if ($error): ?>
       <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
           <strong>エラー:</strong> <?= htmlspecialchars($error) ?>
       </div>
   <?php endif; ?>

   <?php if ($result): ?>
       <div class="result-box">
           <h3 class="text-2xl font-bold mb-4">実行結果</h3>
           <?php if ($result['type'] === 'symmetric_encrypt'): ?>
               <div class="space-y-3">
                   <div><strong>暗号化方式:</strong> <?= $result['method'] ?></div>
                   <div><strong>鍵長:</strong> <?= $result['key_length'] ?></div>
                   <div><strong>元の平文:</strong> <span class="bg-white bg-opacity-20 px-2 py-1 rounded"><?= htmlspecialchars($result['original_text']) ?></span></div>
                   <div><strong>暗号化データ:</strong></div>
                   <div class="bg-white bg-opacity-20 p-3 rounded font-mono text-sm break-all"><?= htmlspecialchars($result['encrypted']) ?></div>
               </div>
           <?php elseif ($result['type'] === 'symmetric_decrypt'): ?>
               <div class="space-y-3">
                   <div><strong>復号成功!</strong></div>
                   <div><strong>復号されたテキスト:</strong> <span class="bg-white bg-opacity-20 px-2 py-1 rounded"><?= htmlspecialchars($result['decrypted_text']) ?></span></div>
               </div>
           <?php elseif ($result['type'] === 'generate_rsa_keys'): ?>
               <div class="space-y-3">
                   <div><strong>RSA鍵ペア生成完了</strong> (<?= $result['bits'] ?>ビット)</div>
                   <div><strong>保存ファイル:</strong> <?= $result['private_key_file'] ?> / <?= $result['public_key_file'] ?></div>
                   <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                       <div>
                           <strong>秘密鍵 (Private Key):</strong>
                           <div class="bg-white bg-opacity-20 p-2 rounded text-xs font-mono mt-2 max-h-40 overflow-y-auto"><?= htmlspecialchars($result['private_key']) ?></div>
                       </div>
                       <div>
                           <strong>公開鍵 (Public Key):</strong>
                           <div class="bg-white bg-opacity-20 p-2 rounded text-xs font-mono mt-2 max-h-40 overflow-y-auto"><?= htmlspecialchars($result['public_key']) ?></div>
                       </div>
                   </div>
               </div>
           <?php elseif ($result['type'] === 'rsa_encrypt'): ?>
               <div class="space-y-3">
                   <div><strong>RSA暗号化完了</strong></div>
                   <div><strong>元の平文:</strong> <span class="bg-white bg-opacity-20 px-2 py-1 rounded"><?= htmlspecialchars($result['original_text']) ?></span></div>
                   <div><strong>暗号化データ:</strong></div>
                   <div class="bg-white bg-opacity-20 p-3 rounded font-mono text-sm break-all"><?= htmlspecialchars($result['encrypted']) ?></div>
               </div>
           <?php elseif ($result['type'] === 'rsa_decrypt'): ?>
               <div class="space-y-3">
                   <div><strong>RSA復号成功!</strong></div>
                   <div><strong>復号されたテキスト:</strong> <span class="bg-white bg-opacity-20 px-2 py-1 rounded"><?= htmlspecialchars($result['decrypted_text']) ?></span></div>
               </div>
           <?php elseif ($result['type'] === 'create_signature'): ?>
               <div class="space-y-3">
                   <div><strong>デジタル署名作成完了</strong></div>
                   <div><strong>署名対象データ:</strong> <span class="bg-white bg-opacity-20 px-2 py-1 rounded"><?= htmlspecialchars($result['original_data']) ?></span></div>
                   <div><strong>データのハッシュ値 (SHA-256):</strong></div>
                   <div class="bg-white bg-opacity-20 p-2 rounded font-mono text-sm"><?= $result['hash'] ?></div>
                   <div><strong>デジタル署名:</strong></div>
                   <div class="bg-white bg-opacity-20 p-3 rounded font-mono text-sm break-all"><?= htmlspecialchars($result['signature']) ?></div>
               </div>
           <?php elseif ($result['type'] === 'verify_signature'): ?>
               <div class="space-y-3">
                   <div><strong>デジタル署名検証完了</strong></div>
                   <div><strong>検証結果:</strong> 
                       <span class="<?= $result['is_valid'] ? 'bg-green-500' : 'bg-red-500' ?> text-white px-3 py-1 rounded font-bold">
                           <?= $result['is_valid'] ? '署名は有効です' : '署名は無効です' ?>
                       </span>
                   </div>
                   <div><strong>検証対象データ:</strong> <span class="bg-white bg-opacity-20 px-2 py-1 rounded"><?= htmlspecialchars($result['data']) ?></span></div>
                   <div><strong>データのハッシュ値:</strong></div>
                   <div class="bg-white bg-opacity-20 p-2 rounded font-mono text-sm"><?= $result['hash'] ?></div>
               </div>
           <?php elseif ($result['type'] === 'hybrid_encrypt'): ?>
               <div class="space-y-3">
                   <div><strong>ハイブリッド暗号化完了</strong> (<?= $result['method'] ?>)</div>
                   <div><strong>元の平文:</strong> <span class="bg-white bg-opacity-20 px-2 py-1 rounded"><?= htmlspecialchars($result['original_text']) ?></span></div>
                   <div><strong>AESで暗号化されたデータ:</strong></div>
                   <div class="bg-white bg-opacity-20 p-2 rounded font-mono text-sm break-all"><?= htmlspecialchars($result['encrypted_data']) ?></div>
                   <div><strong>RSAで暗号化されたAES鍵:</strong></div>
                   <div class="bg-white bg-opacity-20 p-2 rounded font-mono text-sm break-all"><?= htmlspecialchars($result['encrypted_key']) ?></div>
               </div>
           <?php elseif ($result['type'] === 'hybrid_decrypt'): ?>
               <div class="space-y-3">
                   <div><strong>ハイブリッド復号成功!</strong></div>
                   <div><strong>復号されたテキスト:</strong> <span class="bg-white bg-opacity-20 px-2 py-1 rounded"><?= htmlspecialchars($result['decrypted_text']) ?></span></div>
               </div>
           <?php endif; ?>
       </div>
   <?php endif; ?>

   <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
       <!-- 共通鍵暗号 -->
       <div class="bg-white rounded-lg shadow-lg p-6 crypto-section symmetric">
           <h2 class="text-2xl font-bold text-blue-600 mb-4">1. 共通鍵暗号 (AES-256-CBC)</h2>
           
           <div class="explanation-box">
               <h4 class="font-bold text-blue-800 mb-2">仕組みの説明</h4>
               <p class="text-sm text-blue-700">
                   暗号化と復号に同じ鍵を使用します。高速で大量のデータ処理に適していますが、
                   鍵の安全な共有が課題です。AES-256は256ビットの鍵を使用する強力な暗号方式です。
               </p>
           </div>
           
           <div class="space-y-6">
               <div>
                   <div class="step-indicator">
                       <div class="step-number">1</div>
                       <h3 class="text-lg font-semibold">暗号化</h3>
                   </div>
                   <form method="POST" class="space-y-4">
                       <input type="hidden" name="action" value="symmetric_encrypt">
                       <div>
                           <label class="block text-sm font-medium text-gray-700 mb-1">平文</label>
                           <textarea name="plaintext" rows="3" class="w-full border rounded-lg p-3" 
                                     placeholder="暗号化したいテキストを入力してください">この文書は機密情報です。AES-256で暗号化してください。</textarea>
                       </div>
                       <div>
                           <label class="block text-sm font-medium text-gray-700 mb-1">パスワード（共通鍵の元）</label>
                           <input type="text" name="password" class="w-full border rounded-lg p-3" 
                                  placeholder="共通鍵として使用するパスワード" value="MySecretPassword123">
                       </div>
                       <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700">
                           AES-256で暗号化
                       </button>
                   </form>
               </div>
               
               <div>
                   <div class="step-indicator">
                       <div class="step-number">2</div>
                       <h3 class="text-lg font-semibold">復号</h3>
                   </div>
                   <form method="POST" class="space-y-4">
                       <input type="hidden" name="action" value="symmetric_decrypt">
                       <div>
                           <label class="block text-sm font-medium text-gray-700 mb-1">暗号化データ</label>
                           <textarea name="encrypted_data" rows="3" class="w-full border rounded-lg p-3" 
                                     placeholder="上記で生成された暗号化データを貼り付けてください"></textarea>
                       </div>
                       <div>
                           <label class="block text-sm font-medium text-gray-700 mb-1">パスワード</label>
                           <input type="text" name="decrypt_password" class="w-full border rounded-lg p-3" 
                                  placeholder="暗号化時と同じパスワード">
                       </div>
                       <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600">
                           AES-256で復号
                       </button>
                   </form>
               </div>
           </div>
       </div>

       <!-- 公開鍵暗号 -->
       <div class="bg-white rounded-lg shadow-lg p-6 crypto-section asymmetric">
           <h2 class="text-2xl font-bold text-red-600 mb-4">2. 公開鍵暗号 (RSA-2048)</h2>
           
           <div class="explanation-box">
               <h4 class="font-bold text-red-800 mb-2">仕組みの説明</h4>
               <p class="text-sm text-red-700">
                   公開鍵と秘密鍵のペアを使用します。公開鍵で暗号化したデータは対応する秘密鍵でのみ復号可能です。
                   鍵の配送問題を解決しますが、共通鍵暗号より処理が重く、長いデータの暗号化には不向きです。
               </p>
           </div>
           
           <div class="space-y-6">
               <div>
                   <div class="step-indicator">
                       <div class="step-number">1</div>
                       <h3 class="text-lg font-semibold">RSA鍵ペア生成</h3>
                   </div>
                   <form method="POST" class="space-y-4">
                       <input type="hidden" name="action" value="generate_rsa_keys">
                       <div>
                           <label class="block text-sm font-medium text-gray-700 mb-1">鍵長</label>
                           <select name="key_size" class="w-full border rounded-lg p-3">
                               <option value="1024">1024ビット（学習用）</option>
                               <option value="2048" selected>2048ビット（推奨）</option>
                               <option value="4096">4096ビット（高セキュリティ）</option>
                           </select>
                       </div>
                       <button type="submit" class="w-full bg-red-600 text-white py-2 rounded-lg hover:bg-red-700">
                           RSA鍵ペアを生成
                       </button>
                   </form>
               </div>
               
               <div>
                   <div class="step-indicator">
                       <div class="step-number">2</div>
                       <h3 class="text-lg font-semibold">公開鍵で暗号化</h3>
                   </div>
                   <form method="POST" class="space-y-4">
                       <input type="hidden" name="action" value="rsa_encrypt">
                       <div>
                           <label class="block text-sm font-medium text-gray-700 mb-1">平文</label>
                           <textarea name="rsa_plaintext" rows="2" class="w-full border rounded-lg p-3" 
                                     placeholder="暗号化したいテキスト（短いメッセージ）">重要な契約書です</textarea>
                       </div>
                       <div>
                           <label class="block text-sm font-medium text-gray-700 mb-1">公開鍵</label>
                           <textarea name="public_key" rows="8" class="w-full border rounded-lg p-3 text-xs" 
                                     placeholder="上記で生成された公開鍵を貼り付けてください"></textarea>
                       </div>
                       <button type="submit" class="w-full bg-red-500 text-white py-2 rounded-lg hover:bg-red-600">
                           RSAで暗号化
                       </button>
                   </form>
               </div>
               
               <div>
                   <div class="step-indicator">
                       <div class="step-number">3</div>
                       <h3 class="text-lg font-semibold">秘密鍵で復号</h3>
                   </div>
                   <form method="POST" class="space-y-4">
                       <input type="hidden" name="action" value="rsa_decrypt">
                       <div>
                           <label class="block text-sm font-medium text-gray-700 mb-1">暗号化データ</label>
                           <textarea name="rsa_encrypted_data" rows="3" class="w-full border rounded-lg p-3 text-xs" 
                                     placeholder="上記で生成された暗号化データを貼り付けてください"></textarea>
                       </div>
                       <div>
                           <label class="block text-sm font-medium text-gray-700 mb-1">秘密鍵</label>
                           <textarea name="private_key" rows="8" class="w-full border rounded-lg p-3 text-xs" 
                                     placeholder="対応する秘密鍵を貼り付けてください"></textarea>
                       </div>
                       <button type="submit" class="w-full bg-red-400 text-white py-2 rounded-lg hover:bg-red-500">
                           RSAで復号
                       </button>
                   </form>
               </div>
           </div>
       </div>

       <!-- ハイブリッド暗号 -->
       <div class="bg-white rounded-lg shadow-lg p-6 crypto-section hybrid">
           <h2 class="text-2xl font-bold text-green-600 mb-4">3. ハイブリッド暗号 (RSA + AES)</h2>
           
           <div class="explanation-box">
               <h4 class="font-bold text-green-800 mb-2">仕組みの説明</h4>
               <p class="text-sm text-green-700">
                   公開鍵暗号と共通鍵暗号の利点を組み合わせます。まずランダムなAES鍵でデータを暗号化し、
                   そのAES鍵をRSAで暗号化します。これにより高速性とセキュリティを両立できます。
               </p>
           </div>
           
           <div class="space-y-6">
               <div>
                   <div class="step-indicator">
                       <div class="step-number">1</div>
                       <h3 class="text-lg font-semibold">ハイブリッド暗号化</h3>
                   </div>
                   <form method="POST" class="space-y-4">
                       <input type="hidden" name="action" value="hybrid_encrypt">
                       <div>
                           <label class="block text-sm font-medium text-gray-700 mb-1">平文</label>
                           <textarea name="hybrid_plaintext" rows="4" class="w-full border rounded-lg p-3" 
                                     placeholder="長いテキストでも高速に暗号化できます">これは非常に機密性の高い長い文書です。ハイブリッド暗号方式を使用することで、RSAの安全性とAESの高速性を両立できます。大量のデータでも効率的に暗号化が可能です。従来の公開鍵暗号では処理が重くなる問題を解決しています。</textarea>
                       </div>
                       <div>
                           <label class="block text-sm font-medium text-gray-700 mb-1">公開鍵（受信者）</label>
                           <textarea name="hybrid_public_key" rows="8" class="w-full border rounded-lg p-3 text-xs" 
                                     placeholder="受信者の公開鍵を貼り付けてください"></textarea>
                       </div>
                       <button type="submit" class="w-full bg-green-600 text-white py-2 rounded-lg hover:bg-green-700">
                           ハイブリッド暗号化
                       </button>
                   </form>
               </div>
               
               <div>
                   <div class="step-indicator">
                       <div class="step-number">2</div>
                       <h3 class="text-lg font-semibold">ハイブリッド復号</h3>
                   </div>
                   <form method="POST" class="space-y-4">
                       <input type="hidden" name="action" value="hybrid_decrypt">
                       <div>
                           <label class="block text-sm font-medium text-gray-700 mb-1">暗号化データ（AES）</label>
                           <textarea name="hybrid_encrypted_data" rows="3" class="w-full border rounded-lg p-3 text-xs" 
                                     placeholder="AESで暗号化されたデータ"></textarea>
                       </div>
                       <div>
                           <label class="block text-sm font-medium text-gray-700 mb-1">暗号化鍵（RSA）</label>
                           <textarea name="hybrid_encrypted_key" rows="3" class="w-full border rounded-lg p-3 text-xs" 
                                     placeholder="RSAで暗号化されたAES鍵"></textarea>
                       </div>
                       <div>
                           <label class="block text-sm font-medium text-gray-700 mb-1">秘密鍵</label>
                           <textarea name="hybrid_private_key" rows="8" class="w-full border rounded-lg p-3 text-xs" 
                                     placeholder="あなたの秘密鍵を貼り付けてください"></textarea>
                       </div>
                       <button type="submit" class="w-full bg-green-500 text-white py-2 rounded-lg hover:bg-green-600">
                           ハイブリッド復号
                       </button>
                   </form>
               </div>
           </div>
       </div>

       <!-- デジタル署名 -->
       <div class="bg-white rounded-lg shadow-lg p-6 crypto-section signature">
           <h2 class="text-2xl font-bold text-yellow-600 mb-4">4. デジタル署名 (RSA + SHA-256)</h2>
           
           <div class="explanation-box">
               <h4 class="font-bold text-yellow-800 mb-2">仕組みの説明</h4>
               <p class="text-sm text-yellow-700">
                   データの完全性と認証を提供します。送信者が秘密鍵でデータのハッシュ値に署名し、
                   受信者が公開鍵で署名を検証することで、データの改ざんや送信者のなりすましを検出できます。
               </p>
           </div>
           
           <div class="space-y-6">
               <div>
                   <div class="step-indicator">
                       <div class="step-number">1</div>
                       <h3 class="text-lg font-semibold">デジタル署名の作成</h3>
                   </div>
                   <form method="POST" class="space-y-4">
                       <input type="hidden" name="action" value="create_signature">
                       <div>
                           <label class="block text-sm font-medium text-gray-700 mb-1">署名対象データ</label>
                           <textarea name="signature_data" rows="3" class="w-full border rounded-lg p-3" 
                                     placeholder="署名したい文書やメッセージ">この契約書の内容に同意します。2024年12月1日 田中太郎</textarea>
                       </div>
                       <div>
                           <label class="block text-sm font-medium text-gray-700 mb-1">秘密鍵（署名者）</label>
                           <textarea name="signature_private_key" rows="8" class="w-full border rounded-lg p-3 text-xs" 
                                     placeholder="署名者の秘密鍵を貼り付けてください"></textarea>
                       </div>
                       <button type="submit" class="w-full bg-yellow-600 text-white py-2 rounded-lg hover:bg-yellow-700">
                           デジタル署名を作成
                       </button>
                   </form>
               </div>
               
               <div>
                   <div class="step-indicator">
                       <div class="step-number">2</div>
                       <h3 class="text-lg font-semibold">デジタル署名の検証</h3>
                   </div>
                   <form method="POST" class="space-y-4">
                       <input type="hidden" name="action" value="verify_signature">
                       <div>
                           <label class="block text-sm font-medium text-gray-700 mb-1">検証対象データ</label>
                           <textarea name="verify_data" rows="3" class="w-full border rounded-lg p-3" 
                                     placeholder="署名時と同じデータを入力してください"></textarea>
                       </div>
                       <div>
                           <label class="block text-sm font-medium text-gray-700 mb-1">デジタル署名</label>
                           <textarea name="signature" rows="3" class="w-full border rounded-lg p-3 text-xs" 
                                     placeholder="検証したいデジタル署名を貼り付けてください"></textarea>
                       </div>
                       <div>
                           <label class="block text-sm font-medium text-gray-700 mb-1">公開鍵（署名者）</label>
                           <textarea name="verify_public_key" rows="8" class="w-full border rounded-lg p-3 text-xs" 
                                     placeholder="署名者の公開鍵を貼り付けてください"></textarea>
                       </div>
                       <button type="submit" class="w-full bg-yellow-500 text-white py-2 rounded-lg hover:bg-yellow-600">
                           デジタル署名を検証
                       </button>
                   </form>
               </div>
           </div>
       </div>
   </div>

   <!-- 保存されている鍵ファイル一覧 -->
   <?php if (!empty($saved_keys)): ?>
   <div class="mt-8 bg-white rounded-lg shadow-lg p-6">
       <h2 class="text-2xl font-bold text-gray-800 mb-4">保存されている鍵ファイル</h2>
       <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
           <?php foreach ($saved_keys as $key): ?>
           <div class="border rounded-lg p-4 <?= $key['type'] === 'private' ? 'border-red-300 bg-red-50' : 'border-green-300 bg-green-50' ?>">
               <div class="flex items-center justify-between mb-2">
                   <h4 class="font-bold <?= $key['type'] === 'private' ? 'text-red-700' : 'text-green-700' ?>">
                       <?= $key['type'] === 'private' ? '秘密鍵' : '公開鍵' ?>: <?= htmlspecialchars($key['filename']) ?>
                   </h4>
                   <span class="text-sm text-gray-500"><?= $key['size'] ?> bytes</span>
               </div>
               <div class="key-display"><?= htmlspecialchars($key['content']) ?></div>
               <button onclick="copyToClipboard('<?= htmlspecialchars(addslashes($key['content'])) ?>')" 
                       class="mt-2 text-sm <?= $key['type'] === 'private' ? 'text-red-600 hover:text-red-800' : 'text-green-600 hover:text-green-800' ?> font-medium">
                   クリップボードにコピー
               </button>
           </div>
           <?php endforeach; ?>
       </div>
   </div>
   <?php endif; ?>

   <!-- 学習ガイド -->
   <div class="mt-8 bg-gradient-to-r from-blue-50 to-purple-50 rounded-lg p-6">
       <h2 class="text-2xl font-bold text-gray-800 mb-4">学習ガイド</h2>
       
       <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
           <div>
               <h3 class="text-lg font-bold text-blue-700 mb-3">推奨学習順序</h3>
               <ol class="list-decimal list-inside space-y-2 text-sm">
                   <li><strong>共通鍵暗号</strong>から始めて基本的な暗号化を理解</li>
                   <li><strong>公開鍵暗号</strong>で鍵ペアの概念を学習</li>
                   <li><strong>デジタル署名</strong>で認証の仕組みを体験</li>
                   <li><strong>ハイブリッド暗号</strong>で実用的な組み合わせを理解</li>
               </ol>
           </div>
           
           <div>
               <h3 class="text-lg font-bold text-purple-700 mb-3">実践のポイント</h3>
               <ul class="list-disc list-inside space-y-2 text-sm">
                   <li>生成された鍵は自動的にファイル保存されます</li>
                   <li>各暗号化結果をコピーして次のステップで使用</li>
                   <li>デジタル署名では data の完全一致が重要</li>
                   <li>ハイブリッド暗号では両方のデータが必要</li>
               </ul>
           </div>
       </div>
       
       <div class="mt-6 p-4 bg-yellow-100 rounded-lg">
           <h4 class="font-bold text-yellow-800 mb-2">セキュリティ注意事項</h4>
           <p class="text-sm text-yellow-700">
               このシステムは教育目的です。実際の運用では、鍵の安全な管理、適切な鍵長の選択、
               信頼できる認証局の使用など、より厳格なセキュリティ対策が必要です。
           </p>
       </div>
   </div>
</div>

<script>
function copyToClipboard(text) {
   navigator.clipboard.writeText(text).then(() => {
       alert('クリップボードにコピーしました');
   }).catch(() => {
       // フォールバック
       const textArea = document.createElement('textarea');
       textArea.value = text;
       document.body.appendChild(textArea);
       textArea.select();
       document.execCommand('copy');
       document.body.removeChild(textArea);
       alert('クリップボードにコピーしました');
   });
}

// フォーム間でのデータ自動コピー機能
document.addEventListener('DOMContentLoaded', function() {
   // 結果が表示された後の自動フィル機能
   const result = <?= json_encode($result ?: []) ?>;
   
   if (result.type === 'generate_rsa_keys') {
       // 公開鍵を暗号化フォームに自動セット
       const publicKeyFields = document.querySelectorAll('textarea[name="public_key"], textarea[name="hybrid_public_key"], textarea[name="verify_public_key"]');
       publicKeyFields.forEach(field => {
           if (!field.value) {
               field.value = result.public_key;
           }
       });
       
       // 秘密鍵を復号・署名フォームに自動セット
       const privateKeyFields = document.querySelectorAll('textarea[name="private_key"], textarea[name="hybrid_private_key"], textarea[name="signature_private_key"]');
       privateKeyFields.forEach(field => {
           if (!field.value) {
               field.value = result.private_key;
           }
       });
   }
   
   if (result.type === 'symmetric_encrypt') {
       // 暗号化結果を復号フォームに自動セット
       const decryptField = document.querySelector('textarea[name="encrypted_data"]');
       if (decryptField && !decryptField.value) {
           decryptField.value = result.encrypted;
       }
       const passwordField = document.querySelector('input[name="decrypt_password"]');
       if (passwordField && !passwordField.value) {
           passwordField.value = result.password;
       }
   }
   
   if (result.type === 'rsa_encrypt') {
       // RSA暗号化結果を復号フォームに自動セット
       const decryptField = document.querySelector('textarea[name="rsa_encrypted_data"]');
       if (decryptField && !decryptField.value) {
           decryptField.value = result.encrypted;
       }
   }
   
   if (result.type === 'create_signature') {
       // 署名結果を検証フォームに自動セット
       const verifyDataField = document.querySelector('textarea[name="verify_data"]');
       if (verifyDataField && !verifyDataField.value) {
           verifyDataField.value = result.original_data;
       }
       const signatureField = document.querySelector('textarea[name="signature"]');
       if (signatureField && !signatureField.value) {
           signatureField.value = result.signature;
       }
   }
   
   if (result.type === 'hybrid_encrypt') {
       // ハイブリッド暗号化結果を復号フォームに自動セット
       const dataField = document.querySelector('textarea[name="hybrid_encrypted_data"]');
       if (dataField && !dataField.value) {
           dataField.value = result.encrypted_data;
       }
       const keyField = document.querySelector('textarea[name="hybrid_encrypted_key"]');
       if (keyField && !keyField.value) {
           keyField.value = result.encrypted_key;
       }
   }
});

// 結果ボックスのスクロール
if (document.querySelector('.result-box')) {
   document.querySelector('.result-box').scrollIntoView({ 
       behavior: 'smooth', 
       block: 'center' 
   });
}
</script>

</body>
</html>