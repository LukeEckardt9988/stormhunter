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
    [
        'name' => 'Christus im AT',
        'value' => 'yellow',
        'cssClass' => 'bg-warning',
        'description' => 'Hinweise, Prophetien und typologische Bezüge auf Jesus Christus im Alten Testament.'
    ],
    [
        'name' => 'Ermutigung, Trost & Hoffnung',
        'value' => 'green',
        'cssClass' => 'bg-green-custom',
        'description' => 'Verse, die Ermutigung, Trost in schwierigen Zeiten oder Hoffnung auf Gottes Zusagen spenden.'
    ],
    [
        'name' => 'Gebot & Weisung',
        'value' => 'blue',
        'cssClass' => 'bg-blue-custom',
        'description' => 'Klare Gebote, Anweisungen Gottes und ethische Richtlinien für das Leben und den Glauben.'
    ],
    [
        'name' => 'Sünde, Warnung & Gericht',
        'value' => 'red',
        'cssClass' => 'bg-red-custom',
        'description' => 'Hinweise auf Sünde, menschliches Versagen, Warnungen vor Konsequenzen und Gottes gerechtes Gericht.'
    ],
    [
        'name' => 'Prophetie & Erfüllung',
        'value' => 'orange',
        'cssClass' => 'bg-orange-custom',
        'description' => 'Weissagungen, prophetische Worte über zukünftige Ereignisse und deren Erfüllung, oft in Bezug auf Christus oder die Endzeit.'
    ],
    [
        'name' => 'Geschichte & Kontext',
        'value' => 'brown',
        'cssClass' => 'bg-brown-custom',
        'description' => 'Historische Berichte, erzählerischer Rahmen, kultureller Hintergrund, Genealogien und geografische Angaben.'
    ],
    [
        'name' => 'Gleichnis, Lehre & Weißheit', // Kombiniert aus 'Gleichnis' und Aspekten von 'Weisheit/Lehre'
        'value' => 'parable',
        'cssClass' => 'bg-parable-custom',
        'description' => 'Bildhafte Erzählungen (Gleichnisse), biblische Lehren, Prinzipien und allgemeine Lebensweisheiten.'
    ],
    [
        'name' => 'Zurechtweisung & Umkehr',
        'value' => 'rebuke',
        'cssClass' => 'bg-rebuke-custom',
        'description' => 'Verse, die Fehlverhalten aufzeigen, zur Selbstreflexion, Buße und Umkehr zu Gott aufrufen.'
    ],
    [
        'name' => 'Israel & Gottes Volk', // Ehemals 'Namen (Orte & Personen)'
        'value' => 'name', // Behält den alten 'value' für Konsistenz, falls bestehende Daten darauf basieren
        'cssClass' => 'bg-name-custom',
        'description' => 'Verse, die sich auf Gottes erwähltes Volk Israel (AT) und die Gemeinde als geistliches Israel (NT) beziehen – deren Berufung, Geschichte und Identität.'
    ],
    [
        'name' => 'Heiligung, Erkenntnis & Wachstum', // Ehemals 'Heiligung / Nachfolge'
        'value' => 'sanctification',
        'cssClass' => 'bg-sanctification-custom',
        'description' => 'Aspekte des geistlichen Wachstums, der Heiligung durch den Heiligen Geist, der tieferen Erkenntnis Gottes und der Jüngerschaft.'
    ],
    [
        'name' => 'Christus Verheißung, Werke & Wort', // Ehemals 'Wunder'
        'value' => 'miracle', // Behält den alten 'value'
        'cssClass' => 'bg-miracle-custom',
        'description' => 'Verse, die sich zentral auf Jesus Christus beziehen: seine Identität, Verheißungen, sein Wirken, seine Wunder und seine Lehren/Worte.'
    ],
    [
        'name' => 'Gottes Wesen, Wirken & Wunder', // Ehemals 'Gottes Wirken / Handeln'
        'value' => 'gods_work',
        'cssClass' => 'bg-gods-work-custom',
        'description' => 'Beschreibungen von Gottes Charakter, Eigenschaften, seinem souveränen Handeln, seinen Plänen und übernatürlichen Wundern.'
    ],
    [
        'name' => 'Gesetz & Alter Bund', // Ehemals 'Gesetz'
        'value' => 'law',
        'cssClass' => 'bg-law-custom',
        'description' => 'Gesetzliche Bestimmungen, insbesondere das mosaische Gesetz und der Alte Bund Gottes mit Israel.'
    ],
    [
        'name' => 'Weisheit & Lehre', // Beibehalten für spezifische Weisheitsliteratur
        'value' => 'wisdom',
        'cssClass' => 'bg-wisdom-custom',
        'description' => 'Spezifische Weisheitsliteratur (z.B. Sprüche, Prediger), tiefere biblische Lehren und Prinzipien für ein gottgefälliges Leben.'
    ],
    [
        'name' => 'Anbetung, Gebet & Lobpreis', // Ehemals 'Anbetung / Lobpreis / Gebet'
        'value' => 'worship',
        'cssClass' => 'bg-worship-custom',
        'description' => 'Ausdrücke der Anbetung, Psalmen, Gebete, Lobpreisungen und Dankbarkeit gegenüber Gott.'
    ],
    [
        'name' => 'Neuer Bund', // Ehemals 'Bund'
        'value' => 'covenant',
        'cssClass' => 'bg-covenant-custom',
        'description' => 'Verse, die sich auf den Neuen Bund durch Jesus Christus beziehen, das Abendmahl und die Erlösung durch sein Blut.'
    ],
    [
        'name' => 'Menschliche Natur & Zustand', // Ehemals 'Unklar / Sonstiges'
        'value' => 'gray',
        'cssClass' => 'bg-gray-custom',
        'description' => 'Verse über das Wesen des Menschen, seine Erschaffenheit, Begrenztheit, seinen Zustand vor Gott (auch den inneren Kampf).'
    ]
   
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
                        <span class="badge me-2 p-3 <?php echo htmlspecialchars($colorEntry['cssClass']); ?>" style="font-size: 1rem;">&nbsp;&nbsp;&nbsp;</span>
                        <h5 class="mb-0"><?php echo htmlspecialchars($colorEntry['name']); ?></h5>
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