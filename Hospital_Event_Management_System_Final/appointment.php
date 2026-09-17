<?php
session_start();
require_once __DIR__ . '/includes/config.php';

$message = '';
$error = '';
$selectedPatientId = (int) ($_GET['patient_id'] ?? 0);
$preferredAppointmentDate = trim($_GET['appointment_date'] ?? '');

if (isset($_SESSION['patient_id'])) {
    $selectedPatientId = (int) $_SESSION['patient_id'];
}

if (isset($_SESSION['pending_appointment_date'])) {
    $preferredAppointmentDate = (string) $_SESSION['pending_appointment_date'];
    unset($_SESSION['pending_appointment_date']);
}

if (isset($_GET['registered'])) {
    $message = 'Registration successful. Select a doctor to complete your appointment.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = (int) ($_POST['patient_id'] ?? 0);
    $doctorId = (int) ($_POST['doctor_id'] ?? 0);
    $appointmentDate = $_POST['appointment_date'] ?? '';

    if ($patientId < 1 || $doctorId < 1 || $appointmentDate === '') {
        $error = 'Please complete all appointment fields.';
    } elseif ($appointmentDate < date('Y-m-d')) {
        $error = 'Please choose today or a future date.';
    } else {
        $statement = $conn->prepare('SELECT id FROM patients WHERE id = ?');
        $statement->bind_param('i', $patientId);
        $statement->execute();
        $patientExists = (bool) $statement->get_result()->fetch_assoc();
        $statement->close();

        $statement = $conn->prepare('SELECT id FROM doctors WHERE id = ?');
        $statement->bind_param('i', $doctorId);
        $statement->execute();
        $doctorExists = (bool) $statement->get_result()->fetch_assoc();
        $statement->close();

        if (!$patientExists || !$doctorExists) {
            $error = 'The selected patient or doctor is not available.';
        } else {
            $statement = $conn->prepare('SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status IN (\'Scheduled\', \'Confirmed\') LIMIT 1');
            $statement->bind_param('is', $doctorId, $appointmentDate);
            $statement->execute();
            $doctorBooked = (bool) $statement->get_result()->fetch_assoc();
            $statement->close();

            if ($doctorBooked) {
                $error = 'This doctor already has an appointment on that date. Please choose another date.';
            } else {
                $status = 'Scheduled';
                $statement = $conn->prepare('INSERT INTO appointments (patient_id, doctor_id, appointment_date, status) VALUES (?, ?, ?, ?)');
                $statement->bind_param('iiss', $patientId, $doctorId, $appointmentDate, $status);
                $statement->execute();
                $statement->close();
                $message = 'Appointment request submitted successfully.';
            }
        }
    }
}

$patients = $conn->query('SELECT id, name FROM patients ORDER BY name');
$doctors = $conn->query('SELECT id, name, specialization FROM doctors ORDER BY name');

function escapeAppointmentValue($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Book Appointment | Hospital Event Management</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .booking-page { min-height: 100vh; display: grid; place-items: center; padding: 24px; background: linear-gradient(135deg, #eaf3f5, #f8fbfc 55%, #dcebed); }
        .booking-card { width: min(100%, 620px); }
        .booking-card h1 { margin-bottom: 8px; color: #17324d; }
        .booking-subtitle { margin-bottom: 26px; color: #526579; }
        .booking-form { display: grid; gap: 14px; }
        .booking-form label { display: block; margin-bottom: 6px; font-weight: bold; color: #17324d; }
        .booking-form select, .booking-form input, .booking-form button { width: 100%; padding: 11px; border: 1px solid #b7c5d1; border-radius: 4px; font: inherit; }
        .booking-form button { border: 0; background: #1d7795; color: #fff; font-weight: bold; cursor: pointer; }
        .booking-form button:hover { background: #155b72; }
        .booking-notice { padding: 10px 12px; border-left: 3px solid #17643a; background: #e7f5ed; color: #17643a; }
        .booking-error { padding: 10px 12px; border-left: 3px solid #b42318; background: #fff1f0; color: #922018; }
        .booking-links { display: flex; justify-content: space-between; gap: 12px; margin-top: 22px; }
        .booking-links a { color: #1d7795; font-weight: bold; text-decoration: none; }
        .booking-links a:hover { text-decoration: underline; }
        @media (max-width: 600px) { .booking-page { padding: 16px; } }
    </style>
</head>
<body>
    <main class="card booking-card">
        <p class="login-eyebrow">Hospital Event Management</p>
        <h1>Book an appointment</h1>
        <p class="booking-subtitle">Choose a patient, doctor and preferred date.</p>

        <?php if ($message !== ''): ?><p class="booking-notice" role="status"><?php echo escapeAppointmentValue($message); ?></p><?php endif; ?>
        <?php if ($error !== ''): ?><p class="booking-error" role="alert"><?php echo escapeAppointmentValue($error); ?></p><?php endif; ?>

        <?php if ($patients && $patients->num_rows > 0 && $doctors && $doctors->num_rows > 0): ?>
            <form method="post" class="booking-form">
                <div><label for="patient_id">Patient</label><?php if (isset($_SESSION['patient_id'])): ?><input type="hidden" name="patient_id" value="<?php echo $selectedPatientId; ?>"><input id="patient_id" type="text" value="Your registered profile" readonly><?php else: ?><select id="patient_id" name="patient_id" required><option value="">Select patient</option><?php while ($patient = $patients->fetch_assoc()): ?><option value="<?php echo (int) $patient['id']; ?>" <?php echo $selectedPatientId === (int) $patient['id'] ? 'selected' : ''; ?>><?php echo escapeAppointmentValue($patient['name']); ?></option><?php endwhile; ?></select><?php endif; ?></div>
                <div><label for="doctor_id">Doctor</label><select id="doctor_id" name="doctor_id" required><option value="">Select doctor</option><?php while ($doctor = $doctors->fetch_assoc()): ?><option value="<?php echo (int) $doctor['id']; ?>"><?php echo escapeAppointmentValue($doctor['name'] . ' - ' . $doctor['specialization']); ?></option><?php endwhile; ?></select></div>
                <div><label for="appointment_date">Preferred date</label><input id="appointment_date" name="appointment_date" type="date" min="<?php echo date('Y-m-d'); ?>" value="<?php echo escapeAppointmentValue($preferredAppointmentDate); ?>" required></div>
                <button type="submit">Request appointment</button>
            </form>
        <?php else: ?><p class="booking-error">Appointments require at least one registered patient and doctor.</p><?php endif; ?>

        <nav class="booking-links" aria-label="Booking navigation"><a href="index.php">&larr; Back to portals</a><a href="register.php">Register as new patient</a><a href="admin/login.php">Admin login</a></nav>
    </main>
</body>
</html>
