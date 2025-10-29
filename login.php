<?php
// Rozpocznij sesję na samej górze, przed jakimkolwiek outputem HTML
session_start();

// Database credentials (te same co w api.php)
define('DB_HOST', 's103.cyber-folks.pl');
define('DB_USER', 'dhrtbzfpyj_autostore');
define('DB_PASS', 'Autostore2025.');
define('DB_NAME', 'dhrtbzfpyj_autostore');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error_message = '';
$success_message = '';

// Obsługa wylogowania
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}

// Sprawdź, czy tabela 'users' istnieje. Jeśli nie, utwórz ją.
$table_check = $conn->query("SHOW TABLES LIKE 'users'");
if ($table_check === false) {
    die("Błąd krytyczny: Nie udało się sprawdzić istnienia tabeli 'users'. Błąd: " . $conn->error);
}
if ($table_check->num_rows == 0) {
    $create_table_sql = "CREATE TABLE `users` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `username` VARCHAR(50) NOT NULL UNIQUE,
      `password` VARCHAR(255) NOT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    if (!$conn->query($create_table_sql)) {
        die("Błąd krytyczny: Nie udało się utworzyć tabeli 'users'. Błąd: " . $conn->error);
    }
    // Dodaj kolumnę theme, jeśli nie istnieje (na wypadek starej instalacji)
} else {
    $theme_column_check = $conn->query("SHOW COLUMNS FROM `users` LIKE 'theme'");
    if ($theme_column_check->num_rows == 0) {
        if (!$conn->query("ALTER TABLE `users` ADD `theme` VARCHAR(10) DEFAULT 'light' NOT NULL")) {
            // Nie przerywaj działania, jeśli się nie uda, ale zaloguj błąd
            error_log("Błąd krytyczny: Nie udało się dodać kolumny 'theme' do tabeli 'users'.");
        }
    }
}

$user_check_result = $conn->query("SELECT COUNT(*) as count FROM `users`");
if ($user_check_result === false) {
    die("Błąd krytyczny: Nie udało się policzyć użytkowników. Błąd: " . $conn->error);
}
$user_count = $user_check_result->fetch_assoc()['count'];

if ($user_count == 0) { // Zmieniono na porównanie nietypowane, aby poprawnie obsłużyć string "0" z bazy danych
    // --- TRYB KONFIGURACJI: Tworzenie pierwszego administratora ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
        $username = $conn->real_escape_string($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        if (empty($username) || empty($password)) {
            $error_message = 'Nazwa użytkownika i hasło nie mogą być puste.';
        } elseif ($password !== $password_confirm) {
            $error_message = 'Hasła nie są zgodne.';
        } elseif (strlen($password) < 8) {
            $error_message = 'Hasło musi mieć co najmniej 8 znaków.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $stmt->bind_param("ss", $username, $hashed_password);

            if ($stmt->execute()) {
                // Automatycznie zaloguj nowego administratora
                $_SESSION['user_id'] = $stmt->insert_id;
                $_SESSION['username'] = $username;
                header('Location: index.php');
                exit();
            } else {
                $error_message = 'Nie udało się utworzyć konta administratora. Błąd: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// --- TRYB NORMALNY: Logowanie ---
// Jeśli użytkownik jest już zalogowany, przekieruj do index.php
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Obsługa formularza logowania (tylko jeśli są już użytkownicy)
if ($user_count > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $conn->real_escape_string($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = 'Wprowadź nazwę użytkownika i hasło.';
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, theme FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['theme'] = $user['theme']; // Zapisz motyw do sesji
                header('Location: index.php');
                exit();
            } else {
                $error_message = 'Nieprawidłowa nazwa użytkownika lub hasło.';
            }
        } else {
            $error_message = 'Nieprawidłowa nazwa użytkownika lub hasło.';
        }
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ($user_count === 0) ? 'Konfiguracja Administratora' : 'Logowanie'; ?> - Panel Monitorowania Baterii</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-container">
            <div class="themed-logo">
                <img src="logo_light.png" alt="Logo Firmy" class="logo logo-light">
                <img src="logo_dark.png" alt="Logo Firmy" class="logo logo-dark">
            </div>

            <div class="card">
                <?php if ($user_count == 0): // Zmieniono na porównanie nietypowane ?>
                    <h1>Konfiguracja Administratora</h1>
                    <p style="color: var(--color-text-secondary); margin-top: -1rem; margin-bottom: 1.5rem;">Witaj! Skonfiguruj pierwsze konto, aby rozpocząć.</p>
                    
                    <form method="POST" action="login.php" novalidate>
                        <?php if ($error_message): ?><div class="form-feedback error" style="display: block;"><?php echo $error_message; ?></div><?php endif; ?>
                        <div class="form-group">
                            <label for="username">Nazwa użytkownika:</label>
                            <input type="text" id="username" name="username" class="form-input" required autocomplete="username">
                        </div>
                        <div class="form-group">
                            <label for="password">Hasło (min. 8 znaków):</label>
                            <input type="password" id="password" name="password" class="form-input" required minlength="8" autocomplete="new-password">
                        </div>
                        <div class="form-group">
                            <label for="password_confirm">Potwierdź hasło:</label>
                            <input type="password" id="password_confirm" name="password_confirm" class="form-input" required autocomplete="new-password">
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Utwórz konto i zaloguj</button>
                    </form>
                <?php else: ?>
                    <h1>Logowanie</h1>
                    <form method="POST" action="login.php" novalidate>
                        <?php if ($error_message): ?><div class="form-feedback error" style="display: block;"><?php echo $error_message; ?></div><?php endif; ?>
                        <div class="form-group">
                            <label for="username">Nazwa użytkownika:</label>
                            <input type="text" id="username" name="username" class="form-input" required autocomplete="username">
                        </div>
                        <div class="form-group">
                            <label for="password">Hasło:</label>
                            <input type="password" id="password" name="password" class="form-input" required autocomplete="current-password">
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Zaloguj</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // Skrypt do inicjalizacji motywu na stronie logowania
    document.addEventListener('DOMContentLoaded', () => {
        const docHtml = document.documentElement;

        const setTheme = (theme) => {
            docHtml.setAttribute('data-theme', theme);
            try {
                localStorage.setItem('theme', theme);
            } catch (e) {
                console.info("Zapis motywu w localStorage pominięty z powodu braku dostępu.");
            }
        };

        try {
            const savedTheme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            // Ustaw atrybut bez zapisywania go ponownie
            docHtml.setAttribute('data-theme', savedTheme);
        } catch (e) {
            console.warn("Nie można uzyskać dostępu do localStorage. Motyw może nie zostać zapisany.", e);
            const defaultTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            docHtml.setAttribute('data-theme', defaultTheme);
        }
    });
    </script>
</body>
</html>