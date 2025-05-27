// app.js

// Globale Variablen für das Popover-Management
let activePopoverInstance = null; // Geändert von currentPopoverInstance für Klarheit
let activePopoverTargetElement = null; // Geändert von currentPopoverTargetElement

// Die currentUserId wird direkt aus dem globalen Scope gelesen,
// da sie in bibel_lesen.php als 'currentUserIdFromPHP' definiert
// und dann in app.js als window.currentUserId verfügbar gemacht wird (oder direkt als currentUserId).
// Wir machen es explizit:
const currentUserId = typeof currentUserIdFromPHP !== 'undefined' ? currentUserIdFromPHP : 0;

document.addEventListener('DOMContentLoaded', function() {
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap ist nicht geladen. Popover und Modals funktionieren nicht.');
        return;
    }

    const verseContainer = document.querySelector('.verse-container');
    if (!verseContainer) {
        console.error("Element mit Klasse '.verse-container' nicht gefunden! EventListener kann nicht angehängt werden.");
        return;
    }

    verseContainer.addEventListener('click', function(event) {
        const verseElement = event.target.closest('.verse');
        if (!verseElement) {
            if (activePopoverInstance && !event.target.closest('.popover') && !event.target.closest('.list-group-item')) {
                 activePopoverInstance.dispose();
                 activePopoverInstance = null;
                 activePopoverTargetElement = null;
            }
            return;
        }

        if (activePopoverTargetElement === verseElement && activePopoverInstance) {
             activePopoverInstance.dispose();
             activePopoverInstance = null;
             activePopoverTargetElement = null;
             return; 
        }

        if (activePopoverInstance) {
            activePopoverInstance.dispose();
        }
        
        const globalId = verseElement.dataset.globalId;
        const verseNumSpan = verseElement.previousElementSibling;
        let verseNumText = verseNumSpan ? verseNumSpan.textContent.trim() : '';
        const bookChapterTitleH1 = document.querySelector('h1.text-center');
        let bookChapterTitleText = bookChapterTitleH1 ? bookChapterTitleH1.textContent : 'Vers';
        
         const highlightColors = [
                { name: 'Wichtig (Gelb)', value: 'yellow', cssClass: 'bg-warning' }, // Bootstrap-Klasse für Gelb
                { name: 'Verheißung (Grün)', value: 'green', cssClass: 'bg-success-subtle' }, // Sanftes Bootstrap Grün
                { name: 'Gebot (Blau)', value: 'blue', cssClass: 'bg-info-subtle' },       // Sanftes Bootstrap Blau
                { name: 'Sünde/Warnung (Rot)', value: 'red', cssClass: 'bg-danger-subtle' },   // Sanftes Bootstrap Rot
                { name: 'Prophetie (Orange)', value: 'orange', cssClass: 'bg-orange-subtle' }, // Eigene CSS-Klasse (siehe Schritt 3)
                { name: 'Unklar (Grau)', value: 'gray', cssClass: 'bg-secondary-subtle' },   // Bootstrap Grau
                { name: 'Keine Markierung', value: 'remove', cssClass: ''}
            ];

            let colorOptionsHtml = '';
            highlightColors.forEach(color => {
                colorOptionsHtml += `<a href="#" class="list-group-item list-group-item-action" onclick="event.preventDefault(); handleHighlightVerse(this, ${globalId}, '${color.value}')">${color.name}</a>`;
            });
            
            const actions = `
                <div class="list-group list-group-flush">
                    <a href="#" class="list-group-item list-group-item-action" onclick="event.preventDefault(); handleCompareVerse(${globalId}, '${bookChapterTitleText.replace(/'/g, "\\'")}', '${verseNumText.replace(/'/g, "\\'")}')">Vergleichen</a>
                    <div class="list-group-item">
                        <div class="dropdown">
                            <a class="dropdown-toggle text-decoration-none text-dark" href="#" role="button" id="highlightDropdownMenuLink-${globalId}" data-bs-toggle="dropdown" aria-expanded="false">
                                Markieren mit Farbe
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="highlightDropdownMenuLink-${globalId}">
                                ${colorOptionsHtml}
                            </ul>
                        </div>
                    </div>
                    <a href="#" class="list-group-item list-group-item-action" onclick="event.preventDefault(); handleShowComments(${globalId}, '${bookChapterTitleText.replace(/'/g, "\\'")}', '${verseNumText.replace(/'/g, "\\'")}')">Kommentare</a>
                    <a href="#" class="list-group-item list-group-item-action disabled">Wortstudie (bald)</a>
                </div>
            `;
        
        activePopoverInstance = new bootstrap.Popover(verseElement, {
            html: true,
            title: `Aktionen für ${bookChapterTitleText}, Vers ${verseNumText}`,
            content: actions,
            sanitize: false, 
            trigger: 'manual'
        });
        activePopoverInstance.show();
        activePopoverTargetElement = verseElement; 


        
    });

    document.body.addEventListener('click', function(e) {
        if (activePopoverInstance && 
            activePopoverTargetElement && 
            !activePopoverTargetElement.contains(e.target) &&
            !e.target.closest('.popover') && 
            !e.target.closest('.list-group-item')) {
            activePopoverInstance.dispose();
            activePopoverInstance = null;
            activePopoverTargetElement = null;
        }
    }, true);
});

function closeAndDisposeActivePopover() { // Umbenannt für Klarheit
    if (activePopoverInstance) {
        activePopoverInstance.dispose();
        activePopoverInstance = null;
        activePopoverTargetElement = null;
    }
}

// In app.js

// In app.js

// In app.js

function handleHighlightVerse(clickedMenuItemOrButton, globalVerseId, colorValueFromMenu = 'yellow') {
    // clickedMenuItemOrButton ist das <a> Element aus dem Popover (für bibel_lesen.php)
    // oder der Button selbst (für suche.php)
    // colorValueFromMenu ist der Wert, der beim Klick im Popover-Dropdown übergeben wird (z.B. 'yellow', 'green', 'remove')

    if (currentUserId === 0) {
        alert("Bitte zuerst einloggen, um Verse zu markieren.");
        if (activePopoverInstance && typeof closeAndDisposeActivePopover === 'function') {
            closeAndDisposeActivePopover();
        }
        return;
    }
    
    let elementToHighlight = null;
    let isBibellesenPage = false;

    // Versuche, das .verse Element in bibel_lesen.php zu finden
    const verseElementBibellesen = document.querySelector(`.verse[data-global-id='${globalVerseId}']`);
    if (verseElementBibellesen) {
        elementToHighlight = verseElementBibellesen;
        isBibellesenPage = true;
    } else {
        // Versuche, das .search-result-text Element in suche.php zu finden
        const searchResultTextElement = document.querySelector(`.search-result-text[data-highlight-target='${globalVerseId}']`);
        if (searchResultTextElement) {
            elementToHighlight = searchResultTextElement;
        }
    }
    
    if (elementToHighlight) {
        const colorMap = {
            'yellow': 'bg-warning',
            'green': 'bg-success-subtle',
            'blue': 'bg-info-subtle',
            'red': 'bg-danger-subtle',
            'orange': 'bg-orange-subtle',
            'gray': 'bg-secondary-subtle'
        };
        const newCssClass = colorMap[colorValueFromMenu];
        let actionForServer = 'addHighlight';
        let colorForServer = colorValueFromMenu;

        // Alle alten Farbklassen entfernen
        Object.values(colorMap).forEach(cls => elementToHighlight.classList.remove(cls));

        if (colorValueFromMenu === 'remove') {
            actionForServer = 'removeHighlight';
            // Keine neue Klasse hinzufügen
        } else if (elementToHighlight.dataset.currentColor === colorValueFromMenu) {
            // Wenn die gleiche Farbe erneut geklickt wird (außer 'remove'), dann Markierung entfernen
            actionForServer = 'removeHighlight';
            // Die UI-Klasse wurde oben schon entfernt
        } else {
            // Neue Farbe anwenden oder Farbe ändern
            if (newCssClass) {
                elementToHighlight.classList.add(newCssClass);
            }
            actionForServer = 'addHighlight';
        }
        // Speichere die aktuelle Farbe im data-Attribut (oder entferne es)
        elementToHighlight.dataset.currentColor = (actionForServer === 'addHighlight' && colorValueFromMenu !== 'remove') ? colorValueFromMenu : '';


        fetch('ajax_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=${actionForServer}&global_verse_id=${globalVerseId}&user_id=${currentUserId}&color=${colorForServer}`
        })
        .then(response => {
            if (!response.ok) { throw new Error(`HTTP error! status: ${response.status}`); }
            return response.json();
        })
        .then(data => {
            console.log('Antwort vom Server (Markieren):', data);
            if(data.status !== 'success') {
                console.error('Fehler beim Markieren:', data.message);
                // UI-Änderung bei Server-Fehler rückgängig machen (komplexer bei Farbwechsel)
                // Fürs Erste: Fehler anzeigen
                alert('Fehler beim Markieren serverseitig: ' + data.message);
                // Man müsste hier den vorherigen Zustand wiederherstellen
            } else {
                // UI nach erfolgreicher Server-Antwort ggf. anpassen
                if (data.actionTaken === 'removed') {
                    Object.values(colorMap).forEach(cls => elementToHighlight.classList.remove(cls));
                    elementToHighlight.dataset.currentColor = '';
                } else if (data.actionTaken === 'added' || data.actionTaken === 'updated') {
                    Object.values(colorMap).forEach(cls => elementToHighlight.classList.remove(cls));
                    if (colorMap[data.newColor]) {
                        elementToHighlight.classList.add(colorMap[data.newColor]);
                        elementToHighlight.dataset.currentColor = data.newColor;
                    }
                }
            }
        })
        .catch(error => {
            console.error('Fehler bei AJAX (highlightVerse):', error);
            alert('Fehler bei der Kommunikation mit dem Server (Markieren).');
            // UI-Änderung bei Kommunikationsfehler rückgängig machen
             Object.values(colorMap).forEach(cls => elementToHighlight.classList.remove(cls));
             // Hier müsste man den vorherigen data-current-color wiederherstellen.
        });
    } else {
        console.warn(`Konnte kein passendes Element zum Markieren für global_id '${globalVerseId}' finden.`);
    }

    // Popover nur schließen, wenn es von bibel_lesen.php kommt
    if (isBibellesenPage && typeof closeAndDisposeActivePopover === 'function' && activePopoverInstance) {
        closeAndDisposeActivePopover();
    }
}


function handleShowComments(globalVerseId, bookChapterTitleText, verseNumText) {
    closeAndDisposeActivePopover();
    
    const modalTitle = document.getElementById('commentsModalLabel');
    const modalBody = document.getElementById('commentsModalBody');
    const commentsModalElement = document.getElementById('commentsModal');
    if (!modalTitle || !modalBody || !commentsModalElement) {
        console.error("Kommentar-Modal Elemente nicht gefunden.");
        return;
    }
    const commentsModal = bootstrap.Modal.getOrCreateInstance(commentsModalElement);

    modalTitle.textContent = `Kommentare für ${bookChapterTitleText}, Vers ${verseNumText}`;
    modalBody.innerHTML = `<div class="text-center my-3"><div class="spinner-border" role="status"><span class="visually-hidden">Lade Kommentare...</span></div></div>`;
    commentsModal.show();

    fetch('ajax_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=getComments&global_verse_id=${globalVerseId}`
    })
    .then(response => {
        if (!response.ok) { throw new Error(`HTTP error! status: ${response.status} (getComments)`); }
        return response.text();
    })
    .then(commentsHtml => {
         modalBody.innerHTML = `
            ${commentsHtml}
            <hr class="my-3">
            <h5>Neuen Kommentar schreiben:</h5>
            <textarea id="newCommentTextForModal_${globalVerseId}" class="form-control" rows="3" placeholder="Ihr Kommentar..."></textarea>
            <button class="btn btn-primary btn-sm mt-2" onclick="submitNewCommentFromModal(${globalVerseId}, '${bookChapterTitleText}', '${verseNumText}')">Kommentar speichern</button>
         `;
    })
    .catch(error => {
        modalBody.innerHTML = '<p class="text-danger">Fehler beim Laden der Kommentare.</p>';
        console.error('Fehler bei AJAX (getComments für Modal):', error);
    });
}

function submitNewCommentFromModal(globalVerseId, bookChapterTitleText, verseNumText) {
    if (currentUserId === 0) {
        alert("Bitte zuerst einloggen, um Kommentare zu schreiben.");
        return;
    }
    const newCommentTextElement = document.getElementById(`newCommentTextForModal_${globalVerseId}`);
    const note = newCommentTextElement ? newCommentTextElement.value.trim() : "";

    if (note === "") {
        alert("Bitte geben Sie einen Kommentartext ein.");
        return;
    }

    fetch('ajax_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=addComment&global_verse_id=${globalVerseId}&user_id=${currentUserId}&note_text=${encodeURIComponent(note)}`
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.status === 'success') {
            handleShowComments(globalVerseId, bookChapterTitleText, verseNumText);
        }
    })
    .catch(error => {
        console.error('Fehler bei AJAX (submitNewCommentFromModal):', error);
        alert('Fehler beim Speichern des Kommentars im Modal.');
    });
}

function handleCompareVerse(globalVerseId, bookChapterTitleText, verseNumText) {
    closeAndDisposeActivePopover();
    
    const modalTitle = document.getElementById('compareModalLabel');
    const modalBody = document.getElementById('compareModalBody');
    const compareModalElement = document.getElementById('compareModal');
     if (!modalTitle || !modalBody || !compareModalElement) {
        console.error("Vergleichs-Modal Elemente nicht gefunden.");
        return;
    }
    const compareModal = bootstrap.Modal.getOrCreateInstance(compareModalElement);
    
    modalTitle.textContent = `Übersetzungsvergleich für ${bookChapterTitleText}, Vers ${verseNumText}`;
    modalBody.innerHTML = '<div class="text-center my-3"><div class="spinner-border" role="status"><span class="visually-hidden">Lade Vergleiche...</span></div></div>';
    compareModal.show();

    fetch('ajax_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=compareVerse&global_verse_id=${globalVerseId}`
    })
    .then(response => {
        if (!response.ok) { throw new Error(`HTTP error! status: ${response.status}`); }
        return response.text();
    })
    .then(compareHtml => {
        modalBody.innerHTML = compareHtml;
    })
    .catch(error => {
        modalBody.innerHTML = '<p class="text-danger">Fehler beim Laden der Vergleichsverse.</p>';
        console.error('Fehler bei AJAX (compareVerse):', error);
    });
}

