<?php
require '/var/www/secret/db.php';
require '/var/www/id.linuxmc.tech/audit_logger.php'; // Audit Logger laden
require '/var/www/id.linuxmc.tech/includes/mailer.php'; // Mailer laden

// Sicherheits-Header
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Session Einstellungen (30 Tage haltbar)
$lifetime = 86400 * 30; 
ini_set('session.gc_maxlifetime', $lifetime);
ini_set('session.use_trans_sid', 0);     // Session ID nicht in URL erlauben!
ini_set('session.use_only_cookies', 1);  // Nur Cookies erlauben!
session_set_cookie_params([
    'lifetime' => $lifetime,
    'path' => '/',
    'domain' => '.linuxmc.tech', // Gilt für alle Subdomains von linuxmc.tech inklusive id. und root.
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

$action = $_GET['action'] ?? '';
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// CSRF Check für POST Anfragen (Login, Register...)
// Es sei denn, es ist ein externer Aufruf (derzeit noch nicht unterschieden, aber Frontend sendet Header)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    // Form data read
    if($action === 'sso_login') {
        $postToken = $_POST['csrf_token'] ?? '';
    } else {
        // Bei JSON decode liegt der Token im Body? Nein, Header. 
        // Oder JSON Body? Wir nutzen hier Header-Token im Frontend.
        $postToken = '';
    }
    
    $clientToken = $headerToken ?: $postToken;
    
    // Login ohne vorhandene Session? -> Token neu generieren wär sauber, aber hier existiert session_start() schon.
    // Falls Session leer ist (neuer User), muss Token existieren.
    // Session Token muss da sein
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    // Für die Erst-Prüfung bei POST requests (Login/API)
    // Wenn kein Token, dann Abweisen
    if (empty($clientToken) || !hash_equals($_SESSION['csrf_token'], $clientToken)) {
         if ($action === 'sso_login') {
             die("Ungültige Anfrage (CSRF Token mismatch). Bitte zurück und neu laden.");
         } else {
             http_response_code(403);
             echo json_encode(['error' => 'CSRF Token Invalid or Missing']);
             exit;
         }
    }
}

// Für normales JSON-Login
if ($action === 'login') {
    // Check passiert unten im Login-Block
}

// === SSO LOGIN (VON EXTERN ANGELÖST) ===
if ($action === 'sso_login') {
    $redirect_url = $_GET['redirect'] ?? 'https://linuxmc.tech';
    // Sicherheits-Check: Redirects nur auf erlaubte Domains!
    $whitelist = ['linuxmc.tech', 'id.linuxmc.tech', 'linuxmc.root64.de', 'id.linuxmc.root64.de'];
    $allowed = false;
    foreach($whitelist as $host) if(strpos($redirect_url, $host) !== false) $allowed = true;
    if(!$allowed) die("Unbekannte Ziel-Seite!");

    // Login Formular prüfen
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT id, username, password_hash, avatar, is_2fa_enabled, email_verified_at FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        
        // --- EMAIL VERIFICATION CHECK ---
        if (empty($user['email_verified_at'])) {
            // User ist noch nicht verifiziert. Trotzdem Login erlauben für "Resend Email", aber auf Pending-Seite zwingen.
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['avatar'] = $user['avatar'] ?? 'default';
            // Redirect auf Pending Page
            header("Location: verify_email_pending.php");
            exit;
        }

        // --- 2FA CHECK START ---
        if (!empty($user['is_2fa_enabled'])) {
            log_audit($user['id'], 'LOGIN_2FA_REQUIRED');
            // User hat 2FA an!
            $_SESSION['2fa_pending_user_id'] = $user['id'];
            $_SESSION['2fa_pending_username'] = $user['username'];
            $_SESSION['2fa_pending_avatar']   = $user['avatar'] ?? 'default';
            $_SESSION['2fa_redirect_after']   = $redirect_url;
            
            // Redirect zum 2FA Eingabefeld
            header("Location: verify_2fa.php");
            exit;
        }
        // --- 2FA CHECK END ---

        log_audit($user['id'], 'LOGIN_SUCCESS', 'SSO Login via Form');

        // Erfolgreich eingeloggt -> Session starten
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['avatar'] = $user['avatar'] ?? 'default';

        // --- SESSION TRACKING INSERT (SSO) ---
        try {
            $sessId = session_id();
            $ip = $_SERVER['REMOTE_ADDR'];
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            // Cleanup existing for this ID
            $delOld = $pdo->prepare("DELETE FROM user_sessions WHERE session_id = ?");
            $delOld->execute([$sessId]);
            // Insert new
            $insSess = $pdo->prepare("INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, last_active) VALUES (?, ?, ?, ?, NOW())");
            $insSess->execute([$user['id'], $sessId, $ip, $ua]);
        } catch (Exception $e) { /* Ignore Session DB Error */ }
        // -------------------------------------

        // => Jetzt zurück zur SSO-Weiche (dort wird das Token erst generiert)
        // Token nicht direkt hier, damit User auch bei Fehler zurückkommen
        header("Location: sso.php?redirect=" . urlencode($redirect_url));
        exit;
    } else {
        // Loggen wenn User existiert aber Passwort falsch
        if($user) log_audit($user['id'], 'LOGIN_FAILED', 'Falsches Passwort (SSO)');
        // Fehler anzeigen
        header("Location: sso.php?redirect=" . urlencode($redirect_url) . "&error=1");
        exit;
    }
}

// === LOGIN LOGIK (Interne API) ===
if ($action === 'login') {
    // Normaler API Call -> Daten aus JSON
    if (!$data || !is_array($data)) {
        exit(json_encode(['error' => 'Login: Ungültige Anfrage']));
    }
    
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    // Kurzer Check gegen DoS
    if (strlen($password) > 4096) {
        exit(json_encode(['error' => 'Login fehlgeschlagen.']));
    }
    
    $stmt = $pdo->prepare("SELECT id, username, password_hash, avatar, is_2fa_enabled, email_verified_at FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        
        // --- EMAIL VERIFICATION CHECK FOR API ---
        if (empty($user['email_verified_at'])) {
            // Log user in properly so they can access resend_verification API
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['avatar'] = $user['avatar'] ?? 'default';

            // --- SESSION TRACKING INSERT (REQUIRED FOR DASHBOARD CHECK) ---
            try {
                $sessId = session_id();
                $ip = $_SERVER['REMOTE_ADDR'];
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                // Cleanup existing for this ID
                $delOld = $pdo->prepare("DELETE FROM user_sessions WHERE session_id = ?");
                $delOld->execute([$sessId]);
                // Insert new
                $insSess = $pdo->prepare("INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, last_active) VALUES (?, ?, ?, ?, NOW())");
                $insSess->execute([$user['id'], $sessId, $ip, $ua]);
            } catch (Exception $e) { /* Ignore Session DB Error */ }
            // -------------------------------------

            // Return instructions to redirect
            echo json_encode(['success' => true, 'redirect' => 'https://id.linuxmc.tech/verify_email_pending.php']);
            exit;
        }

        // --- 2FA CHECK JSON API ---
        if (!empty($user['is_2fa_enabled'])) {
            log_audit($user['id'], 'LOGIN_2FA_REQUIRED', 'JSON API');
            $_SESSION['2fa_pending_user_id'] = $user['id'];
            $_SESSION['2fa_pending_username'] = $user['username'];
            $_SESSION['2fa_pending_avatar']   = $user['avatar'] ?? 'default';
            
            // JSON Antwort sagt dem Frontend: Bitte weiterleiten!
            echo json_encode(['success' => true, 'require_2fa' => true, 'redirect' => 'https://id.linuxmc.tech/verify_2fa.php']);
            exit;
        }
        
        // Session ID neu generieren (Session Fixation Schutz)
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['avatar'] = $user['avatar'] ?? 'default';
        
        // --- SESSION TRACKING INSERT (API) ---
        try {
            $sessId = session_id();
            $ip = $_SERVER['REMOTE_ADDR'];
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            // Cleanup existing for this ID
            $delOld = $pdo->prepare("DELETE FROM user_sessions WHERE session_id = ?");
            $delOld->execute([$sessId]);
            // Insert new
            $insSess = $pdo->prepare("INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, last_active) VALUES (?, ?, ?, ?, NOW())");
            $insSess->execute([$user['id'], $sessId, $ip, $ua]);
        } catch (Exception $e) { /* Ignore Session DB Error */ }
        // -------------------------------------
        
        log_audit($user['id'], 'LOGIN_SUCCESS', 'API Login');
        echo json_encode(['success' => true]);
    } else {
        if($user) log_audit($user['id'], 'LOGIN_FAILED', 'API Login Failed');
        echo json_encode(['error' => 'Benutzername oder Passwort falsch.']);
    }
    exit;
}


// === RESEND VERIFICATION EMAIL LOGIK ===
if ($action === 'resend_verification') {
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Nicht eingeloggt.']);
        exit;
    }

    $userId = $_SESSION['user_id'];

    // Rate Limiting (z.B. alle 2 Minuten)
    if (isset($_SESSION['last_verification_sent']) && (time() - $_SESSION['last_verification_sent'] < 120)) {
        echo json_encode(['error' => 'Bitte warte kurz, bevor du eine neue E-Mail anforderst.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT username, email, verification_token, email_verified_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            echo json_encode(['error' => 'Benutzer nicht gefunden.']);
            exit;
        }

        if ($user['email_verified_at']) {
            echo json_encode(['success' => true, 'message' => 'Bereits verifiziert.']); // Frontend kann das als Success werten
            exit;
        }

        $email = $user['email'];
        $username = $user['username'];
        $verification_token = $user['verification_token'];

        // Falls Token fehlt (altes Konto?), neuen generieren
        if (empty($verification_token)) {
            $verification_token = bin2hex(random_bytes(32));
            $pdo->prepare("UPDATE users SET verification_token = ? WHERE id = ?")->execute([$verification_token, $userId]);
        }

        $verifyLink = "https://id.linuxmc.tech/verify_email.php?token=" . $verification_token;
        $subject = "LinuxMC ID - E-Mail Verifizierung (Erneut gesendet)";
        
        $body = '
        <div style="background-color: #0f172a; color: #e6eef8; font-family: sans-serif; padding: 40px 20px; text-align: center;">
            <div style="max-width: 600px; margin: 0 auto; background-color: #1e293b; border-radius: 12px; padding: 40px; border: 1px solid rgba(139, 233, 253, 0.15); box-shadow: 0 10px 40px rgba(0,0,0,0.4);">
                <h2 style="color: #8be9fd; margin-top: 0; font-size: 24px;">Verifizierung bestätigen</h2>
                <p style="font-size: 16px; line-height: 1.6; color: rgba(230, 238, 248, 0.9);">Hallo <strong>'.htmlspecialchars($username).'</strong>,<br>
                du hast einen neuen Bestätigungslink für deinen Account angefordert.</p>
                
                <div style="margin: 35px 0;">
                    <a href="'.$verifyLink.'" style="background-color: rgba(139, 233, 253, 0.1); color: #8be9fd; border: 1px solid #8be9fd; padding: 14px 28px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px; display: inline-block; transition: all 0.3s;">E-Mail Jetzt Bestätigen</a>
                </div>
                
                <p style="font-size: 13px; color: #94a3b8; margin-top: 30px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                    Falls der Button nicht funktioniert, kopiere diesen Link:<br>
                    <a href="'.$verifyLink.'" style="color: #8be9fd; word-break: break-all;">'.$verifyLink.'</a>
                </p>
                <br>
                <p style="font-size: 14px; color: #64748b;">Dein LinuxMC Team</p>
            </div>
        </div>';

        if (send_smtp_mail($email, $subject, $body, true)) {
            $_SESSION['last_verification_sent'] = time();
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Fehler beim Senden der E-Mail.']);
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Interner Serverfehler']);
    }
    exit;
}

// === CHANGE PENDING EMAIL LOGIK ===
if ($action === 'change_pending_email') {
    // 1. Check Login
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Nicht eingeloggt.']);
        exit;
    }

    $userId = $_SESSION['user_id'];
    $newEmail = trim($data['new_email'] ?? '');

    // 2. Validate Email
    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['error' => 'Ungültige E-Mail-Adresse.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT username, email, email_verified_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            echo json_encode(['error' => 'Benutzer nicht gefunden.']);
            exit;
        }

        // 3. Ensure User is UNVERIFIED
        if ($user['email_verified_at']) {
            echo json_encode(['error' => 'Account bereits verifiziert. Bitte nutze die Einstellungen.', 'success' => false]);
            exit;
        }

        // 4. Check Uniqueness
        $stmtUnique = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmtUnique->execute([$newEmail, $userId]);
        if ($stmtUnique->fetch()) {
             echo json_encode(['error' => 'Diese E-Mail-Adresse wird bereits verwendet.']);
             exit;
        }

        // 5. Update Email & Generate New Token
        $verification_token = bin2hex(random_bytes(32));
        $pdo->prepare("UPDATE users SET email = ?, verification_token = ? WHERE id = ?")->execute([$newEmail, $verification_token, $userId]);

        // 6. Send New Verification Email
        $verifyLink = "https://id.linuxmc.tech/verify_email.php?token=" . $verification_token;
        $subject = "LinuxMC ID - E-Mail Verifizierung (Adresse geändert)";
        
        $body = '
        <div style="background-color: #0f172a; color: #e6eef8; font-family: sans-serif; padding: 40px 20px; text-align: center;">
            <div style="max-width: 600px; margin: 0 auto; background-color: #1e293b; border-radius: 12px; padding: 40px; border: 1px solid rgba(139, 233, 253, 0.15); box-shadow: 0 10px 40px rgba(0,0,0,0.4);">
                <h2 style="color: #8be9fd; margin-top: 0; font-size: 24px;">Neue E-Mail bestätigen</h2>
                <p style="font-size: 16px; line-height: 1.6; color: rgba(230, 238, 248, 0.9);">Hallo <strong>'.htmlspecialchars($user['username']).'</strong>,<br>
                du hast deine E-Mail-Adresse zu <strong>'.htmlspecialchars($newEmail).'</strong> geändert.</p>
                
                <div style="margin: 35px 0;">
                    <a href="'.$verifyLink.'" style="background-color: rgba(139, 233, 253, 0.1); color: #8be9fd; border: 1px solid #8be9fd; padding: 14px 28px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px; display: inline-block; transition: all 0.3s;">E-Mail Jetzt Bestätigen</a>
                </div>
            </div>
        </div>';

        if (send_smtp_mail($newEmail, $subject, $body, true)) {
            $_SESSION['last_verification_sent'] = time();
            echo json_encode(['success' => true, 'message' => 'E-Mail aktualisiert und gesendet!']);
        } else {
            echo json_encode(['error' => 'Fehler beim Senden der E-Mail.']);
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Interner Serverfehler']);
    }
    exit;
}

// === REGISTER LOGIK ===
if ($action === 'register') {
    
    // 1. Honeypot Check (Spam Schutz)
    if (!empty($data['honeypot'])) { 
        // Fake success: Spambot glaubt es hat geklappt, aber wir speichern nichts
        sleep(2); // Wartezeit simulieren
        exit(json_encode(['success' => true])); 
    }

    // 2. Daten bereinigen
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';
    $realname = trim($data['realname'] ?? '');
    $address  = trim($data['address'] ?? '');
    $zip      = trim($data['zip'] ?? '');
    $city     = trim($data['city'] ?? '');
    $country  = trim($data['country'] ?? ''); // NEU: Land
    $email    = trim($data['email'] ?? '');

    // 3. Validierung
    if (strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
        exit(json_encode(['error' => 'Benutzername ungültig (Nur Buchstaben, Zahlen, -, _).']));
    }
    
    // NEU: Mindestens 13 Zeichen!
    if (strlen($password) < 13) {
        exit(json_encode(['error' => 'Passwort muss mindestens 13 Zeichen lang sein.']));
    }
    if (strlen($password) > 4096) {
        exit(json_encode(['error' => 'Passwort zu lang.']));
    }
    
    if (strlen($realname) < 2) exit(json_encode(['error' => 'Name fehlt.']));
    if (strlen($address) < 5) exit(json_encode(['error' => 'Straße fehlt.']));
    if (strlen($zip) < 3) exit(json_encode(['error' => 'PLZ ungültig.']));
    if (strlen($city) < 2) exit(json_encode(['error' => 'Stadt fehlt.']));
    if (empty($country)) exit(json_encode(['error' => 'Land fehlt.']));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        exit(json_encode(['error' => 'E-Mail Adresse ungültig.']));
    }

    // 4. Datenbank Check & Insert
    try {
        // Prüfen ob User oder Mail schon existiert
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            exit(json_encode(['error' => 'Benutzername oder E-Mail schon vergeben.']));
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $verification_token = bin2hex(random_bytes(32));
        
        // Einfügen mit allen neuen Feldern
        $sql = "INSERT INTO users (username, password_hash, realname, address, zip, city, country, email, verification_token) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$username, $hash, $realname, $address, $zip, $city, $country, $email, $verification_token])) {
            // E-Mail senden mit PHPMailer via send_stmp_mail()
            $verifyLink = "https://id.linuxmc.tech/verify_email.php?token=" . $verification_token;
            $subject = "LinuxMC ID - E-Mail Verifizierung";
            
            // HTML Body (Dark Theme)
            $body = '
            <div style="background-color: #0f172a; color: #e6eef8; font-family: sans-serif; padding: 40px 20px; text-align: center;">
                <div style="max-width: 600px; margin: 0 auto; background-color: #1e293b; border-radius: 12px; padding: 40px; border: 1px solid rgba(139, 233, 253, 0.15); box-shadow: 0 10px 40px rgba(0,0,0,0.4);">
                    <h2 style="color: #8be9fd; margin-top: 0; font-size: 24px;">Willkommen bei LinuxMC!</h2>
                    <p style="font-size: 16px; line-height: 1.6; color: rgba(230, 238, 248, 0.9);">Hallo <strong>'.htmlspecialchars($username).'</strong>,<br>
                    bitte bestätige deine E-Mail Adresse, um deinen Account vollständig zu aktivieren.</p>
                    
                    <div style="margin: 35px 0;">
                        <a href="'.$verifyLink.'" style="background-color: rgba(139, 233, 253, 0.1); color: #8be9fd; border: 1px solid #8be9fd; padding: 14px 28px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px; display: inline-block; transition: all 0.3s;">E-Mail Bestätigen</a>
                    </div>
                    
                    <p style="font-size: 13px; color: #94a3b8; margin-top: 30px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                        Falls der Button nicht funktioniert, kopiere diesen Link:<br>
                        <a href="'.$verifyLink.'" style="color: #8be9fd; word-break: break-all;">'.$verifyLink.'</a>
                    </p>
                    <br>
                    <p style="font-size: 14px; color: #64748b;">Dein LinuxMC Team</p>
                </div>
            </div>';
            
            // Senden (User bekommt true zurück, auch wenn Mail failt, um Enum zu verhindern - oder Fehler loggen)
            if(!send_smtp_mail($email, $subject, $body, true)) {
                 error_log("Mail an $email konnte nicht gesendet werden.");
            }

            echo json_encode(['success' => true]);
        } else {
            throw new Exception("Datenbank Fehler beim Speichern.");
        }
    } catch (Exception $e) {
        // Logge den Fehler intern, zeige dem User nur "Fehler"
        // error_log($e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Interner Serverfehler.']);
    }
    exit;
}
?>