<?php
require_once 'config.php'; // Lädt Konfiguration und $pdo
$message = '';

// Wenn Benutzer bereits eingeloggt ist UND einer Gemeinde angehört, zur Hauptseite weiterleiten
if (isset($_SESSION['user_id']) && isset($_SESSION['community_id']) && !empty($_SESSION['community_id'])) {
    header('Location: bibel_lesen.php');
    exit();
}
// Wenn Benutzer eingeloggt, aber keiner Gemeinde (mehr) angehört (z.B. falls Gemeinde gelöscht wurde)
if (isset($_SESSION['user_id']) && (empty($_SESSION['community_id']) || $_SESSION['community_id'] === null || $_SESSION['community_id'] === 0) ) {
    // Leite zur Seite, wo der User eine Gemeinde auswählen/erstellen kann
    header('Location: welcome.php?notice=select_community'); 
    exit();
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username_or_email = trim($_POST['username_or_email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username_or_email) || empty($password)) {
        $message = '<div class="alert alert-danger">Bitte alle Felder ausfüllen.</div>';
    } else {
        try {
            // Hole Benutzerdaten inklusive community_id
            $sql = "SELECT user_id, username, password_hash, community_id FROM users WHERE username = :username_identifier OR email = :email_identifier";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':username_identifier' => $username_or_email,
                ':email_identifier' => $username_or_email 
            ]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                // Login erfolgreich, Session-Variablen setzen
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['community_id'] = $user['community_id']; 

                // NEU: Gemeindename holen und in Session speichern
                if (!empty($user['community_id'])) {
                    $stmtCommunityName = $pdo->prepare("SELECT community_name FROM communities WHERE community_id = :cid");
                    $stmtCommunityName->execute([':cid' => $user['community_id']]);
                    $community = $stmtCommunityName->fetch(PDO::FETCH_ASSOC);
                    if ($community) {
                        $_SESSION['community_name'] = $community['community_name'];
                    } else {
                        $_SESSION['community_name'] = null; // Falls Gemeinde aus irgendeinem Grund nicht gefunden wird
                    }
                    // User hat eine Gemeinde, weiter zur App
                    header('Location: bibel_lesen.php');
                    exit();
                } else {
                    // User hat noch keine Gemeinde
                    $_SESSION['community_name'] = null; // Sicherstellen, dass es leer ist
                    header('Location: welcome.php?notice=no_community_please_join_or_create');
                    exit();
                }
            } else {
                $message = '<div class="alert alert-danger">Benutzername/E-Mail oder Passwort ungültig.</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Datenbankfehler beim Login: ' . $e->getMessage() . '</div>';
        }
    }
}

$pageTitle = "Login - Bibel-App"; // Seitentitel für den HTML-Header
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css"> <style> /* Stile für Login/Register/Welcome-Seiten */
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background-color: #f8f9fa; }
        .form-container { max-width: 450px; width:100%; padding: 2rem; background-color: #fff; border-radius: 0.5rem; box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15); }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="text-center mb-4">
            <h2>Anmelden</h2>
        </div>
        <?php if(!empty($message)) echo $message; // Zeige Nachrichten hier an ?>
        <form action="login.php" method="POST">
            <div class="mb-3">
                <label for="username_or_email" class="form-label">Benutzername oder E-Mail</label>
                <input type="text" class="form-control" id="username_or_email" name="username_or_email" value="<?php echo htmlspecialchars($_POST['username_or_email'] ?? ''); ?>" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Passwort</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">Einloggen</button>
            </div>
        </form>
        <p class="mt-3 text-center">Noch kein Konto? <a href="register.php">Hier registrieren</a></p>
        <p class="mt-1 text-center"><a href="welcome.php">Zurück zur Startseite</a></p>
    </div>
</body>
</html>
