<?php
// Einfacher Logger Helper

function log_audit($user_id, $event_type, $details = null) {
    global $pdo;

    if (!$pdo) {
        // Fallback falls $pdo nicht global verfügbar ist (sollte aber durch db.php gegeben sein)
        return;
    }

    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        // IPv6 kann lang sein, wir kürzen sicherheitshalber auf 45 Zeichen
        $ip = substr($ip, 0, 45); 
        
        // Details kürzen
        if ($details && strlen($details) > 255) {
            $details = substr($details, 0, 252) . '...';
        }

        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, event_type, ip_address, details) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $event_type, $ip, $details]);
    } catch (Exception $e) {
        // Logging sollte niemals den Hauptprozess stoppen
        error_log("Audit Log Error: " . $e->getMessage());
    }
}
