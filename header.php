<?php
// header.php
if (session_status() == PHP_SESSION_NONE) session_start();
$loggedInUserId = $_SESSION['user_id'] ?? 0;
$loggedInUsername = $_SESSION['username'] ?? 'Gast';
$loggedInCommunityName = $_SESSION['community_name'] ?? null; // Holt den Gemeindenamen aus der Session
$pageTitle = $pageTitle ?? 'Ohael Bibel-App';

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

    <meta name="theme-color" content="#212529">

    <link rel="manifest" href="manifest.json">

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

    <title><?php echo htmlspecialchars($pageTitle ?? 'Ohael Bibel-App'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">

</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
           <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="512ohneBG.png" alt="Ohael Bibel-App Logo" class="navbar-brand-logo me-2"> 
                
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'bibel_lesen.php' ? 'active' : ''); ?> d-flex flex-column align-items-center text-center px-2" href="bibel_lesen.php" aria-label="Bibel lesen" title="Bibel lesen">
                            <i class="bi bi-eyeglasses" style="font-size: 1.5rem;"></i>
                            <span class="nav-link-text">Lesen</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'suche.php' ? 'active' : ''); ?> d-flex flex-column align-items-center text-center px-2" href="suche.php" aria-label="Wortsuche" title="Wortsuche">
                            <i class="bi bi-search" style="font-size: 1.5rem;"></i>
                            <span class="nav-link-text">Suchen</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'farblegende.php' ? 'active' : ''); ?> d-flex flex-column align-items-center text-center px-2" href="farblegende.php" aria-label="Farblegende" title="Farblegende">
                            <i class="bi bi-palette" style="font-size: 1.5rem;"></i>
                            <span class="nav-link-text">Farben</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'highscore.php' ? 'active' : ''); ?> d-flex flex-column align-items-center text-center px-2" href="highscore.php" aria-label="Highscore" title="Highscore">
                            <i class="bi bi-trophy" style="font-size: 1.5rem;"></i>
                            <span class="nav-link-text">Score</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'status.php' ? 'active' : ''); ?> d-flex flex-column align-items-center text-center px-2" href="status.php" aria-label="Fortschritt" title="Fortschritt">
                            <i class="bi bi-check-circle-fill" style="font-size: 1.5rem;"></i>
                            <span class="nav-link-text">Status</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'kommentare_uebersicht.php' ? 'active' : ''); ?> d-flex flex-column align-items-center text-center px-2" href="kommentare_uebersicht.php" aria-label="Kommentare" title="Kommentare">
                            <i class="bi bi-chat-left-text" style="font-size: 1.5rem;"></i>
                            <span class="nav-link-text">Posts</span>
                        </a>
                    </li>
                    <?php if ($loggedInUserId !== 0): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'meine_markierungen.php' ? 'active' : ''); ?> d-flex flex-column align-items-center text-center px-2" href="meine_markierungen.php" aria-label="Meine wichtigen Verse" title="Meine wichtigen Verse">
                                <i class="bi bi-star-fill" style="font-size: 1.5rem;"></i>
                                <span class="nav-link-text">Wichtig</span>
                            </a>
                        </li>
                    <?php endif; ?>
               
                    
                    <?php if ($loggedInUserId !== 0): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarUserDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($loggedInUsername ?? 'Benutzer'); ?>
                                <?php if (!empty($loggedInCommunityName)): ?>
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
                    <li class="nav-item me-2">
                        <button id="theme-toggle-btn" class="btn btn-outline-light btn-sm" type="button" title="Hell-/Dunkelmodus umschalten">
                            <i class="bi bi-sun-fill theme-icon-sun"></i>
                            <i class="bi bi-moon-fill theme-icon-moon d-none"></i>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <?php if ($loggedInUserId !== 0): ?>
       
    <?php endif; ?>

    <div class="container mt-4 main-content">
 

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