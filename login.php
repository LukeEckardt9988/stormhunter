<?php
require_once 'config.php'; // Lädt Konfiguration, $pdo und die Remember-Me-Prüflogik
$message = '';

// Wenn Benutzer bereits eingeloggt ist (entweder durch aktive Session oder durch Remember-Me-Cookie in config.php)
if (isset($_SESSION['user_id'])) {
    if (!empty($_SESSION['community_id'])) {
        header('Location: bibel_lesen.php');
        exit();
    } else {
        // User hat keine Gemeinde, zur Auswahlseite leiten
        header('Location: welcome.php?notice=select_community_after_login');
        exit();
    }
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username_or_email = trim($_POST['username_or_email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']) && $_POST['remember_me'] == '1';

    if (empty($username_or_email) || empty($password)) {
        $message = '<div class="alert alert-danger">Bitte alle Felder ausfüllen.</div>';
    } else {
        try {
            // Hole Benutzerdaten inklusive community_id und community_name
            // Wichtig: Der JOIN muss korrekt auf Ihre Tabellen- und Spaltennamen angepasst sein.
            // Annahme: users.community_id verweist auf communities.community_id
            $sql = "SELECT u.user_id, u.username, u.email, u.password_hash, u.community_id, c.community_name 
                    FROM users u
                    LEFT JOIN communities c ON u.community_id = c.community_id 
                    WHERE u.username = :username_identifier OR u.email = :email_identifier";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':username_identifier' => $username_or_email,
                ':email_identifier' => $username_or_email
            ]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                // Login erfolgreich, Session-Variablen setzen
                session_regenerate_id(true); // Neue Session-ID zur Sicherheit

                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                // $_SESSION['email'] = $user['email']; // Falls benötigt
                $_SESSION['community_id'] = $user['community_id'];
                $_SESSION['community_name'] = $user['community_name']; // Wird direkt aus dem JOIN geholt

                // NEU: "Remember Me" Funktionalität
                if ($remember_me) {
                    $selector = bin2hex(random_bytes(16));
                    $validator = bin2hex(random_bytes(32));
                    $hashed_validator = password_hash($validator, PASSWORD_DEFAULT);
                    
                    // Lebensdauer des Cookies und Tokens (z.B. 30 Tage)
                    $expires_seconds = 60 * 60 * 24 * 30; 
                    $expires_datetime = date('Y-m-d H:i:s', time() + $expires_seconds);

                    // Alte Tokens für diesen Benutzer löschen, um die Tabelle sauber zu halten
                    $stmtDeleteOld = $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ?");
                    $stmtDeleteOld->execute([$user['user_id']]);

                    // Neuen Token in der Datenbank speichern
                    $stmtInsert = $pdo->prepare("INSERT INTO auth_tokens (selector, hashed_validator, user_id, expires) VALUES (?, ?, ?, ?)");
                    $stmtInsert->execute([$selector, $hashed_validator, $user['user_id'], $expires_datetime]);

                    // Cookie setzen
                    setcookie('remember_me_token', $selector . ':' . $validator, time() + $expires_seconds, "/", "", isset($_SERVER["HTTPS"]), true);
                }

                // Weiterleitung basierend auf Community-Zugehörigkeit
                if (!empty($user['community_id'])) {
                    header('Location: bibel_lesen.php');
                    exit();
                } else {
                    header('Location: welcome.php?notice=no_community_please_join_or_create');
                    exit();
                }

            } else {
                $message = '<div class="alert alert-danger">Benutzername/E-Mail oder Passwort ungültig.</div>';
            }
        } catch (PDOException $e) {
            error_log("Datenbankfehler beim Login: " . $e->getMessage());
            $message = '<div class="alert alert-danger">Ein Datenbankfehler ist aufgetreten. Bitte versuchen Sie es später erneut.</div>';
        }
    }
}

$pageTitle = "Login - Bibel-App";
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: #f8f9fa; /* Gleicher Hintergrund wie Ihre Welcome-Seite */
        }
        .form-container { /* Wiederverwendet von Welcome-Seite */
            max-width: 450px;
            width: 100%;
            padding: 2rem;
            background-color: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, .15);
        }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="text-center mb-4">
            <i class="bi bi-book-half" style="font-size: 3rem; color: var(--bs-primary);"></i>
            <h2 class="mt-2">Anmelden</h2>
        </div>
        <?php if (!empty($message)) echo $message; ?>
        <form action="login.php" method="POST">
            <div class="mb-3">
                <label for="username_or_email" class="form-label">Benutzername oder E-Mail</label>
                <input type="text" class="form-control" id="username_or_email" name="username_or_email" value="<?php echo htmlspecialchars($_POST['username_or_email'] ?? ''); ?>" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Passwort</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="mb-3 form-check"> 
                <input type="checkbox" class="form-check-input" id="rememberMe" name="remember_me" value="1">
                <label class="form-check-label" for="rememberMe">Angemeldet bleiben</label>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">Einloggen</button>
            </div>
        </form>
        <p class="mt-3 text-center">Noch kein Konto? <a href="register.php">Hier registrieren</a></p>
        <p class="mt-1 text-center"><a href="index.php">Zurück zur Hauptseite</a></p> 
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>