// app.js - Finale, VOLLSTÄNDIGE Version (27.05.2025)

// Globale Variablen
const currentUserId = typeof currentUserIdFromPHP !== 'undefined' ? currentUserIdFromPHP : 0;
let isBrushModeActive = false;

// Event-Listener, der nach dem Laden der Seite ausgeführt wird
document.addEventListener('DOMContentLoaded', function () {
    if (typeof bootstrap === 'undefined') {
        console.error('FEHLER: Bootstrap ist nicht geladen!');
        return;
    }
    
    // Logik für bibel_lesen.php (und global)
    initializeThemeToggler();
    initializeVerseActionLogic(); 
    initializeModalLikeButtonLogic(); // Für Likes im Kommentare-Modal von bibel_lesen.php

    const brushModeButton = document.getElementById('brush-mode-toggle');
    if (brushModeButton) {
        brushModeButton.addEventListener('click', () => {
            isBrushModeActive = !isBrushModeActive;
            brushModeButton.classList.toggle('btn-primary', isBrushModeActive);
            if (!isBrushModeActive) {
                brushModeButton.classList.remove('btn-outline-secondary');
            }

            if (isBrushModeActive) {
                hideVerseActionModal();
                const toastEl = document.getElementById('brushModeToast');
                if (toastEl) {
                    const toast = bootstrap.Toast.getOrCreateInstance(toastEl);
                    toast.show();
                }
            }
        });
    }

    // Logik NUR für kommentare_uebersicht.php
    if (document.getElementById('commentListContainer')) {
        initializeCommentPageLogic();
    }
});


// --- Globale Hilfsfunktionen ---
function htmlspecialchars(str) {
    if (typeof str !== 'string') return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
    return str.replace(/[&<>"']/g, m => map[m]);
}
function nl2br(str) {
    if (typeof str !== 'string') return '';
    return str.replace(/(?:\r\n|\r|\n)/g, '<br>');
}


// --- Funktionen für bibel_lesen.php (und global genutzte wie Theme) ---

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

function initializeVerseActionLogic() {
    const verseContainer = document.querySelector('.verse-container');
    if (!verseContainer) return;

    verseContainer.addEventListener('click', function (event) {
        const clickedVerse = event.target.closest('.verse');
        if (!clickedVerse) return;

        if (isBrushModeActive) {
            const lastColor = localStorage.getItem('lastUsedColor');
            if (!lastColor || lastColor === 'remove_color') {
                alert("Bitte wählen Sie zuerst eine Farbe aus, um den Pinsel-Modus zu verwenden.");
                return;
            }
            handleColorHighlight(clickedVerse.dataset.globalId, lastColor);
            return; 
        }
        
        showVerseActionsInModal(clickedVerse);
    });
}

function showVerseActionsInModal(verseElement) {
    const modalElement = document.getElementById('verseActionModal');
    if (!modalElement) return;

    const verseActionModal = bootstrap.Modal.getOrCreateInstance(modalElement);
    
    const globalId = verseElement.dataset.globalId;
    const verseNumSpan = verseElement.previousElementSibling;
    const verseNumText = verseNumSpan ? verseNumSpan.textContent.trim() : '';
    const bookChapterTitleText = document.querySelector('h1.text-center')?.textContent.trim() || 'Vers';

    const modalTitle = document.getElementById('verseActionModalLabel');
    const modalBody = document.getElementById('verseActionModalBody');

    modalTitle.textContent = `Aktionen für ${bookChapterTitleText}, Vers ${verseNumText}`;

    const highlightColors = [
        { name: 'Wichtig (Gelb)', value: 'yellow', cssClass: 'bg-warning' }, { name: 'Ermutigung & Trost (Grün)', value: 'green', cssClass: 'bg-green-custom' },
        { name: 'Gebot (Blau)', value: 'blue', cssClass: 'bg-blue-custom' }, { name: 'Sünde/Warnung (Rot)', value: 'red', cssClass: 'bg-red-custom' },
        { name: 'Verheißung & Prophetie (Orange)', value: 'orange', cssClass: 'bg-orange-custom' }, { name: 'Geschichte (Braun)', value: 'brown', cssClass: 'bg-brown-custom' },
        { name: 'Gleichnis (Türkis)', value: 'parable', cssClass: 'bg-parable-custom' }, { name: 'Zurechtweisung (Lavendel)', value: 'rebuke', cssClass: 'bg-rebuke-custom' },
        { name: 'Namen/Orte (Oliv)', value: 'name', cssClass: 'bg-name-custom' }, { name: 'Heiligung (Rosé)', value: 'sanctification', cssClass: 'bg-sanctification-custom' },
        { name: 'Wunder (Gold)', value: 'miracle', cssClass: 'bg-miracle-custom' }, { name: 'Gottes Wirken (Himmelblau)', value: 'gods_work', cssClass: 'bg-gods-work-custom' },
        { name: 'Gesetz (Schiefergrau)', value: 'law', cssClass: 'bg-law-custom' }, { name: 'Weisheit/Lehre (Flieder)', value: 'wisdom', cssClass: 'bg-wisdom-custom' },
        { name: 'Anbetung/Gebet (Pfirsich)', value: 'worship', cssClass: 'bg-worship-custom' }, { name: 'Bund (Weinrot)', value: 'covenant', cssClass: 'bg-covenant-custom' },
        { name: 'Unklar (Grau)', value: 'gray', cssClass: 'bg-gray-custom' }, { name: 'Entfernen', value: 'remove_color', cssClass: 'remove-color-swatch' }
    ];

    const colorPickerHtml = `
        <div class="popover-color-picker">
            ${highlightColors.map(color => {
                const displayName = color.name.split(' (')[0];
                return `
                    <div class="popover-color-item" title="${color.name}" onclick="event.preventDefault(); handleColorHighlight(${globalId}, '${color.value}')">
                        <div class="popover-color-swatch ${color.cssClass}"></div>
                        <div class="popover-color-name">${displayName}</div>
                    </div>
                `;
            }).join('')}
        </div>
    `;

    const isAlreadyImportant = verseElement.dataset.isImportant === 'true'; 
    const importantLinkText = isAlreadyImportant ? "Als 'wichtig' entfernen" : "Für mich wichtig markieren";
    
    const actionsHtml = `
        <div class="list-group list-group-flush">
            ${colorPickerHtml}
            <div class="list-group-item mt-2">
                <div class="d-grid gap-2">
                     <a href="#" class="btn btn-outline-primary" onclick="event.preventDefault(); handleMarkAsImportant(${globalId})">${importantLinkText}</a>
                     <a href="#" class="btn btn-outline-secondary" onclick="event.preventDefault(); handleCompareVerse(${globalId}, '${bookChapterTitleText.replace(/'/g, "\\'")}', '${verseNumText.replace(/'/g, "\\'")}')">Verse vergleichen</a>
                     <a href="#" class="btn btn-outline-secondary" onclick="event.preventDefault(); handleShowComments(${globalId}, '${bookChapterTitleText.replace(/'/g, "\\'")}', '${verseNumText.replace(/'/g, "\\'")}')">Kommentare</a>
                </div>
            </div>
        </div>`;

    modalBody.innerHTML = actionsHtml;
    verseActionModal.show();
}

function hideVerseActionModal() {
    const modalElement = document.getElementById('verseActionModal');
    if (modalElement) {
        const verseModal = bootstrap.Modal.getInstance(modalElement);
        if (verseModal && verseModal._isShown) { // Prüfen ob Modal überhaupt angezeigt wird
            verseModal.hide();
        }
    }
}

function handleColorHighlight(globalVerseId, colorValue) {
    hideVerseActionModal();

    if (currentUserId === 0 && colorValue !== 'remove_color') {
        alert("Bitte zuerst einloggen, um farbliche Markierungen zu setzen.");
        return;
    }
    
    if (colorValue !== 'remove_color') {
        localStorage.setItem('lastUsedColor', colorValue);
    }
    
    const verseElement = document.querySelector(`.verse[data-global-id='${globalVerseId}']`);
    if (!verseElement) return;

    const colorMap = {
        'yellow': 'bg-warning', 'green': 'bg-green-custom', 'blue': 'bg-blue-custom', 'red': 'bg-red-custom', 'orange': 'bg-orange-custom', 
        'brown': 'bg-brown-custom', 'gray': 'bg-gray-custom', 'parable': 'bg-parable-custom', 'rebuke': 'bg-rebuke-custom', 'name': 'bg-name-custom', 
        'sanctification': 'bg-sanctification-custom', 'miracle': 'bg-miracle-custom', 'gods_work': 'bg-gods-work-custom', 'law': 'bg-law-custom',
        'wisdom': 'bg-wisdom-custom', 'worship': 'bg-worship-custom', 'covenant': 'bg-covenant-custom'
    };

    const oldColorClass = Object.values(colorMap).find(c => verseElement.classList.contains(c));
    if (oldColorClass) {
        verseElement.classList.remove(oldColorClass);
    }

    if (colorValue !== 'remove_color' && colorMap[colorValue]) {
        verseElement.classList.add(colorMap[colorValue]);
    }
    // verseElement.dataset.currentColor wird im Backend gesetzt, UI ist optimistich

    const actionForServer = colorValue === 'remove_color' ? 'removeHighlight' : 'addHighlight';
    fetch('ajax_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=${actionForServer}&global_verse_id=${globalVerseId}&color=${colorValue}` // user_id kommt aus Session im Backend
    })
    .then(response => response.json())
    .then(data => {
        if (data.status !== 'success') {
            alert('Fehler beim Speichern der Markierung: ' + data.message);
            // Bei Fehler: UI zurücksetzen
            if (colorMap[colorValue]) verseElement.classList.remove(colorMap[colorValue]);
            if (oldColorClass) verseElement.classList.add(oldColorClass);
        }
        // Kein window.location.reload(); für besseres Nutzererlebnis
    })
    .catch(error => {
        console.error('Farb-Update-Fehler:', error);
        alert('Kommunikationsfehler beim Speichern der Markierung.');
        if (colorMap[colorValue]) verseElement.classList.remove(colorMap[colorValue]);
        if (oldColorClass) verseElement.classList.add(oldColorClass);
    });
}


function handleMarkAsImportant(globalVerseId) {
    hideVerseActionModal();
    if (currentUserId === 0) {
        alert("Bitte zuerst einloggen.");
        return;
    }

    const verseElement = document.querySelector(`.verse[data-global-id='${globalVerseId}']`);
    if (!verseElement) return;

    const wasImportant = verseElement.classList.contains('user-important-verse-text');
    const actionForServer = wasImportant ? 'removeImportant' : 'addImportant';

    // Optimistisches UI Update für den Stern
    const starIconClass = 'user-important-star';
    let existingStar = verseElement.querySelector('i.' + starIconClass);

    if (actionForServer === 'addImportant') {
        if (!existingStar) {
            const starIcon = document.createElement('i');
            starIcon.className = `bi bi-star-fill ${starIconClass}`;
            starIcon.title = "Für mich wichtig";
            const spaceNode = document.createTextNode('\u00A0'); // Non-breaking space
            verseElement.prepend(spaceNode);
            verseElement.prepend(starIcon);
        }
        verseElement.classList.add('user-important-verse-text');
        verseElement.dataset.isImportant = 'true';
    } else { // removeImportant
        if (existingStar) {
            if (existingStar.previousSibling && existingStar.previousSibling.nodeType === Node.TEXT_NODE && existingStar.previousSibling.textContent === '\u00A0') {
                existingStar.previousSibling.remove();
            }
            existingStar.remove();
        }
        verseElement.classList.remove('user-important-verse-text');
        verseElement.dataset.isImportant = 'false';
    }
    
    fetch('ajax_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=${actionForServer}&global_verse_id=${globalVerseId}` // user_id kommt aus Session
    })
    .then(response => response.json())
    .then(data => {
        if (data.status !== 'success') {
            alert('Fehler: ' + data.message);
            // Rollback UI
            verseElement.classList.toggle('user-important-verse-text', wasImportant);
            verseElement.dataset.isImportant = wasImportant.toString();
            existingStar = verseElement.querySelector('i.' + starIconClass);
            if (wasImportant && !existingStar) {
                 const starIcon = document.createElement('i'); /* ... Stern wiederherstellen ... */ 
            } else if (!wasImportant && existingStar) {
                 if (existingStar.previousSibling && existingStar.previousSibling.nodeType === Node.TEXT_NODE) existingStar.previousSibling.remove();
                 existingStar.remove(); /* ... Stern entfernen ... */
            }
        }
    })
    .catch(error => {
        console.error('Wichtig-Update-Fehler:', error);
        alert('Kommunikationsfehler beim Speichern der "Wichtig"-Markierung.');
        // Kompletter Rollback
        verseElement.classList.toggle('user-important-verse-text', wasImportant);
        verseElement.dataset.isImportant = wasImportant.toString();
        existingStar = verseElement.querySelector('i.' + starIconClass);
         if (wasImportant && !existingStar) { /* ... Stern wiederherstellen ... */ } else if (!wasImportant && existingStar) { /* ... Stern entfernen ... */ }
    });
}

function handleCompareVerse(globalVerseId, bookChapterTitleText, verseNumText) {
    hideVerseActionModal();
    const modalElement = document.getElementById('compareModal');
    if (!modalElement) return;
    const compareModal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const modalTitle = document.getElementById('compareModalLabel');
    const modalBody = document.getElementById('compareModalBody');
    modalTitle.textContent = `Versvergleich für ${bookChapterTitleText}, Vers ${verseNumText}`;
    modalBody.innerHTML = '<div class="text-center my-3"><div class="spinner-border" role="status"></div></div>';
    compareModal.show();
    fetch(`ajax_handler.php?action=compareVerse&global_verse_id=${globalVerseId}`) // War POST, GET ist hier sinnvoller
        .then(response => response.text())
        .then(html => modalBody.innerHTML = html)
        .catch(err => modalBody.innerHTML = '<p class="text-danger">Fehler beim Laden der Vergleichsverse.</p>');
}

function handleShowComments(globalVerseId, bookChapterTitleText, verseNumText) {
    hideVerseActionModal();
    const modalElement = document.getElementById('commentsModal');
    if (!modalElement) return;
    const commentsModal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const modalTitle = document.getElementById('commentsModalLabel');
    const modalBody = document.getElementById('commentsModalBody');
    modalTitle.textContent = `Kommentare für ${bookChapterTitleText}, Vers ${verseNumText}`;
    modalBody.innerHTML = '<div class="text-center my-3"><div class="spinner-border" role="status"></div></div>';
    commentsModal.show();
    fetch(`ajax_handler.php?action=getComments&global_verse_id=${globalVerseId}`) // War POST, GET ist hier sinnvoller
        .then(response => response.text())
        .then(html => {
            modalBody.innerHTML = `${html}<hr class="my-3"><h5>Neuen Kommentar schreiben:</h5><textarea id="newCommentTextForModal_${globalVerseId}" class="form-control" rows="3"></textarea><button class="btn btn-primary btn-sm mt-2" onclick="submitNewCommentFromModal(${globalVerseId}, '${bookChapterTitleText.replace(/'/g, "\\'")}', '${verseNumText.replace(/'/g, "\\'")}')">Speichern</button>`;
        }).catch(err => modalBody.innerHTML = '<p class="text-danger">Fehler beim Laden der Kommentare.</p>');
}

function submitNewCommentFromModal(globalVerseId, bookChapterTitleText, verseNumText) {
    if (currentUserId === 0) { alert("Bitte zuerst einloggen."); return; }
    const newCommentTextElement = document.getElementById(`newCommentTextForModal_${globalVerseId}`);
    const noteText = newCommentTextElement ? newCommentTextElement.value.trim() : "";
    if (noteText === "") { alert("Bitte Text eingeben."); return; }
    fetch('ajax_handler.php', {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=addComment&global_verse_id=${globalVerseId}&note_text=${encodeURIComponent(noteText)}` // user_id kommt aus Session
    }).then(response => response.json()).then(data => {
        alert(data.message);
        if (data.status === 'success') {
            handleShowComments(globalVerseId, bookChapterTitleText, verseNumText); // Kommentare neu laden
        }
    }).catch(error => alert('Kommunikationsfehler beim Speichern des Kommentars.'));
}

function initializeModalLikeButtonLogic() {
    const commentsModalElement = document.getElementById('commentsModal');
    if (!commentsModalElement) return;
    commentsModalElement.addEventListener('click', function(event) {
        const likeButton = event.target.closest('.like-comment-btn-modal');
        if (!likeButton) return;
        event.preventDefault();
        if (currentUserId === 0) { alert("Bitte einloggen, um zu liken."); return; }
        const noteId = likeButton.dataset.noteId;
        if (!noteId) return;
        fetch('ajax_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=like_comment&note_id=${noteId}` // user_id kommt aus Session
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const likeCountSpan = likeButton.querySelector('.like-count');
                const heartIcon = likeButton.querySelector('i.bi');
                if (likeCountSpan) likeCountSpan.textContent = data.like_count;
                if (heartIcon) {
                    const isLiked = data.action_taken === 'liked';
                    heartIcon.classList.toggle('bi-heart-fill', isLiked);
                    heartIcon.classList.toggle('text-danger', isLiked);
                    heartIcon.classList.toggle('bi-heart', !isLiked);
                }
            } else {
                alert(data.message || 'Ein Fehler ist aufgetreten.');
            }
        }).catch(error => console.error('Fehler beim Liken im Modal:', error));
    });
}


// --- Funktionen für kommentare_uebersicht.php ---

function initializeCommentPageLogic() {
    loadComments(1); // Lade die erste Seite beim Start

    const filterForm = document.getElementById('filterCommentsForm');
    if (filterForm) {
        filterForm.addEventListener('submit', function (e) {
            e.preventDefault();
            loadComments(1);
        });
    }
    
    const myCommentsCheckbox = document.getElementById('my_comments');
    if (myCommentsCheckbox) {
        myCommentsCheckbox.addEventListener('change', function() {
            loadComments(1); // Bei Änderung der Checkbox neu laden
        });
    }

    const paginationContainer = document.getElementById('commentPagination');
    if (paginationContainer) {
        paginationContainer.addEventListener('click', function (e) {
            if (e.target.tagName === 'A' && e.target.dataset.page) {
                e.preventDefault();
                loadComments(parseInt(e.target.dataset.page));
            }
        });
    }

    const commentListContainer = document.getElementById('commentListContainer');
    if (commentListContainer) {
        commentListContainer.addEventListener('click', function (e) {
            const likeButton = e.target.closest('.like-comment-btn');
            const editButton = e.target.closest('.edit-comment-btn');
            const deleteButton = e.target.closest('.delete-comment-btn');

            if (likeButton) {
                e.preventDefault();
                const noteId = likeButton.dataset.noteId;
                handleLikeCommentOverview(noteId, likeButton); // Eigene Funktion für Likes auf der Übersichtsseite
            }
            if (editButton) {
                e.preventDefault();
                const noteId = editButton.dataset.noteId;
                openEditCommentModalOverview(noteId, editButton.dataset.currentText);
            }
            if (deleteButton) {
                e.preventDefault();
                const noteId = deleteButton.dataset.noteId;
                if (confirm('Möchten Sie diesen Kommentar wirklich löschen?')) {
                    handleDeleteCommentOverview(noteId);
                }
            }
        });
    }

    const saveCommentButton = document.getElementById('saveCommentChanges');
    if (saveCommentButton) {
        saveCommentButton.addEventListener('click', function () {
            const noteId = document.getElementById('editCommentId').value;
            const newText = document.getElementById('editCommentText').value;
            handleUpdateCommentOverview(noteId, newText);
        });
    }
}

function loadComments(page = 1) {
    const container = document.getElementById('commentListContainer');
    const paginationContainer = document.getElementById('commentPagination');
    if (!container || !paginationContainer) {
         console.log("Container für Kommentare oder Pagination nicht gefunden auf dieser Seite.");
         return;
    }

    const form = document.getElementById('filterCommentsForm');
    const params = new URLSearchParams(new FormData(form));
    params.append('page', page);

    const myCommentsCheckbox = document.getElementById('my_comments');
    if (myCommentsCheckbox && myCommentsCheckbox.checked && currentUserId > 0) {
        params.append('filter_user_id', currentUserId);
    } else {
        params.delete('filter_user_id'); // Sicherstellen, dass der Parameter weg ist, wenn Checkbox nicht aktiv
    }
    // Entferne my_comments, da es kein Datenbankfeld ist und nur für die Logik hier dient
    params.delete('my_comments');


    container.innerHTML = '<p class="text-center">Lade Kommentare...</p>';
    paginationContainer.innerHTML = ''; 

    fetch(`ajax_handler.php?action=get_all_comments&${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.comments) {
                renderCommentsOverview(data.comments, container);
                renderPaginationOverview(data.pagination, paginationContainer);
            } else {
                container.innerHTML = `<p class="text-danger">Fehler beim Laden der Kommentare: ${data.message || ''}</p>`;
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Kommentare:', error);
            container.innerHTML = '<p class="text-danger">Ein Kommunikationsfehler ist aufgetreten.</p>';
        });
}

function renderCommentsOverview(comments, container) {
    if (comments.length === 0) {
        container.innerHTML = '<p class="text-center">Keine Kommentare gefunden, die Ihren Kriterien entsprechen.</p>';
        return;
    }
    let html = '<div class="list-group">';
    comments.forEach(comment => {
        const verseLink = `bibel_lesen.php?book_id=${comment.book_id}&chapter=${comment.chapter_number}&highlight_verse=${comment.verse_global_id}#vers-${comment.verse_number}`;
        const commentDateRaw = comment.updated_at && comment.updated_at !== '0000-00-00 00:00:00' ? comment.updated_at : comment.note_date;
        const commentDate = new Date(commentDateRaw).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        const datePrefix = comment.updated_at && comment.updated_at !== '0000-00-00 00:00:00' && comment.updated_at !== comment.note_date ? 'Bearbeitet am' : 'Erstellt am';
        
        const heartIcon = comment.user_liked ? 'bi-heart-fill text-danger' : 'bi-heart';

        html += `
            <div class="list-group-item mb-3 shadow-sm comment-item" id="comment-item-${comment.note_id}">
                <div class="d-flex w-100 justify-content-between">
                    <h5 class="mb-1">
                        <a href="${verseLink}" target="_blank" title="Zum Bibelvers springen">
                            ${comment.book_name_de} ${comment.chapter_number},${comment.verse_number}
                        </a>
                    </h5>
                    <small class="text-muted" title="${datePrefix}">${commentDate}</small>
                </div>
                <p class="mb-1 fst-italic">"${htmlspecialchars(comment.verse_text)}"</p>
                <p class="mt-2 mb-2 comment-text-${comment.note_id}">${nl2br(htmlspecialchars(comment.note_text))}</p>
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">Von: <strong>${htmlspecialchars(comment.author_username)}</strong></small>
                    <div>
                        <button class="btn btn-sm btn-outline-danger like-comment-btn" data-note-id="${comment.note_id}" title="Gefällt mir">
                            <i class="bi ${heartIcon}"></i> <span class="like-count">${comment.likes}</span>
                        </button>
                        ${comment.author_id == currentUserId ? `
                            <button class="btn btn-sm btn-outline-secondary edit-comment-btn ms-2" 
                                    data-note-id="${comment.note_id}" 
                                    data-current-text="${htmlspecialchars(comment.note_text)}" title="Kommentar bearbeiten">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger delete-comment-btn ms-1" 
                                    data-note-id="${comment.note_id}" title="Kommentar löschen">
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

function renderPaginationOverview(pagination, container) {
    if (!pagination || pagination.totalPages <= 1) {
        container.innerHTML = '';
        return;
    }
    let html = '';
    const currentPage = parseInt(pagination.currentPage);
    const totalPages = parseInt(pagination.totalPages);

    html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${currentPage - 1}" aria-label="Zurück">‹</a>
             </li>`;
    
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, currentPage + 2);

    if (currentPage <= 3) endPage = Math.min(5, totalPages);
    if (currentPage > totalPages - 3) startPage = Math.max(1, totalPages - 4);

    if (startPage > 1) {
        html += `<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`;
        if (startPage > 2) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
    }

    for (let i = startPage; i <= endPage; i++) {
        html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                 </li>`;
    }

    if (endPage < totalPages) {
        if (endPage < totalPages - 1) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        html += `<li class="page-item"><a class="page-link" href="#" data-page="${totalPages}">${totalPages}</a></li>`;
    }

    html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${currentPage + 1}" aria-label="Weiter">›</a>
             </li>`;
    container.innerHTML = html;
}

function handleLikeCommentOverview(noteId, buttonElement) {
    if (currentUserId === 0) {
        alert("Bitte einloggen, um zu liken.");
        return;
    }
    fetch('ajax_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=like_comment&note_id=${noteId}` // user_id kommt aus Session
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success' && buttonElement) {
            const likeCountSpan = buttonElement.querySelector('.like-count');
            const heartIcon = buttonElement.querySelector('i.bi');
            if (likeCountSpan) likeCountSpan.textContent = data.like_count;
            if (heartIcon) {
                const isLiked = data.action_taken === 'liked';
                heartIcon.classList.toggle('bi-heart-fill', isLiked);
                heartIcon.classList.toggle('text-danger', isLiked); // text-danger für gefülltes Herz
                heartIcon.classList.toggle('bi-heart', !isLiked);
            }
        } else {
            alert(data.message || "Fehler beim Liken.");
        }
    })
    .catch(error => console.error("Fehler beim Liken auf Übersichtsseite:", error));
}

function openEditCommentModalOverview(noteId, currentText) {
    document.getElementById('editCommentId').value = noteId;
    document.getElementById('editCommentText').value = currentText;
    const editModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('editCommentModal'));
    editModal.show();
}

function handleUpdateCommentOverview(noteId, newText) {
    if (currentUserId === 0) { alert("Bitte zuerst einloggen."); return; }
    if (newText.trim() === "") { alert("Kommentar darf nicht leer sein."); return; }

    fetch('ajax_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=update_comment&note_id=${noteId}&note_text=${encodeURIComponent(newText)}` // user_id aus Session
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.status === 'success') {
            bootstrap.Modal.getInstance(document.getElementById('editCommentModal'))?.hide();
            const commentTextElement = document.querySelector(`.comment-text-${noteId}`);
            if (commentTextElement) commentTextElement.innerHTML = nl2br(htmlspecialchars(newText));
        }
    })
    .catch(error => console.error("Fehler beim Aktualisieren des Kommentars:", error));
}

function handleDeleteCommentOverview(noteId) {
    if (currentUserId === 0) { alert("Bitte zuerst einloggen."); return; }
    fetch('ajax_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete_comment&note_id=${noteId}` // user_id aus Session
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.status === 'success') {
            const commentItem = document.getElementById(`comment-item-${noteId}`);
            if (commentItem) commentItem.remove();
            // Optional: Prüfen ob die Seite jetzt leer ist und ggf. loadComments() für die vorherige Seite aufrufen
        }
    })
    .catch(error => console.error("Fehler beim Löschen des Kommentars:", error));
}


// --- PWA Service Worker ---
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').then(registration => {
      console.log('ServiceWorker registriert mit Scope:', registration.scope);
    }).catch(error => {
      console.log('ServiceWorker Registrierung fehlgeschlagen:', error);
    });
  });
}