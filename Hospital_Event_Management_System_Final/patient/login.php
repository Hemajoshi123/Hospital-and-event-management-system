<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$conn->query('ALTER TABLE patients ADD COLUMN IF NOT EXISTS username VARCHAR(50) UNIQUE');
$conn->query('ALTER TABLE patients ADD COLUMN IF NOT EXISTS password VARCHAR(255)');

if (isset($_SESSION['patient_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $statement = $conn->prepare('SELECT id, username, password FROM patients WHERE username = ? LIMIT 1');
    $statement->bind_param('s', $username);
    $statement->execute();
    $patient = $statement->get_result()->fetch_assoc();
    $statement->close();

    if ($patient && password_verify($password, (string) $patient['password'])) {
        session_regenerate_id(true);
        $_SESSION['patient_id'] = (int) $patient['id'];
        $_SESSION['patient_username'] = $patient['username'];
        header('Location: dashboard.php');
        exit;
    }

    $error = 'Invalid username or password.';
}

function escapePatientLoginValue($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Patient Login | Hospital Event Management</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <main class="card login-card">
        <p class="login-eyebrow">Hospital Event Management</p>
        <h1>Patient login</h1>
        <p class="login-subtitle">Sign in to view your appointments and billing.</p>
        <?php if ($error !== ''): ?><p class="login-error" role="alert"><?php echo escapePatientLoginValue($error); ?></p><?php endif; ?>
        <form method="post">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" autocomplete="username" required autofocus>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            <button type="submit">Login</button>
        </form>
        <p class="login-help">New patient? <a class="login-home" href="../register.php">Register here</a></p>
        <a class="login-home" href="../index.php">&larr; Back to portals</a>
    </main>
</body>
</html>
