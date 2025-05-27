<?php
// header.php
if (session_status() == PHP_SESSION_NONE) session_start();
$loggedInUserId = $_SESSION['user_id'] ?? 0;
$loggedInUsername = $_SESSION['username'] ?? 'Gast';
$loggedInCommunityName = $_SESSION['community_name'] ?? null; // Holt den Gemeindenamen aus der Session
$pageTitle = $pageTitle ?? 'Bibel-App';

// PHP-Logik für Rangberechnung
$userHighlightCount = 0;
$currentRankTitle = "Bibelleser";
$nextRankTitle = "Jüngling";
$pointsToReachNextRank = 50;
$pointsForCurrentRankStart = 0;
$progressPercent = 0;
$progressText = "";

// Stellt sicher, dass $pdo verfügbar ist, bevor es verwendet wird.
// $pdo wird normalerweise in config.php initialisiert, das VOR dem Header inkludiert werden sollte.
if ($loggedInUserId !== 0 && isset($pdo)) {
    $ranks = [
        ['title' => 'Bibelleser', 'min_points' => 0,    'next_target_points' => 50,   'next_rank_title' => 'Jüngling'],
        ['title' => 'Jüngling',  'min_points' => 50,   'next_target_points' => 100,  'next_rank_title' => 'Erwachsen'],
        ['title' => 'Erwachsen', 'min_points' => 100,  'next_target_points' => 200,  'next_rank_title' => 'Diakon'],
        ['title' => 'Diakon',    'min_points' => 200,  'next_target_points' => 500,  'next_rank_title' => 'Prediger'],
        ['title' => 'Prediger',  'min_points' => 500,  'next_target_points' => 1000, 'next_rank_title' => 'Missionar'],
        ['title' => 'Missionar', 'min_points' => 1000, 'next_target_points' => 2500, 'next_rank_title' => 'Apostel'],
        ['title' => 'Apostel',   'min_points' => 2500, 'next_target_points' => null,  'next_rank_title' => 'Apostel'],
    ];

    $stmtCount = $pdo->prepare("SELECT COUNT(DISTINCT global_verse_id) AS total_highlights FROM user_highlights WHERE user_id = ?");
    $stmtCount->execute([$loggedInUserId]);
    $result = $stmtCount->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $userHighlightCount = (int)$result['total_highlights'];
    }

    foreach (array_reverse($ranks, true) as $rankDetails) {
        if ($userHighlightCount >= $rankDetails['min_points']) {
            $currentRankTitle = $rankDetails['title'];
            $pointsForCurrentRankStart = $rankDetails['min_points'];
            $pointsToReachNextRank = $rankDetails['next_target_points'];
            $nextRankTitle = $rankDetails['next_rank_title'];
            break;
        }
    }

    if ($pointsToReachNextRank === null) {
        $progressPercent = 100;
        $progressText = sprintf("%d Verse (%s)", $userHighlightCount, $currentRankTitle);
    } else {
        $pointsEarnedInCurrentSegment = $userHighlightCount - $pointsForCurrentRankStart;
        $pointsNeededForNextSegment = $pointsToReachNextRank - $pointsForCurrentRankStart;
        if ($pointsNeededForNextSegment > 0) {
            $progressPercent = ($pointsEarnedInCurrentSegment / $pointsNeededForNextSegment) * 100;
        } else {
            $progressPercent = ($userHighlightCount >= $pointsForCurrentRankStart && $pointsForCurrentRankStart < $pointsToReachNextRank) ? 100 : 0;
            if ($userHighlightCount >= $pointsToReachNextRank) $progressPercent = 100;
        }
        $progressPercent = min(100, max(0, $progressPercent));
        $progressText = sprintf("%d / %d", $userHighlightCount, $pointsToReachNextRank);
    }
}

?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script>
        (function() {
            try {
                const theme = localStorage.getItem('theme');
                if (theme === 'dark') {
                    document.documentElement.classList.add('dark-mode');
                } else {
                    document.documentElement.classList.remove('dark-mode');
                }
            } catch (e) {
                console.warn('Anti-Flicker: Fehler beim Zugriff auf localStorage für Theme:', e);
            }
        })();
    </script>

    <title><?php echo htmlspecialchars($pageTitle ?? 'Bibel-App'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">

</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-book-half"></i> Bibel-App
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'bibel_lesen.php' ? 'active' : ''); ?>" href="bibel_lesen.php"><i class="bi bi-eyeglasses"></i> Bibel lesen</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'suche.php' ? 'active' : ''); ?>" href="suche.php"><i class="bi bi-search"></i> Wortsuche</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'farblegende.php' ? 'active' : ''); ?>" href="farblegende.php"><i class="bi bi-palette"></i> Farblegende</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'highscore.php' ? 'active' : ''); ?>" href="highscore.php"><i class="bi bi-trophy"></i> Highscore</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'kommentare_uebersicht.php' ? 'active' : ''); ?>" href="kommentare_uebersicht.php"><i class="bi bi-chat-left-text"></i> Kommentare</a>
                    </li>
                    <?php if ($loggedInUserId !== 0): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'meine_markierungen.php' ? 'active' : ''); ?>" href="meine_markierungen.php"><i class="bi bi-star-fill"></i> Meine wichtigen Verse</a>
                        </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav ms-auto align-items-center">
                    <?php if ($loggedInUserId !== 0): ?>
                        <li class="nav-item me-md-3 mb-2 mb-md-0 order-md-first">
                            <div class="rank-info text-white">
                                <span>Rang: <?php echo htmlspecialchars($currentRankTitle); ?></span>
                                <div class="progress mt-1" style="height: 10px; width: 150px;" title="Fortschritt zu: <?php echo htmlspecialchars($nextRankTitle); ?> (<?php echo $pointsToReachNextRank ?? $userHighlightCount; ?> Verse)">
                                    <div class="progress-bar progress-bar-rank bg-success" role="progressbar" style="width: <?php echo $progressPercent; ?>%;" aria-valuenow="<?php echo $progressPercent; ?>" aria-valuemin="0" aria-valuemax="100">
                                    </div>
                                </div>
                                <?php if ($pointsToReachNextRank !== null && $currentRankTitle !== $nextRankTitle): ?>
                                    <div style="font-size: 0.7rem;"><?php echo htmlspecialchars($progressText); ?> bis <?php echo htmlspecialchars($nextRankTitle); ?></div>
                                <?php elseif ($currentRankTitle === $nextRankTitle && $pointsToReachNextRank === null): ?>
                                    <div style="font-size: 0.7rem;"><?php echo htmlspecialchars($currentRankTitle); ?> erreicht! (<?php echo $userHighlightCount; ?> Verse)</div>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item me-2">
                        <button id="theme-toggle-btn" class="btn btn-outline-light btn-sm" type="button" title="Hell-/Dunkelmodus umschalten">
                            <i class="bi bi-sun-fill theme-icon-sun"></i>
                            <i class="bi bi-moon-fill theme-icon-moon d-none"></i>
                        </button>
                    </li>
                    <?php if ($loggedInUserId !== 0): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarUserDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($loggedInUsername ?? 'Benutzer'); ?>
                                <?php if (!empty($loggedInCommunityName)): // ANZEIGE DES GEMEINDENAMENS 
                                ?>
                                    <small class="text-white-50 ms-1">(<?php echo htmlspecialchars($loggedInCommunityName); ?>)</small>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarUserDropdown">
                                <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'login.php' ? 'active' : ''); ?>" href="login.php">Login</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'register.php' ? 'active' : ''); ?>" href="register.php">Registrieren</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <?php if ($loggedInUserId !== 0): ?>
        <div class="progress-container-wrapper">
            <div class="container">
                <div class="rank-progress-bar">
                    <div class="rank-progress-info">
                        <span>Aktueller Rang: <strong><?php echo htmlspecialchars($currentRankTitle); ?></strong></span>
                        <span><?php echo htmlspecialchars($progressText); ?></span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $progressPercent; ?>%;" aria-valuenow="<?php echo $progressPercent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <?php if ($pointsToReachNextRank !== null && $currentRankTitle !== $nextRankTitle): ?>
                        <div class="next-rank-display">Nächstes Ziel: <strong><?php echo htmlspecialchars($nextRankTitle); ?></strong> bei <?php echo $pointsToReachNextRank; ?> Versen</div>
                    <?php elseif ($currentRankTitle === $nextRankTitle && $pointsToReachNextRank === null): ?>
                        <div class="next-rank-display">Maximaler Rang erreicht!</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="container mt-4 main-content">