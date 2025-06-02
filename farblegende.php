<?php
// config.php wird als Erstes geladen
require_once 'config.php'; 

// Diese Variablen werden für den Header benötigt
$loggedInUserId = $_SESSION['user_id'] ?? 0; 
$loggedInUsername = $_SESSION['username'] ?? 'Gast';
$pageTitle = "Farblegende für Markierungen";

require_once 'header.php'; 

// Definition der Farbmarkierungen
// Diese Struktur sollte mit der in app.js (highlightColors Array) übereinstimmen
$colorLegend = [
    // Existierende Farben
    ['name' => 'Wichtig', 'value' => 'yellow', 'cssClass' => 'bg-warning', 'description' => 'Hervorhebung besonders wichtiger Verse oder Abschnitte.'],
    ['name' => 'Ermutigung & Trost', 'value' => 'green', 'cssClass' => 'bg-green-custom', 'description' => 'Verse die Ermutigen oder in schwierigen Zeiten trost spenden.'],
    ['name' => 'Gebot', 'value' => 'blue', 'cssClass' => 'bg-blue-custom', 'description' => 'Gebote, Anweisungen und Aufforderungen Gottes.'],
    ['name' => 'Sünde / Warnung', 'value' => 'red', 'cssClass' => 'bg-red-custom', 'description' => 'Hinweise auf Sünde, Fehler oder Warnungen.'],
    ['name' => 'Verheißung & Prophetie', 'value' => 'orange', 'cssClass' => 'bg-orange-custom', 'description' => 'Weissagungen, prophetische Worte und zukünftige Ereignisse.'],
    ['name' => 'Geschichte', 'value' => 'brown', 'cssClass' => 'bg-brown-custom', 'description' => 'Historische Berichte, Erzählungen und Genealogien.'],
    ['name' => 'Gleichnis', 'value' => 'parable', 'cssClass' => 'bg-parable-custom', 'description' => 'Bildhafte Erzählungen und Gleichnisse Jesu oder anderer biblischer Figuren.'],
    ['name' => 'Zurechtweisung / Ermahnung', 'value' => 'rebuke', 'cssClass' => 'bg-rebuke-custom', 'description' => 'Korrektur, Tadel oder ernste Ermahnungen.'],
    ['name' => 'Namen (Orte & Personen)', 'value' => 'name', 'cssClass' => 'bg-name-custom', 'description' => 'Hervorhebung von Eigennamen von Personen und Orten.'],
    ['name' => 'Heiligung / Nachfolge', 'value' => 'sanctification', 'cssClass' => 'bg-sanctification-custom', 'description' => 'Aspekte des geistlichen Wachstums, der Jüngerschaft und eines geheiligten Lebens.'],
    ['name' => 'Wunder', 'value' => 'miracle', 'cssClass' => 'bg-miracle-custom', 'description' => 'Übernatürliche Ereignisse, Zeichen und Wunder.'],
    ['name' => 'Gottes Wirken / Handeln', 'value' => 'gods_work', 'cssClass' => 'bg-gods-work-custom', 'description' => 'Direkte Interventionen, Beschreibungen von Gottes Taten und seinem souveränen Handeln.'],
    ['name' => 'Gesetz', 'value' => 'law', 'cssClass' => 'bg-law-custom', 'description' => 'Gesetzliche Bestimmungen, insbesondere das mosaische Gesetz.'],
    ['name' => 'Weisheit / Lehre', 'value' => 'wisdom', 'cssClass' => 'bg-wisdom-custom', 'description' => 'Allgemeine Lehren, Sprichwörter und weisheitliche Abschnitte.'],
    ['name' => 'Anbetung / Lobpreis / Gebet', 'value' => 'worship', 'cssClass' => 'bg-worship-custom', 'description' => 'Psalmen, Gebete, Lobpreisungen und Ausdrücke der Anbetung.'],
    ['name' => 'Bund', 'value' => 'covenant', 'cssClass' => 'bg-covenant-custom', 'description' => 'Schlüsselbegriffe und Passagen zu Gottes Bündnissen mit den Menschen.'],
    
    ['name' => 'Unklar / Sonstiges', 'value' => 'gray', 'cssClass' => 'bg-gray-custom', 'description' => 'Verse, deren Bedeutung unklar ist oder die keiner anderen Kategorie eindeutig zuzuordnen sind.']
];

?>

<div class="container mt-4">
    <h1 class="mb-4"><?php echo $pageTitle; ?></h1>
    <p class="lead">Hier finden Sie eine Übersicht über die globalen Farbmarkierungen und ihre Bedeutung. Diese Farben helfen dabei, verschiedene Themen und Aspekte in den biblischen Texten schnell zu erkennen.</p>

    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php foreach ($colorLegend as $colorEntry): ?>
            <div class="col">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center">
                        <span class="badge me-2 p-3 <?php echo htmlspecialchars($colorEntry['cssClass']); ?>" style="font-size: 1rem;">&nbsp;&nbsp;&nbsp;</span> <h5 class="mb-0"><?php echo htmlspecialchars($colorEntry['name']); ?></h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text"><?php echo htmlspecialchars($colorEntry['description']); ?></p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <hr class="my-5">

    <div>
        <h2 class="mb-3">Weitere Markierungen</h2>
        <div class="d-flex align-items-center mb-2">
             <i class="bi bi-star-fill user-important-star fs-4 me-2" title="Für mich wichtig"></i>
            <p class="mb-0"><strong>Für mich wichtig (Stern):</strong> Persönliche Markierung für Verse, die für Sie eine besondere Bedeutung haben. Diese sind nur für Sie sichtbar und werden auf der Seite "Meine wichtigen Verse" gesammelt.</p>
        </div>
    </div>

</div>

<?php
require_once 'footer.php'; 
?>
