<?php
session_start();

try {
    // Validate form data
    $db_host = trim($_POST['db_host'] ?? '');
    $db_name = trim($_POST['db_name'] ?? '');
    $db_user = trim($_POST['db_user'] ?? '');
    $db_pass = $_POST['db_pass'] ?? '';
    $admin_username = trim($_POST['admin_username'] ?? '');
    $admin_password = $_POST['admin_password'] ?? '';

    // Validate all fields are filled
    if (empty($db_host) || empty($db_name) || empty($db_user) || empty($admin_username) || empty($admin_password)) {
        throw new Exception('Por favor complete todos los campos requeridos');
    }

    // Test database connection
    try {
        // First try to connect to MySQL server
        $pdo = new PDO(
            "mysql:host=$db_host",
            $db_user,
            $db_pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );

        // Create database if it doesn't exist
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // Connect to the specific database
        $pdo->exec("USE `$db_name`");

        // Execute schema
        $schema = file_get_contents(__DIR__ . '/schema.sql');
        $statements = explode(';', $schema);
        foreach ($statements as $statement) {
            if (trim($statement) != '') {
                $pdo->exec($statement);
            }
        }

        // Create admin user
        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password) VALUES (?, ?)");
        $stmt->execute([$admin_username, password_hash($admin_password, PASSWORD_DEFAULT)]);

        // Create config file content
        $config_content = <<<PHP
<?php
session_start();

// Database configuration
define('DB_HOST', '$db_host');
define('DB_NAME', '$db_name');
define('DB_USER', '$db_user');
define('DB_PASS', '$db_pass');

// Initialize PDO connection
try {
    \$pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException \$e) {
    die('Connection failed: ' . \$e->getMessage());
}

// Helper functions
function isLoggedIn() {
    return isset(\$_SESSION['admin_user']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /admin/index.php');
        exit();
    }
}
PHP;

        // Create includes directory if it doesn't exist
        $includes_dir = __DIR__ . '/../includes';
        if (!file_exists($includes_dir)) {
            mkdir($includes_dir, 0777, true);
        }

        // Write config file with proper permissions
        $config_file = $includes_dir . '/config.php';
        file_put_contents($config_file, $config_content);
        chmod($config_file, 0644);

        // Create .installed file
        $installed_file = $includes_dir . '/.installed';
        file_put_contents($installed_file, date('Y-m-d H:i:s'));
        chmod($installed_file, 0644);

        // Redirect to admin login with success message
        $_SESSION['success'] = 'Sistema instalado exitosamente';
        header('Location: ../admin/index.php');
        exit();

    } catch (PDOException $e) {
        throw new Exception('Error de conexión a la base de datos: ' . $e->getMessage());
    }

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: index.php');
    exit();
}