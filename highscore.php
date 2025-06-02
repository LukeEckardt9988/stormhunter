<?php
require_once 'config.php'; // Lädt Konfiguration, $pdo

$loggedInUserId = $_SESSION['user_id'] ?? 0;
$loggedInUsername = $_SESSION['username'] ?? 'Gast';

// Definition der Ränge und ihrer zugehörigen Badge-Klassen
// Die text-dark/text-white Klassen sind hier als Standard für den Hell-Modus.
// Ihre styles.css kümmert sich um die korrekte Textfarbe im Dark Mode für die .bg-* Klassen.
function getRankInfo(int $highlightCount): array {
    if ($highlightCount >= 2500) return ['title' => "Apostel",   'badge_class' => 'bg-danger text-white', 'icon' => 'gem']; // Höchster Rang
    if ($highlightCount >= 1000) return ['title' => "Missionar", 'badge_class' => 'bg-warning text-dark', 'icon' => 'stars'];
    if ($highlightCount >= 500)  return ['title' => "Prediger",  'badge_class' => 'bg-info text-dark',    'icon' => 'mic-fill'];
    if ($highlightCount >= 200)  return ['title' => "Diakon",    'badge_class' => 'bg-primary text-white','icon' => 'shield-check'];
    if ($highlightCount >= 100)  return ['title' => "Erwachsen", 'badge_class' => 'bg-success text-white','icon' => 'person-check-fill'];
    if ($highlightCount >= 50)   return ['title' => "Jüngling",  'badge_class' => 'bg-secondary text-white','icon' => 'person-fill'];
    return ['title' => "Bibelleser", 'badge_class' => 'bg-light text-dark',   'icon' => 'book-half']; // Standard
}

$stmt = $pdo->prepare("
    SELECT
        u.user_id,
        u.username,
        COUNT(DISTINCT uh.global_verse_id) AS total_highlights
    FROM
        users u
    LEFT JOIN
        user_highlights uh ON u.user_id = uh.user_id
    GROUP BY
        u.user_id, u.username
    ORDER BY
        total_highlights DESC, u.username ASC
    LIMIT 50
");
$stmt->execute();
$highscoreData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Markierungs-Highscore";
require_once 'header.php';
?>

<div class="container mt-4">
    <h1 class="mb-2 display-5"><?php echo $pageTitle; ?> <small class="text-muted fs-5">(Top 50)</small></h1>
    <p class="lead mb-4">Wer hat die meisten Verse farblich hervorgehoben und welchen Rang erreicht?</p>

    <?php if (empty($highscoreData)): ?>
        <div class="alert alert-info text-center">
            <i class="bi bi-info-circle-fill me-2 fs-4"></i>
            Noch keine Markierungen vorhanden, um einen Highscore zu erstellen.
        </div>
    <?php else: ?>
        <div class="table-responsive shadow-sm mb-5">
            <table class="table table-hover align-middle highscore-table">
                <thead class="sticky-top"> 
                    <tr>
                        <th scope="col" style="width: 5%;">#</th>
                        <th scope="col" style="width: 40%;">Name</th>
                        <th scope="col" style="width: 25%;">Rang</th>
                        <th scope="col" class="text-end" style="width: 25%;">Verse</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($highscoreData as $index => $entry): ?>
                        <?php
                        $rankInfo = getRankInfo((int)$entry['total_highlights']);
                        $isCurrentUser = ($loggedInUserId === $entry['user_id']);
                        $rowClass = $isCurrentUser ? 'current-user-highlight' : '';

                        $platz = $index + 1;
                        $platzDisplay = $platz;
                        if ($platz === 1) $platzDisplay = '<i class="bi bi-trophy-fill text-warning"></i> 1';
                        elseif ($platz === 2) $platzDisplay = '<i class="bi bi-trophy-fill text-secondary"></i> 2';
                        elseif ($platz === 3) $platzDisplay = '<i class="bi bi-trophy-fill text-danger-emphasis"></i> 3';
                        ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <th scope="row" class="fw-normal text-center"><?php echo $platzDisplay; ?></th>
                            <td>
                                <i class="bi bi-person-circle me-2 text-muted"></i>
                                <?php echo htmlspecialchars($entry['username']); ?>
                            </td>
                            <td>
                                <span class="badge rounded-pill <?php echo $rankInfo['badge_class']; ?> p-2" style="font-size: 0.8rem;">
                                    <i class="bi bi-<?php echo $rankInfo['icon']; ?> me-1"></i><?php echo $rankInfo['title']; ?>
                                </span>
                            </td>
                            <td class="text-end fw-bold fs-5"><?php echo $entry['total_highlights']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
require_once 'footer.php';
?>