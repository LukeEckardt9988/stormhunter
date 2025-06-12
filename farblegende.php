<?php
// config.php wird als Erstes geladen
require_once 'config.php';

// Diese Variablen werden für den Header benötigt
$loggedInUserId = $_SESSION['user_id'] ?? 0;
$loggedInUsername = $_SESSION['username'] ?? 'Gast';
$pageTitle = "Farblegende für Markierungen";

require_once 'header.php';

// NEU: Definition der konsolidierten Farbmarkierungen
$colorLegend = [
    [
        'name' => 'Gottes Wesen & Handeln', 'value' => 'god', 'cssClass' => 'bg-gods-work-custom',
        'description' => 'Alles über Gottes Charakter, seine Eigenschaften, sein souveränes Wirken, die Schöpfung und seine Wunder.'
    ],
    [
        'name' => 'Christus im AT', 'value' => 'christ_ot', 'cssClass' => 'bg-warning',
        'description' => 'Verse, die durch Prophetie, Bilder (Typologie) oder direkte Aussagen auf Jesus Christus im Alten Testament hinweisen.'
    ],
    [
        'name' => 'Prophetie & Gericht', 'value' => 'prophecy_general', 'cssClass' => 'bg-orange-custom',
        'description' => 'Allgemeine Weissagungen, Gerichtsankündigungen und prophetische Worte, die sich auf Völker, Personen oder zukünftige Ereignisse beziehen.'
    ],
    [
        'name' => 'Christus: Lehre & Wirken', 'value' => 'christ_teaching', 'cssClass' => 'bg-miracle-custom',
        'description' => 'Die Worte, Lehren, Gleichnisse und Taten/Wunder von Jesus während seines irdischen Dienstes.'
    ],
    [
        'name' => 'Sünde & Umkehr', 'value' => 'sin_repentance', 'cssClass' => 'bg-red-custom',
        'description' => 'Verse über Sünde, menschliches Versagen, Warnungen vor dem Gericht, aber auch über Buße, Umkehr und Vergebung.'
    ],
    [
        'name' => 'Gesetz & Bund', 'value' => 'law_covenant', 'cssClass' => 'bg-law-custom',
        'description' => 'Bezieht sich auf den Alten und Neuen Bund, die Zehn Gebote, das mosaische Gesetz und dessen Erfüllung in Christus.'
    ],
    [
        'name' => 'Glaube & Erlösung', 'value' => 'faith_salvation', 'cssClass' => 'bg-sanctification-custom',
        'description' => 'Zentrale Verse über Erlösung allein aus Gnade durch Glauben, Rechtfertigung und die Kernbotschaft des Evangeliums.'
    ],
     [
        'name' => 'Heiliger Geist', 'value' => 'holy_spirit', 'cssClass' => 'bg-parable-custom',
        'description' => 'Verse, die sich auf die Person, das Wirken, die Gaben und die Frucht des Heiligen Geistes beziehen.'
    ],
    [
        'name' => 'Gebote & Ethik', 'value' => 'commandments', 'cssClass' => 'bg-blue-custom',
        'description' => 'Klare Anweisungen, ethische Weisungen und Gebote für ein gottgefälliges Leben.'
    ],
    [
        'name' => 'Ermutigung & Hoffnung', 'value' => 'hope', 'cssClass' => 'bg-green-custom',
        'description' => 'Verse, die Trost, Hoffnung auf Gottes Zusagen und Ermutigung in allen Lebenslagen spenden.'
    ],
    [
        'name' => 'Geistliches Wachstum', 'value' => 'growth', 'cssClass' => 'bg-rebuke-custom',
        'description' => 'Aspekte der Heiligung, der Jüngerschaft und der praktischen Nachfolge im christlichen Leben.'
    ],
    [
        'name' => 'Anbetung & Gebet', 'value' => 'worship', 'cssClass' => 'bg-worship-custom',
        'description' => 'Psalmen, Gebete, Lobpreis, Anbetung und Ausdrücke der Dankbarkeit gegenüber Gott.'
    ],
    [
        'name' => 'Gottes Volk & Gemeinde', 'value' => 'people_church', 'cssClass' => 'bg-name-custom',
        'description' => 'Verse über Israel (AT) und die Gemeinde/Kirche (NT), deren Identität, Geschichte und Auftrag.'
    ],
    [
        'name' => 'Geschichte & Kontext', 'value' => 'history', 'cssClass' => 'bg-brown-custom',
        'description' => 'Rein historische Berichte, Genealogien, kultureller und geografischer Kontext zum besseren Verständnis.'
    ],
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