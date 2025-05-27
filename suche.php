<?php
require_once 'config.php'; // Lädt Konfiguration, startet Session und stellt $pdo bereit

$loggedInUserId = $_SESSION['user_id'] ?? 0;
$loggedInUsername = $_SESSION['username'] ?? 'Gast';
$pageTitle = "Wortsuche in der Bibel";

$suchbegriff = isset($_GET['q']) ? trim($_GET['q']) : '';
$suchergebnisse = [];
$anzahlErgebnisse = 0;
$gesuchteVersionId = isset($_GET['version_id']) ? (int)$_GET['version_id'] : 1; // Standardmäßig in Version 1 suchen

// --- Versionen für das Filter-Dropdown holen (immer laden) ---
$stmtFilterVersions = $pdo->prepare("SELECT version_id, version_name FROM versions ORDER BY version_name ASC");
$stmtFilterVersions->execute();
$filterVersions = $stmtFilterVersions->fetchAll(PDO::FETCH_ASSOC);

if (!empty($suchbegriff)) {
    $searchQuery = "SELECT 
                        v.verse_text, 
                        v.verse_number, 
                        v.chapter_number, 
                        v.global_verse_id, -- Wichtig für spätere Interaktionen
                        b.book_name_de, 
                        b.book_id,
                        ver.version_abbr,
                        ver.version_id AS result_version_id
                    FROM verses v
                    JOIN books b ON v.book_id = b.book_id
                    JOIN versions ver ON v.version_id = ver.version_id
                    WHERE v.verse_text LIKE :suchbegriff
                    AND v.version_id = :version_id
                    ORDER BY b.book_order ASC, v.chapter_number ASC, v.verse_number ASC
                    LIMIT 200"; // Limit für die Anzahl der Ergebnisse
    
    $stmtSuche = $pdo->prepare($searchQuery);
    $stmtSuche->execute([
        ':suchbegriff' => '%' . $suchbegriff . '%',
        ':version_id' => $gesuchteVersionId
    ]);
    $suchergebnisse = $stmtSuche->fetchAll(PDO::FETCH_ASSOC);
    $anzahlErgebnisse = count($suchergebnisse);
}

require_once 'header.php'; // Lädt den Header
?>

<h1 class="mb-4">Wortsuche in der Bibel</h1>

<form method="GET" action="suche.php" class="mb-4 card card-body bg-light">
    <div class="row g-3 align-items-end">
        <div class="col-lg-6 col-md-12 mb-2 mb-lg-0">
            <label for="search_query" class="form-label">Suchbegriff:</label>
            <input type="text" name="q" id="search_query" class="form-control" placeholder="Geben Sie ein Wort oder eine Phrase ein..." value="<?php echo htmlspecialchars($suchbegriff); ?>" required>
        </div>
        <div class="col-lg-4 col-md-6 mb-2 mb-lg-0">
            <label for="search_version_id" class="form-label">Bibelübersetzung:</label>
            <select name="version_id" id="search_version_id" class="form-select">
                <?php if (!empty($filterVersions)): ?>
                    <?php foreach ($filterVersions as $version): ?>
                        <option value="<?php echo $version['version_id']; ?>" <?php echo ($version['version_id'] == $gesuchteVersionId) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($version['version_name']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option value="">Keine Übersetzungen verfügbar</option>
                <?php endif; ?>
            </select>
        </div>
        <div class="col-lg-2 col-md-6 d-grid">
            <button type="submit" class="btn btn-primary">Suchen</button>
        </div>
    </div>
</form>

<?php if (!empty($suchbegriff)): ?>
    <hr>
    <h3 class="h5 mt-4">
        <?php if ($anzahlErgebnisse > 0): ?>
            <?php echo $anzahlErgebnisse; ?> Treffer für "<?php echo htmlspecialchars($suchbegriff); ?>"
        <?php else: ?>
            Keine Treffer für "<?php echo htmlspecialchars($suchbegriff); ?>"
        <?php endif; ?>
        in der ausgewählten Übersetzung.
    </h3>
    
    <?php if ($anzahlErgebnisse > 0): ?>
        <div class="list-group mt-3">
            <?php foreach ($suchergebnisse as $ergebnis): ?>
                <div class="list-group-item" id="search-result-item-<?php echo $ergebnis['global_verse_id']; ?>"> <p class="mb-1">
                        <a href="bibel_lesen.php?version_id=<?php echo $ergebnis['result_version_id']; ?>&book_id=<?php echo $ergebnis['book_id']; ?>&chapter=<?php echo $ergebnis['chapter_number']; ?>#vers-<?php echo $ergebnis['verse_number']; ?>" target="_blank" title="Diesen Vers in der Leseansicht öffnen">
                            <strong><?php echo htmlspecialchars($ergebnis['book_name_de'] . ' ' . $ergebnis['chapter_number'] . ',' . $ergebnis['verse_number']); ?></strong>
                        </a>
                        <small class="text-muted">(<?php echo htmlspecialchars($ergebnis['version_abbr']); ?>)</small>
                    </p>
                    <div class="search-result-text" data-highlight-target="<?php echo $ergebnis['global_verse_id']; ?>"> 
                        <?php 
                        echo preg_replace(
                            '/(' . preg_quote($suchbegriff, '/') . ')/i', 
                            '<strong class="bg-warning px-1">$1</strong>', 
                            htmlspecialchars($ergebnis['verse_text'])
                        ); 
                        ?>
                    </div>
                    <div class="mt-2">
                        <button class="btn btn-sm btn-outline-secondary" onclick="handleCompareVerse(<?php echo $ergebnis['global_verse_id']; ?>, '<?php echo htmlspecialchars(addslashes($ergebnis['book_name_de'] . ' ' . $ergebnis['chapter_number'])); ?>', '<?php echo $ergebnis['verse_number']; ?>')">Vergleichen</button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="handleHighlightVerse(this, <?php echo $ergebnis['global_verse_id']; ?>)">Markieren</button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="handleShowComments(<?php echo $ergebnis['global_verse_id']; ?>, '<?php echo htmlspecialchars(addslashes($ergebnis['book_name_de'] . ' ' . $ergebnis['chapter_number'])); ?>', '<?php echo $ergebnis['verse_number']; ?>')">Kommentare</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($anzahlErgebnisse >= 200): ?>
            <p class="mt-3 text-muted">Es wurden die ersten 200 Treffer angezeigt. Bitte verfeinern Sie Ihre Suche, um spezifischere Ergebnisse zu erhalten.</p>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

<div class="modal fade" id="compareModal" tabindex="-1" aria-labelledby="compareModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="compareModalLabel">Versvergleich</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="compareModalBody"></div>
    </div>
  </div>
</div>

<div class="modal fade" id="commentsModal" tabindex="-1" aria-labelledby="commentsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="commentsModalLabel">Kommentare für Vers</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="commentsModalBody"></div>
    </div>
  </div>
</div>

<?php
require_once 'footer.php'; // Lädt den Footer (inkl. JavaScript-Dateien)
?>