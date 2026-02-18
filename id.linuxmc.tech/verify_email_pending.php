<?php
require '/var/www/secret/db.php';
require '/var/www/id.linuxmc.tech/audit_logger.php'; // Audit Logger
require_once 'includes/lang.php';
require_once 'includes/translations.php';
$txt = $t[$lang];

// Session settings
ini_set('session.use_trans_sid', 0);
ini_set('session.use_only_cookies', 1);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '.linuxmc.tech',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// 1. Check if logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// 2. Check if verified
$stmt = $pdo->prepare("SELECT email_verified_at FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: index.php");
    exit;
}

if ($user['email_verified_at']) {
    // Already verified -> Go to Dashboard or remembered redirect
    // If there is a pending redirect from SSO, we might not know it here unless stored in session.
    // Usually redirect to dashboard.
    header("Location: dashboard.php");
    exit;
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Mail Verifizierung Notwendig - LinuxMC ID</title>
    <link rel="icon" type="image/png" href="/tuxrandom64.png">
    <link rel="stylesheet" href="/styles.css">
    <script src="/main.js"></script>
    <style>
        .verification-box {
            background: rgba(255, 255, 255, 0.05);
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid var(--border);
            margin: 40px auto;
            max-width: 500px;
        }
        .icon-large {
            font-size: 3rem;
            margin-bottom: 20px;
            display: block;
        }
        .btn-primary {
            background: rgba(139, 233, 253, 0.15);
            color: var(--accent);
            border: 1px solid var(--accent);
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
            margin-top: 15px;
        }
        .btn-primary:hover {
            background: rgba(139, 233, 253, 0.25);
            box-shadow: 0 0 15px rgba(139, 233, 253, 0.3);
        }
        .logout-link {
            display: block;
            margin-top: 20px;
            color: rgba(255,255,255,0.5);
            font-size: 0.9rem;
            text-decoration: none;
        }
        .logout-link:hover {
            color: #fff;
            text-decoration: underline;
        }
        #message-box {
            margin-top: 15px;
            font-size: 0.9rem;
            min-height: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>E-Mail Bestätigung</h1>
        
        <div class="verification-box">
            <span class="icon-large">📧</span>
            <p>Bitte bestätige deine E-Mail Adresse, um fortzufahren.</p>
            <p style="font-size: 0.9rem; opacity: 0.8;">Wir haben dir einen Link an deine E-Mail gesendet.</p>
            
            <button id="resend-btn" class="btn-primary" onclick="resendVerification()">E-Mail erneut senden</button>
            
            <div style="margin-top: 25px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px;">
                <small style="cursor: pointer; color: var(--accent); text-decoration: underline;" onclick="toggleEmailEdit()">Falsche E-Mail Adresse?</small>
                <div id="email-edit-form" style="display: none; margin-top: 10px;">
                    <input type="email" id="new-email" placeholder="Neue E-Mail Adresse" style="width: 100%; padding: 8px; margin-bottom: 8px; border-radius: 4px; border: 1px solid var(--border); background: rgba(0,0,0,0.2); color: #fff;">
                    <button onclick="updateEmail()" class="btn-primary" style="font-size: 0.8rem; padding: 6px 12px; margin-top: 5px;">Speichern & Senden</button>
                </div>
            </div>

            <div id="message-box"></div>
            
            <a href="logout.php" class="logout-link">Abmelden / Anderen Account nutzen</a>
        </div>
    </div>

    <script>
        function toggleEmailEdit() {
            const form = document.getElementById('email-edit-form');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        function updateEmail() {
            const newEmail = document.getElementById('new-email').value;
            const msgBox = document.getElementById('message-box');
            
            if(!newEmail || !newEmail.includes('@')) {
                alert("Bitte eine gültige E-Mail eingeben.");
                return;
            }

            msgBox.innerText = "Aktualisiere...";
            msgBox.style.color = "var(--text)";

            fetch('/auth.php?action=change_pending_email', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '<?= $_SESSION['csrf_token'] ?>'
                },
                body: JSON.stringify({ new_email: newEmail })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    msgBox.style.color = "#50fa7b";
                    msgBox.innerText = data.message || "E-Mail aktualisiert! Bitte Posteingang prüfen.";
                    document.getElementById('email-edit-form').style.display = 'none';
                } else {
                    msgBox.style.color = "#ff5555";
                    msgBox.innerText = data.error || "Fehler aufgetreten.";
                }
            })
            .catch(err => {
                msgBox.style.color = "#ff5555";
                msgBox.innerText = "Verbindungsfehler.";
            });
        }

        function resendVerification() {
            const btn = document.getElementById('resend-btn');
            const msgBox = document.getElementById('message-box');
            
            btn.disabled = true;
            btn.innerText = "Sende...";
            msgBox.style.color = "var(--text)";
            msgBox.innerText = "";
            
            fetch('/auth.php?action=resend_verification', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '<?= $_SESSION['csrf_token'] ?>' // Token wird hier injected
                }, 
                body: JSON.stringify({})
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    msgBox.style.color = "#50fa7b"; // Green
                    msgBox.innerText = "E-Mail wurde erneut gesendet!";
                } else {
                    msgBox.style.color = "#ff5555"; // Red
                    msgBox.innerText = data.error || "Fehler beim Senden.";
                }
            })
            .catch(err => {
                msgBox.style.color = "#ff5555";
                msgBox.innerText = "Verbindungsfehler.";
            })
            .finally(() => {
                setTimeout(() => {
                    btn.disabled = false;
                    btn.innerText = "E-Mail erneut senden";
                }, 5000); // Cooldown
            });
        }
        
        // Auto-Reload check? Maybe not necessary, user will click link in email anyway.
    </script>
</body>
</html>
