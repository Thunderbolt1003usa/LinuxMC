<?php
require '/var/www/secret/db.php';
require '/var/www/id.linuxmc.tech/totp.php'; // TOTP Helper laden

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '.linuxmc.tech',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$userId = $_SESSION['user_id'] ?? null;
$authMethod = 'session';

// API Key Authentifizierung prüfen (für externe Apps)
if (!$userId) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(\w+)/', $authHeader, $matches)) {
        $apiKey = $matches[1];
        $stmt = $pdo->prepare("SELECT id FROM users WHERE api_key = ?");
        $stmt->execute([$apiKey]);
        $row = $stmt->fetch();
        if ($row) {
            $userId = $row['id'];
            $authMethod = 'apikey';
        }
    }
}

require '/var/www/id.linuxmc.tech/audit_logger.php'; // Audit Logger laden

// === PUBLIC ENDPOINTS (No Login Required) ===
if ($action === 'verify_sso_token') {
    $token = $_GET['token'] ?? '';
    if(!$token) {
        http_response_code(400);
        exit(json_encode(['error' => 'No Token']));
    }

    // Token und User suchen
    $stmt = $pdo->prepare("SELECT auth_tokens.user_id, users.username, users.realname, users.avatar, users.bio, users.country, users.created_at FROM auth_tokens JOIN users ON auth_tokens.user_id = users.id WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Token löschen (Einweg-Ticket!)
        $del = $pdo->prepare("DELETE FROM auth_tokens WHERE token = ?");
        $del->execute([$token]);

        $avatarMap = [
            'default' => 'https://id.linuxmc.tech/default.png',
            'tux' => 'https://id.linuxmc.tech/tux.png',
            'steve' => 'https://id.linuxmc.tech/steve.jpg',
            'modem88' => 'https://id.linuxmc.tech/modem88.png'
        ];
        $user['avatar_url'] = $avatarMap[$user['avatar'] ?? 'default'] ?? $avatarMap['default'];

        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        http_response_code(400); // Bad Request (Invalid Token)
        echo json_encode(['error' => 'Token ungültig oder abgelaufen']);
    }
    exit;
}

// Globaler Sicherheits-Check: User muss gefunden sein (für alle anderen Actions)
if (!$userId) {
    http_response_code(403);
    echo json_encode(['error' => 'Nicht autorisiert']);
    exit;
}
if ($action === 'get_user_info') {
    // Hole öffentliche Profil-Daten
    $stmt = $pdo->prepare("SELECT id, username, realname, avatar, bio, country, created_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Avatar URL auflösen
    $avatarMap = [
        'default' => 'https://id.linuxmc.tech/default.png',
        'tux' => 'https://id.linuxmc.tech/tux.png', // Tux fehlt, Fallback auf Default
        'steve' => 'https://id.linuxmc.tech/steve.jpg',   // Achtung: .jpg Endung!
        'modem88' => 'https://id.linuxmc.tech/modem88.png'
    ];
    $user['avatar_url'] = $avatarMap[$user['avatar'] ?? 'default'] ?? $avatarMap['default'];

    echo json_encode(['success' => true, 'user' => $user]);
    exit;
}


// === SESSION ONLY ACTIONS ===
// Die folgenden Aktionen erlauben wir vorerst nur über das Browser-Dashboard (Session)
if ($authMethod !== 'session') {
    http_response_code(403);
    echo json_encode(['error' => 'Nur über Web-Dashboard erlaubt']);
    exit;
}

if ($action === 'delete_account') { 
    $input = json_decode(file_get_contents('php://input'), true);
    $password = $input['password'] ?? '';

    if (!$password) {
        echo json_encode(['error' => 'Passwort fehlt']);
        exit;
    }

    // 1. Check Passowrd
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $hash = $stmt->fetchColumn();

    if (!$hash || !password_verify($password, $hash)) {
        http_response_code(403);
        echo json_encode(['error' => 'Falsches Passwort']);
        exit;
    }

    // 2. Delete Everything
    try {
        $pdo->beginTransaction();

        $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$userId]);
        $pdo->prepare("DELETE FROM audit_logs WHERE user_id = ?")->execute([$userId]);
        $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ?")->execute([$userId]);
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);

        $pdo->commit();

        session_destroy();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'DB Fehler: ' . $e->getMessage()]);
    }
    exit;
}

// if ($action === 'delete_account') block ends above.
// Now we continue to other actions.



// --- SESSION MANAGEMENT ---
if ($action === 'revoke_session') {
    $sid = $_GET['id'] ?? '';
    // Nur eigene Sessions löschen
    $chk = $pdo->prepare("SELECT id FROM user_sessions WHERE session_id = ? AND user_id = ?");
    $chk->execute([$sid, $_SESSION['user_id']]);
    if ($chk->fetch()) {
        $del = $pdo->prepare("DELETE FROM user_sessions WHERE session_id = ?");
        $del->execute([$sid]);
        // Wenn es die aktuelle Session war, zerstören wir sie serverseitig
        if($sid === session_id()) {
            session_destroy();
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Sitzung nicht gefunden.']);
    }
    exit;
}

if ($action === 'revoke_all_sessions') {
    $current = session_id();
    // Alle außer aktuelle löschen
    $del = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ? AND session_id != ?");
    $del->execute([$_SESSION['user_id'], $current]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'geoip') {
    $ip = $_GET['ip'] ?? '';
    // Validierung: Nur IP
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        echo json_encode(['error' => 'Invalid IP']); 
        exit;
    }
    // Rate Limit/Caching wäre hier gut, aber für Prototyp:
    // API Call zu ip-api.com
    $url = "http://ip-api.com/json/" . $ip;
    
    // cURL nutzen um HTTPS Probleme zu vermeiden (Server zu Server ist ok)
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        echo json_encode(['success' => true, 'city' => $data['city'] ?? 'Unknown', 'country' => $data['country'] ?? 'Unknown']);
    } else {
        echo json_encode(['error' => 'API Error']);
    }
    exit;
}
// --------------------------

// CSRF Check (Nur Pflicht für modifizierende Aktionen)
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $clientToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($clientToken) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $clientToken)) {
         http_response_code(403);
         echo json_encode(['error' => 'CSRF Token Invalid or Missing']);
         exit;
    }
}

if ($action === 'generate_key') {
    try {
        // Kryptografisch sicheren Key erzeugen (64 Zeichen Hex)
        $newKey = bin2hex(random_bytes(32)); 
        
        // In Datenbank speichern
        $stmt = $pdo->prepare("UPDATE users SET api_key = ?, api_key_created_at = NOW() WHERE id = ?");
        $stmt->execute([$newKey, $_SESSION['user_id']]);
        
        log_audit($_SESSION['user_id'], 'API_KEY_GENERATED');
        echo json_encode(['success' => true, 'new_key' => $newKey]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Datenbankfehler']);
    }
    exit;
}

if ($action === 'update_profile') {
    require_once '/var/www/id.linuxmc.tech/includes/mailer.php';

    $input = file_get_contents('php://input');
    $d = json_decode($input, true);
    
    // Daten bereinigen
    $realname = trim($d['realname'] ?? '');
    $phone    = trim($d['phone'] ?? '');
    $email    = trim($d['email'] ?? '');
    $address  = trim($d['address'] ?? '');
    $zip      = trim($d['zip'] ?? '');
    $city     = trim($d['city'] ?? '');
    $country  = trim($d['country'] ?? '');
    $bio      = trim($d['bio'] ?? '');
    $avatar   = $d['avatar'] ?? 'default';

    // Validierung (Basis)
    if(strlen($realname) < 2) exit(json_encode(['error' => 'Name zu kurz']));
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)) exit(json_encode(['error' => 'Ungültige E-Mail Adresse']));
    if(strlen($address) < 5) exit(json_encode(['error' => 'Adresse ungültig']));
    
    // Avatar Whitelist (Sicherheit! Damit niemand Skripte einschleust)
    $allowedAvatars = ['default', 'tux', 'steve', 'modem88'];
    if(!in_array($avatar, $allowedAvatars)) {
        $avatar = 'default';
    }

    try {
        // Prüfen, ob sich die E-Mail geändert hat
        $stmtOldParams = $pdo->prepare("SELECT email, username, verification_token FROM users WHERE id = ?");
        $stmtOldParams->execute([$_SESSION['user_id']]);
        $oldUser = $stmtOldParams->fetch();
        $emailChanged = ($oldUser && $oldUser['email'] !== $email);

        $sql = "UPDATE users SET 
                realname = ?, phone = ?, email = ?, address = ?, zip = ?, city = ?, country = ?, bio = ?, avatar = ? 
                WHERE id = ?";
        
        // Wenn E-Mail geändert wurde, setzte email_verified_at auf NULL
        if ($emailChanged) {
            $sql = "UPDATE users SET 
                    realname = ?, phone = ?, email = ?, address = ?, zip = ?, city = ?, country = ?, bio = ?, avatar = ?, 
                    email_verified_at = NULL 
                    WHERE id = ?";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$realname, $phone, $email, $address, $zip, $city, $country, $bio, $avatar, $_SESSION['user_id']]);
        
        if ($emailChanged) {
            // Neuen Token generieren falls nötig, sonst alten Recyclen oder neu machen
            $verification_token = bin2hex(random_bytes(32));
            $pdo->prepare("UPDATE users SET verification_token = ? WHERE id = ?")->execute([$verification_token, $_SESSION['user_id']]);
            
            // Bestätigungsmail senden
            $verifyLink = "https://id.linuxmc.tech/verify_email.php?token=" . $verification_token;
            // E-Mail Logik analog zur Registrierung (Auth.php)
            $username = $oldUser['username']; // Username ändert sich hier nicht
            $subject = "LinuxMC ID - E-Mail Änderung bestätigen";
            
            $body = '
            <div style="background-color: #0f172a; color: #e6eef8; font-family: sans-serif; padding: 40px 20px; text-align: center;">
                <div style="max-width: 600px; margin: 0 auto; background-color: #1e293b; border-radius: 12px; padding: 40px; border: 1px solid rgba(139, 233, 253, 0.15); box-shadow: 0 10px 40px rgba(0,0,0,0.4);">
                    <h2 style="color: #8be9fd; margin-top: 0; font-size: 24px;">Neue E-Mail Adresse bestätigen</h2>
                    <p style="font-size: 16px; line-height: 1.6; color: rgba(230, 238, 248, 0.9);">Hallo <strong>'.htmlspecialchars($username).'</strong>,<br>
                    du hast deine E-Mail Adresse geändert. Bitte bestätige die neue Adresse.</p>
                    
                    <div style="margin: 35px 0;">
                        <a href="'.$verifyLink.'" style="background-color: rgba(139, 233, 253, 0.1); color: #8be9fd; border: 1px solid #8be9fd; padding: 14px 28px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px; display: inline-block; transition: all 0.3s;">Neue E-Mail Bestätigen</a>
                    </div>
                    
                    <p style="font-size: 13px; color: #94a3b8; margin-top: 30px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                        Falls der Button nicht funktioniert, kopiere diesen Link:<br>
                        <a href="'.$verifyLink.'" style="color: #8be9fd; word-break: break-all;">'.$verifyLink.'</a>
                    </p>
                    <br>
                    <p style="font-size: 14px; color: #64748b;">Dein LinuxMC Team</p>
                </div>
            </div>';

            send_smtp_mail($email, $subject, $body, true);
        }

        log_audit($_SESSION['user_id'], 'PROFILE_UPDATED');
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'DB Fehler']);
    }
    exit;
}

// === PASSWORT ÄNDERN LOGIK ===
if ($action === 'change_password') {
    $input = file_get_contents('php://input');
    $d = json_decode($input, true);
    
    $oldPw = $d['old_password'] ?? '';
    $newPw = $d['new_password'] ?? '';
    
    if (strlen($newPw) < 13) {
        http_response_code(400);
        exit(json_encode(['error' => 'Neues Passwort zu kurz (min. 13 Zeichen)']));
    }

    try {
        // 1. Altes Passwort prüfen
        // ACHTUNG: Spaltenname ist password_hash, nicht password!
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($oldPw, $user['password_hash'])) {
            http_response_code(400);
            exit(json_encode(['error' => 'Das alte Passwort ist falsch.']));
        }

        // 2. Neues Passwort hashen und speichern
        $newHash = password_hash($newPw, PASSWORD_DEFAULT);
        
        $upd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $upd->execute([$newHash, $_SESSION['user_id']]);
        
        log_audit($_SESSION['user_id'], 'PASSWORD_CHANGED');
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        // Fehler nicht im Detail ausgeben (Sicherheit), nur loggen wenn möglich
        echo json_encode(['error' => 'Datenbankfehler beim Ändern des Passworts.']); 
    }
    exit;
}

// === 2FA LOGIK ===

// 1. Setup beginnen (Secret generieren)
if ($action === 'setup_2fa_init') {
    $secret = TOTP::createSecret();
    $_SESSION['2fa_temp_secret'] = $secret; // Zwischenspeichern
    
    // OTPAuth URL für QR-Codes (Standard Format)
    // otpauth://totp/Label?secret=SECRET&issuer=Issuer
    $label = 'LinuxMC:' . ($_SESSION['username'] ?? 'User');
    $otpUrl = "otpauth://totp/" . rawurlencode($label) . "?secret=" . $secret . "&issuer=LinuxMC";
    
    echo json_encode(['success' => true, 'secret' => $secret, 'otpauth_url' => $otpUrl]);
    exit;
}

// 2. Setup bestätigen (Code prüfen)
if ($action === 'setup_2fa_confirm') {
    $input = json_decode(file_get_contents('php://input'), true);
    $code = $input['code'] ?? '';
    $tempSecret = $_SESSION['2fa_temp_secret'] ?? null;
    
    if (!$tempSecret) {
        exit(json_encode(['error' => 'Kein Setup gestartet.']));
    }
    
    if (TOTP::verifyCode($tempSecret, $code)) {
        // Code gültig -> In DB speichern
        $stmt = $pdo->prepare("UPDATE users SET totp_secret = ?, is_2fa_enabled = 1 WHERE id = ?");
        $stmt->execute([$tempSecret, $_SESSION['user_id']]);
        
        log_audit($_SESSION['user_id'], '2FA_ENABLED');
        unset($_SESSION['2fa_temp_secret']); // Temp löschen
        echo json_encode(['success' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültiger Code.']);
    }
    exit;
}

// 3. 2FA Deaktivieren
if ($action === 'disable_2fa') {
    $input = json_decode(file_get_contents('php://input'), true);
    $password = $input['password'] ?? '';
    
    // Sicherheitscheck: Passwort muss stimmen!
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $upd = $pdo->prepare("UPDATE users SET is_2fa_enabled = 0, totp_secret = NULL WHERE id = ?");
        $upd->execute([$_SESSION['user_id']]);
        
        log_audit($_SESSION['user_id'], '2FA_DISABLED');
        echo json_encode(['success' => true]);
    } else {
        http_response_code(403);
        echo json_encode(['error' => 'Passwort falsch.']);
    }
    exit;
}

echo json_encode(['error' => 'Ungültige Action']);
?>
