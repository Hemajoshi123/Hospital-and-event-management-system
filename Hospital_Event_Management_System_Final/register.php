<?php
require_once __DIR__ . '/includes/config.php';

$conn->query('ALTER TABLE patients ADD COLUMN IF NOT EXISTS username VARCHAR(50) UNIQUE');
$conn->query('ALTER TABLE patients ADD COLUMN IF NOT EXISTS password VARCHAR(255)');

$error = '';
$genderOptions = ['Male', 'Female', 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $dob = $_POST['dob'] ?? '';
    $appointmentDate = $_POST['appointment_date'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || !in_array($gender, $genderOptions, true) || $dob === '' || $appointmentDate === '' || $phone === '' || $address === '' || $username === '' || strlen($password) < 8) {
        $error = 'Please complete all registration fields.';
    } elseif ($dob > date('Y-m-d')) {
        $error = 'Date of birth cannot be in the future.';
    } elseif ($appointmentDate < date('Y-m-d')) {
        $error = 'Please choose today or a future appointment date.';
    } else {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $statement = $conn->prepare('INSERT INTO patients (name, gender, dob, phone, address, username, password) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $statement->bind_param('sssssss', $name, $gender, $dob, $phone, $address, $username, $passwordHash);

        if ($statement->execute()) {
            $patientId = $conn->insert_id;
            $statement->close();
            session_start();
            $_SESSION['patient_id'] = $patientId;
            $_SESSION['patient_username'] = $username;
            $_SESSION['pending_appointment_date'] = $appointmentDate;
            header('Location: appointment.php?registered=1');
            exit;
        }

        $statement->close();
        $error = 'Registration could not be completed. Please try again.';
    }
}

function escapeRegistrationValue($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Patient Registration | Hospital Event Management</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .registration-page { min-height: 100vh; display: grid; place-items: center; padding: 24px; background: linear-gradient(135deg, #eaf3f5, #f8fbfc 55%, #dcebed); }
        .registration-card { width: min(100%, 650px); }
        .registration-card h1 { margin-bottom: 8px; color: #17324d; }
        .registration-subtitle { margin-bottom: 24px; color: #526579; }
        .registration-form { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .registration-form .full-width { grid-column: 1 / -1; }
        .registration-form label { display: block; margin-bottom: 6px; font-weight: bold; color: #17324d; }
        .registration-form input, .registration-form select, .registration-form textarea, .registration-form button { box-sizing: border-box; width: 100%; padding: 11px; border: 1px solid #b7c5d1; border-radius: 4px; font: inherit; }
        .registration-form textarea { min-height: 78px; resize: vertical; }
        .registration-form button { border: 0; background: #1d7795; color: #fff; font-weight: bold; cursor: pointer; }
        .registration-form button:hover { background: #155b72; }
        .registration-error { grid-column: 1 / -1; padding: 10px 12px; border-left: 3px solid #b42318; background: #fff1f0; color: #922018; }
        .registration-links { display: flex; justify-content: space-between; gap: 12px; margin-top: 22px; }
        .registration-links a { color: #1d7795; font-weight: bold; text-decoration: none; }
        .registration-links a:hover { text-decoration: underline; }
        @media (max-width: 600px) { .registration-page { padding: 16px; } .registration-form { grid-template-columns: 1fr; } .registration-form .full-width, .registration-error { grid-column: auto; } }
    </style>
</head>
<body>
    <main class="card registration-card">
        <p class="login-eyebrow">Hospital Event Management</p>
        <h1>Patient registration</h1>
        <p class="registration-subtitle">Register your details before booking an online appointment.</p>

        <form method="post" class="registration-form">
            <?php if ($error !== ''): ?><p class="registration-error" role="alert"><?php echo escapeRegistrationValue($error); ?></p><?php endif; ?>
            <div><label for="username">Username</label><input id="username" name="username" type="text" value="<?php echo escapeRegistrationValue($_POST['username'] ?? ''); ?>" autocomplete="username" required autofocus></div>
            <div><label for="password">Password</label><input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required></div>
            <div><label for="name">Full name</label><input id="name" name="name" type="text" value="<?php echo escapeRegistrationValue($_POST['name'] ?? ''); ?>" required></div>
            <div><label for="gender">Gender</label><select id="gender" name="gender" required><option value="">Select gender</option><?php foreach ($genderOptions as $gender): ?><option value="<?php echo escapeRegistrationValue($gender); ?>" <?php echo ($_POST['gender'] ?? '') === $gender ? 'selected' : ''; ?>><?php echo escapeRegistrationValue($gender); ?></option><?php endforeach; ?></select></div>
            <div><label for="dob">Date of birth</label><input id="dob" name="dob" type="date" max="<?php echo date('Y-m-d'); ?>" value="<?php echo escapeRegistrationValue($_POST['dob'] ?? ''); ?>" required></div>
            <div><label for="appointment_date">Appointment date</label><input id="appointment_date" name="appointment_date" type="date" min="<?php echo date('Y-m-d'); ?>" value="<?php echo escapeRegistrationValue($_POST['appointment_date'] ?? ''); ?>" required></div>
            <div><label for="phone">Phone number</label><input id="phone" name="phone" type="tel" value="<?php echo escapeRegistrationValue($_POST['phone'] ?? ''); ?>" required></div>
            <div class="full-width"><label for="address">Address</label><textarea id="address" name="address" required><?php echo escapeRegistrationValue($_POST['address'] ?? ''); ?></textarea></div>
            <button class="full-width" type="submit">Register and book appointment</button>
        </form>

        <nav class="registration-links" aria-label="Registration navigation"><a href="index.php">&larr; Back to portals</a><a href="appointment.php">Book as existing patient</a></nav>
    </main>
</body>
</html>
