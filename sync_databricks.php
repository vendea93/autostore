<?php
// =========================================================================
//  SKRYPT SYNCHRONIZACJI Z DATABRICKS v2.0
// =========================================================================

// --- Konfiguracja i obsługa błędów ---
ini_set('display_errors', 0); // Wyłączamy wyświetlanie błędów, aby nie zakłócać odpowiedzi JSON
ini_set('log_errors', 1);     // Włączamy logowanie błędów do pliku
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
set_time_limit(300); // Ustaw maksymalny czas wykonania na 5 minut

ob_start(); // Rozpocznij buforowanie wyjścia
session_start(); // Rozpocznij sesję

$current_time = date('Y-m-d H:i:s'); // Zdefiniuj czas na początku, aby był zawsze dostępny

// --- Funkcja do wysyłania odpowiedzi JSON ---
// Ta funkcja jest kluczowa. Zapewnia, że ZAWSZE zwracamy poprawny JSON.
function send_json_response($status, $message, $data = null) {
    ob_end_clean(); // Wyczyść bufor przed wysłaniem odpowiedzi
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    $response = ['status' => $status, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit();
}

// --- KROK 1: Wczytanie konfiguracji ---
// Używamy @, aby stłumić domyślny błąd PHP i obsłużyć go samodzielnie.
if (@!require_once 'config.php') {
    send_json_response('error', 'Błąd krytyczny: Nie można załadować pliku konfiguracyjnego `config.php`. Upewnij się, że plik istnieje w tym samym katalogu.');
}

// --- KROK 2: Pobranie danych z Databricks ---
try {
    $databricks_sql_query = <<<'SQL'
    WITH LatestLocationAssignments AS (
        SELECT *, ROW_NUMBER() OVER(PARTITION BY sap_join_key ORDER BY updated_utc DESC) as rn
        FROM rambase.bronze.rb_location_assignment
        WHERE product_key = '46112408' AND location_assignment_status < 9 AND current_qty > 0 AND company_key = 46
    )
    SELECT
        p.product_name, sl.stock_location_name AS location, la.link_key AS goods_reception_document,
        la.created_utc AS ssa_creation_date,
        la.current_qty AS quantity
    FROM LatestLocationAssignments AS la
    JOIN rambase.bronze.rb_stock_locations AS sl ON la.stock_location_key = sl.activity_key
    JOIN rambase.bronze.rb_products AS p ON la.product_key = p.product_key
    WHERE la.rn = 1;
    SQL;
    
    $python_path = '/home/dhrtbzfpyj/virtualenv/databricks_app/3.9/bin/python';
    $script_path = '/home/dhrtbzfpyj/databricks_app/run_databricks_query.py';
    $temp_dir = '/home/dhrtbzfpyj/databricks_app/temp_queries';

    if (!is_dir($temp_dir)) mkdir($temp_dir, 0755, true);
    $temp_file = tempnam($temp_dir, 'sync_query_');
    file_put_contents($temp_file, $databricks_sql_query);
    
    $command = "export HOME=" . escapeshellarg('/home/dhrtbzfpyj') . " OPENBLAS_NUM_THREADS=1; " . escapeshellarg($python_path) . " " . escapeshellarg($script_path) . " " . escapeshellarg($temp_file) . " 2>&1";
    $output = shell_exec($command);
    unlink($temp_file);

    // NOWA, NIEZAWODNA LOGIKA: Znajdź początek JSON-a w odpowiedzi.
    $json_start_pos = strpos($output, '{');
    if ($json_start_pos === false) {
        throw new Exception("Nie znaleziono danych JSON w odpowiedzi skryptu Pythona. Pełna treść: " . htmlspecialchars($output));
    }
    $json_string = substr($output, $json_start_pos);

    $databricks_data = json_decode($json_string, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Błąd dekodowania JSON z Databricks: " . json_last_error_msg());
    }
    if ($databricks_data['status'] !== 'success') {
        throw new Exception("Błąd z Databricks: " . ($databricks_data['message'] ?? 'Nieznany błąd'));
    }

    $products_from_databricks = $databricks_data['data'];

} catch (Exception $e) {
    send_json_response('error', $e->getMessage());
}

// --- KROK 3: Synchronizacja z bazą MySQL ---
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    send_json_response('error', "Błąd połączenia z bazą MySQL: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');
$conn->begin_transaction();

try {
    $conn->query("TRUNCATE TABLE `battery_units`");

    // POPRAWIONE ZAPYTANIE: Dodano kolumnę `last_charged`
    $stmt_insert = $conn->prepare("INSERT INTO battery_units (product_name, goods_reception_document, location, last_charged) VALUES (?, ?, ?, ?)");
    if (!$stmt_insert) throw new Exception("Błąd przygotowania zapytania INSERT: " . $conn->error);

    $total_inserted_units = 0;
    foreach ($products_from_databricks as $group) {
        $quantity = (int)($group['quantity'] ?? 0);
        $ssa_creation_date = isset($group['ssa_creation_date']) ? substr($group['ssa_creation_date'], 0, 10) : null;
        if ($quantity > 0) {
            for ($i = 0; $i < $quantity; $i++) {
                $stmt_insert->bind_param("ssss", $group['product_name'], $group['goods_reception_document'], $group['location'], $ssa_creation_date);
                $stmt_insert->execute();
                $total_inserted_units++;
            }
        }
    }
    $stmt_insert->close();

    // Zapisz datę ostatniej synchronizacji
    $stmt_time = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'last_sync_time'");
    $stmt_time->bind_param("s", $current_time);
    $stmt_time->execute();
    $stmt_time->close();

    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    send_json_response('error', "Błąd podczas zapisu do bazy danych: " . $e->getMessage());
}

$conn->close();

$final_message = "Synchronizacja zakończona. Zaimportowano $total_inserted_units baterii z " . count($products_from_databricks) . " grup.";
send_json_response('success', $final_message, ['last_sync_time' => $current_time]);
?>