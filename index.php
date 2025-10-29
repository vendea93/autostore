<?php
session_start();

// --- POBRANIE MOTYWU UŻYTKOWNIKA ---
// Domyślnie 'light', jeśli nie ma w sesji
$user_theme = $_SESSION['theme'] ?? 'light';

/*
--- WERSJA APLIKACJI ---
Zmieniaj tę wartość przy każdej aktualizacji kodu.
*/
define('APP_VERSION', '1.1.2'); // Podniesiono wersję po poprawkach

/*
--- LOGOWANIE TYMCZASOWO WYŁĄCZONE ---
Aby ponownie włączyć, odkomentuj poniższy blok i zmień flagę
'API_PROTECTION_ENABLED' na 'true' w pliku api.php.
*/
/* if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
} */
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Monitorowania Baterii</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body data-theme="<?php echo htmlspecialchars($user_theme); ?>">
    <header class="site-header">
        <div class="themed-logo">
            <img src="logo_light.png" alt="Logo Firmy" class="logo logo-light">
            <img src="logo_dark.png" alt="Logo Firmy" class="logo logo-dark">
        </div>
        <div class="header-actions">
            <button class="theme-switcher" id="theme-switcher" title="Zmień motyw">
                <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22ZM12 20V4C16.4183 4 20 7.58172 20 12C20 16.4183 16.4183 20 12 20Z"></path></svg>
                <svg class="icon-sun" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 18C15.3137 18 18 15.3137 18 12C18 8.68629 15.3137 6 12 6C8.68629 6 6 8.68629 6 12C6 15.3137 8.68629 18 12 18ZM11 1H13V4H11V1ZM11 20H13V23H11V20ZM4.22183 4.22183L5.63604 5.63604L3.51472 7.75736L2.10051 6.34315L4.22183 4.22183ZM20.4853 20.4853L21.8995 19.0711L19.7782 16.9497L18.364 18.364L20.4853 20.4853ZM1 11H4V13H1V11ZM20 11H23V13H20V11ZM18.364 2.10051L19.7782 3.51472L16.9497 5.63604L15.636 4.22183L18.364 2.10051ZM6.34315 21.8995L7.75736 20.4853L5.63604 18.364L4.22183 19.7782L6.34315 21.8995Z"></path></svg>
            </button>
            <a href="admin.php" class="btn btn-secondary btn-sm">Panel Admina</a>
            <a href="login.php?action=logout" class="btn btn-secondary btn-sm">Wyloguj</a>
        </div>
    </header>

    <main>
        <div class="container">
            <h1>Panel Monitorowania Baterii</h1>

            <div class="card">
                <div class="main-actions-container">
                    <div class="add-battery-form-wrapper">
                        <form id="add-battery-form" class="add-battery-form" novalidate>
                            <input type="text" id="serial-number" class="form-input" placeholder="Dodaj ręcznie nr seryjny..." required>
                            <input type="text" id="location" class="form-input" placeholder="Lokalizacja (opcjonalnie)...">
                            <button type="submit" class="btn btn-primary">Dodaj baterię</button>
                        </form>
                    </div>
                    <div class="data-actions-wrapper">
                        <button id="expand-all-btn" class="btn btn-secondary">Rozwiń wszystko</button>
                        <button id="collapse-all-btn" class="btn btn-secondary">Zwiń wszystko</button>
                    </div>
                </div>
                <div id="form-feedback" class="form-feedback"></div>
            </div>

            <div class="card">
                <div>
                    <table class="battery-table">
                        <thead>
                            <tr>
                                <th class="sortable" data-sort="product_name">Nazwa Produktu <span class="sort-arrow"></span></th>
                                <th class="sortable" data-sort="location">Lokalizacja <span class="sort-arrow"></span></th>
                                <th class="sortable" data-sort="goods_reception_document">Dokument przyjęcia <span class="sort-arrow"></span></th>
                                <th class="sortable" data-sort="serial_number">Nr Seryjny <span class="sort-arrow"></span></th>
                                <th class="sortable" data-sort="last_charged">Data ost. ładowania <span class="sort-arrow"></span></th>
                                <th class="sortable" data-sort="daysLeft">Pozostały czas <span class="sort-arrow"></span></th>
                                <th class="sortable" data-sort="notes">Notatki <span class="sort-arrow"></span></th>
                                <th>Akcje</th>
                            </tr>
                        </thead>
                        <tbody id="battery-list">
                            <tr>
                                <td colspan="8">
                                    <div class="spinner-container"><div class="spinner"></div></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <footer class="site-footer-main">
        <div class="container" style="padding-block: 0;">
            <p>Wersja aplikacji: <?php echo APP_VERSION; ?></p>
        </div>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // IIFE to encapsulate the application logic
        (function app() {
            // --- DOM Elements ---
            const batteryList = document.getElementById('battery-list');
            const addForm = document.getElementById('add-battery-form');
            const serialNumberInput = document.getElementById('serial-number');
            const formFeedback = document.getElementById('form-feedback');
            const locationInput = document.getElementById('location');
            const expandAllBtn = document.getElementById('expand-all-btn');
            const collapseAllBtn = document.getElementById('collapse-all-btn');

            // --- State ---
            let batteries = [];
            let sortConfig = { key: 'location', direction: 'ascending' };
            let expandedLocations = new Set(); // Przechowuje rozwinięte lokalizacje
            let expandedDocuments = new Set(); // Przechowuje rozwinięte numery dokumentów

            // --- API Helper ---
            async function apiFetch(endpoint, options = {}) {
                try {
                    const response = await fetch(endpoint, {
                        headers: { 'Content-Type': 'application/json' },
                        ...options,
                    });
                    const result = await response.json();
                    if (!response.ok) {
                        throw new Error(result.message || `HTTP error! Status: ${response.status}`);
                    }
                    return result;
                } catch (error) {
                    console.error('API Fetch Error:', error);
                    showFeedback(error.message, 'error');
                    throw error; // Re-throw to handle in calling function
                }
            }

            // --- UI Functions ---
            function showFeedback(message, type, duration = 3000) {
                if (formFeedback.timer) clearTimeout(formFeedback.timer);
                formFeedback.textContent = message;
                formFeedback.className = `form-feedback ${type}`;
                formFeedback.style.display = 'block';
                formFeedback.timer = setTimeout(() => { formFeedback.style.display = 'none'; }, duration);
            }

            function getProgressColor(daysLeft) {
                // Hue goes from 120 (green) down to 0 (red) as daysLeft decreases.
                const hue = (Math.max(0, daysLeft) / 365) * 120;
                // Using HSL for smooth color transition.
                return `hsl(${hue}, 70%, 55%)`; /* Zmienione nasycenie i jasność na bardziej pastelowe */
            }

            /**
             * Zwraca poprawną formę słowa "bateria" w języku polskim w zależności od liczby.
             * @param {number} count - Liczba baterii.
             * @returns {string} Poprawna forma: "bateria", "baterie" lub "baterii".
             */
            function getBatteryNounForm(count) {
                if (count === 1) {
                    return 'bateria';
                }
                const lastDigit = count % 10;
                const lastTwoDigits = count % 100;
                if (lastDigit >= 2 && lastDigit <= 4 && (lastTwoDigits < 12 || lastTwoDigits > 14)) {
                    return 'baterie';
                }
                return 'baterii';
            }

            function render() {
                // 1. Sortowanie danych
                batteries.sort((a, b) => {
                    let valA, valB;
                    if (sortConfig.key === 'daysLeft') {
                        const diffDaysA = a.last_charged ? (new Date() - new Date(a.last_charged)) / (1000 * 60 * 60 * 24) : -Infinity;
                        valA = 365 - diffDaysA;
                        const diffDaysB = b.last_charged ? (new Date() - new Date(b.last_charged)) / (1000 * 60 * 60 * 24) : -Infinity;
                        valB = 365 - diffDaysB;
                    } else {
                        valA = a[sortConfig.key] || '';
                        valB = b[sortConfig.key] || '';
                        // Sortowanie alfabetyczne dla nazw, numerów dostaw i notatek
                        if (['product_name', 'location', 'goods_reception_document', 'serial_number', 'notes'].includes(sortConfig.key)) {
                            valA = String(valA).toLowerCase();
                            valB = String(valB).toLowerCase();
                        }
                    }
                    if (valA < valB) return sortConfig.direction === 'ascending' ? -1 : 1;
                    if (valA > valB) return sortConfig.direction === 'ascending' ? 1 : -1;
                    return 0;
                });

                // 2. Czyszczenie tabeli
                batteryList.innerHTML = '';
                if (batteries.length === 0) {
                    batteryList.innerHTML = `<tr><td colspan="8" class="empty-state">Brak baterii na liście. Uruchom synchronizację w panelu admina.</td></tr>`;
                    return;
                }

                // 3. Grupowanie po lokalizacji
                const groupedByLocation = batteries.reduce((acc, battery) => {
                    const key = battery.location || 'Brak lokalizacji';
                    if (!acc[key]) {
                        acc[key] = [];
                    }
                    acc[key].push(battery);
                    return acc;
                }, {});

                // 4. Renderowanie
                for (const locationName in groupedByLocation) {
                    const locationBatteries = groupedByLocation[locationName];
                    const isLocationExpanded = expandedLocations.has(locationName);

                    // Wiersz nagłówka lokalizacji
                    const locationHeaderRow = document.createElement('tr');
                    locationHeaderRow.className = `group-header location-header ${isLocationExpanded ? 'expanded' : ''}`;
                    locationHeaderRow.dataset.locationName = locationName;
                    locationHeaderRow.innerHTML = /*html*/`
                        <td colspan="8">
                            <span class="group-toggle-icon">▶</span>
                            <strong>${locationName}</strong>
                            <span class="group-count">(${locationBatteries.length} ${getBatteryNounForm(locationBatteries.length)})</span>
                        </td>
                    `;
                    batteryList.appendChild(locationHeaderRow);

                    // Grupowanie po dokumencie przyjęcia w ramach lokalizacji
                    const groupedByDocument = locationBatteries.reduce((acc, battery) => {
                        const key = battery.goods_reception_document || 'Brak dokumentu przyjęcia';
                        if (!acc[key]) acc[key] = [];
                        acc[key].push(battery);
                        return acc;
                    }, {});

                    for (const documentId in groupedByDocument) {
                        const documentBatteries = groupedByDocument[documentId];
                        const documentGroupId = `${locationName}__${documentId}`;
                        const isDocumentExpanded = expandedDocuments.has(documentGroupId);

                        // Wiersz nagłówka dokumentu
                        const documentHeaderRow = document.createElement('tr');
                        documentHeaderRow.className = `group-header document-header ${isDocumentExpanded ? 'expanded' : ''}`;
                        documentHeaderRow.dataset.documentGroupId = documentGroupId;
                        documentHeaderRow.style.display = isLocationExpanded ? '' : 'none';
                        documentHeaderRow.innerHTML = /*html*/`
                            <td colspan="8">
                                <span class="group-toggle-icon">▶</span>
                                <strong>${documentId}</strong>
                                <span class="group-count">(${documentBatteries.length} ${getBatteryNounForm(documentBatteries.length)})</span>
                            </td>
                        `;
                        batteryList.appendChild(documentHeaderRow);

                        // Wiersze baterii
                        documentBatteries.forEach(battery => {
                            const diffDays = battery.last_charged ? (new Date() - new Date(battery.last_charged)) / (1000 * 60 * 60 * 24) : -Infinity;
                            const daysLeft = 365 - diffDays;
                            const progressPercent = (Math.max(0, daysLeft) / 365) * 100;
                            const progressColor = getProgressColor(daysLeft);
                            
                            const batteryRow = document.createElement('tr');
                            batteryRow.className = 'battery-row';
                            batteryRow.dataset.id = battery.id;
                            batteryRow.dataset.parentDocumentGroupId = documentGroupId;
                            batteryRow.style.display = isLocationExpanded && isDocumentExpanded ? '' : 'none';

                            batteryRow.innerHTML = /*html*/`
                                <td data-label="Nazwa Produktu">${battery.product_name || '—'}</td>
                                <td data-label="Lokalizacja">
                                    <span class="view-mode">${battery.location || '—'}</span>
                                    <input type="text" class="edit-mode location-input" value="${battery.location || ''}" placeholder="Lokalizacja...">
                                </td>
                                <td data-label="Dokument przyjęcia">${battery.goods_reception_document || '—'}</td>
                                <td data-label="Nr Seryjny">
                                    <span class="view-mode">${battery.serial_number || '—'}</span> 
                                    <input type="text" class="edit-mode serial-number-input" value="${battery.serial_number || ''}" placeholder="Nr seryjny...">
                                </td>
                                <td data-label="Data ost. ładowania">
                                    <span class="view-mode">${battery.last_charged ? new Date(battery.last_charged).toLocaleDateString('pl-PL', { year: 'numeric', month: '2-digit', day: '2-digit' }) : '—'}</span>
                                    <input type="date" class="edit-mode date-input" value="${battery.last_charged || ''}">
                                </td>
                                <td data-label="Pozostały czas">
                                    <div class="progress-container" data-days-left="${Math.round(daysLeft)}">
                                        <div class="progress-bar" style="width: ${progressPercent}%; background-color: ${progressColor};"></div>
                                        <div class="progress-text">${Math.round(daysLeft)} dni</div>
                                    </div>
                                </td>
                                <td data-label="Notatki">
                                    <span class="view-mode note-content">${battery.notes || '—'}</span>
                                    <input type="text" class="edit-mode notes-input" value="${battery.notes || ''}" placeholder="Dodaj notatkę...">
                                </td>
                                <td data-label="Akcje">
                                    <div class="actions">
                                        <div class="view-mode">
                                            <button class="btn btn-secondary btn-sm edit-btn">Edytuj</button>
                                            <button class="btn btn-success btn-sm charge-btn">Naładuj</button>
                                            <button class="btn btn-danger btn-sm delete-btn">Usuń</button>
                                        </div>
                                        <div class="edit-mode">
                                            <button class="btn btn-success btn-sm save-btn">Zapisz</button>
                                            <button class="btn btn-secondary btn-sm cancel-btn">Anuluj</button>
                                        </div>
                                    </div>
                                </td>
                            `;
                            batteryList.appendChild(batteryRow);

                            if (daysLeft <= 0) {
                                const progressContainer = batteryRow.querySelector('.progress-container');
                                progressContainer.classList.add('expired');
                            }
                        });
                    }
                }
                
                updateSortUI();
            }
            
            function updateSortUI() {
                document.querySelectorAll('.sortable').forEach(th => {
                    th.classList.remove('active');
                    th.querySelector('.sort-arrow').textContent = '';
                    if (th.dataset.sort === sortConfig.key) {
                        th.classList.add('active');
                        th.querySelector('.sort-arrow').textContent = sortConfig.direction === 'ascending' ? '▲' : '▼';
                    }
                });
            }

            // --- Event Handlers ---
            async function handleAddFormSubmit(e) {
                e.preventDefault();
                const serial_number = serialNumberInput.value.trim();
                const location = locationInput.value.trim();
                if (!serial_number) {
                    showFeedback('Numer seryjny nie może być pusty.', 'error');
                    return;
                }
                try {
                    const payload = { 
                        serial_number, 
                        location: location || null
                    };
                    await apiFetch('api.php', {
                        method: 'POST',
                        body: JSON.stringify(payload),
                    });
                    await fetchAndRenderBatteries();
                    serialNumberInput.value = '';
                    locationInput.value = '';
                    showFeedback(`Bateria "${serial_number}" została dodana.`, 'success');
                } catch (error) {
                    // Error handled by apiFetch
                }
            }

            function handleExpandCollapseAll(expand) {
                const allLocations = new Set();
                const allDocuments = new Set();

                if (expand) {
                    batteries.forEach(b => {
                        allLocations.add(b.location || 'Brak lokalizacji');
                        allDocuments.add(`${b.location || 'Brak lokalizacji'}__${b.goods_reception_document || 'Brak dokumentu przyjęcia'}`);
                    });
                }
                expandedLocations = allLocations;
                expandedDocuments = allDocuments;
                render();
            }
            function handleTableClick(e) {
                const target = e.target;
                const row = target.closest('tr');
                if (!row) return;

                // Obsługa kliknięcia na nagłówek lokalizacji
                if (row.classList.contains('location-header')) {
                    const locationName = row.dataset.locationName;
                    row.classList.toggle('expanded');
                    const isExpanded = row.classList.contains('expanded');

                    if (isExpanded) expandedLocations.add(locationName);
                    else expandedLocations.delete(locationName);

                    document.querySelectorAll(`.document-header, .battery-row`).forEach(childRow => {
                        const parentLocation = (childRow.dataset.parentDocumentGroupId || childRow.dataset.documentGroupId || '').split('__')[0];
                        if (parentLocation === locationName) {
                            const documentGroupId = childRow.dataset.parentDocumentGroupId || childRow.dataset.documentGroupId;
                            const isDocumentExpanded = expandedDocuments.has(documentGroupId);
                            if (childRow.classList.contains('document-header')) {
                                childRow.style.display = isExpanded ? '' : 'none';
                            }
                            if (childRow.classList.contains('battery-row')) {
                                childRow.style.display = isExpanded && isDocumentExpanded ? '' : 'none';
                            }
                        }
                    });
                    return;
                }

                // Obsługa kliknięcia na nagłówek dokumentu
                if (row.classList.contains('document-header')) {
                    const documentGroupId = row.dataset.documentGroupId;
                    row.classList.toggle('expanded');
                    const isExpanded = row.classList.contains('expanded');

                    if (isExpanded) expandedDocuments.add(documentGroupId);
                    else expandedDocuments.delete(documentGroupId);

                    document.querySelectorAll(`.battery-row[data-parent-document-group-id="${documentGroupId}"]`).forEach(childRow => {
                        childRow.style.display = isExpanded ? '' : 'none';
                    });
                    return;
                }

                // Obsługa kliknięcia na przyciski w wierszu baterii
                if (!row.dataset.id) return;
                const id = parseInt(row.dataset.id, 10);
                if (target.classList.contains('edit-btn')) {
                    row.classList.add('editing');
                } else if (target.classList.contains('cancel-btn')) {
                    row.classList.remove('editing');
                    render(); // Przywróć oryginalne wartości
                } else if (target.classList.contains('save-btn')) {
                    handleSave(id, row);
                } else if (target.classList.contains('delete-btn')) {
                    handleDelete(id, row);
                } else if (target.classList.contains('charge-btn')) {
                    handleCharge(id, row);
                }
            }
            
            function handleSortClick(e) {
                const header = e.target.closest('.sortable');
                if (!header) return;
                const key = header.dataset.sort;
                if (sortConfig.key === key) {
                    sortConfig.direction = sortConfig.direction === 'ascending' ? 'descending' : 'ascending';
                } else {
                    sortConfig.key = key;
                    sortConfig.direction = 'ascending';
                }
                render();
            }

            async function handleSave(id, row) {
                const payload = {
                    id,
                    serial_number: row.querySelector('.serial-number-input').value.trim() || null,
                    location: row.querySelector('.location-input').value.trim() || null,
                    last_charged: row.querySelector('.date-input').value || null,
                    notes: row.querySelector('.notes-input').value.trim(),
                };
                try {
                    await apiFetch('api.php', { method: 'PUT', body: JSON.stringify(payload) });
                    
                    // Zaktualizuj dane lokalnie, bez przeładowania strony
                    const batteryIndex = batteries.findIndex(b => b.id === id);
                    if (batteryIndex > -1) {
                        // Zastąp stary obiekt baterii nowymi danymi
                        batteries[batteryIndex] = { ...batteries[batteryIndex], ...payload };
                    }
                    
                    // Odśwież tabelę, zachowując sortowanie i rozwinięte grupy
                    render();
                    showFeedback(`Bateria o ID ${id} została zaktualizowana.`, 'success');
                } catch (error) {
                    // Błąd jest już obsłużony i wyświetlony przez apiFetch.
                    // Można ewentualnie odświeżyć tabelę, aby przywrócić stare dane.
                    render();
                }
            }

            async function handleDelete(id, row) {
                const serialNumber = row.querySelector('td[data-label="Nr Seryjny"] .view-mode').textContent;
                if (!confirm(`Czy na pewno chcesz usunąć baterię o ID ${id} (SN: ${serialNumber})?`)) return;
                try {
                    await apiFetch('api.php', { method: 'DELETE', body: JSON.stringify({ id }) });
                    batteries = batteries.filter(b => b.id !== id);
                    render();
                    showFeedback(`Bateria o ID ${id} została usunięta.`, 'success');
                } catch (error) {
                    // Error handled by apiFetch
                }
            }

            async function handleCharge(id, row) {
                const serialNumber = row.querySelector('td[data-label="Nr Seryjny"] .view-mode').textContent;
                if (!confirm(`Czy potwierdzasz naładowanie baterii o ID ${id} (SN: ${serialNumber})? Data zostanie zaktualizowana na dzisiejszą.`)) return;

                const currentBattery = batteries.find(b => b.id === id);
                const today = new Date().toISOString().slice(0, 10);
                const payload = { 
                    id, 
                    last_charged: today
                };

                // Natychmiastowa aktualizacja UI dla lepszego UX
                const progressContainer = row.querySelector('.progress-container');
                if(progressContainer) {
                    progressContainer.querySelector('.progress-bar').style.width = '100%';
                    progressContainer.querySelector('.progress-bar').style.backgroundColor = getProgressColor(365);
                    progressContainer.querySelector('.progress-text').textContent = '365 dni';
                    progressContainer.classList.remove('expired');
                }

                try {
                    await apiFetch('api.php', { method: 'PUT', body: JSON.stringify(payload) });
                    
                    // Zaktualizuj dane lokalnie, bez przeładowania strony
                    if (currentBattery) {
                        currentBattery.last_charged = today;
                    }
                    
                    // Odśwież tabelę i pokaż komunikat
                    render();
                    showFeedback(`Data ładowania dla baterii o ID ${id} została zaktualizowana.`, 'success');
                } catch (error) {
                    // Błąd obsłużony w apiFetch
                    render(); // Przywróć stan w razie błędu
                }
            }

            // --- Data Fetching ---
            async function fetchAndRenderBatteries() {
                try {
                    const result = await apiFetch('api.php');
                    batteries = result.data || [];
                    render();
                } catch (error) {
                    batteryList.innerHTML = `<tr><td colspan="8" class="empty-state">Nie udało się załadować danych. Sprawdź połączenie i spróbuj ponownie.</td></tr>`;
                }
            }

            // --- Initialization ---
            function init() {
                addForm.addEventListener('submit', handleAddFormSubmit);
                batteryList.addEventListener('click', handleTableClick);
                document.querySelector('thead').addEventListener('click', handleSortClick);
                expandAllBtn.addEventListener('click', () => handleExpandCollapseAll(true));
                collapseAllBtn.addEventListener('click', () => handleExpandCollapseAll(false));
                
                // Theme switcher
                const themeSwitcher = document.getElementById('theme-switcher');
                const docHtml = document.documentElement;

                // Ustaw motyw na podstawie danych z PHP (z sesji/bazy)
                const initialTheme = document.body.getAttribute('data-theme') || 'light';
                docHtml.setAttribute('data-theme', initialTheme);

                themeSwitcher.addEventListener('click', async () => {
                    const newTheme = docHtml.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                    docHtml.setAttribute('data-theme', newTheme);
                    try {
                        // Zapisz motyw w localStorage jako fallback i dla natychmiastowej zmiany na innych kartach
                        localStorage.setItem('theme', newTheme);
                        // Zapisz motyw w bazie danych
                        await apiFetch('api.php?action=save-theme', {
                            method: 'POST',
                            body: JSON.stringify({ theme: newTheme })
                        });
                    } catch (e) {
                        console.warn("Nie udało się zapisać motywu w bazie danych.", e);
                        // Można tu dodać komunikat dla użytkownika
                    }
                });

                // Initial data fetch
                fetchAndRenderBatteries();
            }

            init();
        })();
    });
    </script>
</body>
</html>
    </script>
</body>
</html>