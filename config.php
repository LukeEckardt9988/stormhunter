<?php
// config.php

// Fehlermeldungen aktivieren (für die Entwicklung sehr nützlich)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Session starten (wichtig für Login-Status)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


//////////////////////////////////////////////////////////////////////////////////////////////
define('ADMIN_POST_PASSWORD_HASH', '$2y$10$2HVddOoKXAVEDzKvqOAmgOOMx3jnzICJjbJxivcNDJDqcfmSPEZ0q');

// Die user_id, die als Admin für das Posten von News gilt (Ihre user_id)
define('NEWS_ADMIN_USER_ID', 1); // Ihre User-ID
///////////////////////////////////////////////////////////////////////////////////////////////



// Datenbank-Zugangsdaten
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'dbs14268018'); // Name Ihrer Datenbank

// Globale PDO-Verbindungsvariable
$pdo = null;

// Funktion, um eine PDO-Verbindung zu erhalten und zu speichern
function get_db_connection() {
    global $pdo;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
        } catch (PDOException $e) {
            // Im Produktivbetrieb sollte hier eine benutzerfreundlichere Fehlermeldung stehen
            // oder das Logging in eine Datei erfolgen, anstatt die Verbindungsinfos preiszugeben.
            error_log("Datenbankverbindungsfehler: " . $e->getMessage());
            die("FEHLER: Konnte keine Verbindung zur Datenbank herstellen. Bitte versuchen Sie es später erneut.");
        }
    }
    return $pdo;
}

// Stellt sicher, dass die Verbindung einmalig hergestellt wird, wenn diese Datei geladen wird.
$pdo = get_db_connection();

// Hilfsfunktion zum sicheren Löschen von Remember-Me-Tokens und Cookies
function clearRememberMeCookieAndToken($pdo_conn, $selector_to_delete = null) {
    if ($selector_to_delete) {
        try {
            $stmt = $pdo_conn->prepare("DELETE FROM auth_tokens WHERE selector = ?");
            $stmt->execute([$selector_to_delete]);
        } catch (PDOException $e) {
            error_log("Fehler beim Löschen des Auth-Tokens: " . $e->getMessage());
        }
    }
    // Cookie löschen, indem die Ablaufzeit in die Vergangenheit gesetzt wird
    setcookie('remember_me_token', '', time() - 3600, "/", "", isset($_SERVER["HTTPS"]), true);
}

// --- NEU: "Remember Me" Logik ---
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me_token'])) {
    list($selector, $validator) = explode(':', $_COOKIE['remember_me_token'], 2);

    if (!empty($selector) && !empty($validator)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM auth_tokens WHERE selector = ? AND expires >= NOW()");
            $stmt->execute([$selector]);
            $token_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($token_data && password_verify($validator, $token_data['hashed_validator'])) {
                // Token ist gültig! Logge den Benutzer ein.
                $stmtUser = $pdo->prepare("SELECT u.user_id, u.username, u.email, u.community_id, c.community_name 
                                           FROM users u 
                                           LEFT JOIN communities c ON u.community_id = c.community_id 
                                           WHERE u.user_id = ?");
                $stmtUser->execute([$token_data['user_id']]);
                $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    session_regenerate_id(true); // Wichtig für die Sicherheit: Session neu generieren

                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    // $_SESSION['email'] = $user['email']; // Email in Session speichern, falls benötigt
                    $_SESSION['community_id'] = $user['community_id'];
                    $_SESSION['community_name'] = $user['community_name'];

                    // Token Rotation für erhöhte Sicherheit (alten Token löschen, neuen erstellen)
                    $stmtDeleteOld = $pdo->prepare("DELETE FROM auth_tokens WHERE id = ?");
                    $stmtDeleteOld->execute([$token_data['id']]);

                    $new_selector = bin2hex(random_bytes(16));
                    $new_validator = bin2hex(random_bytes(32));
                    $new_hashed_validator = password_hash($new_validator, PASSWORD_DEFAULT);
                    $expires_seconds = 60 * 60 * 24 * 30; // 30 Tage
                    $new_expires_datetime = date('Y-m-d H:i:s', time() + $expires_seconds);

                    $stmtInsertNew = $pdo->prepare("INSERT INTO auth_tokens (selector, hashed_validator, user_id, expires) VALUES (?, ?, ?, ?)");
                    $stmtInsertNew->execute([$new_selector, $new_hashed_validator, $user['user_id'], $new_expires_datetime]);
                    
                    setcookie('remember_me_token', $new_selector . ':' . $new_validator, time() + $expires_seconds, "/", "", isset($_SERVER["HTTPS"]), true);
                } else {
                    // Benutzer zum Token nicht gefunden, Token ungültig machen
                    clearRememberMeCookieAndToken($pdo, $selector);
                }
            } else {
                // Validator stimmt nicht überein oder Token nicht gefunden/abgelaufen
                clearRememberMeCookieAndToken($pdo, $selector);
            }
        } catch (PDOException $e) {
            error_log("Datenbankfehler bei Remember-Me-Prüfung: " . $e->getMessage());
            // Cookie sicherheitshalber löschen, falls ein DB-Fehler auftritt
            setcookie('remember_me_token', '', time() - 3600, "/", "", isset($_SERVER["HTTPS"]), true);
        }
    } else {
        // Cookie-Format ungültig
        setcookie('remember_me_token', '', time() - 3600, "/", "", isset($_SERVER["HTTPS"]), true);
    }
}
?>