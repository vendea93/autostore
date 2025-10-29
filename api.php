<?php
// Włącz raportowanie wszystkich błędów na potrzeby deweloperskie.
// W środowisku produkcyjnym warto to zmienić na logowanie do pliku.
ini_set('display_errors', 0); // Wyłącz wyświetlanie błędów bezpośrednio w odpowiedzi
ini_set('log_errors', 1); // Włącz logowanie błędów do pliku
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE); // Raportuj wszystko oprócz przestarzałych funkcji i notatek

// Rozpocznij buforowanie wyjścia, aby zapobiec wysyłaniu niechcianych danych przed JSON
ob_start();
// Rozpocznij sesję - kluczowe dla działania logowania w przyszłości.
session_start();
header('Content-Type: application/json');

require_once 'config.php'; // Wczytujemy konfigurację bazy danych

// Zmienna do łatwego włączania/wyłączania ochrony API
define('API_PROTECTION_ENABLED', false);

// --- Połączenie z bazą danych ---
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Błąd połączenia z bazą danych: ' . $conn->connect_error]);
    exit();
}
$conn->set_charset('utf8mb4');

// --- Funkcje pomocnicze ---
function send_response($status_code, $status, $message, $data = null) {
    ob_end_clean(); // Wyczyść bufor wyjścia przed wysłaniem odpowiedzi

    http_response_code($status_code);
    $response = ['status' => $status, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit();
}

function sanitize_input($data, $connection) {
    if ($data === null) return null;
    return $connection->real_escape_string(trim($data));
}

// Helper function for query error handling - DEFINED GLOBALLY
function handle_query_error($conn, $message) {
    send_response(500, 'error', $message . ': ' . $conn->error);
}

// --- Automatyczne tworzenie/aktualizacja tabel ---
try {
    // Sprawdź i utwórz tabelę 'battery_units'
    $table_check = $conn->query("SHOW TABLES LIKE 'battery_units'");
    if ($table_check->num_rows == 0) {
        $create_table_sql = "
        CREATE TABLE `battery_units` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `product_name` VARCHAR(255) NOT NULL,
          `goods_reception_document` VARCHAR(255) NOT NULL,
          `location` VARCHAR(255) NULL,
          `serial_number` VARCHAR(255) NULL UNIQUE,
          `last_charged` DATE NULL,
          `notes` TEXT NULL,
          `last_modified_by_user_id` INT NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX `idx_goods_reception_document` (`goods_reception_document`),
          INDEX `idx_location` (`location`),
          FOREIGN KEY (`last_modified_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
        )";
        if (!$conn->query($create_table_sql)) {
            send_response(500, 'error', 'Błąd krytyczny: Nie udało się utworzyć tabeli `battery_units`. Błąd: ' . $conn->error);
        }
    }
} catch (Exception $e) {
    send_response(500, 'error', 'Błąd krytyczny podczas sprawdzania tabeli `battery_units`: ' . $e->getMessage());
}


// --- Ochrona API (można włączyć w przyszłości) ---
if (API_PROTECTION_ENABLED && !isset($_SESSION['user_id'])) {
    send_response(401, 'error', 'Brak autoryzacji. Wymagane zalogowanie.');
}

// --- Główny router API ---
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? null;

// Specjalna obsługa dla akcji zapisu motywu
if ($method === 'POST' && $action === 'save-theme') {
    if (API_PROTECTION_ENABLED && !isset($_SESSION['user_id'])) {
        send_response(401, 'error', 'Brak autoryzacji.');
    }
    $theme = $input['theme'] ?? 'light';
    if ($theme !== 'light' && $theme !== 'dark') {
        send_response(400, 'error', 'Nieprawidłowa nazwa motywu.');
    }
    
    // Krok 1: Zawsze zapisuj motyw w sesji, aby działał dla niezalogowanych użytkowników.
    $_SESSION['theme'] = $theme;

    // Krok 2: Jeśli użytkownik jest zalogowany, zapisz jego preferencje w bazie danych.
    if (isset($_SESSION['user_id'])) { // Zapisz motyw w bazie danych tylko jeśli użytkownik jest zalogowany
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("UPDATE users SET theme = ? WHERE id = ?");
        $stmt->bind_param("si", $theme, $user_id); // "s" dla string (theme), "i" dla integer (id)
        $stmt->execute();
    }
    // Zawsze zwracaj sukces, ponieważ motyw został zapisany przynajmniej w sesji.
    send_response(200, 'success', 'Motyw został zapisany.');
}

// Specjalna obsługa dla pobierania statystyk
if ($method === 'GET' && $action === 'get-stats') {
    $stats = [
        'total_batteries' => 0,
        'ok_status' => 0,
        'due_soon_status' => 0,
        'charge_needed' => 0, // 'charge_needed' to teraz 'po terminie'
        'last_sync_time' => 'Nigdy' // Dodajemy domyślną wartość
    ];

    // Zlicz wszystkie baterie
    $total_result = $conn->query("SELECT COUNT(id) as total FROM battery_units"); handle_query_error($conn, "Błąd zapytania (total_batteries)");
    $stats['total_batteries'] = (int)$total_result->fetch_assoc()['total'];

    // Zlicz baterie w dobrym stanie (termin za ponad 30 dni)
    $ok_status_result = $conn->query("SELECT COUNT(id) as total FROM battery_units WHERE DATE_ADD(last_charged, INTERVAL 1 YEAR) > DATE_ADD(CURDATE(), INTERVAL 2 MONTH)"); handle_query_error($conn, "Błąd zapytania (ok_status)");
    $stats['ok_status'] = (int)$ok_status_result->fetch_assoc()['total'];

    // Zlicz baterie do naładowania wkrótce (termin w ciągu 30 dni)
    $due_soon_result = $conn->query("SELECT COUNT(id) as total FROM battery_units WHERE DATE_ADD(last_charged, INTERVAL 1 YEAR) > CURDATE() AND DATE_ADD(last_charged, INTERVAL 1 YEAR) <= DATE_ADD(CURDATE(), INTERVAL 2 MONTH)"); handle_query_error($conn, "Błąd zapytania (due_soon_status)");
    $stats['due_soon_status'] = (int)$due_soon_result->fetch_assoc()['total'];

    // Zlicz baterie po terminie (termin minął lub data jest pusta)
    $charge_needed_result = $conn->query("SELECT COUNT(id) as total FROM battery_units WHERE last_charged IS NULL OR DATE_ADD(last_charged, INTERVAL 1 YEAR) <= CURDATE()"); handle_query_error($conn, "Błąd zapytania (charge_needed)");
    $stats['charge_needed'] = (int)$charge_needed_result->fetch_assoc()['total'];

    // Pobierz datę ostatniej synchronizacji
    $sync_time_result = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'last_sync_time'"); handle_query_error($conn, "Błąd zapytania (last_sync_time)");
    if ($sync_time_result->num_rows > 0) {
        $stats['last_sync_time'] = $sync_time_result->fetch_assoc()['setting_value'];
    }

    // Ensure all stats are numeric, default to 0 if null
    foreach ($stats as $key => $value) {
        if (is_numeric($value)) {
            $stats[$key] = (int)$value;
        } elseif ($key !== 'last_sync_time') { // last_sync_time can be string "Nigdy"
            $stats[$key] = 0;
        }
    }

    send_response(200, 'success', 'Statystyki pobrano pomyślnie.', $stats);
}

// Specjalna obsługa dla danych do wykresu
if ($method === 'GET' && $action === 'get-chart-data') {
    $chart_data = [
        'ok' => 0,
        'due_soon' => 0,
        'overdue' => 0,
    ];

    // Krok 1: Zlicz wszystkie baterie
    $total_result = $conn->query("SELECT COUNT(id) as total FROM battery_units"); handle_query_error($conn, "Błąd zapytania (chart total_batteries)");
    $total_batteries = (int)$total_result->fetch_assoc()['total'];

    // Krok 2: Zlicz baterie PO TERMINIE (termin minął lub data jest pusta)
    $overdue_result = $conn->query("SELECT COUNT(id) as total FROM battery_units WHERE last_charged IS NULL OR DATE_ADD(last_charged, INTERVAL 1 YEAR) <= CURDATE()"); handle_query_error($conn, "Błąd zapytania (chart overdue)");
    $chart_data['overdue'] = (int)$overdue_result->fetch_assoc()['total'];

    // Krok 3: Zlicz baterie DO NAŁADOWANIA WKRÓTCE (termin w ciągu najbliższych 2 miesięcy, ale jeszcze nie minął)
    $due_soon_result = $conn->query("SELECT COUNT(id) as total FROM battery_units WHERE DATE_ADD(last_charged, INTERVAL 1 YEAR) > CURDATE() AND DATE_ADD(last_charged, INTERVAL 1 YEAR) <= DATE_ADD(CURDATE(), INTERVAL 2 MONTH)"); handle_query_error($conn, "Błąd zapytania (chart due_soon)");
    $chart_data['due_soon'] = (int)$due_soon_result->fetch_assoc()['total'];

    // Krok 4: Baterie w dobrym stanie (reszta)
    // To jest najbardziej niezawodny sposób, aby uniknąć podwójnego liczenia lub pominięcia baterii.
    $chart_data['ok'] = $total_batteries - $chart_data['overdue'] - $chart_data['due_soon'];

    // Ensure all chart data values are numeric, default to 0 if null
    $chart_data['ok'] = max(0, $chart_data['ok']); // Ensure non-negative
    $chart_data['due_soon'] = max(0, $chart_data['due_soon']);
    $chart_data['overdue'] = max(0, $chart_data['overdue']);

    send_response(200, 'success', 'Dane do wykresu pobrano pomyślnie.', $chart_data);
}

// Specjalna obsługa dla eksportu CSV
if ($method === 'GET' && $action === 'export-csv') {
    $result = $conn->query("SELECT id, product_name, location, goods_reception_document, serial_number, last_charged, notes FROM battery_units ORDER BY location, goods_reception_document, id");
    if (!$result) handle_query_error($conn, "Błąd zapytania (export-csv)");

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="eksport_baterii_'.date('Y-m-d').'.csv"');

    $output = fopen('php://output', 'w');
    
    // Nagłówki CSV
    fputcsv($output, ['ID', 'Nazwa Produktu', 'Lokalizacja', 'Dokument Przyjęcia', 'Numer Seryjny', 'Data Ost. Ładowania', 'Notatki']);

    // Dane
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }

    fclose($output);
    $conn->close();
    exit();
}

switch ($method) {
    case 'GET':
        $result = $conn->query("SELECT id, product_name, goods_reception_document, location, serial_number, last_charged, notes FROM battery_units ORDER BY location ASC, goods_reception_document ASC, id ASC");
        if (!$result) handle_query_error($conn, "Błąd zapytania (default GET batteries)");
        $batteries = [];
        while ($row = $result->fetch_assoc()) {
            $batteries[] = $row;
        }
        send_response(200, 'success', 'Dane pobrano pomyślnie.', $batteries);
        break;

    case 'POST':
        // Dodawanie ręczne jest teraz bardziej skomplikowane, na razie je upraszczamy.
        // W przyszłości można by to rozbudować.
        $product_name = sanitize_input($input['product_name'] ?? 'Ręcznie dodana', $conn);
        $location = sanitize_input($input['location'] ?? null, $conn);
        $goods_reception_document = sanitize_input($input['goods_reception_document'] ?? 'MANUAL-' . time(), $conn);
        $serial_number = sanitize_input($input['serial_number'] ?? null, $conn);

        if ($serial_number) {
            $stmt = $conn->prepare("SELECT id FROM battery_units WHERE serial_number = ?");
            $stmt->bind_param("s", $serial_number);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                send_response(409, 'error', 'Bateria o tym numerze seryjnym już istnieje.');
            }
            $stmt->close();
        }

        $stmt = $conn->prepare("INSERT INTO battery_units (product_name, goods_reception_document, location, serial_number) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $product_name, $goods_reception_document, $location, $serial_number);
        if ($stmt->execute()) {
            $new_id = $stmt->insert_id;
            send_response(201, 'success', 'Bateria została dodana.', ['id' => $new_id, 'serial_number' => $serial_number, 'location' => $location]);
        } else {
            send_response(500, 'error', 'Nie udało się dodać baterii: ' . $stmt->error);
        }
        $stmt->close();
        break;

    case 'PUT':
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            send_response(400, 'error', 'Nieprawidłowe ID baterii.');
        }
    
        $fields = [];
        $params = [];
        $types = '';
    
        // Dynamicznie buduj zapytanie na podstawie przesłanych pól
        if (isset($input['serial_number']) && $input['serial_number'] !== null) {
            $new_serial = sanitize_input($input['serial_number'], $conn);
            // Sprawdź, czy nowy numer seryjny nie jest już zajęty przez INNĄ baterię
            $stmt_check = $conn->prepare("SELECT id FROM battery_units WHERE serial_number = ? AND id != ?");
            $stmt_check->bind_param("si", $new_serial, $id);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                send_response(409, 'error', 'Bateria o tym numerze seryjnym już istnieje.');
            }
            $stmt_check->close();

            $fields[] = 'serial_number = ?';
            $params[] = $new_serial;
            $types .= 's';
        }
        if (isset($input['location'])) {
            $fields[] = 'location = ?';
            $params[] = sanitize_input($input['location'], $conn);
            $types .= 's';
        }
        if (isset($input['last_charged'])) {
            $fields[] = 'last_charged = ?';
            $params[] = $input['last_charged'] === '' ? null : sanitize_input($input['last_charged'], $conn);
            $types .= 's';
        }
        if (isset($input['notes'])) {
            $fields[] = 'notes = ?';
            $params[] = sanitize_input($input['notes'], $conn);
            $types .= 's';
        }

    
        if (empty($fields)) {
            send_response(400, 'error', 'Brak danych do aktualizacji.');
        }
    
        $params[] = $id;
        $types .= 'i';
    
        $stmt = $conn->prepare("UPDATE battery_units SET " . implode(', ', $fields) . " WHERE id = ?");
        
        // --- POCZĄTEK POPRAWKI: Użycie call_user_func_array dla lepszej kompatybilności ---
        // Tworzymy tablicę referencji, ponieważ bind_param tego wymaga
        $bind_params = [];
        $bind_params[] = $types;
        foreach ($params as $key => $value) {
            $bind_params[] = &$params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_params);
        // --- KONIEC POPRAWKI ---

        if ($stmt->execute()) {
            send_response(200, 'success', 'Bateria została zaktualizowana.');
        } else {
            send_response(500, 'error', 'Nie udało się zaktualizować baterii: ' . $stmt->error);
        }
        $stmt->close();
        break;

    case 'DELETE':
        $id = filter_var($input['id'] ?? 0, FILTER_VALIDATE_INT);
        if (!$id) {
            send_response(400, 'error', 'Nieprawidłowe ID baterii.');
        }
        $stmt = $conn->prepare("DELETE FROM battery_units WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            send_response(200, 'success', 'Bateria została usunięta.');
        } else {
            send_response(500, 'error', 'Nie udało się usunąć baterii: ' . $stmt->error);
        }
        $stmt->close();
        break;

    default:
        send_response(405, 'error', 'Metoda niedozwolona.');
        break;
}

$conn->close();
?>