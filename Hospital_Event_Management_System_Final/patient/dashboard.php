<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$conn->query('ALTER TABLE patients ADD COLUMN IF NOT EXISTS username VARCHAR(50) UNIQUE');
$conn->query('ALTER TABLE patients ADD COLUMN IF NOT EXISTS password VARCHAR(255)');

if (isset($_GET['logout'])) {
	$_SESSION = [];
	session_destroy();
	header('Location: login.php');
	exit;
}

if (!isset($_SESSION['patient_id'])) {
	header('Location: login.php');
	exit;
}

$selectedPatientId = (int) $_SESSION['patient_id'];
$selectedPatient = null;
$appointments = false;
$bills = false;
$stats = ['upcoming' => 0, 'completed' => 0, 'billed' => 0];

if ($selectedPatientId > 0) {
	$statement = $conn->prepare('SELECT id, name, gender, dob, phone, address FROM patients WHERE id = ?');
	$statement->bind_param('i', $selectedPatientId);
	$statement->execute();
	$selectedPatient = $statement->get_result()->fetch_assoc();
	$statement->close();

	if ($selectedPatient) {
		$statement = $conn->prepare(
			'SELECT appointments.appointment_date, appointments.status,
					doctors.name AS doctor_name, doctors.specialization
			 FROM appointments
			 LEFT JOIN doctors ON doctors.id = appointments.doctor_id
			 WHERE appointments.patient_id = ?
			 ORDER BY appointments.appointment_date DESC'
		);
		$statement->bind_param('i', $selectedPatientId);
		$statement->execute();
		$appointments = $statement->get_result();
		$statement->close();

		$statement = $conn->prepare('SELECT amount, bill_date FROM billing WHERE patient_id = ? ORDER BY bill_date DESC');
		$statement->bind_param('i', $selectedPatientId);
		$statement->execute();
		$bills = $statement->get_result();
		$statement->close();

		$statements = [
			'upcoming' => 'SELECT COUNT(*) AS total FROM appointments WHERE patient_id = ? AND appointment_date >= CURDATE() AND status IN (\'Scheduled\', \'Confirmed\')',
			'completed' => 'SELECT COUNT(*) AS total FROM appointments WHERE patient_id = ? AND status = \'Completed\'',
			'billed' => 'SELECT COALESCE(SUM(amount), 0) AS total FROM billing WHERE patient_id = ?',
		];
		foreach ($statements as $key => $query) {
			$statement = $conn->prepare($query);
			$statement->bind_param('i', $selectedPatientId);
			$statement->execute();
			$stats[$key] = $key === 'billed' ? (float) $statement->get_result()->fetch_assoc()['total'] : (int) $statement->get_result()->fetch_assoc()['total'];
			$statement->close();
		}
	}
}

function escapePatientDashboardValue($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Patient Dashboard | Hospital Event Management</title>
	<link rel="stylesheet" href="../assets/css/style.css">
	<style>
		body:has(.patient-page) { background: linear-gradient(135deg, #eaf3f5 0%, #f8fbfc 55%, #dcebed 100%); }
		.patient-page { max-width: 1180px; margin: 0 auto; padding: 30px 24px; }
		.patient-header { position: relative; display: flex; min-height: 230px; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 22px; padding: 30px; overflow: hidden; border-radius: 12px; background: linear-gradient(120deg, #093042 0%, #1d7795 58%, #8cc3ca 100%); color: #fff; box-shadow: 0 14px 30px rgba(9, 48, 66, .2); }
		.patient-header > div:first-child, .patient-header > .admin-link { position: relative; z-index: 1; }
		.patient-header::after { position: absolute; top: 0; right: 0; width: 43%; height: 100%; background: linear-gradient(90deg, rgba(9, 48, 66, .95), rgba(9, 48, 66, .08)), url("https://images.unsplash.com/photo-1538108149393-fbbd81895907?auto=format&fit=crop&w=1000&q=85") center / cover; content: ""; }
		.patient-header h1 { margin: 0 0 5px; color: #fff; }
		.patient-header p { margin: 0; color: #d8edf2; }
		.admin-link { padding: 10px 14px; border: 1px solid rgba(255, 255, 255, .5); border-radius: 4px; color: #fff; font-weight: bold; text-decoration: none; }
		.admin-link:hover { background: rgba(255, 255, 255, .12); }
		.patient-page > .card { background: rgba(255, 255, 255, .96); }
		.selector { display: flex; align-items: end; gap: 12px; }
		.selector div { flex: 1; }
		label { display: block; margin-bottom: 6px; font-weight: bold; color: #17324d; }
		select { width: 100%; padding: 11px; border: 1px solid #b7c5d1; border-radius: 4px; font: inherit; }
		.selector button { padding: 11px 20px; border: 0; border-radius: 4px; background: #1d7795; color: #fff; font-weight: bold; cursor: pointer; }
		.profile { border-left: 4px solid #1d7795; }
		.profile h2 { margin: 0 0 8px; color: #17324d; }
		.profile p { margin: 4px 0; color: #526579; }
		.stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
		.stat-card { border-top: 4px solid #1d7795; background: rgba(255, 255, 255, .96); }
		.stat-card h2 { margin: 0 0 6px; color: #526579; font-size: 1rem; }
		.stat-number { font-size: 2rem; font-weight: bold; color: #17324d; }
		.table-wrap { overflow-x: auto; }
		table { width: 100%; border-collapse: collapse; }
		th, td { padding: 12px 10px; border-bottom: 1px solid #d9e3e8; text-align: left; white-space: nowrap; }
		th { background: #f3f7fa; color: #17324d; }
		tbody tr:hover { background: #f6fafb; }
		.empty-state { color: #526579; }
		@media (max-width: 650px) { .patient-page { padding: 16px; } .patient-header { min-height: 260px; align-items: flex-start; flex-direction: column; } .patient-header::after { top: auto; bottom: 0; width: 100%; height: 105px; background: linear-gradient(0deg, rgba(9, 48, 66, .9), rgba(9, 48, 66, .1)), url("https://images.unsplash.com/photo-1538108149393-fbbd81895907?auto=format&fit=crop&w=1000&q=85") center / cover; } .patient-header .admin-link { margin-top: auto; } .selector, .selector div, .selector button { width: 100%; } .stats { grid-template-columns: 1fr; } }
	</style>
</head>
<body>
	<main class="patient-page">
		<header class="patient-header">
			<div><h1>Patient Dashboard</h1><p>View your appointments, profile details and billing history.</p></div>
			<a class="admin-link" href="dashboard.php?logout=1">Log out</a>
		</header>

		<?php if ($selectedPatient): ?>
			<section class="card profile">
				<h2><?php echo escapePatientDashboardValue($selectedPatient['name']); ?></h2>
				<p><?php echo escapePatientDashboardValue($selectedPatient['gender']); ?> &middot; DOB: <?php echo escapePatientDashboardValue($selectedPatient['dob']); ?> &middot; Phone: <?php echo escapePatientDashboardValue($selectedPatient['phone']); ?></p>
				<p>Address: <?php echo escapePatientDashboardValue($selectedPatient['address']); ?></p>
			</section>

			<section class="stats" aria-label="Patient summary">
				<div class="card stat-card"><h2>Upcoming appointments</h2><span class="stat-number"><?php echo $stats['upcoming']; ?></span></div>
				<div class="card stat-card"><h2>Completed appointments</h2><span class="stat-number"><?php echo $stats['completed']; ?></span></div>
				<div class="card stat-card"><h2>Total billed</h2><span class="stat-number">&#8377; <?php echo number_format($stats['billed'], 2); ?></span></div>
			</section>

			<section class="card"><h2>Appointments</h2><div class="table-wrap"><table><thead><tr><th>Date</th><th>Doctor</th><th>Specialization</th><th>Status</th></tr></thead><tbody><?php if ($appointments && $appointments->num_rows > 0): while ($appointment = $appointments->fetch_assoc()): ?><tr><td><?php echo escapePatientDashboardValue($appointment['appointment_date']); ?></td><td><?php echo escapePatientDashboardValue($appointment['doctor_name'] ?? 'Unknown doctor'); ?></td><td><?php echo escapePatientDashboardValue($appointment['specialization'] ?? '-'); ?></td><td><?php echo escapePatientDashboardValue($appointment['status']); ?></td></tr><?php endwhile; else: ?><tr><td colspan="4" class="empty-state">No appointments found.</td></tr><?php endif; ?></tbody></table></div></section>

			<section class="card"><h2>Billing history</h2><div class="table-wrap"><table><thead><tr><th>Bill date</th><th>Amount</th></tr></thead><tbody><?php if ($bills && $bills->num_rows > 0): while ($bill = $bills->fetch_assoc()): ?><tr><td><?php echo escapePatientDashboardValue($bill['bill_date']); ?></td><td>&#8377; <?php echo number_format((float) $bill['amount'], 2); ?></td></tr><?php endwhile; else: ?><tr><td colspan="2" class="empty-state">No billing records found.</td></tr><?php endif; ?></tbody></table></div></section>
		<?php elseif ($selectedPatientId > 0): ?>
			<p class="card empty-state">The selected patient was not found.</p>
		<?php else: ?>
			<p class="card empty-state">Select a patient to view the dashboard.</p>
		<?php endif; ?>
	</main>
</body>
</html>