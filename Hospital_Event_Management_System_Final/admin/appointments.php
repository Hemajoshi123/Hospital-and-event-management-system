<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
	header('Location: login.php');
	exit;
}

require_once __DIR__ . '/../includes/config.php';

$message = '';
$error = '';
$statusOptions = ['Scheduled', 'Confirmed', 'Completed', 'Cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? '';
	$appointmentId = (int) ($_POST['appointment_id'] ?? 0);

	if ($action === 'add') {
		$patientId = (int) ($_POST['patient_id'] ?? 0);
		$doctorId = (int) ($_POST['doctor_id'] ?? 0);
		$appointmentDate = $_POST['appointment_date'] ?? '';
		$status = $_POST['status'] ?? 'Scheduled';

		if ($patientId < 1 || $doctorId < 1 || $appointmentDate === '' || !in_array($status, $statusOptions, true)) {
			$error = 'Please complete all appointment fields.';
		} else {
			$statement = $conn->prepare('INSERT INTO appointments (patient_id, doctor_id, appointment_date, status) VALUES (?, ?, ?, ?)');
			$statement->bind_param('iiss', $patientId, $doctorId, $appointmentDate, $status);
			$statement->execute();
			$statement->close();
			$message = 'Appointment added successfully.';
		}
	} elseif ($action === 'status' && $appointmentId > 0) {
		$status = $_POST['status'] ?? '';

		if (!in_array($status, $statusOptions, true)) {
			$error = 'Invalid appointment status.';
		} else {
			$statement = $conn->prepare('UPDATE appointments SET status = ? WHERE id = ?');
			$statement->bind_param('si', $status, $appointmentId);
			$statement->execute();
			$statement->close();
			$message = 'Appointment status updated.';
		}
	} elseif ($action === 'delete' && $appointmentId > 0) {
		$statement = $conn->prepare('DELETE FROM appointments WHERE id = ?');
		$statement->bind_param('i', $appointmentId);
		$statement->execute();
		$statement->close();
		$message = 'Appointment deleted.';
	}
}

$patients = $conn->query('SELECT id, name FROM patients ORDER BY name');
$doctors = $conn->query('SELECT id, name, specialization FROM doctors ORDER BY name');
$appointments = $conn->query(
	'SELECT appointments.id, appointments.appointment_date, appointments.status,
			patients.name AS patient_name, doctors.name AS doctor_name, doctors.specialization
	 FROM appointments
	 LEFT JOIN patients ON patients.id = appointments.patient_id
	 LEFT JOIN doctors ON doctors.id = appointments.doctor_id
	 ORDER BY appointments.appointment_date DESC, appointments.id DESC'
);

function escapeValue($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Appointments | Hospital Event Management</title>
	<link rel="stylesheet" href="../assets/css/style.css">
	<style>
		.page { max-width: 1180px; margin: 0 auto; padding: 24px; }
		.page-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
		.page-header h1 { margin: 0; color: #17324d; }
		.back-link { color: #1d7795; font-weight: bold; text-decoration: none; }
		.form-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; align-items: end; }
		label { display: block; margin-bottom: 6px; font-weight: bold; color: #17324d; }
		input, select, button { box-sizing: border-box; width: 100%; padding: 10px; border: 1px solid #b7c5d1; border-radius: 4px; font: inherit; }
		button { border: 0; background: #1d7795; color: white; cursor: pointer; font-weight: bold; }
		button:hover { background: #155b72; }
		.notice { padding: 10px 12px; border-radius: 4px; background: #e7f5ed; color: #17643a; }
		.error { padding: 10px 12px; border-radius: 4px; background: #fdecec; color: #a32222; }
		.table-wrap { overflow-x: auto; }
		table { width: 100%; border-collapse: collapse; }
		th, td { padding: 12px 10px; border-bottom: 1px solid #d9e1e8; text-align: left; white-space: nowrap; }
		th { background: #f3f7fa; color: #17324d; }
		.inline-form { display: flex; gap: 6px; align-items: center; }
		.inline-form select, .inline-form button { width: auto; padding: 7px; }
		.delete-button { background: #b42318; }
		.delete-button:hover { background: #8f1c14; }
		@media (max-width: 800px) { .form-grid { grid-template-columns: 1fr 1fr; } }
		@media (max-width: 520px) { .page { padding: 16px; } .page-header { align-items: flex-start; flex-direction: column; } .form-grid { grid-template-columns: 1fr; } }
	</style>
</head>
<body>
	<main class="page">
		<header class="page-header">
			<h1>Appointments Management</h1>
			<a class="back-link" href="dashboard.php">Back to dashboard</a>
		</header>

		<?php if ($message !== ''): ?><p class="notice" role="status"><?php echo escapeValue($message); ?></p><?php endif; ?>
		<?php if ($error !== ''): ?><p class="error" role="alert"><?php echo escapeValue($error); ?></p><?php endif; ?>

		<section class="card">
			<h2>Schedule appointment</h2>
			<?php if ($patients && $patients->num_rows > 0 && $doctors && $doctors->num_rows > 0): ?>
				<form method="post" class="form-grid">
					<input type="hidden" name="action" value="add">
					<div>
						<label for="patient_id">Patient</label>
						<select id="patient_id" name="patient_id" required>
							<option value="">Select patient</option>
							<?php while ($patient = $patients->fetch_assoc()): ?>
								<option value="<?php echo (int) $patient['id']; ?>"><?php echo escapeValue($patient['name']); ?></option>
							<?php endwhile; ?>
						</select>
					</div>
					<div>
						<label for="doctor_id">Doctor</label>
						<select id="doctor_id" name="doctor_id" required>
							<option value="">Select doctor</option>
							<?php while ($doctor = $doctors->fetch_assoc()): ?>
								<option value="<?php echo (int) $doctor['id']; ?>"><?php echo escapeValue($doctor['name'] . ' - ' . $doctor['specialization']); ?></option>
							<?php endwhile; ?>
						</select>
					</div>
					<div>
						<label for="appointment_date">Date</label>
						<input id="appointment_date" name="appointment_date" type="date" required>
					</div>
					<div>
						<label for="status">Status</label>
						<select id="status" name="status">
							<?php foreach ($statusOptions as $status): ?><option value="<?php echo escapeValue($status); ?>"><?php echo escapeValue($status); ?></option><?php endforeach; ?>
						</select>
					</div>
					<button type="submit">Add appointment</button>
				</form>
			<?php else: ?>
				<p>Add at least one patient and one doctor before scheduling an appointment.</p>
			<?php endif; ?>
		</section>

		<section class="card">
			<h2>Appointment list</h2>
			<div class="table-wrap">
				<table>
					<thead><tr><th>Patient</th><th>Doctor</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
					<tbody>
						<?php if ($appointments && $appointments->num_rows > 0): ?>
							<?php while ($appointment = $appointments->fetch_assoc()): ?>
								<tr>
									<td><?php echo escapeValue($appointment['patient_name'] ?? 'Unknown patient'); ?></td>
									<td><?php echo escapeValue($appointment['doctor_name'] ?? 'Unknown doctor'); ?></td>
									<td><?php echo escapeValue($appointment['appointment_date']); ?></td>
									<td>
										<form method="post" class="inline-form">
											<input type="hidden" name="action" value="status">
											<input type="hidden" name="appointment_id" value="<?php echo (int) $appointment['id']; ?>">
											<select name="status" aria-label="Appointment status">
												<?php foreach ($statusOptions as $status): ?>
													<option value="<?php echo escapeValue($status); ?>" <?php echo $status === $appointment['status'] ? 'selected' : ''; ?>><?php echo escapeValue($status); ?></option>
												<?php endforeach; ?>
											</select>
											<button type="submit">Save</button>
										</form>
									</td>
									<td>
										<form method="post" onsubmit="return confirm('Delete this appointment?');">
											<input type="hidden" name="action" value="delete">
											<input type="hidden" name="appointment_id" value="<?php echo (int) $appointment['id']; ?>">
											<button class="delete-button" type="submit">Delete</button>
										</form>
									</td>
								</tr>
							<?php endwhile; ?>
						<?php else: ?>
							<tr><td colspan="5">No appointments found.</td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</section>
	</main>
</body>
</html>