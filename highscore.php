<?php
require_once 'config.php';

$loggedInUserId = $_SESSION['user_id'] ?? 0;
$loggedInUsername = $_SESSION['username'] ?? 'Gast';

// Definition der Ränge
function getRankTitle(int $highlightCount): string {
    if ($highlightCount >= 2500) return "Apostel";
    if ($highlightCount >= 1000) return "Missionar";
    if ($highlightCount >= 500) return "Prediger";
    if ($highlightCount >= 200) return "Diakon";
    if ($highlightCount >= 100) return "Erwachsen";
    if ($highlightCount >= 50) return "Jüngling";
    return "Bibelleser"; // Standard für < 50
}

$stmt = $pdo->prepare("
    SELECT
        u.user_id,
        u.username,
        COUNT(DISTINCT uh.global_verse_id) AS total_highlights
    FROM
        users u
    LEFT JOIN -- LEFT JOIN, um auch User ohne Markierungen listen zu können (optional)
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

<h1 class="mb-2"><?php echo $pageTitle; ?> (Top 50)</h1>
<p class="lead mb-4">Wer hat die meisten Verse farblich hervorgehoben und welchen Rang erreicht?</p>



<?php if (empty($highscoreData)): ?>
    <div class="alert alert-info">
        Noch keine Markierungen vorhanden, um einen Highscore zu erstellen.
    </div>
<?php else: ?>
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th scope="col">#</th>
                <th scope="col">Benutzername</th>
                <th scope="col">Rang</th>
                <th scope="col" class="text-end">Markierte Verse</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($highscoreData as $index => $entry): ?>
                <tr>
                    <th scope="row"><?php echo $index + 1; ?></th>
                    <td><?php echo htmlspecialchars($entry['username']); ?></td>
                    <td><?php echo getRankTitle((int)$entry['total_highlights']); ?></td>
                    <td class="text-end"><?php echo $entry['total_highlights']; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
require_once 'footer.php';
?>
