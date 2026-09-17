<?php
require_once __DIR__ . '/../includes/config.php';

$selectedDoctorId = (int) ($_GET['doctor_id'] ?? 0);
$doctors = $conn->query('SELECT id, name, specialization FROM doctors ORDER BY name');
$selectedDoctor = null;
$appointments = false;
$stats = ['today' => 0, 'upcoming' => 0, 'completed' => 0];

if ($selectedDoctorId > 0) {
	$statement = $conn->prepare('SELECT id, name, specialization, phone FROM doctors WHERE id = ?');
	$statement->bind_param('i', $selectedDoctorId);
	$statement->execute();
	$selectedDoctor = $statement->get_result()->fetch_assoc();
	$statement->close();

	if ($selectedDoctor) {
		$statement = $conn->prepare(
			'SELECT appointments.id, appointments.appointment_date, appointments.status,
					patients.name AS patient_name, patients.phone, patients.gender
			 FROM appointments
			 LEFT JOIN patients ON patients.id = appointments.patient_id
			 WHERE appointments.doctor_id = ?
			 ORDER BY appointments.appointment_date ASC, appointments.id ASC'
		);
		$statement->bind_param('i', $selectedDoctorId);
		$statement->execute();
		$appointments = $statement->get_result();
		$statement->close();

		$statements = [
			'today' => 'SELECT COUNT(*) AS total FROM appointments WHERE doctor_id = ? AND appointment_date = CURDATE()',
			'upcoming' => 'SELECT COUNT(*) AS total FROM appointments WHERE doctor_id = ? AND appointment_date >= CURDATE() AND status IN (\'Scheduled\', \'Confirmed\')',
			'completed' => 'SELECT COUNT(*) AS total FROM appointments WHERE doctor_id = ? AND status = \'Completed\'',
		];

		foreach ($statements as $key => $query) {
			$statement = $conn->prepare($query);
			$statement->bind_param('i', $selectedDoctorId);
			$statement->execute();
			$stats[$key] = (int) $statement->get_result()->fetch_assoc()['total'];
			$statement->close();
		}
	}
}

function escapeDoctorDashboardValue($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Doctor Dashboard | Hospital Event Management</title>
	<link rel="stylesheet" href="../assets/css/style.css">
	<style>
		body:has(.doctor-page) { background: linear-gradient(135deg, #eaf3f5 0%, #f8fbfc 55%, #dcebed 100%); }
		.doctor-page { max-width: 1180px; margin: 0 auto; padding: 30px 24px; }
		.doctor-header { position: relative; display: flex; min-height: 230px; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 22px; padding: 30px; overflow: hidden; border-radius: 12px; background: linear-gradient(120deg, #093042 0%, #1d7795 58%, #8cc3ca 100%); color: #fff; box-shadow: 0 14px 30px rgba(9, 48, 66, .2); }
		.doctor-header > div:first-child, .doctor-header > .admin-link { position: relative; z-index: 1; }
		.doctor-header::after { position: absolute; top: 0; right: 0; width: 43%; height: 100%; background: linear-gradient(90deg, rgba(9, 48, 66, .95), rgba(9, 48, 66, .08)), url("https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?auto=format&fit=crop&w=1000&q=85") center / cover; content: ""; }
		.doctor-header h1 { margin: 0 0 5px; color: #fff; }
		.doctor-header p { margin: 0; color: #d8edf2; }
		.admin-link { padding: 10px 14px; border: 1px solid rgba(255, 255, 255, .5); border-radius: 4px; color: #fff; font-weight: bold; text-decoration: none; }
		.admin-link:hover { background: rgba(255, 255, 255, .12); }
		.doctor-page > .card { border-color: rgba(255, 255, 255, .65); background: rgba(255, 255, 255, .96); }
		.selector { display: flex; align-items: end; gap: 12px; }
		.selector div { flex: 1; }
		label { display: block; margin-bottom: 6px; font-weight: bold; color: #17324d; }
		select { width: 100%; padding: 11px; border: 1px solid #b7c5d1; border-radius: 4px; font: inherit; }
		.selector button { padding: 11px 20px; border: 0; border-radius: 4px; background: #1d7795; color: #fff; font-weight: bold; cursor: pointer; }
		.selector button:hover { background: #155b72; }
		.doctor-profile { display: flex; align-items: center; gap: 16px; border-left: 5px solid #1d7795; }
		.doctor-avatar { display: block; width: 78px; height: 78px; flex: 0 0 78px; border: 4px solid #d9eef2; border-radius: 50%; object-fit: cover; box-shadow: 0 4px 12px rgba(23, 50, 77, .14); }
		.doctor-profile h2 { margin: 0 0 4px; color: #17324d; }
		.doctor-profile p { margin: 0; color: #526579; }
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
		@media (max-width: 650px) { .doctor-page { padding: 16px; } .doctor-header { min-height: 260px; align-items: flex-start; flex-direction: column; } .doctor-header::after { top: auto; bottom: 0; width: 100%; height: 105px; background: linear-gradient(0deg, rgba(9, 48, 66, .9), rgba(9, 48, 66, .1)), url("https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?auto=format&fit=crop&w=1000&q=85") center 35% / cover; } .doctor-header .admin-link { margin-top: auto; } .selector, .selector div, .selector button { width: 100%; } .stats { grid-template-columns: 1fr; } }
	</style>
</head>
<body>
	<main class="doctor-page">
		<header class="doctor-header">
			<div>
				<h1>Doctor Dashboard</h1>
				<p>View appointments and patient information.</p>
			</div>
			<a class="admin-link" href="../admin/login.php">Admin area</a>
		</header>

		<section class="card">
			<form method="get" class="selector">
				<div>
					<label for="doctor_id">Select doctor</label>
					<select id="doctor_id" name="doctor_id" required>
						<option value="">Choose a doctor</option>
						<?php if ($doctors): while ($doctor = $doctors->fetch_assoc()): ?>
							<option value="<?php echo (int) $doctor['id']; ?>" <?php echo $selectedDoctorId === (int) $doctor['id'] ? 'selected' : ''; ?>><?php echo escapeDoctorDashboardValue($doctor['name'] . ' - ' . $doctor['specialization']); ?></option>
						<?php endwhile; endif; ?>
					</select>
				</div>
				<button type="submit">View dashboard</button>
			</form>
		</section>

		<?php if ($selectedDoctor): ?>
			<section class="card doctor-profile">
				<img class="doctor-avatar" src="https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?auto=format&fit=crop&w=240&q=85" alt="Doctor profile photo">
				<div><h2><?php echo escapeDoctorDashboardValue($selectedDoctor['name']); ?></h2>
				<p><?php echo escapeDoctorDashboardValue($selectedDoctor['specialization']); ?> &middot; <?php echo escapeDoctorDashboardValue($selectedDoctor['phone']); ?></p>
				</div>
			</section>

			<section class="stats" aria-label="Appointment summary">
				<div class="card stat-card"><h2>Today's appointments</h2><span class="stat-number"><?php echo $stats['today']; ?></span></div>
				<div class="card stat-card"><h2>Upcoming appointments</h2><span class="stat-number"><?php echo $stats['upcoming']; ?></span></div>
				<div class="card stat-card"><h2>Completed appointments</h2><span class="stat-number"><?php echo $stats['completed']; ?></span></div>
			</section>

			<section class="card">
				<h2>Appointment list</h2>
				<div class="table-wrap">
					<table>
						<thead><tr><th>Patient</th><th>Gender</th><th>Phone</th><th>Date</th><th>Status</th></tr></thead>
						<tbody>
							<?php if ($appointments && $appointments->num_rows > 0): while ($appointment = $appointments->fetch_assoc()): ?>
								<tr>
									<td><?php echo escapeDoctorDashboardValue($appointment['patient_name'] ?? 'Unknown patient'); ?></td>
									<td><?php echo escapeDoctorDashboardValue($appointment['gender'] ?? '-'); ?></td>
									<td><?php echo escapeDoctorDashboardValue($appointment['phone'] ?? '-'); ?></td>
									<td><?php echo escapeDoctorDashboardValue($appointment['appointment_date']); ?></td>
									<td><?php echo escapeDoctorDashboardValue($appointment['status']); ?></td>
								</tr>
							<?php endwhile; else: ?><tr><td colspan="5" class="empty-state">No appointments found for this doctor.</td></tr><?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>
		<?php elseif ($selectedDoctorId > 0): ?>
			<p class="card empty-state">The selected doctor was not found.</p>
		<?php else: ?>
			<p class="card empty-state">Select a doctor to view the dashboard.</p>
		<?php endif; ?>
	</main>
</body>
</html>