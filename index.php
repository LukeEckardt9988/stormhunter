<?php
// index.php
// Diese Datei dient als Haupt-Einstiegspunkt.
// Für den Moment leiten wir direkt zur Bibelleseseite weiter.

// Es ist eine gute Praxis, die config.php auch hier einzubinden, 
// falls Sie später Logik hinzufügen, die eine Session oder DB-Verbindung benötigt.
require_once 'config.php';

// index.php
header('Location: welcome.php');
exit();

// Alternativ könnten Sie hier eine kleine Willkommensseite mit Links anzeigen:
/*
$pageTitle = "Willkommen zur Bibel-App";
require_once 'header.php';
?>
<div class="jumbotron text-center">
    <h1>Willkommen zur Bibel-App</h1>
    <p class="lead">Lesen, suchen und studieren Sie die Heilige Schrift.</p>
    <hr class="my-4">
    <p>
        <a class="btn btn-primary btn-lg" href="bibel_lesen.php" role="button">Bibel lesen</a>
        <a class="btn btn-info btn-lg" href="suche.php" role="button">Wortsuche</a>
    </p>
</div>
<?php
require_once 'footer.php';
*/
?>