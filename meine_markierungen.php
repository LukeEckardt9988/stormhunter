<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
$loggedInUserId = $_SESSION['user_id'];
$loggedInUsername = $_SESSION['username'] ?? 'Gast';

$stmt = $pdo->prepare("
    SELECT
        v.verse_text,
        v.verse_number,
        v.chapter_number,
        v.global_verse_id, -- Wichtig für den Link
        b.book_name_de,
        b.book_id,
        uiv.marked_at
    FROM
        user_important_verses uiv
    JOIN
        verses v ON uiv.global_verse_id = v.global_verse_id AND v.version_id = 1 -- Annahme: Version 1 für den Text
    JOIN
        books b ON v.book_id = b.book_id
    WHERE
        uiv.user_id = ?
    ORDER BY
        uiv.marked_at DESC -- Neueste zuerst, oder b.book_order, v.chapter_number, etc.
");
$stmt->execute([$loggedInUserId]);
$myImportantVerses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Meine wichtigen Verse";
require_once 'header.php';
?>

<h1 class="mb-4">Meine wichtigen Verse</h1>

<?php if (empty($myImportantVerses)): ?>
    <div class="alert alert-info">
        Sie haben noch keine Verse als "Für mich wichtig" markiert. Klicken Sie in der <a href="bibel_lesen.php" class="alert-link">Bibel-Leseansicht</a> auf einen Vers und wählen Sie die entsprechende Option.
    </div>
<?php else: ?>
    <div class="list-group">
        <?php foreach ($myImportantVerses as $verse): ?>
            <a href="bibel_lesen.php?book_id=<?php echo $verse['book_id']; ?>&chapter=<?php echo $verse['chapter_number']; ?>&highlight_verse=<?php echo $verse['global_verse_id']; ?>#vers-<?php echo $verse['verse_number']; ?>" 
               class="list-group-item list-group-item-action">
                <div class="d-flex w-100 justify-content-between">
                    <h5 class="mb-1"><?php echo htmlspecialchars($verse['book_name_de'] . ' ' . $verse['chapter_number'] . ',' . $verse['verse_number']); ?></h5>
                    <small class="text-muted">Markiert am: <?php echo date("d.m.Y H:i", strtotime($verse['marked_at'])); ?></small>
                </div>
                <p class="mb-1">"<?php echo htmlspecialchars($verse['verse_text']); ?>"</p>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
require_once 'footer.php';
?>
