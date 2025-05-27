// app.js - Dynamische Updates ohne Seiten-Neuladen (Optimiert)

// Globale Variablen
const currentUserId = typeof currentUserIdFromPHP !== 'undefined' ? currentUserIdFromPHP : 0;
let activePopoverInstance = null;
let activePopoverTargetElement = null;

document.addEventListener('DOMContentLoaded', function () {
    if (typeof bootstrap === 'undefined') {
        console.error('FEHLER: Bootstrap ist nicht geladen!');
        return;
    }
    initializeThemeToggler();
    initializeVersePopoverLogic();
    initializeModalLikeButtonLogic(); // <-- DIESE NEUE ZEILE HINZUFÜGEN

});

function initializeThemeToggler() {
    const themeToggleButton = document.getElementById('theme-toggle-btn');
    if (!themeToggleButton) return;
    const sunIcon = document.querySelector('.theme-icon-sun');
    const moonIcon = document.querySelector('.theme-icon-moon');
    if (!sunIcon || !moonIcon) return;

    const setTheme = (theme) => {
        document.body.classList.toggle('dark-mode', theme === 'dark');
        sunIcon.classList.toggle('d-none', theme === 'dark');
        moonIcon.classList.toggle('d-none', theme !== 'dark');
    };
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme) setTheme(savedTheme);
    themeToggleButton.addEventListener('click', () => {
        const newTheme = document.body.classList.contains('dark-mode') ? 'light' : 'dark';
        setTheme(newTheme);
        localStorage.setItem('theme', newTheme);
    });
}

function initializeVersePopoverLogic() {
    const verseContainer = document.querySelector('.verse-container');
    if (!verseContainer) return;

    verseContainer.addEventListener('click', function (event) {
        const clickedVerse = event.target.closest('.verse');
        if (!clickedVerse) return;

        if (activePopoverTargetElement === clickedVerse) {
            closeActivePopover();
        } else {
            if (activePopoverInstance) closeActivePopover();
            createAndShowPopoverForVerse(clickedVerse);
        }
    });

    document.addEventListener('click', function (event) {
        if (!activePopoverInstance || !activePopoverTargetElement) return;
        const clickedOnPopoverTrigger = activePopoverTargetElement.contains(event.target);
        const clickedInsidePopoverContent = event.target.closest('.popover');
        if (!clickedOnPopoverTrigger && !clickedInsidePopoverContent) {
            closeActivePopover();
        }
    });
}

function createAndShowPopoverForVerse(verseElement) {
    const globalId = verseElement.dataset.globalId;
    if (!globalId) return;

    const verseNumSpan = verseElement.previousElementSibling;
    const verseNumText = verseNumSpan && verseNumSpan.classList.contains('verse-num') ? verseNumSpan.textContent.trim() : '';
    const bookChapterTitleH1 = document.querySelector('h1.text-center');
    const bookChapterTitleText = bookChapterTitleH1 ? bookChapterTitleH1.textContent.trim() : 'Vers';

    const highlightColors = [
        { name: 'Wichtig (Gelb)', value: 'yellow' }, { name: 'Verheißung (Grün)', value: 'green' },
        { name: 'Gebot (Blau)', value: 'blue' }, { name: 'Sünde/Warnung (Rot)', value: 'red' },
        { name: 'Prophetie (Orange)', value: 'orange' }, { name: 'Geschichte (Braun)', value: 'brown' },
        { name: 'Gleichnis (Türkis)', value: 'parable' },
        { name: 'Zurechtweisung (Lavendel)', value: 'rebuke' },
        { name: 'Namen/Orte (Oliv)', value: 'name' },
        { name: 'Heiligung (Rosé)', value: 'sanctification' },
        { name: 'Wunder (Gold)', value: 'miracle' },
        { name: 'Gottes Wirken (Himmelblau)', value: 'gods_work' },
        { name: 'Gesetz (Schiefergrau)', value: 'law' },
        { name: 'Weisheit/Lehre (Flieder)', value: 'wisdom' },
        { name: 'Anbetung/Gebet (Pfirsich)', value: 'worship' },
        { name: 'Bund (Weinrot)', value: 'covenant' },
        { name: 'Unklar (Grau)', value: 'gray' },
        { name: 'Keine Farb-Markierung', value: 'remove_color' }
    ];
    const colorOptionsHtml = highlightColors.map(color =>
        `<li><a class="dropdown-item" href="#" onclick="event.preventDefault(); handleColorHighlight(${globalId}, '${color.value}')">${color.name}</a></li>`
    ).join('');

    const isAlreadyImportant = verseElement.dataset.isImportant === 'true';
    const importantLinkText = isAlreadyImportant ? "Als 'wichtig' entfernen" : "Für mich wichtig markieren";

    const actionsHtml = `
        <div class="list-group list-group-flush">
            <a href="#" class="list-group-item list-group-item-action important-link" onclick="event.preventDefault(); handleMarkAsImportant(${globalId}, this)">${importantLinkText}</a>
            <div class="list-group-item">
                <div class="dropdown">
                    <a class="dropdown-toggle text-decoration-none text-dark" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Farblich hervorheben (Global)</a>
                    <ul class="dropdown-menu" style="max-height: 200px; overflow-y: auto;">${colorOptionsHtml}</ul>
                </div>
            </div>
            <a href="#" class="list-group-item list-group-item-action" onclick="event.preventDefault(); handleCompareVerse(${globalId}, '${bookChapterTitleText.replace(/'/g, "\\'")}', '${verseNumText.replace(/'/g, "\\'")}')">Verse vergleichen</a>
            <a href="#" class="list-group-item list-group-item-action" onclick="event.preventDefault(); handleShowComments(${globalId}, '${bookChapterTitleText.replace(/'/g, "\\'")}', '${verseNumText.replace(/'/g, "\\'")}')">Kommentare</a>
        </div>`;

    try {
        activePopoverInstance = new bootstrap.Popover(verseElement, {
            html: true, title: `Aktionen für ${bookChapterTitleText}, Vers ${verseNumText}`,
            content: actionsHtml, sanitize: false, trigger: 'manual'
        });
        if (activePopoverInstance) {
            activePopoverInstance.show();
            activePopoverTargetElement = verseElement;
        } else {
            console.error("app.js FEHLER: bootstrap.Popover() hat kein gültiges Objekt zurückgegeben.");
        }
    } catch (error) {
        console.error("app.js FEHLER: Fehler beim Erstellen oder Anzeigen des Popovers:", error);
        closeActivePopover();
    }
}

function closeActivePopover() {
    if (activePopoverInstance) {
        try {
            activePopoverInstance.dispose();
        } catch (e) { console.error("app.js FEHLER beim dispose() des Popovers:", e); }
        activePopoverInstance = null;
        activePopoverTargetElement = null;
    }
}

function handleColorHighlight(globalVerseId, colorValue) {
    if (currentUserId === 0 && colorValue !== 'remove_color') {
        alert("Bitte zuerst einloggen, um farbliche Markierungen zu setzen.");
        return;
    }
    closeActivePopover();

    const verseElement = document.querySelector(`.verse[data-global-id='${globalVerseId}']`);
    if (!verseElement) return;

    const colorMap = {
        'yellow': 'bg-warning', 'green': 'bg-green-custom', 'blue': 'bg-blue-custom',
        'red': 'bg-red-custom', 'orange': 'bg-orange-custom', 'brown': 'bg-brown-custom',
        'gray': 'bg-gray-custom', 'parable': 'bg-parable-custom', 'rebuke': 'bg-rebuke-custom',
        'name': 'bg-name-custom', 'sanctification': 'bg-sanctification-custom',
        'miracle': 'bg-miracle-custom', 'gods_work': 'bg-gods-work-custom', 'law': 'bg-law-custom',
        'wisdom': 'bg-wisdom-custom', 'worship': 'bg-worship-custom', 'covenant': 'bg-covenant-custom'
    };
    let actionForServer = colorValue === 'remove_color' ? 'removeHighlight' : 'addHighlight';

    const oldColor = verseElement.dataset.currentColor; // Alte Farbe für mögliches Rollback merken
    Object.values(colorMap).forEach(cssClass => verseElement.classList.remove(cssClass));

    if (colorValue !== 'remove_color' && colorMap[colorValue]) {
        if (oldColor === colorValue && actionForServer === 'addHighlight') {
            actionForServer = 'removeHighlight'; // Toggle: Gleiche Farbe -> entfernen
            verseElement.dataset.currentColor = '';
        } else {
            verseElement.classList.add(colorMap[colorValue]);
            verseElement.dataset.currentColor = colorValue;
            actionForServer = 'addHighlight'; // Sicherstellen, dass es add ist, wenn eine neue Farbe gesetzt wird
        }
    } else {
        verseElement.dataset.currentColor = '';
        actionForServer = 'removeHighlight'; // Sicherstellen für 'remove_color'
    }

    // AJAX Call nur wenn eingeloggter User die Farbe ändert oder entfernt
    if (currentUserId > 0) {
        fetch('ajax_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=${actionForServer}&global_verse_id=${globalVerseId}&user_id=${currentUserId}&color=${(actionForServer === 'removeHighlight' || colorValue === 'remove_color') ? '' : colorValue}`
        })
            .then(response => response.json())
            .then(data => {
                if (data.status !== 'success') {
                    alert('Fehler vom Server: ' + data.message);
                    // Rollback der UI-Änderung
                    Object.values(colorMap).forEach(cssClass => verseElement.classList.remove(cssClass));
                    if (oldColor && colorMap[oldColor]) {
                        verseElement.classList.add(colorMap[oldColor]);
                    }
                    verseElement.dataset.currentColor = oldColor ?? '';
                } else {
                    // Da Farben global sind, ist ein Reload hier am sichersten, um den
                    // absolut korrekten globalen Zustand zu sehen (falls ein anderer User schnell war).
                    // Für ein "smoother" Erlebnis könnte man den Reload weglassen,
                    // riskiert aber, dass nicht die "neueste" globale Farbe angezeigt wird.
                    // Kompromiss: Nur bei 'removeHighlight' neu laden, wenn die globale Farbe sich ändern könnte.
                    if (actionForServer === 'removeHighlight' && oldColor) {
                        window.location.reload();
                    } else if (actionForServer === 'addHighlight' && data.newGlobalColor && data.newGlobalColor !== colorValue) {
                        // Optional: Wenn der Server eine andere globale Farbe zurückgibt, Seite neu laden
                        window.location.reload();
                    }
                    // Ansonsten ist die UI schon aktuell für die Aktion dieses Users.
                }
            }).catch(error => {
                console.error("Fehler bei AJAX (handleColorHighlight):", error);
                alert("Kommunikationsfehler beim Speichern der Farb-Markierung.");
                // Rollback der UI-Änderung
                Object.values(colorMap).forEach(cssClass => verseElement.classList.remove(cssClass));
                if (oldColor && colorMap[oldColor]) {
                    verseElement.classList.add(colorMap[oldColor]);
                }
                verseElement.dataset.currentColor = oldColor ?? '';
            });
    } else if (actionForServer === 'addHighlight' && colorValue !== 'remove_color') {
        // Gast versucht Farbe zu setzen -> UI zurücksetzen und Meldung
        Object.values(colorMap).forEach(cssClass => verseElement.classList.remove(cssClass));
        verseElement.dataset.currentColor = '';
        // alert("Nur eingeloggte Benutzer können farbliche Markierungen setzen."); // Bereits oben
    }
}

function handleMarkAsImportant(globalVerseId, linkElement) {
    if (currentUserId === 0) {
        alert("Bitte zuerst einloggen, um Verse als wichtig zu markieren.");
        return;
    }
    // Popover wird nach Klick auf den Link automatisch durch den document-listener geschlossen
    // Daher ist closeActivePopover() hier nicht zwingend, schadet aber nicht.
    closeActivePopover();

    const verseElement = document.querySelector(`.verse[data-global-id='${globalVerseId}']`);
    if (!verseElement) return;

    const wasImportant = verseElement.classList.contains('user-important-verse-text');
    const actionForServer = wasImportant ? 'removeImportant' : 'addImportant';

    // Optimistisches UI-Update (sofortige visuelle Rückmeldung)
    verseElement.classList.toggle('user-important-verse-text');
    verseElement.dataset.isImportant = verseElement.classList.contains('user-important-verse-text').toString();

    const starIconClass = 'user-important-star';
    let existingStar = verseElement.querySelector('i.' + starIconClass); // i-Tag mit der Klasse

    if (verseElement.classList.contains('user-important-verse-text')) {
        if (!existingStar) {
            const starIcon = document.createElement('i');
            starIcon.className = `bi bi-star-fill ${starIconClass}`; // Bootstrap- und benutzerdefinierte Klasse
            starIcon.title = "Für mich wichtig";

            // Füge ein Leerzeichen vor dem Stern ein, wenn der Stern nicht das erste Kind ist
            // und das vorherige Element kein Leerzeichen ist.
            // Besser: Stern immer als erstes Kind einfügen, dann Text.
            // Für die Einfachheit hier:
            const textNodeForSpacing = document.createTextNode('\u00A0'); // Non-breaking space
            verseElement.insertBefore(textNodeForSpacing, verseElement.firstChild);
            verseElement.insertBefore(starIcon, verseElement.firstChild);
        }
        if (linkElement) linkElement.textContent = "Als 'wichtig' entfernen";
    } else {
        if (existingStar) {
            // Entferne auch das Leerzeichen davor, falls es unser eingefügtes war
            if (existingStar.nextSibling && existingStar.nextSibling.nodeType === Node.TEXT_NODE && existingStar.nextSibling.textContent === '\u00A0') {
                existingStar.nextSibling.remove();
            }
            existingStar.remove();
        }
        if (linkElement) linkElement.textContent = "Für mich wichtig markieren";
    }

    fetch('ajax_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=${actionForServer}&global_verse_id=${globalVerseId}&user_id=${currentUserId}`
    })
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'success') {
                alert('Fehler vom Server: ' + data.message);
                // Rollback des UI-Updates
                verseElement.classList.toggle('user-important-verse-text', wasImportant);
                verseElement.dataset.isImportant = wasImportant.toString();
                existingStar = verseElement.querySelector('i.' + starIconClass); // Erneut suchen
                if (wasImportant && !existingStar) {
                    const starIcon = document.createElement('i');
                    starIcon.className = `bi bi-star-fill ${starIconClass}`;
                    starIcon.title = "Für mich wichtig";
                    const textNodeForSpacing = document.createTextNode('\u00A0');
                    verseElement.insertBefore(textNodeForSpacing, verseElement.firstChild);
                    verseElement.insertBefore(starIcon, verseElement.firstChild);
                } else if (!wasImportant && existingStar) {
                    if (existingStar.nextSibling && existingStar.nextSibling.nodeType === Node.TEXT_NODE && existingStar.nextSibling.textContent === '\u00A0') {
                        existingStar.nextSibling.remove();
                    }
                    existingStar.remove();
                }
                if (linkElement) linkElement.textContent = wasImportant ? "Als 'wichtig' entfernen" : "Für mich wichtig markieren";
            }
            // KEIN window.location.reload(); hier, um das Springen zu vermeiden.
        }).catch(error => {
            console.error("Fehler bei AJAX (handleMarkAsImportant):", error);
            alert("Kommunikationsfehler beim Speichern der 'Wichtig'-Markierung.");
            // Rollback des UI-Updates
            verseElement.classList.toggle('user-important-verse-text', wasImportant);
            verseElement.dataset.isImportant = wasImportant.toString();
            existingStar = verseElement.querySelector('i.' + starIconClass); // Erneut suchen
            if (wasImportant && !existingStar) { /* Stern hinzufügen Logik */ } else if (!wasImportant && existingStar) { /* Stern entfernen Logik */ }
            if (linkElement) linkElement.textContent = wasImportant ? "Als 'wichtig' entfernen" : "Für mich wichtig markieren";
        });
}

// --- Bestehende Funktionen für Modals (Vergleichen, Kommentare) ---
function handleCompareVerse(globalVerseId, bookChapterTitleText, verseNumText) {
    closeActivePopover();
    const modalElement = document.getElementById('compareModal');
    if (!modalElement) return;
    const compareModal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const modalTitle = document.getElementById('compareModalLabel');
    const modalBody = document.getElementById('compareModalBody');
    if (!modalTitle || !modalBody) return;
    modalTitle.textContent = `Versvergleich für ${bookChapterTitleText}, Vers ${verseNumText}`;
    modalBody.innerHTML = '<div class="text-center my-3"><div class="spinner-border" role="status"></div></div>';
    compareModal.show();
    fetch('ajax_handler.php', {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=compareVerse&global_verse_id=${globalVerseId}`
    }).then(response => response.text()).then(htmlContent => modalBody.innerHTML = htmlContent)
        .catch(error => modalBody.innerHTML = '<p class="text-danger">Fehler.</p>');
}

function handleShowComments(globalVerseId, bookChapterTitleText, verseNumText) {
    closeActivePopover();
    const modalElement = document.getElementById('commentsModal');
    if (!modalElement) return;
    const commentsModal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const modalTitle = document.getElementById('commentsModalLabel');
    const modalBody = document.getElementById('commentsModalBody');
    if (!modalTitle || !modalBody) return;
    modalTitle.textContent = `Kommentare für ${bookChapterTitleText}, Vers ${verseNumText}`;
    modalBody.innerHTML = '<div class="text-center my-3"><div class="spinner-border" role="status"></div></div>';
    commentsModal.show();
    fetch('ajax_handler.php', {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=getComments&global_verse_id=${globalVerseId}`
    }).then(response => response.text()).then(htmlContent => {
        modalBody.innerHTML = `${htmlContent}<hr class="my-3"><h5>Neuen Kommentar schreiben:</h5><textarea id="newCommentTextForModal_${globalVerseId}" class="form-control" rows="3"></textarea><button class="btn btn-primary btn-sm mt-2" onclick="submitNewCommentFromModal(${globalVerseId}, '${bookChapterTitleText.replace(/'/g, "\\'")}', '${verseNumText.replace(/'/g, "\\'")}')">Speichern</button>`;
    }).catch(error => modalBody.innerHTML = '<p class="text-danger">Fehler.</p>');
}

function submitNewCommentFromModal(globalVerseId, bookChapterTitleText, verseNumText) {
    if (currentUserId === 0) { alert("Bitte zuerst einloggen."); return; }
    const newCommentTextElement = document.getElementById(`newCommentTextForModal_${globalVerseId}`);
    const noteText = newCommentTextElement ? newCommentTextElement.value.trim() : "";
    if (noteText === "") { alert("Bitte Text eingeben."); return; }
    fetch('ajax_handler.php', {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=addComment&global_verse_id=${globalVerseId}&user_id=${currentUserId}&note_text=${encodeURIComponent(noteText)}`
    }).then(response => response.json()).then(data => {
        alert(data.message);
        if (data.status === 'success') {
            handleShowComments(globalVerseId, bookChapterTitleText, verseNumText);
        }
    }).catch(error => alert('Kommunikationsfehler.'));
}






// In einer neuen JS-Datei oder am Ende von app.js

document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('commentListContainer')) { // Nur ausführen, wenn wir auf der Kommentarseite sind
        loadComments(1); // Lade die erste Seite beim Start

        document.getElementById('filterCommentsForm').addEventListener('submit', function (e) {
            e.preventDefault();
            loadComments(1); // Lade Seite 1 mit neuen Filtern
        });

        // Event-Listener für Pagination-Klicks
        document.getElementById('commentPagination').addEventListener('click', function (e) {
            e.preventDefault();
            if (e.target.tagName === 'A' && e.target.dataset.page) {
                loadComments(parseInt(e.target.dataset.page));
            }
        });

        // Event-Delegation für Like-Buttons
        document.getElementById('commentListContainer').addEventListener('click', function (e) {
            if (e.target.closest('.like-comment-btn')) {
                e.preventDefault();
                const button = e.target.closest('.like-comment-btn');
                const noteId = button.dataset.noteId;
                handleLikeComment(noteId, button);
            }
            if (e.target.closest('.edit-comment-btn')) {
                e.preventDefault();
                const button = e.target.closest('.edit-comment-btn');
                const noteId = button.dataset.noteId;
                // Hier Logik zum Öffnen des Edit-Modals und Befüllen
                openEditCommentModal(noteId, button.dataset.currentText);
            }
            if (e.target.closest('.delete-comment-btn')) {
                e.preventDefault();
                const button = e.target.closest('.delete-comment-btn');
                const noteId = button.dataset.noteId;
                if (confirm('Möchten Sie diesen Kommentar wirklich löschen?')) {
                    handleDeleteComment(noteId);
                }
            }
        });

        // Speichern-Button im Edit-Modal
        const saveCommentButton = document.getElementById('saveCommentChanges');
        if (saveCommentButton) {
            saveCommentButton.addEventListener('click', function () {
                const noteId = document.getElementById('editCommentId').value;
                const newText = document.getElementById('editCommentText').value;
                handleUpdateComment(noteId, newText);
            });
        }
    }
});

function loadComments(page = 1) {
    const container = document.getElementById('commentListContainer');
    const paginationContainer = document.getElementById('commentPagination');
    if (!container || !paginationContainer) return;

    const form = document.getElementById('filterCommentsForm');
    const formData = new FormData(form);
    const params = new URLSearchParams(formData);
    params.append('page', page);

    // Wenn "Nur meine Kommentare" angehakt ist, filter_user_id setzen
    const myCommentsCheckbox = document.getElementById('my_comments');
    if (myCommentsCheckbox && myCommentsCheckbox.checked && currentUserId > 0) {
        params.append('filter_user_id', currentUserId);
    }


    container.innerHTML = '<p class="text-center">Lade Kommentare...</p>';
    paginationContainer.innerHTML = ''; // Pagination leeren

    fetch(`ajax_handler.php?action=get_all_comments&${params.toString()}`) // GET-Request
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.comments) {
                renderComments(data.comments, container);
                renderPagination(data.pagination, paginationContainer);
            } else {
                container.innerHTML = '<p class="text-danger">Fehler beim Laden der Kommentare.</p>';
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            container.innerHTML = '<p class="text-danger">Ein Fehler ist aufgetreten.</p>';
        });
}

function renderComments(comments, container) {
    if (comments.length === 0) {
        container.innerHTML = '<p class="text-center">Keine Kommentare gefunden, die Ihren Kriterien entsprechen.</p>';
        return;
    }
    let html = '<div class="list-group">';
    comments.forEach(comment => {
        const verseLink = `bibel_lesen.php?book_id=${comment.book_id}&chapter=${comment.chapter_number}&highlight_verse=${comment.verse_global_id}#vers-${comment.verse_number}`;
        const commentDate = new Date(comment.note_date).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });

        html += `
            <div class="list-group-item mb-3 shadow-sm">
                <div class="d-flex w-100 justify-content-between">
                    <h5 class="mb-1">
                        <a href="${verseLink}" target="_blank">
                            ${comment.book_name_de} ${comment.chapter_number},${comment.verse_number}
                        </a>
                    </h5>
                    <small class="text-muted">${commentDate}</small>
                </div>
                <p class="mb-1"><em>"${htmlspecialchars(comment.verse_text)}"</em></p>
                <p class="mt-2 mb-2 comment-text-${comment.note_id}">${nl2br(htmlspecialchars(comment.note_text))}</p>
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">Von: ${htmlspecialchars(comment.author_username)}</small>
                    <div>
                        <button class="btn btn-sm btn-outline-primary like-comment-btn" data-note-id="${comment.note_id}">
                            <i class="bi bi-heart"></i> <span class="like-count">${comment.likes}</span>
                        </button>
                        ${comment.author_id == currentUserId ? `
                            <button class="btn btn-sm btn-outline-secondary edit-comment-btn ms-2" 
                                    data-note-id="${comment.note_id}" 
                                    data-current-text="${htmlspecialchars(comment.note_text)}">
                                <i class="bi bi-pencil"></i> Bearbeiten
                            </button>
                            <button class="btn btn-sm btn-outline-danger delete-comment-btn ms-1" 
                                    data-note-id="${comment.note_id}">
                                <i class="bi bi-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                </div>
            </div>`;
    });
    html += '</div>';
    container.innerHTML = html;
}

function renderPagination(pagination, container) {
    if (pagination.totalPages <= 1) {
        container.innerHTML = '';
        return;
    }
    let html = '';
    // Zurück-Button
    html += `<li class="page-item ${pagination.currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${pagination.currentPage - 1}">Zurück</a>
             </li>`;
    // Seitenzahlen
    for (let i = 1; i <= pagination.totalPages; i++) {
        html += `<li class="page-item ${i === pagination.currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                 </li>`;
    }
    // Vorwärts-Button
    html += `<li class="page-item ${pagination.currentPage === pagination.totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${pagination.currentPage + 1}">Weiter</a>
             </li>`;
    container.innerHTML = html;
}

function handleLikeComment(noteId, buttonElement) {
    if (currentUserId === 0) {
        alert("Bitte einloggen, um zu liken.");
        return;
    }
    fetch('ajax_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=like_comment&user_id=${currentUserId}&global_verse_id=${noteId}` // noteId wird hier als global_verse_id gesendet
    })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && buttonElement) {
                const likeCountSpan = buttonElement.querySelector('.like-count');
                if (likeCountSpan) likeCountSpan.textContent = data.like_count;
                // Optional: Icon ändern (gefülltes Herz vs. leeres Herz)
                // buttonElement.querySelector('i.bi').classList.toggle('bi-heart-fill', data.action === 'liked');
                // buttonElement.querySelector('i.bi').classList.toggle('bi-heart', data.action === 'unliked');
            } else {
                alert(data.message || "Fehler beim Liken.");
            }
        })
        .catch(error => console.error("Fehler beim Liken:", error));
}

function openEditCommentModal(noteId, currentText) {
    document.getElementById('editCommentId').value = noteId;
    document.getElementById('editCommentText').value = currentText;
    const modal = new bootstrap.Modal(document.getElementById('editCommentModal'));
    modal.show();
}

function handleUpdateComment(noteId, newText) {
    fetch('ajax_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=update_comment&user_id=${currentUserId}&global_verse_id=${noteId}&note_text=${encodeURIComponent(newText)}`
    })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.status === 'success') {
                bootstrap.Modal.getInstance(document.getElementById('editCommentModal')).hide();
                // UI direkt aktualisieren oder loadComments() für die aktuelle Seite aufrufen
                const commentTextElement = document.querySelector(`.comment-text-${noteId}`);
                if (commentTextElement) commentTextElement.innerHTML = nl2br(htmlspecialchars(newText));
                // Besser: loadComments(aktuelle_seite);
            }
        })
        .catch(error => console.error("Fehler beim Speichern des Kommentars:", error));
}

function handleDeleteComment(noteId) {
    fetch('ajax_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete_comment&user_id=${currentUserId}&global_verse_id=${noteId}`
    })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.status === 'success') {
                loadComments(1); // Oder die aktuelle Seite neu laden
            }
        })
        .catch(error => console.error("Fehler beim Löschen des Kommentars:", error));
}


// Hilfsfunktionen
function htmlspecialchars(str) {
    if (typeof str !== 'string') return '';
    return str.replace(/[&<>"']/g, function (match) {
        const G_MAP = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
        return G_MAP[match];
    });
}
function nl2br(str) {
    if (typeof str !== 'string') return '';
    return str.replace(/(?:\r\n|\r|\n)/g, '<br>');
}



// FÜGEN SIE DIESE GESAMTE NEUE FUNKTION IN app.js EIN

function initializeModalLikeButtonLogic() {
    const commentsModalElement = document.getElementById('commentsModal');
    if (!commentsModalElement) return;

    // Wir verwenden Event Delegation, da der Inhalt des Modals dynamisch geladen wird.
    commentsModalElement.addEventListener('click', function (event) {
        const likeButton = event.target.closest('.like-comment-btn-modal');

        if (likeButton) {
            event.preventDefault(); // Standard-Aktion des Buttons verhindern

            if (currentUserId === 0) {
                alert("Bitte einloggen, um zu liken.");
                return;
            }

            const noteId = likeButton.dataset.noteId;
            if (!noteId) {
                console.error("Fehler: Konnte note-id vom Button nicht lesen.");
                return;
            }

            // AJAX-Anfrage zum Liken/Unliken senden
            fetch('ajax_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                // Wir senden `note_id`, wie es der PHP-Handler erwartet
                body: `action=like_comment&note_id=${noteId}`
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Netzwerk-Antwort war nicht ok.');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success') {
                        // UI des Buttons direkt im Modal aktualisieren
                        const likeCountSpan = likeButton.querySelector('.like-count');
                        const heartIcon = likeButton.querySelector('i.bi');

                        if (likeCountSpan) {
                            likeCountSpan.textContent = data.like_count;
                        }
                        if (heartIcon) {
                            const isLiked = data.action_taken === 'liked';
                            heartIcon.classList.toggle('bi-heart-fill', isLiked);
                            heartIcon.classList.toggle('text-danger', isLiked);
                            heartIcon.classList.toggle('bi-heart', !isLiked);
                        }
                    } else {
                        alert(data.message || 'Ein serverseitiger Fehler ist aufgetreten.');
                    }
                })
                .catch(error => {
                    console.error('Fehler beim Liken im Modal:', error);
                    alert('Kommunikationsfehler beim Liken.');
                });
        }
    });
}