<?php
session_start();

// --- POBRANIE DANYCH DO DASHBOARDU ---
require_once 'config.php'; // Wczytujemy tylko konfigurację bazy danych
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

$dashboard_data = [
    'last_sync_time' => 'Nigdy',
    'total_batteries' => 0,
    'charge_needed' => 0,
    'ok_status' => 0,
    'due_soon_status' => 0
];

if ($conn->connect_error) {
    $dashboard_data['last_sync_time'] = "Błąd połączenia z bazą danych";
} else {
    // Data ostatniej synchronizacji
    $result = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'last_sync_time'");
    if ($result && $result->num_rows > 0) {
        $dashboard_data['last_sync_time'] = $result->fetch_assoc()['setting_value'];
    }
    // Łączna liczba baterii
    $total_result = $conn->query("SELECT COUNT(id) as total FROM battery_units");
    if ($total_result) {
        $dashboard_data['total_batteries'] = (int)$total_result->fetch_assoc()['total'];
    }
    // Liczba baterii PO TERMINIE (termin minął lub data jest pusta)
    $charge_needed_result = $conn->query("SELECT COUNT(id) as total FROM battery_units WHERE last_charged IS NULL OR DATE_ADD(last_charged, INTERVAL 1 YEAR) <= CURDATE()");
    if ($charge_needed_result) {
        $dashboard_data['charge_needed'] = (int)$charge_needed_result->fetch_assoc()['total'];
    }
    // Liczba baterii w dobrym stanie (termin za ponad 30 dni)
    $ok_status_result = $conn->query("SELECT COUNT(id) as total FROM battery_units WHERE DATE_ADD(last_charged, INTERVAL 1 YEAR) > DATE_ADD(CURDATE(), INTERVAL 2 MONTH)");
    if ($ok_status_result) {
        $dashboard_data['ok_status'] = (int)$ok_status_result->fetch_assoc()['total'];
    }
    // Liczba baterii DO NAŁADOWANIA WKRÓTCE (termin w ciągu najbliższych 30 dni)
    $due_soon_result = $conn->query("SELECT COUNT(id) as total FROM battery_units WHERE DATE_ADD(last_charged, INTERVAL 1 YEAR) > CURDATE() AND DATE_ADD(last_charged, INTERVAL 1 YEAR) <= DATE_ADD(CURDATE(), INTERVAL 2 MONTH)");
    if ($due_soon_result) {
        $dashboard_data['due_soon_status'] = (int)$due_soon_result->fetch_assoc()['total'];
    }

    $conn->close();
}


// --- POBRANIE MOTYWU UŻYTKOWNIKA ---
$user_theme = $_SESSION['theme'] ?? 'light';

/*
--- WERSJA APLIKACJI ---
Zmieniaj tę wartość przy każdej aktualizacji kodu.
*/
define('APP_VERSION', '1.1.2'); // Wersja zgodna z index.php
/* 
--- OCHRONA STRONY ---
Sprawdza, czy użytkownik jest zalogowany.
*/
/* if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
*/ ?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administracyjny - Monitorowanie Baterii</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <!-- Integracja biblioteki Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body data-theme="<?php echo htmlspecialchars($user_theme); ?>">
    <header class="site-header">
        <div class="themed-logo">
            <img src="logo_light.png" alt="Logo Firmy" class="logo logo-light">
            <img src="logo_dark.png" alt="Logo Firmy" class="logo logo-dark">
        </div>
        <div class="header-actions">
            <a href="index.php" class="btn btn-secondary btn-sm">Powrót do panelu</a>
            <button class="theme-switcher" id="theme-switcher" title="Zmień motyw">
                <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22ZM12 20V4C16.4183 4 20 7.58172 20 12C20 16.4183 16.4183 20 12 20Z"></path></svg>
                <svg class="icon-sun" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 18C15.3137 18 18 15.3137 18 12C18 8.68629 15.3137 6 12 6C8.68629 6 6 8.68629 6 12C6 15.3137 8.68629 18 12 18ZM11 1H13V4H11V1ZM11 20H13V23H11V20ZM4.22183 4.22183L5.63604 5.63604L3.51472 7.75736L2.10051 6.34315L4.22183 4.22183ZM20.4853 20.4853L21.8995 19.0711L19.7782 16.9497L18.364 18.364L20.4853 20.4853ZM1 11H4V13H1V11ZM20 11H23V13H20V11ZM18.364 2.10051L19.7782 3.51472L16.9497 5.63604L15.636 4.22183L18.364 2.10051ZM6.34315 21.8995L7.75736 20.4853L5.63604 18.364L4.22183 19.7782L6.34315 21.8995Z"></path></svg>
            </button>
            <a href="login.php?action=logout" class="btn btn-secondary btn-sm">Wyloguj</a>
        </div>
    </header>

    <main>
        <div class="container">
            <h1>Panel Administracyjny</h1>
            
            <!-- Sekcja ze statystykami (KPI) -->
            <div class="kpi-grid">
                <div class="card kpi-card">
                    <div class="kpi-value" id="kpi-total-batteries"><?php echo number_format($dashboard_data['total_batteries']); ?></div>
                    <div class="kpi-label">Wszystkie baterie</div>
                </div>
                <div class="card kpi-card">
                    <div class="kpi-value" id="kpi-ok-status" style="color: var(--color-success);"><?php echo number_format($dashboard_data['ok_status']); ?></div>
                    <div class="kpi-label">Status OK</div>
                </div>
                <div class="card kpi-card">
                    <div class="kpi-value" id="kpi-due-soon-status" style="color: var(--color-warning);"><?php echo number_format($dashboard_data['due_soon_status']); ?></div>
                    <div class="kpi-label">
                        Do naładowania wkrótce
                        <div class="kpi-sublabel">(mniej niż 2 miesiące)</div>
                    </div>
                </div>
                <div class="card kpi-card">
                    <div class="kpi-value" id="kpi-charge-needed" style="color: var(--color-danger);"><?php echo number_format($dashboard_data['charge_needed']); ?></div>
                    <div class="kpi-label">Po terminie</div>
                </div>
                <div class="card kpi-card">
                    <div class="kpi-value kpi-value-small" id="kpi-last-sync"><?php echo htmlspecialchars($dashboard_data['last_sync_time']); ?></div>
                    <div class="kpi-label">Ostatnia aktualizacja</div>
                </div>
            </div>

            <div class="admin-grid">
                <div class="card">
                    <h2>Synchronizacja Danych</h2>
                    <p>Pobierz najnowsze dane o stanie baterii z systemu Databricks i zaktualizuj lokalną bazę danych. Proces może potrwać kilka minut.</p>
                    
                    <div id="sync-feedback" class="form-feedback" style="display: none;"></div>
                    <div class="progress-bar-container" id="sync-progress-container" style="display: none;">
                        <div class="progress-bar-indeterminate"></div>
                    </div>

                    <div class="main-actions-container">
                        <button id="sync-button" class="btn btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18" style="margin-right: 8px;"><path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22ZM12 4C16.4183 4 20 7.58172 20 12C20 16.4183 16.4183 20 12 20V4ZM13 11.3216V7H11V12.6784L15.2426 16.9211L16.6569 15.5068L13 11.3216Z"></path></svg>
                            <span id="sync-button-text">Uruchom Synchronizację</span>
                        </button>
                    </div>
                </div>
                
                <div class="card">
                    <h2>Raporty i Narzędzia</h2>
                    <div class="chart-container">
                        <canvas id="batteryStatusChart"></canvas>
                    </div>
                    <div class="main-actions-container" style="margin-top: var(--space-6);">
                        <a href="api.php?action=export-csv" class="btn btn-secondary">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18" style="margin-right: 8px;"><path d="M4 19H20V12H22V20C22 20.5523 21.5523 21 21 21H3C2.44772 21 2 20.5523 2 20V12H4V19ZM13 9V16H11V9H6L12 3L18 9H13Z"></path></svg>
                            Eksportuj do CSV
                        </a>
                    </div>
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
    // Skrypt do inicjalizacji motywu
    document.addEventListener('DOMContentLoaded', () => {
        // --- OBSŁUGA SYNCHRONIZACJI AJAX ---
        const syncButton = document.getElementById('sync-button');
        const themeSwitcher = document.getElementById('theme-switcher');
        const syncFeedback = document.getElementById('sync-feedback');
        const syncButtonText = document.getElementById('sync-button-text');
        const syncProgressContainer = document.getElementById('sync-progress-container');

        const docHtml = document.documentElement;
        
        // Ustaw motyw na podstawie danych z PHP
        const initialTheme = document.body.getAttribute('data-theme') || 'light';
        docHtml.setAttribute('data-theme', initialTheme);

        themeSwitcher.addEventListener('click', async () => {
            const newTheme = docHtml.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            docHtml.setAttribute('data-theme', newTheme);
            try {
                localStorage.setItem('theme', newTheme);
                await fetch('api.php?action=save-theme', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ theme: newTheme })
                });
            } catch (error) {
                console.warn("Nie udało się zapisać motywu w bazie danych.", error);
            }
        });

        function showSyncFeedback(message, type) {
            syncFeedback.textContent = message;
            syncFeedback.className = `form-feedback ${type}`;
            syncFeedback.style.display = 'block';
        }

        async function updateStats() {
            try {
                const response = await fetch('api.php?action=get-stats');
                const result = await response.json();
                if (result.status === 'success') {
                    document.getElementById('kpi-total-batteries').textContent = (result.data.total_batteries || 0).toLocaleString('pl-PL');
                    document.getElementById('kpi-ok-status').textContent = (result.data.ok_status || 0).toLocaleString('pl-PL');
                    document.getElementById('kpi-due-soon-status').textContent = (result.data.due_soon_status || 0).toLocaleString('pl-PL');
                    document.getElementById('kpi-charge-needed').textContent = (result.data.charge_needed || 0).toLocaleString('pl-PL');
                    document.getElementById('kpi-last-sync').textContent = result.data.last_sync_time || 'Nigdy';
                }
            } catch (error) { console.error("Błąd podczas odświeżania statystyk:", error); }
        }

        // --- OBSŁUGA WYKRESU KOŁOWEGO ---
        let batteryChart = null;
        async function renderChart() {
            try {
                const response = await fetch('api.php?action=get-chart-data');
                const result = await response.json();
                if (result.status !== 'success') return;

                const ctx = document.getElementById('batteryStatusChart').getContext('2d');
                const chartData = {
                    labels: ['Status OK', 'Do naładowania wkrótce', 'Po terminie'],
                    datasets: [{
                        label: 'Status Baterii',
                        data: [result.data.ok, result.data.due_soon, result.data.overdue],
                        backgroundColor: [
                            'rgba(56, 161, 105, 0.8)',  // Success
                            'rgba(221, 107, 32, 0.8)', // Warning
                            'rgba(229, 62, 62, 0.8)'   // Danger
                        ],
                        borderColor: [
                            'rgba(56, 161, 105, 1)',
                            'rgba(221, 107, 32, 1)',
                            'rgba(229, 62, 62, 1)'
                        ],
                        borderWidth: 1
                    }]
                };

                if (batteryChart) batteryChart.destroy(); // Zniszcz stary wykres przed narysowaniem nowego
                batteryChart = new Chart(ctx, { type: 'pie', data: chartData, options: { responsive: true, maintainAspectRatio: false } });

            } catch (error) { console.error("Błąd podczas renderowania wykresu:", error); }
        }

        syncButton.addEventListener('click', async () => {
            syncButton.disabled = true;
            syncButtonText.textContent = 'Uruchamiam...';
            showSyncFeedback('Rozpoczynam synchronizację. Proszę czekać...', 'info');
            syncProgressContainer.style.display = 'block';

            try {
                const response = await fetch('sync_databricks.php');
                const result = await response.json();

                if (response.ok && result.status === 'success') {
                    showSyncFeedback(result.message, 'success');
                    await updateStats(); // Odśwież statystyki po udanej synchronizacji
                    await renderChart(); // Odśwież wykres
                } else {
                    throw new Error(result.message || 'Wystąpił nieznany błąd serwera.');
                }
            } catch (error) {
                showSyncFeedback(`Błąd krytyczny: ${error.message}`, 'error');
            } finally {
                syncButton.disabled = false;
                syncButtonText.textContent = 'Uruchom Synchronizację';
                syncProgressContainer.style.display = 'none';
            }
        });

        // Inicjalizacja przy załadowaniu strony
        renderChart();
    });
    </script>
</body>
</html>