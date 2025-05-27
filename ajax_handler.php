<?php
// ajax_handler.php (im Hauptverzeichnis)
require_once 'config.php'; // Lädt Konfiguration, startet Session und stellt $pdo bereit

$response = ['status' => 'error', 'message' => 'Ungültige Aktion oder fehlende Parameter.'];
$action = $_POST['action'] ?? null;

if ($action) {
    try {
        // --- AKTION: Versvergleich ---
        if ($action === 'compareVerse' && isset($_POST['global_verse_id'])) {
            header('Content-Type: text/html; charset=utf-8');
            $globalVerseId = (int)$_POST['global_verse_id'];

            $sql = "SELECT v.verse_text, ver.version_name 
                    FROM verses v
                    JOIN versions ver ON v.version_id = ver.version_id
                    WHERE v.global_verse_id = :gvid
                    ORDER BY ver.version_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':gvid' => $globalVerseId]);
            $versesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($versesData) {
                $output = '<ul class="list-group list-group-flush">';
                foreach ($versesData as $verseItem) {
                    $output .= '<li class="list-group-item px-0">';
                    $output .= '<small class="text-muted d-block">' . htmlspecialchars($verseItem['version_name']) . '</small>';
                    $output .= '<div>' . nl2br(htmlspecialchars($verseItem['verse_text'])) . '</div>';
                    $output .= '</li>';
                }
                $output .= '</ul>';
                echo $output;
            } else {
                echo '<p class="text-center">Keine weiteren Übersetzungen für diesen Vers gefunden.</p>';
            }
            exit();
        }

        // Für alle anderen Aktionen ist der Standard JSON
        header('Content-Type: application/json; charset=utf-8');

        // --- AKTION: Kommentare für einen Vers laden ---
        if ($action === 'getComments' && isset($_POST['global_verse_id'])) {
            header('Content-Type: text/html; charset=utf-8'); // Diese Aktion sendet HTML
            $globalVerseId = (int)$_POST['global_verse_id'];

            $sql = "SELECT un.note_text, un.note_date, u.username 
                    FROM user_notes un
                    JOIN users u ON un.user_id = u.user_id
                    WHERE un.global_verse_id = :gvid AND un.is_public = 1
                    ORDER BY un.note_date DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':gvid' => $globalVerseId]);
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($comments) {
                $output = '<ul class="list-group list-group-flush">';
                foreach ($comments as $comment) {
                    $output .= '<li class="list-group-item px-0">';
                    $output .= '<div>' . nl2br(htmlspecialchars($comment['note_text'])) . '</div>';
                    $output .= '<small class="text-muted">Von: ' . htmlspecialchars($comment['username']) . ' am ' . date("d.m.Y H:i", strtotime($comment['note_date'])) . '</small>';
                    $output .= '</li>';
                }
                $output .= '</ul>';
                echo $output;
            } else {
                echo '<p class="text-center mt-2">Noch keine Kommentare für diesen Vers vorhanden.</p>';
            }
            exit();
        }

        // --- AKTION: Markierung umschalten ---
     // --- AKTION: Markierung umschalten / setzen ---
    // Prüft auf action 'addHighlight' oder 'removeHighlight'
    if ( (isset($_POST['action']) && ($_POST['action'] === 'addHighlight' || $_POST['action'] === 'removeHighlight')) && 
         isset($_POST['global_verse_id']) && 
         isset($_POST['user_id']) ) {
        
        header('Content-Type: application/json; charset=utf-8');

        $globalVerseId = (int)$_POST['global_verse_id'];
        $userId = (int)$_POST['user_id'];
        $actionToPerformByClient = $_POST['action']; // 'addHighlight' oder 'removeHighlight'
        $colorFromClient = $_POST['color'] ?? 'yellow'; // Farbe vom Client

        $response = ['status' => 'error', 'message' => 'Unbekannter Fehler beim Markieren.'];

        if ($userId === 0) {
            $response = ['status' => 'error', 'message' => 'Bitte einloggen, um Verse zu markieren.'];
        } else {
            if ($actionToPerformByClient === 'removeHighlight') {
                $stmtDelete = $pdo->prepare("DELETE FROM user_highlights WHERE user_id = :user_id AND global_verse_id = :gvid");
                if ($stmtDelete->execute([':user_id' => $userId, ':gvid' => $globalVerseId])) {
                    $response = ['status' => 'success', 'message' => 'Markierung entfernt.', 'actionTaken' => 'removed'];
                } else {
                    $response = ['status' => 'error', 'message' => 'Fehler: Markierung entfernen. DB: ' . implode(" ", $stmtDelete->errorInfo())];
                }
            } elseif ($actionToPerformByClient === 'addHighlight') {
                // Prüfen, ob bereits eine Markierung für diesen Vers und Benutzer existiert
                $stmtCheck = $pdo->prepare("SELECT highlight_id FROM user_highlights WHERE user_id = :user_id AND global_verse_id = :gvid");
                $stmtCheck->execute([':user_id' => $userId, ':gvid' => $globalVerseId]);
                $existingHighlight = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if ($existingHighlight) {
                    // Markierung existiert -> Farbe aktualisieren
                    $stmtUpdate = $pdo->prepare("UPDATE user_highlights SET highlight_color = :color, highlight_date = NOW() WHERE highlight_id = :hid");
                    if ($stmtUpdate->execute([':color' => $colorFromClient, ':hid' => $existingHighlight['highlight_id']])) {
                        $response = ['status' => 'success', 'message' => 'Markierungsfarbe aktualisiert.', 'actionTaken' => 'updated', 'newColor' => $colorFromClient];
                    } else {
                        $response = ['status' => 'error', 'message' => 'Fehler: Farbe aktualisieren. DB: ' . implode(" ", $stmtUpdate->errorInfo())];
                    }
                } else {
                    // Keine Markierung vorhanden -> neue Markierung einfügen
                    $stmtInsert = $pdo->prepare("INSERT INTO user_highlights (user_id, global_verse_id, highlight_color, highlight_date) VALUES (:user_id, :gvid, :color, NOW())");
                    if ($stmtInsert->execute([':user_id' => $userId, ':gvid' => $globalVerseId, ':color' => $colorFromClient])) {
                        $response = ['status' => 'success', 'message' => 'Vers markiert.', 'actionTaken' => 'added', 'newColor' => $colorFromClient];
                    } else {
                        $response = ['status' => 'error', 'message' => 'Fehler: Vers markieren. DB: ' . implode(" ", $stmtInsert->errorInfo())];
                    }
                }
            }
        }
        echo json_encode($response);
        exit(); 
    
    }
        // --- AKTION: Kommentar hinzufügen ---
        elseif ($action === 'addComment' && isset($_POST['global_verse_id']) && isset($_POST['user_id']) && isset($_POST['note_text'])) {
            $globalVerseId = (int)$_POST['global_verse_id'];
            $userId = (int)$_POST['user_id'];
            $noteText = trim($_POST['note_text']);
            
            if ($userId === 0) {
                 $response = ['status' => 'error', 'message' => 'Bitte einloggen, um Kommentare zu schreiben.'];
            } elseif (empty($noteText)) {
                $response = ['status' => 'error', 'message' => 'Der Kommentar darf nicht leer sein.'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO user_notes (user_id, global_verse_id, note_text, is_public, note_date) VALUES (:user_id, :gvid, :note, 1, NOW())");
                if ($stmt->execute([':user_id' => $userId, ':gvid' => $globalVerseId, ':note' => $noteText])) {
                    $response = ['status' => 'success', 'message' => 'Kommentar erfolgreich gespeichert!'];
                } else {
                    $response = ['status' => 'error', 'message' => 'Fehler: Kommentar speichern. DB: ' . implode(" ", $stmt->errorInfo())];
                }
            }
        }
    } catch (PDOException $e) {
        $response = ['status' => 'error', 'message' => 'Datenbankfehler: ' . $e->getMessage(), 'trace' => $e->getTraceAsString()]; // Trace für Debugging
    } catch (Exception $e) {
        $response = ['status' => 'error', 'message' => 'Allgemeiner Fehler: ' . $e->getMessage(), 'trace' => $e->getTraceAsString()]; // Trace für Debugging
    }
}

echo json_encode($response);
?>