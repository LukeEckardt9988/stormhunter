<?php
require_once 'config.php'; // Stellt $pdo und die Session bereit

// Benutzer- und Gemeindeinformationen aus der Session holen
$loggedInUserId = $_SESSION['user_id'] ?? 0;
$loggedInCommunityId = $_SESSION['community_id'] ?? 0;
$loggedInCommunityName = $_SESSION['community_name'] ?? 'deiner Gemeinde';

$pageTitle = "Fortschritt"; // Wird dynamisch angepasst
require_once 'header.php';

// PHP-Array der Farbinformationen (Namen und HEX-Codes für Chart.js)
// Diese müssen exakt zu Ihren CSS-Klassen und den 'value' in app.js passen!
// Die HEX-Codes sollten den tatsächlichen Hintergrundfarben Ihrer .bg-*-custom Klassen entsprechen.
// Am Anfang von status.php

// NEU: PHP-Array der Farbinformationen für Chart.js
$highlightColorsPHP = [
    'god'               => ['name' => 'Gottes Wesen & Handeln', 'hex' => '#81d4fa'],
    'christ_prophecy'   => ['name' => 'Christus: Person & Prophetie', 'hex' => '#ffe082'],
    'christ_teaching'   => ['name' => 'Christus: Lehre & Wirken', 'hex' => '#fff59d'],
    'sin_repentance'    => ['name' => 'Sünde & Umkehr', 'hex' => '#ef9a9a'],
    'law_covenant'      => ['name' => 'Gesetz & Bund', 'hex' => '#b0bec5'],
    'faith_salvation'   => ['name' => 'Glaube & Erlösung', 'hex' => '#f8bbd0'],
    'holy_spirit'       => ['name' => 'Heiliger Geist', 'hex' => '#a0e6d7'],
    'commandments'      => ['name' => 'Gebote & Ethik', 'hex' => '#90caf9'],
    'hope'              => ['name' => 'Ermutigung & Hoffnung', 'hex' => '#a5d6a7'],
    'growth'            => ['name' => 'Geistliches Wachstum', 'hex' => '#d1c4e9'],
    'worship'           => ['name' => 'Anbetung & Gebet', 'hex' => '#ffccbc'],
    'people_church'     => ['name' => 'Gottes Volk & Gemeinde', 'hex' => '#e6ee9c'],
    'history'           => ['name' => 'Geschichte & Kontext', 'hex' => '#d2b48c'],
    // Alte, nicht mehr verwendete Farben/Werte können hier entfernt werden.
];

?>

<div class="container mt-4">
    <?php
    // Nur wenn der Benutzer eingeloggt ist, den Fortschritt anzeigen
    if ($loggedInUserId > 0) {

        $colorUsageData = [];
        $bookStats = [];
        $finalPageTitle = "";
        $subTitle = "";

        $paramsForQuery = []; // Parameter für die Hauptabfragen

        // --- Fall 1: Benutzer ist in einer Gemeinde ---
        if ($loggedInCommunityId > 0) {
            $finalPageTitle = "Fortschritt der Gemeinde: " . htmlspecialchars($loggedInCommunityName);
            $subTitle = "Hier seht ihr eine Übersicht, wie viele Verse ihr als Gemeinde in jedem Buch der Bibel bereits farblich markiert habt.";

            $stmtMembers = $pdo->prepare("SELECT user_id FROM users WHERE community_id = :community_id");
            $stmtMembers->execute([':community_id' => $loggedInCommunityId]);
            $memberIds = $stmtMembers->fetchAll(PDO::FETCH_COLUMN);

            if (empty($memberIds)) {
                echo '<div class="alert alert-info">Eure Gemeinde hat noch keine Mitglieder oder es wurden noch keine Verse markiert.</div>';
            } else {
                $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
                $userConditionSQL = "uh.user_id IN ($placeholders)";
                $paramsForQuery = $memberIds;
            }
        } else { // --- Fall 2: Benutzer ist in KEINER Gemeinde (persönlicher Fortschritt) ---
            $finalPageTitle = "Mein persönlicher Fortschritt";
            $subTitle = "Hier siehst du eine Übersicht, wie viele Verse du in jedem Buch der Bibel bereits farblich markiert hast.";
            echo '<div class="alert alert-info mb-4">Du bist aktuell keiner Gemeinde zugeordnet. Daher wird dir hier dein persönlicher Fortschritt angezeigt.</div>';
            $userConditionSQL = "uh.user_id = ?";
            $paramsForQuery = [$loggedInUserId];
        }

        // JavaScript Seitentitel aktualisieren (wird nach dem Header geladen)
        if ($finalPageTitle) {
            echo '<script>document.title = "' . addslashes($pageTitle . ' | ' . $finalPageTitle) . '";</script>';
        }

        echo '<h1 class="mb-3">' . htmlspecialchars($finalPageTitle) . '</h1>';

        // Nur fortfahren, wenn wir Parameter für die Abfragen haben (also User/Mitglieder)
        if (!empty($paramsForQuery)) {
            // Daten für das Farb-Diagramm holen
            $sqlColorStats = "
            SELECT
                uh.highlight_color,
                COUNT(uh.highlight_id) AS color_count
            FROM user_highlights uh
            WHERE $userConditionSQL AND uh.highlight_color != 'remove_color' AND uh.highlight_color IS NOT NULL AND uh.highlight_color != ''
            GROUP BY uh.highlight_color
            ORDER BY color_count DESC;
        ";
            $stmtColorStats = $pdo->prepare($sqlColorStats);
            $stmtColorStats->execute($paramsForQuery);
            $rawColorUsageData = $stmtColorStats->fetchAll(PDO::FETCH_ASSOC);

            $chartLabels = [];
            $chartData = [];
            $chartBackgroundColors = [];

            foreach ($rawColorUsageData as $data) {
                if (isset($highlightColorsPHP[$data['highlight_color']])) {
                    $chartLabels[] = $highlightColorsPHP[$data['highlight_color']]['name'];
                    $chartData[] = (int)$data['color_count'];
                    $chartBackgroundColors[] = $highlightColorsPHP[$data['highlight_color']]['hex'];
                }
            }

            // Daten für die Buchübersicht holen
            $sqlBookStats = "
            SELECT
                b.book_name_de,
                b.book_id AS book_id_for_link,
                COUNT(DISTINCT v.global_verse_id) AS total_verses,
                COUNT(DISTINCT uh.global_verse_id) AS highlighted_verses
            FROM books b
            LEFT JOIN verses v ON b.book_id = v.book_id AND v.version_id = 1 -- Annahme: version_id 1 als Basis für Gesamtverse
            LEFT JOIN user_highlights uh ON v.global_verse_id = uh.global_verse_id AND uh.highlight_color != 'remove_color' AND uh.highlight_color IS NOT NULL AND uh.highlight_color != '' AND ($userConditionSQL)
            GROUP BY b.book_id, b.book_name_de, b.book_order
            ORDER BY b.book_order ASC;
        ";
            $stmtBookStats = $pdo->prepare($sqlBookStats);
            $stmtBookStats->execute($paramsForQuery);
            $bookStats = $stmtBookStats->fetchAll(PDO::FETCH_ASSOC);
    ?>

            <?php if (!empty($chartData)): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5 class="my-0 font-weight-normal">Verwendung der Markierungsfarben</h5>
                    </div>
                    <div class="card-body">
                        <div style="max-height: 400px; max-width: 400px; margin: auto;">
                            <canvas id="colorUsageChart"></canvas>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-muted text-center">Noch keine Farbmarkierungen vorhanden, um ein Diagramm zu erstellen.</p>
            <?php endif; ?>

            <hr class="my-4">
            <h2 class="mb-3">Fortschritt pro Buch</h2>
            <p class="lead"><?php echo htmlspecialchars($subTitle); ?></p>

            <?php if (!empty($bookStats)): ?>
                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-2 row-cols-lg-3 g-3 mb-4">
                    <?php foreach ($bookStats as $book): ?>
                        <?php
                        // Die Berechnung von $percentage, $bookLink und $progressBarClass bleibt gleich
                        $percentage = 0;
                        if ($book['total_verses'] > 0) {
                            $percentage = ($book['highlighted_verses'] / $book['total_verses']) * 100;
                        }
                        $bookLink = "bibel_lesen.php?book_id=" . $book['book_id_for_link'] . "&chapter=1";
                        $progressBarClass = ($loggedInCommunityId > 0 && !empty($memberIds) ? 'bg-info' : 'bg-success');
                        ?>
                        <div class="col">
                            <a href="<?php echo $bookLink; ?>" class="book-status-compact-item d-block p-2 border rounded mb-0 text-decoration-none shadow-sm">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="book-name text-truncate me-2"><?php echo htmlspecialchars($book['book_name_de']); ?></span>
                                    <span class="book-stats text-nowrap">
                                        <small><?php echo $book['highlighted_verses']; ?>/<?php echo $book['total_verses']; ?> (<?php echo number_format($percentage, 0); ?>%)</small>
                                    </span>
                                </div>
                                <div class="progress book-status-tiny-progress mt-1" style="height: 5px;">
                                    <div class="progress-bar <?php echo $progressBarClass; ?>" role="progressbar" style="width: <?php echo $percentage; ?>%;" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </a>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted text-center">Kein Fortschritt für Bücher vorhanden.</p>
            <?php endif; ?>
        <?php
        } // Ende if (!empty($paramsForQuery))
    } else { // --- Fall 3: Benutzer ist nicht eingeloggt ---
        ?>
        <div class="alert alert-warning text-center mt-5">
            <h4>Bitte einloggen</h4>
            <p>Um deinen persönlichen Fortschritt oder den Fortschritt deiner Gemeinde zu sehen, musst du dich zuerst <a href="login.php">anmelden</a> oder <a href="register.php">registrieren</a>.</p>
        </div>
    <?php
    }
    ?>
</div> <?php if ($loggedInUserId > 0 && !empty($chartData)): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('colorUsageChart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode($chartLabels); ?>,
                        datasets: [{
                            label: 'Anzahl Markierungen',
                            data: <?php echo json_encode($chartData); ?>,
                            backgroundColor: <?php echo json_encode($chartBackgroundColors); ?>,
                            hoverOffset: 8,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false, // Erlaubt dem Chart, die Höhe des Containers zu nutzen
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    boxWidth: 12
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.parsed !== null) {
                                            const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                            const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                            label += context.parsed + ' (' + percentage + '%)';
                                        }
                                        return label;
                                    }
                                }
                            }
                        }
                    }
                });
            }
        });
    </script>
<?php endif; ?>

<?php
require_once 'footer.php';
?>