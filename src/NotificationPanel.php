<?php
session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/GetAccessToken.php';

// Şifre kontrolü
$correctPassword = '1594872630Ai';

// Çıkış işlemi
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Giriş kontrolü
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === $correctPassword) {
        $_SESSION['authenticated'] = true;
    } else {
        $loginError = true;
    }
}

// Kimlik doğrulaması yapılmamışsa giriş formu göster
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Giriş - Bildirim Paneli</title>
        <style>
            *{margin:0;padding:0;box-sizing:border-box}
            body{font-family:'Segoe UI',sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
            .login-container{background:white;border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,0.3);padding:40px;max-width:400px;width:100%}
            .login-header{text-align:center;margin-bottom:30px}
            .login-header h1{color:#667eea;margin-bottom:10px}
            input[type=password]{width:100%;padding:15px;border:2px solid #e0e0e0;border-radius:10px;margin-bottom:20px}
            .btn-login{width:100%;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;border:none;padding:15px;font-weight:600;border-radius:10px;cursor:pointer}
            .error-message{background:#f8d7da;border:2px solid #f5c6cb;color:#721c24;padding:12px;border-radius:8px;margin-bottom:20px;text-align:center}
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <div style="font-size:3em">🔒</div>
                <h1>Güvenli Giriş</h1>
                <p>Bildirim Yönetim Paneli</p>
            </div>

            <?php if (isset($loginError)): ?>
                <div class="error-message">❌ Hatalı şifre! Lütfen tekrar deneyin.</div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="password" name="password" placeholder="Şifrenizi girin..." required autofocus>
                <button type="submit" class="btn-login">🔓 Giriş Yap</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Kimlik doğrulaması yapılmış kullanıcılar için
$serviceAccountKeyPath = __DIR__ . '/php-jwt-main/ruya-tabiri-79c05-ff951a78ed22.json';
$accessToken = getAccessToken($serviceAccountKeyPath);
$fcmUrl = "https://fcm.googleapis.com/v1/projects/ruya-tabiri-79c05/messages:send";

// Hazır şablonlar
$templates = [
    ['title' => 'Hoş Geldiniz', 'body' => 'Uygulamamıza hoş geldiniz! Size en iyi deneyimi sunmak için buradayız.'],
    ['title' => 'Yeni İçerik', 'body' => 'Sizin için yeni içerikler hazırladık. Hemen göz atın!'],
    ['title' => 'Günlük Hatırlatma', 'body' => 'Bugün rüyanızı yorumlamayı unutmayın!'],
    ['title' => 'Özel Teklif', 'body' => 'Sadece bugün geçerli özel fırsatları kaçırmayın!'],
];

// Bildirim gönderme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['password'])) {
    $selectedUsers = $_POST['selected_users'] ?? [];
    $notificationTitle = $_POST['notification_title'] ?? '';
    $notificationBody = $_POST['notification_body'] ?? '';
    
    if (empty($selectedUsers)) {
        $message = "<div class='alert alert-error'>Lütfen en az bir kullanıcı seçin.</div>";
    } elseif (empty($notificationTitle) || empty($notificationBody)) {
        $message = "<div class='alert alert-error'>Başlık ve içerik boş olamaz.</div>";
    } else {
        $successCount = 0;
        $failCount = 0;
        $results = [];
        
        foreach ($selectedUsers as $userId) {
            $stmt = $conn->prepare("SELECT dt.device_token, u.name FROM device_tokens dt JOIN users u ON dt.user_id = u.id WHERE u.id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                $deviceToken = $row['device_token'];
                $userName = $row['name'];

                $notification = [
                    'message' => [
                        'token' => $deviceToken,
                        'notification' => [
                            'title' => $notificationTitle,
                            'body' => $notificationBody,
                        ],
                        'data' => [
                            'extra_info' => 'Yönetici tarafından gönderildi',
                        ],
                    ],
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $fcmUrl);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer $accessToken",
                    "Content-Type: application/json",
                ]);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notification));

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                if ($httpCode == 200) {
                    $successCount++;
                    $results[] = "✓ $userName";
                } else {
                    $failCount++;
                    $results[] = "✗ $userName";

                    $logFile = __DIR__ . '/fcm_error_log.txt';
                    $logEntry = sprintf(
                        "[%s] Kullanıcı: %s | HTTP: %s | CURL: %s | Yanıt: %s\n",
                        date('Y-m-d H:i:s'),
                        $userName,
                        $httpCode,
                        $curlError ?: 'Yok',
                        trim($response)
                    );
                    file_put_contents($logFile, $logEntry, FILE_APPEND);
                }
            }
            $stmt->close();
        }
        
        $message = "<div class='alert alert-success'>
                        <strong>Gönderim Tamamlandı!</strong><br>
                        Başarılı: $successCount | Başarısız: $failCount<br>
                        <small>" . implode(', ', $results) . "</small>
                     </div>";
    }
}

// Kullanıcıları çek (kayıtlı ve misafir)
$sql = "SELECT u.id, u.name, u.email, dt.device_token 
        FROM users u 
        LEFT JOIN device_tokens dt ON u.id = dt.user_id 
        WHERE dt.device_token IS NOT NULL
        ORDER BY u.name";
$result = $conn->query($sql);
$registeredUsers = [];
$guestUsers = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        if ($row['name'] === 'Misafir') {
            $guestUsers[] = $row;
        } else {
            $registeredUsers[] = $row;
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gelişmiş Bildirim Paneli</title>
    <style>
        body{font-family:'Segoe UI',sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;padding:20px}
        .container{max-width:1200px;margin:0 auto;background:white;border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,0.3);overflow:hidden}
        .header{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;padding:30px;text-align:center;position:relative}
        .logout-btn{position:absolute;top:20px;right:20px;background:rgba(255,255,255,0.2);color:white;border:2px solid white;padding:8px 20px;border-radius:20px;text-decoration:none;font-weight:600}
        .logout-btn:hover{background:white;color:#667eea}
        .content{padding:30px}
        .alert{padding:15px 20px;border-radius:10px;margin-bottom:20px}
        .alert-success{background:#d4edda;border:2px solid #c3e6cb;color:#155724}
        .alert-error{background:#f8d7da;border:2px solid #f5c6cb;color:#721c24}
        .templates{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:15px;margin-bottom:20px}
        .template-card{background:#f8f9ff;border:2px solid #e0e7ff;border-radius:10px;padding:15px;cursor:pointer;transition:.3s}
        .template-card:hover{transform:translateY(-3px);box-shadow:0 5px 15px rgba(102,126,234,0.2);border-color:#667eea}
        .users-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:10px;max-height:400px;overflow-y:auto;padding:10px;background:#f9f9f9;border-radius:10px}
        .user-card{background:white;border:2px solid #e0e0e0;border-radius:8px;padding:12px;display:flex;align-items:center}
        .user-card:hover{border-color:#667eea;box-shadow:0 2px 8px rgba(102,126,234,0.1)}
        .btn{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;border:none;padding:15px 40px;font-size:1.1em;font-weight:600;border-radius:10px;cursor:pointer;width:100%}
        .btn:hover{transform:translateY(-2px);box-shadow:0 10px 25px rgba(102,126,234,0.4)}
        .select-all{background:#f0f0f0;padding:12px;border-radius:8px;margin-bottom:15px;display:flex;align-items:center;font-weight:600}
        input,textarea{width:100%;padding:15px;border:2px solid #e0e0e0;border-radius:10px;margin-bottom:20px;font-size:1em}
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="?logout" class="logout-btn" onclick="return confirm('Çıkış yapmak istiyor musun?')">🚪 Çıkış</a>
            <h1>📱 Bildirim Yönetim Paneli</h1>
            <p>Kullanıcılara hızlı ve kolay bildirim gönderin</p>
        </div>

        <div class="content">
            <?php if (isset($message)) echo $message; ?>

            <form method="POST" action="" id="notificationForm">
                <h2>📝 Hazır Şablonlar</h2>
                <div class="templates">
                    <?php foreach ($templates as $template): ?>
                        <div class="template-card" onclick="applyTemplate('<?= htmlspecialchars($template['title']) ?>','<?= htmlspecialchars($template['body']) ?>')">
                            <h4><?= htmlspecialchars($template['title']) ?></h4>
                            <p><?= htmlspecialchars($template['body']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h2>👥 Kayıtlı Kullanıcılar (<?= count($registeredUsers) ?> kişi)</h2>
                <div class="select-all">
                    <input type="checkbox" id="select_all_registered"> <label for="select_all_registered">Tümünü Seç</label>
                </div>
                <div class="users-grid">
                    <?php foreach ($registeredUsers as $user): ?>
                        <div class="user-card">
                            <input type="checkbox" name="selected_users[]" value="<?= $user['id'] ?>" class="user-checkbox registered">
                            <div class="user-info">
                                <div><strong><?= htmlspecialchars($user['name']) ?></strong></div>
                                <div style="font-size:.85em;color:#888"><?= htmlspecialchars($user['email']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h2>👥 Misafir Kullanıcılar (<?= count($guestUsers) ?> kişi)</h2>
                <div class="select-all">
                    <input type="checkbox" id="select_all_guests"> <label for="select_all_guests">Tümünü Seç</label>
                </div>
                <div class="users-grid">
                    <?php foreach ($guestUsers as $user): ?>
                        <div class="user-card">
                            <input type="checkbox" name="selected_users[]" value="<?= $user['id'] ?>" class="user-checkbox guest">
                            <div class="user-info">
                                <div><strong><?= htmlspecialchars($user['name']) ?></strong></div>
                                <div style="font-size:.85em;color:#888"><?= htmlspecialchars($user['email']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <p style="text-align:center;color:#667eea;font-weight:600;margin-top:10px">
                    Seçili: <span id="selected-count">0</span> kullanıcı
                </p>

                <h2>✉️ Bildirim İçeriği</h2>
                <input type="text" id="notification_title" name="notification_title" placeholder="Bildirim başlığı..." required>
                <textarea id="notification_body" name="notification_body" placeholder="Bildirim içeriğini girin..." required></textarea>
                <button type="submit" class="btn">🚀 Bildirimi Gönder</button>
            </form>
        </div>
    </div>

    <script>
        // Tümünü seç işlevleri
        document.getElementById('select_all_registered').addEventListener('change', function(){
            document.querySelectorAll('.user-checkbox.registered').forEach(cb => cb.checked = this.checked);
            updateSelectedCount();
        });
        document.getElementById('select_all_guests').addEventListener('change', function(){
            document.querySelectorAll('.user-checkbox.guest').forEach(cb => cb.checked = this.checked);
            updateSelectedCount();
        });
        document.querySelectorAll('.user-checkbox').forEach(cb => cb.addEventListener('change', updateSelectedCount));

        function updateSelectedCount(){
            const c = document.querySelectorAll('.user-checkbox:checked').length;
            document.getElementById('selected-count').textContent = c;
        }

        // Şablon uygula
        function applyTemplate(t,b){
            document.getElementById('notification_title').value=t;
            document.getElementById('notification_body').value=b;
        }

        // Gönderim onayı
        document.getElementById('notificationForm').addEventListener('submit',function(e){
            const c=document.querySelectorAll('.user-checkbox:checked').length;
            if(c>0 && !confirm(c+" kullanıcıya bildirim gönderilecek. Onaylıyor musun?")) e.preventDefault();
        });
    </script>
</body>
</html>
