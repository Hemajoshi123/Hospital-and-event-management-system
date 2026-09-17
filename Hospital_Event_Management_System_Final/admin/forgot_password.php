<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$conn->query('ALTER TABLE admins ADD COLUMN IF NOT EXISTS email VARCHAR(150) UNIQUE');
$conn->query('ALTER TABLE admins ADD COLUMN IF NOT EXISTS phone VARCHAR(20)');
$legacyAdmin = $conn->query("SELECT id FROM admins WHERE username IN ('Hemu', 'hemu', 'admin') ORDER BY id LIMIT 1");
if ($legacyAdmin && ($adminRow = $legacyAdmin->fetch_assoc())) {
    $adminId = (int) $adminRow['id'];
    $statement = $conn->prepare('UPDATE admins SET username = ?, email = ?, phone = ? WHERE id = ?');
    $adminUsername = 'Hemu';
    $adminEmail = 'hemajoshi6665@gmail.com';
    $adminPhone = '9800000000';
    $statement->bind_param('sssi', $adminUsername, $adminEmail, $adminPhone, $adminId);
    $statement->execute();
    $statement->close();
}

$step = !empty($_SESSION['reset_admin_id']) ? 2 : 1;
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $demoOtp = '';
    $action = $_POST['action'] ?? '';

    if ($action === 'send_otp') {
        $email = trim($_POST['email'] ?? '');
        $statement = $conn->prepare('SELECT id, phone FROM admins WHERE email = ? LIMIT 1');
        $statement->bind_param('s', $email);
        $statement->execute();
        $admin = $statement->get_result()->fetch_assoc();
        $statement->close();

        if (!$admin || trim((string) $admin['phone']) === '') {
            $error = 'No admin account found with this email and mobile number.';
        } else {
            $otp = (string) random_int(100000, 999999);
            $_SESSION['reset_admin_id'] = (int) $admin['id'];
            $_SESSION['reset_otp_hash'] = password_hash($otp, PASSWORD_DEFAULT);
            $_SESSION['reset_otp_expires'] = time() + 300;
            $step = 2;

            if (SMS_API_URL !== '' && SMS_API_KEY !== '') {
                $payload = json_encode(['api_key' => SMS_API_KEY, 'sender' => SMS_SENDER_ID, 'to' => $admin['phone'], 'message' => 'Your hospital admin password reset OTP is ' . $otp]);
                $curl = curl_init(SMS_API_URL);
                curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
                curl_exec($curl);
                $smsError = curl_errno($curl);
                curl_close($curl);
                if ($smsError) {
                    unset($_SESSION['reset_admin_id'], $_SESSION['reset_otp_hash'], $_SESSION['reset_otp_expires']);
                    $step = 1;
                    $error = 'OTP could not be sent. Please check the SMS gateway settings.';
                } else {
                    $message = 'OTP sent to your registered mobile number.';
                }
            } else {
                $demoOtp = $otp;
                $message = 'Demo mode: SMS gateway is not configured. Use the OTP shown below.';
            }
        }
    } elseif ($action === 'verify_otp') {
        $otp = trim($_POST['otp'] ?? '');
        if (!isset($_SESSION['reset_otp_hash'], $_SESSION['reset_admin_id']) || time() > (int) $_SESSION['reset_otp_expires']) {
            $error = 'OTP expired. Please request a new OTP.';
            $step = 1;
        } elseif (!password_verify($otp, $_SESSION['reset_otp_hash'])) {
            $error = 'Invalid OTP.';
            $step = 2;
        } else {
            $_SESSION['reset_verified'] = true;
            $step = 3;
            $message = 'OTP verified. You can now change your password.';
        }
    } elseif ($action === 'change_password' && !empty($_SESSION['reset_verified'])) {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        if (strlen($newPassword) < 8) {
            $error = 'New password must be at least 8 characters.';
            $step = 3;
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Passwords do not match.';
            $step = 3;
        } else {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $statement = $conn->prepare('UPDATE admins SET password = ? WHERE id = ?');
            $statement->bind_param('si', $passwordHash, $_SESSION['reset_admin_id']);
            $statement->execute();
            $statement->close();
            unset($_SESSION['reset_admin_id'], $_SESSION['reset_otp_hash'], $_SESSION['reset_otp_expires'], $_SESSION['reset_verified']);
            header('Location: login.php?reset=1');
            exit;
        }
    }
}

function escapeResetValue($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password | Hospital Event Management</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <main class="card login-card">
        <p class="login-eyebrow">Admin account recovery</p>
        <h1>Reset password</h1>
        <p class="login-subtitle">Verify your email and mobile OTP before changing the password.</p>
        <?php if ($error !== ''): ?><p class="login-error" role="alert"><?php echo escapeResetValue($error); ?></p><?php endif; ?>
        <?php if ($message !== ''): ?><p class="login-success" role="status"><?php echo escapeResetValue($message); ?></p><?php endif; ?>

        <?php if ($step === 1): ?>
            <form method="post">
                <input type="hidden" name="action" value="send_otp">
                <label for="email">Registered email</label>
                <input id="email" name="email" type="email" required autofocus>
                <button type="submit">Send OTP to mobile</button>
            </form>
        <?php elseif ($step === 2): ?>
            <?php if ($demoOtp !== ''): ?><p class="login-success"><strong>Demo OTP: <?php echo escapeResetValue($demoOtp); ?></strong></p><?php endif; ?>
            <form method="post">
                <input type="hidden" name="action" value="verify_otp">
                <label for="otp">Mobile OTP</label>
                <input id="otp" name="otp" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus>
                <button type="submit">Verify OTP</button>
            </form>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="action" value="change_password">
                <label for="new_password">New password</label>
                <input id="new_password" name="new_password" type="password" minlength="8" autocomplete="new-password" required autofocus>
                <label for="confirm_password">Confirm password</label>
                <input id="confirm_password" name="confirm_password" type="password" minlength="8" autocomplete="new-password" required>
                <button type="submit">Change password</button>
            </form>
        <?php endif; ?>
        <a class="login-home" href="login.php">&larr; Back to login</a>
    </main>
</body>
</html>
