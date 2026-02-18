<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '.linuxmc.tech',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
// Wenn schon eingeloggt -> Weiterleitung (später zum Dashboard)
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

// Gemeinsames Language Handling
require_once 'includes/lang.php';
require_once 'includes/translations.php';
$txt = $t[$lang];

// 1.1 CSRF Token initialisieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$txt = $t[$lang];
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?>">
    <title>LinuxMC ID</title>
    <!-- Wir laden dein globales CSS -->
    <link rel="stylesheet" href="/styles.css">
    <style>
        /* Spezifische Styles für perfektes Zentrieren */
        body {
            margin: 0;
            padding: 0;
            height: 100vh;
            width: 100vw;
            overflow: hidden; 
            position: relative;
            background-color: #0f172a; /* Fallback Farbe */
        }

        /* LANGUAGE SWITCHER */
        .lang-switch {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        .lang-switch a {
            text-decoration: none;
            font-size: 1.5rem;
            margin-left: 10px;
            opacity: 0.5;
            transition: opacity 0.2s;
        }
        .lang-switch a.active, .lang-switch a:hover {
            opacity: 1;
        }
        
        /* Der Container, der alles mittig hält */
        .center-wrapper {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 420px;
            z-index: 10;
        }

        /* Das Design der Karte */
        .auth-card {
            background: rgba(30, 41, 59, 0.9); /* Dunkles Blau-Grau */
            padding: 2.5rem;
            border-radius: 16px;
            border: 1px solid rgba(139, 233, 253, 0.15); /* Hellblauer Rand */
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px);
            text-align: center;
            color: #e2e8f0;
        }

        h1 { color: #8be9fd; margin: 0 0 0.5rem 0; font-size: 2rem; }
        p.subtitle { color: #94a3b8; margin-bottom: 2rem; margin-top: 0; font-size: 0.9rem;}

        .form-group { margin-bottom: 1rem; text-align: left; }
        .form-group label { display: block; font-size: 0.85rem; margin-bottom: 0.4rem; color: #cbd5e1; }
        
        /* Eingabefelder */
        input, select {
            width: 100%;
            padding: 12px;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(139, 233, 253, 0.1);
            border-radius: 8px;
            color: white;
            font-size: 1rem;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #8be9fd;
            box-shadow: 0 0 0 2px rgba(139, 233, 253, 0.2);
        }

        /* Buttons */
        button.btn-main {
            width: 100%;
            padding: 12px;
            background: #8be9fd;
            color: #0f172a;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 1rem;
            transition: all 0.2s;
        }
        button.btn-main:hover { 
            background: #78dce8; 
            transform: translateY(-2px); 
        }

        .switch-link { margin-top: 1.5rem; font-size: 0.9rem; color: #64748b; }
        .switch-link a { color: #8be9fd; cursor: pointer; font-weight: 600; text-decoration: none; }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        
        /* Fehlermeldungen */
        .error-msg { 
            color: #ff5252; 
            background: rgba(255, 82, 82, 0.1); 
            padding: 10px; 
            border-radius: 6px; 
            display: none; 
            margin-bottom: 15px; 
            font-size: 0.9rem; 
            text-align: left;
            border: 1px solid rgba(255, 82, 82, 0.2);
        }
        
        /* Sprachschalter oben rechts */
        .lang-switch { position: absolute; top: 20px; right: 20px; z-index: 20; font-family: sans-serif;}
        .lang-switch a { color: rgba(255,255,255,0.4); text-decoration: none; font-size: 0.9rem; margin-left: 10px; font-weight: bold;}
        .lang-switch a.active { color: #8be9fd; text-decoration: underline;}

        /* Scrollbar für Registrierung auf kleinen Handys */
        #view-register { max-height: 85vh; overflow-y: auto; }
        #view-register::-webkit-scrollbar { width: 4px; }
        #view-register::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 2px; }
    </style>
</head>
<body>

<!-- Sprachauswahl -->
<div class="lang-switch">
    <?php $v = $_GET['view'] ?? 'login'; ?>
    <a href="?lang=de&view=<?= htmlspecialchars($v) ?>" title="Deutsch" style="font-size: 1.5rem; text-decoration: none; opacity: <?= $lang=='de'?1:0.5 ?>;">🇩🇪</a>
    <a href="?lang=en&view=<?= htmlspecialchars($v) ?>" title="English" style="font-size: 1.5rem; text-decoration: none; margin-left:10px; opacity: <?= $lang=='en'?1:0.5 ?>;">🇺🇸</a>
</div>

<div class="center-wrapper">
    
    <!-- Top Links (Navigation) -->
    <div style="margin-bottom: 2rem; text-align: center;">
        <a class="mailto" href="https://linuxmc.tech/index.php?lang=<?= $lang ?>"><?= $txt['home'] ?></a>
        <a class="mailto" href="https://linuxmc.tech/ipinfo.php?lang=<?= $lang ?>"><?= $txt['ipv6'] ?></a>
        <a class="mailto" href="mailto:ys6v9rqpp@mozmail.com"><?= $txt['contact'] ?></a>
        <a class="mailto" href="https://wiki.linuxmc.tech">Wiki</a>
        <a class="mailto" href="https://files.linuxmc.tech">Files</a>
        <a class="mailto" href="https://id.linuxmc.tech">ID</a>
    </div>

    <!-- LOGIN FORMULAR -->
    <div id="view-login" class="auth-card">
        <h1>LinuxMC ID</h1>
        <p class="subtitle"><?= $txt['sub_title'] ?></p>
        <div id="login-error" class="error-msg"></div>

        <form onsubmit="doLogin(event)">
            <div class="form-group">
                <label><?= $txt['user'] ?></label>
                <input type="text" id="l_user" required>
            </div>
            <div class="form-group">
                <label><?= $txt['pass'] ?></label>
                <input type="password" id="l_pass" required>
            </div>
            <button type="submit" class="btn-primary"><?= $txt['btn_login'] ?></button>
        </form>

        <div class="switch-link">
            <?= $txt['no_acc'] ?> <a onclick="switchView('register')"><?= $txt['create_acc'] ?></a>
        </div>
        
        <div style="margin-top: 15px; font-size: 0.85rem; color: #888; text-align: center;">
            Passwort vergessen? <a href="mailto:ys6v9rqpp@mozmail.com" style="color: #8be9fd; text-decoration: none;">Support kontaktieren</a>
        </div>
    </div>

    <!-- REGISTRIERUNGS FORMULAR -->
    <div id="view-register" class="auth-card" style="display:none;">
        <h1><?= $txt['reg_title'] ?></h1>
        <p class="subtitle"><?= $txt['reg_sub'] ?></p>
        <div id="reg-error" class="error-msg"></div>

        <form onsubmit="doRegister(event)">
            <div class="form-group">
                <label><?= $txt['user'] ?></label>
                <input type="text" id="r_user" required>
            </div>
            
            <!-- Neuer Block: Echter Name -->
            <div class="form-group">
                <label><?= $txt['realname'] ?></label>
                <input type="text" id="r_realname" required>
            </div>

            <!-- Adresse -->
            <div class="form-group">
                <label><?= $txt['address'] ?></label>
                <input type="text" id="r_address" required>
            </div>

            <!-- PLZ und Stadt nebeneinander -->
            <div class="grid-2">
                <div class="form-group">
                    <label><?= $txt['zip'] ?></label>
                    <input type="text" id="r_zip" required>
                </div>
                <div class="form-group">
                    <label><?= $txt['city'] ?></label>
                    <input type="text" id="r_city" required>
                </div>
            </div>

            <!-- NEU: Land Auswahl -->
            <div class="form-group">
                <label><?= $txt['country'] ?></label>
                <select id="r_country" required>
                    <option value="Deutschland">Deutschland</option>
                    <option value="Österreich">Österreich</option>
                    <option value="Schweiz">Schweiz</option>
                    <option value="USA">USA</option>
                    <option value="UK">United Kingdom</option>
                    <option value="Other">Other / Andere</option>
                </select>
            </div>

            <div class="form-group">
                <label><?= $txt['email'] ?></label>
                <input type="email" id="r_email" required>
            </div>
            
            <!-- Passwort mit 13 Zeichen Hinweis -->
            <div class="form-group">
                <label><?= $txt['pass'] ?></label>
                <input type="password" id="r_pass" placeholder="<?= $txt['pass_hint'] ?>" minlength="13" required>
            </div>

            <!-- Honeypot (Spam Falle) -->
            <input type="text" id="r_website" style="display:none;" autocomplete="off">

            <button type="submit" class="btn-primary"><?= $txt['btn_reg'] ?></button>
        </form>

        <div class="switch-link">
            <?= $txt['has_acc'] ?> <a onclick="switchView('login')"><?= $txt['to_login'] ?></a>
        </div>
    </div>
    
    <!-- Footer Links -->
    <div style="margin-top: 2rem; text-align: center;"></div>

</div>

<script>
    // Holt sich den Fehlertext aus PHP
    const ERR_LEN = "<?= $txt['err_len'] ?>";

    // Wechselt zwischen Login und Register
    function switchView(view) {
        document.getElementById('view-login').style.display = view === 'login' ? 'block' : 'none';
        document.getElementById('view-register').style.display = view === 'register' ? 'block' : 'none';
        // Fehler ausblenden beim Wechsel
        document.querySelectorAll('.error-msg').forEach(e => e.style.display = 'none');
    }

    async function doLogin(e) {
        e.preventDefault();
        const user = document.getElementById('l_user').value;
        const pass = document.getElementById('l_pass').value;
        const btn = e.target.querySelector('button');
        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        btn.disabled = true; // Button sperren während Ladevorgang
        btn.style.opacity = "0.7";

        try {
            const res = await fetch('auth.php?action=login', {
                method: 'POST', 
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': token
                },
                body: JSON.stringify({username: user, password: pass})
            });
            const data = await res.json();
            
            if(data.success) {
                if(data.require_2fa) {
                    window.location.href = data.redirect;
                } else {
                    window.location.href = 'dashboard.php';
                }
            } else {
                showError('login-error', data.error);
                btn.disabled = false;
                btn.style.opacity = "1";
            }
        } catch(err) {
            showError('login-error', 'Verbindungsfehler zum Server.');
            btn.disabled = false;
            btn.style.opacity = "1";
        }
    }

    async function doRegister(e) {
        e.preventDefault();
        const pass = document.getElementById('r_pass').value;

        // Zusätzlicher Check in JS
        if(pass.length < 13) {
            showError('reg-error', ERR_LEN);
            return;
        }

        const payload = {
            username: document.getElementById('r_user').value,
            password: pass,
            realname: document.getElementById('r_realname').value,
            address:  document.getElementById('r_address').value,
            zip:      document.getElementById('r_zip').value,
            city:     document.getElementById('r_city').value,
            country:  document.getElementById('r_country').value, // Das neue Feld senden
            email:    document.getElementById('r_email').value,
            honeypot: document.getElementById('r_website').value
        };

        const btn = e.target.querySelector('button');
        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        btn.disabled = true;
        btn.style.opacity = "0.7";

        try {
            const res = await fetch('auth.php?action=register', {
                method: 'POST', 
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': token
                },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            
            if(data.success) {
                alert('Account erfolgreich erstellt! Bitte einloggen.');
                switchView('login');
            } else {
                showError('reg-error', data.error);
            }
        } catch(err) {
            showError('reg-error', 'Verbindungsfehler zum Server.');
        }
        btn.disabled = false;
        btn.style.opacity = "1";
    }

    // Beim Laden prüfen ob wir direkt zur Registrierung sollen
    window.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        if(urlParams.get('view') === 'register') {
            switchView('register');
        }
    });

    function showError(id, msg) {
        const el = document.getElementById(id);
        el.innerText = msg;
        el.style.display = 'block';
    }
</script>
</body>
</html>
