<?php
require_once 'config.php'; // Lädt Konfiguration und $pdo

$word_query = $_GET['wort'] ?? ''; // Das gesuchte Wort aus der URL
$sprache = $_GET['sprache'] ?? 'hebraeisch'; // z.B. 'hebraeisch' oder 'griechisch'

// Hier käme Ihre Datenbanklogik, um Informationen zum Wort zu holen
$wort_informationen = []; // Platzhalter
$verse_vorkommen = [];  // Platzhalter

if ($word_query) {
    // Beispiel: Dummy-Daten oder eine einfache DB-Abfrage
    if ($sprache === 'hebraeisch' && $word_query === 'Gott') {
        $wort_informationen = [
            'hebraeisch' => 'אֱלֹהִים (Elohim)',
            'strong_nr' => 'H430',
            'bedeutung' => 'Gott, Götter, göttliche Wesen. Oft verwendet als generischer Begriff für Gott oder als Plural der Majestät.',
            'transliteration' => 'Elohim'
        ];
        // Beispielhaft Verse finden (hier fest codiert, später aus DB)
        $verse_vorkommen = [
            ['buch' => '1. Mose', 'kapitel' => 1, 'vers' => 1, 'text' => 'Im Anfang schuf Gott Himmel und Erde.'],
            ['buch' => 'Psalm', 'kapitel' => 46, 'vers' => 1, 'text' => 'Gott ist unsre Zuversicht und Stärke...'],
        ];
    } elseif ($sprache === 'griechisch' && $word_query === 'Liebe') {
         $wort_informationen = [
            'griechisch' => 'ἀγάπη (Agápe)',
            'strong_nr' => 'G26',
            'bedeutung' => 'Göttliche, selbstlose Liebe; Nächstenliebe.',
            'transliteration' => 'Agápe'
        ];
    } else {
        $wort_informationen = ['bedeutung' => 'Keine Informationen für dieses Wort gefunden.'];
    }
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wortstudie: <?php echo htmlspecialchars($word_query); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding-top: 70px; }
        .navbar { margin-bottom: 1rem; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container">
        <a class="navbar-brand" href="bibel_lesen.php">Bibel-App</a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="bibel_lesen.php">Bibel lesen</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                    <li class="nav-item"><a class="nav-link" href="register.php">Registrieren</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <h1 class="mb-4">Wortstudie: <?php echo htmlspecialchars(ucfirst($word_query)); ?> (<?php echo htmlspecialchars(ucfirst($sprache)); ?>)</h1>

    <?php if (!empty($word_query) && !empty($wort_informationen)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h3>
                    <?php echo htmlspecialchars($wort_informationen[$sprache] ?? $word_query); ?>
                    <?php if(isset($wort_informationen['transliteration'])): ?>
                        (<?php echo htmlspecialchars($wort_informationen['transliteration']); ?>)
                    <?php endif; ?>
                </h3>
                <?php if(isset($wort_informationen['strong_nr'])): ?>
                    <small class="text-muted">Strong-Nummer: <?php echo htmlspecialchars($wort_informationen['strong_nr']); ?></small>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <p><strong>Bedeutung:</strong> <?php echo htmlspecialchars($wort_informationen['bedeutung'] ?? 'N/A'); ?></p>
                </div>
        </div>

        <?php if(!empty($verse_vorkommen)): ?>
            <h3_5>Vorkommen in der Bibel (Beispiele):</h3_5>
            <ul class="list-group">
                <?php foreach($verse_vorkommen as $vorkommen): ?>
                    <li class="list-group-item">
                        <a href="bibel_lesen.php?book_name=<?php echo urlencode($vorkommen['buch']); ?>&chapter=<?php echo $vorkommen['kapitel']; ?>#vers-<?php echo $vorkommen['vers']; ?>">
                            <?php echo htmlspecialchars($vorkommen['buch'] . ' ' . $vorkommen['kapitel'] . ',' . $vorkommen['vers']); ?>
                        </a>:
                        "<?php echo htmlspecialchars($vorkommen['text']); ?>"
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

    <?php else: ?>
        <p>Bitte geben Sie ein Wort in der URL ein, z.B. <code>wortstudie.php?wort=Gott&sprache=hebraeisch</code></p>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>