<?php
require_once __DIR__ . '/../includes/config.php';

$selectedStaffId = (int) ($_GET['staff_id'] ?? 0);
$staffMembers = $conn->query('SELECT id, name, role, phone FROM staff ORDER BY name');
$selectedStaff = null;

if ($selectedStaffId > 0) {
	$statement = $conn->prepare('SELECT id, name, role, phone FROM staff WHERE id = ?');
	$statement->bind_param('i', $selectedStaffId);
	$statement->execute();
	$selectedStaff = $statement->get_result()->fetch_assoc();
	$statement->close();
}

$stats = [];
$statements = [
	'patients' => 'SELECT COUNT(*) AS total FROM patients',
	'doctors' => 'SELECT COUNT(*) AS total FROM doctors',
	'appointments' => 'SELECT COUNT(*) AS total FROM appointments WHERE appointment_date >= CURDATE() AND status IN (\'Scheduled\', \'Confirmed\')',
	'events' => 'SELECT COUNT(*) AS total FROM events WHERE event_date >= CURDATE()',
	'available_beds' => "SELECT COUNT(*) AS total FROM beds WHERE status = 'Available'",
];
foreach ($statements as $key => $query) {
	$result = $conn->query($query);
	$stats[$key] = $result ? (int) $result->fetch_assoc()['total'] : 0;
}

$recentAppointments = $conn->query(
	'SELECT appointments.appointment_date, appointments.status,
			patients.name AS patient_name, doctors.name AS doctor_name
	 FROM appointments
	 LEFT JOIN patients ON patients.id = appointments.patient_id
	 LEFT JOIN doctors ON doctors.id = appointments.doctor_id
	 ORDER BY appointments.appointment_date DESC, appointments.id DESC LIMIT 8'
);
$upcomingEvents = $conn->query('SELECT title, event_date, venue, organizer FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 8');

function escapeStaffDashboardValue($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Staff Dashboard | Hospital Event Management</title>
	<link rel="stylesheet" href="../assets/css/style.css">
	<style>
		body:has(.staff-page) { background: linear-gradient(135deg, #eaf3f5 0%, #f8fbfc 55%, #dcebed 100%); }
		.staff-page { max-width: 1180px; margin: 0 auto; padding: 30px 24px; }
		.staff-header { position: relative; display: flex; min-height: 230px; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 22px; padding: 30px; overflow: hidden; border-radius: 12px; background: linear-gradient(120deg, #093042 0%, #1d7795 58%, #8cc3ca 100%); color: #fff; box-shadow: 0 14px 30px rgba(9, 48, 66, .2); }
		.staff-header > div:first-child, .staff-header > .admin-link { position: relative; z-index: 1; }
		.staff-header::after { position: absolute; top: 0; right: 0; width: 43%; height: 100%; background: linear-gradient(90deg, rgba(9, 48, 66, .95), rgba(9, 48, 66, .08)), url("https://images.unsplash.com/photo-1582750433449-648ed127bb54?auto=format&fit=crop&w=1000&q=85") center / cover; content: ""; }
		.staff-header h1 { margin: 0 0 5px; color: #fff; }
		.staff-header p { margin: 0; color: #d8edf2; }
		.admin-link { padding: 10px 14px; border: 1px solid rgba(255, 255, 255, .5); border-radius: 4px; color: #fff; font-weight: bold; text-decoration: none; }
		.admin-link:hover { background: rgba(255, 255, 255, .12); }
		.staff-page > .card { background: rgba(255, 255, 255, .96); }
		.selector { display: flex; align-items: end; gap: 12px; }
		.selector div { flex: 1; }
		label { display: block; margin-bottom: 6px; font-weight: bold; color: #17324d; }
		select { width: 100%; padding: 11px; border: 1px solid #b7c5d1; border-radius: 4px; font: inherit; }
		.selector button { padding: 11px 20px; border: 0; border-radius: 4px; background: #1d7795; color: #fff; font-weight: bold; cursor: pointer; }
		.profile { border-left: 4px solid #1d7795; }
		.profile h2 { margin: 0 0 8px; color: #17324d; }
		.profile p { margin: 4px 0; color: #526579; }
		.stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; }
		.stat-card { border-top: 4px solid #1d7795; background: rgba(255, 255, 255, .96); }
		.stat-card h2 { margin: 0 0 6px; color: #526579; font-size: .92rem; }
		.stat-number { font-size: 1.8rem; font-weight: bold; color: #17324d; }
		.activity-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
		.table-wrap { overflow-x: auto; }
		table { width: 100%; border-collapse: collapse; }
		th, td { padding: 11px 9px; border-bottom: 1px solid #d9e3e8; text-align: left; white-space: nowrap; }
		th { background: #f3f7fa; color: #17324d; }
		tbody tr:hover { background: #f6fafb; }
		.empty-state { color: #526579; }
		@media (max-width: 900px) { .stats { grid-template-columns: repeat(3, 1fr); } .activity-grid { grid-template-columns: 1fr; } }
		@media (max-width: 650px) { .staff-page { padding: 16px; } .staff-header { min-height: 260px; align-items: flex-start; flex-direction: column; } .staff-header::after { top: auto; bottom: 0; width: 100%; height: 105px; background: linear-gradient(0deg, rgba(9, 48, 66, .9), rgba(9, 48, 66, .1)), url("https://images.unsplash.com/photo-1582750433449-648ed127bb54?auto=format&fit=crop&w=1000&q=85") center / cover; } .staff-header .admin-link { margin-top: auto; } .selector, .selector div, .selector button { width: 100%; } .stats { grid-template-columns: 1fr 1fr; } }
	</style>
</head>
<body>
	<main class="staff-page">
		<header class="staff-header">
			<div><h1>Staff Dashboard</h1><p>Monitor daily hospital operations and activity.</p></div>
			<a class="admin-link" href="../admin/login.php">Admin area</a>
		</header>

		<section class="card">
			<form method="get" class="selector">
				<div><label for="staff_id">Select staff member</label><select id="staff_id" name="staff_id"><option value="">View as staff member</option><?php if ($staffMembers): while ($staff = $staffMembers->fetch_assoc()): ?><option value="<?php echo (int) $staff['id']; ?>" <?php echo $selectedStaffId === (int) $staff['id'] ? 'selected' : ''; ?>><?php echo escapeStaffDashboardValue($staff['name'] . ' - ' . $staff['role']); ?></option><?php endwhile; endif; ?></select></div>
				<button type="submit">Load dashboard</button>
			</form>
		</section>

		<?php if ($selectedStaff): ?><section class="card profile"><h2><?php echo escapeStaffDashboardValue($selectedStaff['name']); ?></h2><p><?php echo escapeStaffDashboardValue($selectedStaff['role']); ?> &middot; <?php echo escapeStaffDashboardValue($selectedStaff['phone']); ?></p></section><?php endif; ?>

		<section class="stats" aria-label="Hospital summary">
			<div class="card stat-card"><h2>Patients</h2><span class="stat-number"><?php echo $stats['patients']; ?></span></div>
			<div class="card stat-card"><h2>Doctors</h2><span class="stat-number"><?php echo $stats['doctors']; ?></span></div>
			<div class="card stat-card"><h2>Upcoming appointments</h2><span class="stat-number"><?php echo $stats['appointments']; ?></span></div>
			<div class="card stat-card"><h2>Upcoming events</h2><span class="stat-number"><?php echo $stats['events']; ?></span></div>
			<div class="card stat-card"><h2>Available beds</h2><span class="stat-number"><?php echo $stats['available_beds']; ?></span></div>
		</section>

		<section class="activity-grid">
			<div class="card"><h2>Recent appointments</h2><div class="table-wrap"><table><thead><tr><th>Patient</th><th>Doctor</th><th>Date</th><th>Status</th></tr></thead><tbody><?php if ($recentAppointments && $recentAppointments->num_rows > 0): while ($appointment = $recentAppointments->fetch_assoc()): ?><tr><td><?php echo escapeStaffDashboardValue($appointment['patient_name'] ?? 'Unknown'); ?></td><td><?php echo escapeStaffDashboardValue($appointment['doctor_name'] ?? 'Unknown'); ?></td><td><?php echo escapeStaffDashboardValue($appointment['appointment_date']); ?></td><td><?php echo escapeStaffDashboardValue($appointment['status']); ?></td></tr><?php endwhile; else: ?><tr><td colspan="4" class="empty-state">No appointments found.</td></tr><?php endif; ?></tbody></table></div></div>
			<div class="card"><h2>Upcoming events</h2><div class="table-wrap"><table><thead><tr><th>Event</th><th>Date</th><th>Venue</th></tr></thead><tbody><?php if ($upcomingEvents && $upcomingEvents->num_rows > 0): while ($event = $upcomingEvents->fetch_assoc()): ?><tr><td><?php echo escapeStaffDashboardValue($event['title']); ?></td><td><?php echo escapeStaffDashboardValue($event['event_date']); ?></td><td><?php echo escapeStaffDashboardValue($event['venue']); ?></td></tr><?php endwhile; else: ?><tr><td colspan="3" class="empty-state">No upcoming events found.</td></tr><?php endif; ?></tbody></table></div></div>
		</section>
	</main>
</body>
</html>