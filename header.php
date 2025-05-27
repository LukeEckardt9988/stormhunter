<?php
// header.php
// Stellt sicher, dass config.php (und damit die Session) geladen ist,
// bevor irgendetwas anderes passiert.
if (session_status() == PHP_SESSION_NONE) { // Nur wenn nicht schon in config.php geschehen
    require_once 'config.php';
}

// Variablen für die Navigationsleiste, die in config.php gesetzt werden könnten
$loggedInUserId = $_SESSION['user_id'] ?? 0;
$loggedInUsername = $_SESSION['username'] ?? 'Gast';

// Titel für die aktuelle Seite (kann in den einzelnen Seiten überschrieben werden)
$pageTitle = $pageTitle ?? 'Bibel-App'; // Standardtitel
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding-top: 70px; /* Platz für die fixe Navbar */ }
        .verse-container { line-height: 1.8; }
        .verse { 
            cursor: pointer; 
            padding: 2px 4px; 
            border-radius: 3px; 
            transition: background-color 0.2s; 
            display: inline;
        }
        .verse:hover { background-color: #f0f0f0; }
        .verse-num { font-size: 0.7em; font-weight: bold; color: #888; vertical-align: super; margin-right: 5px;}
        .verse.bg-warning { background-color: #fff3cd !important; } 
        .navbar { margin-bottom: 1rem; } 
        .popover { max-width: 280px; }
        .popover .list-group-item { padding: 0.5rem 1rem; }
        .search-result-text strong { background-color: yellow; } /* Für suche.php */
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container">
        <a class="navbar-brand" href="index.php"> 
            Bibel-App
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'bibel_lesen.php' ? 'active' : ''); ?>" href="bibel_lesen.php">Bibel lesen</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'suche.php' ? 'active' : ''); ?>" href="suche.php">Wortsuche</a>
                </li>
                </ul>
            <ul class="navbar-nav ms-auto">
                <?php if ($loggedInUserId !== 0): ?>
                    <li class="nav-item">
                        <span class="navbar-text me-3">
                            Willkommen, <?php echo htmlspecialchars($loggedInUsername); ?>!
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Registrieren</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4">


