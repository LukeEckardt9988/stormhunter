<?php
require_once 'config.php'; // Stellt Session und ggf. $pdo bereit

// Wenn Benutzer bereits eingeloggt ist und einer Gemeinde angehört, 
// direkt zur Bibelleseseite weiterleiten.
if (isset($_SESSION['user_id']) && isset($_SESSION['community_id'])) {
    header('Location: bibel_lesen.php');
    exit();
}
// Wenn Benutzer eingeloggt, aber keiner Gemeinde angehört (sollte nach Registrierung nicht passieren, aber als Fallback)
if (isset($_SESSION['user_id']) && !isset($_SESSION['community_id'])) {
    // Hier könnte eine Seite "Gemeinde auswählen/erstellen" angezeigt werden
    // Fürs Erste leiten wir zur Gemeindeerstellung
    header('Location: create_community.php?notice=no_community');
    exit();
}

$pageTitle = "Willkommen bei der Bibel-App";
// Da dies eine Seite vor dem Login ist, wird der Standard-Header ohne Benutzerinfos benötigt.
// Wir könnten eine separate, schlankere Header-Variante erstellen oder den bestehenden anpassen.
// Fürs Erste verwenden wir den bestehenden, $loggedInUserId etc. werden dort mit ?? 0 initialisiert.
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css"> <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: #f8f9fa; /* Heller Hintergrund für die Willkommensseite */
        }
        .welcome-container {
            max-width: 500px;
            width: 100%;
            padding: 2rem;
            background-color: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15);
        }
        .welcome-container .logo {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .welcome-container .logo .bi-book-half {
            font-size: 2.5rem;
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="welcome-container">
        <div class="logo">
            <i class="bi bi-book-half"></i> Bibel-App
        </div>
        <h2 class="text-center mb-4">Gemeinsam entdecken</h2>
        
        <?php if (isset($_GET['community_created'])): ?>
            <div class="alert alert-success">Gemeinde erfolgreich erstellt! Sie können sich jetzt einloggen.</div>
        <?php endif; ?>
        <?php if (isset($_GET['registered'])): ?>
            <div class="alert alert-success">Registrierung erfolgreich! Bitte loggen Sie sich ein, um Ihrer Gemeinde beizutreten oder eine auszuwählen.</div>
        <?php endif; ?>

        <div class="d-grid gap-3">
            <a href="login.php" class="btn btn-primary btn-lg">Login</a>
            <a href="register.php" class="btn btn-outline-secondary btn-lg">Registrieren & Gemeinde beitreten</a>
            <a href="create_community.php" class="btn btn-outline-success btn-lg">Neue Gemeinde gründen</a>
        </div>
        <p class="text-center mt-4 text-muted">
            Entdecken Sie die Bibel gemeinsam mit Ihrer Gemeinde.
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
