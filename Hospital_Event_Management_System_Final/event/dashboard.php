<?php
require_once __DIR__ . '/../includes/config.php';

$selectedEventId = (int) ($_GET['event_id'] ?? 0);
$events = $conn->query('SELECT id, title, event_date, venue, organizer FROM events ORDER BY event_date DESC, title');
$selectedEvent = null;
$participants = false;
$stats = ['participants' => 0, 'present' => 0, 'absent' => 0];

if ($selectedEventId > 0) {
	$statement = $conn->prepare('SELECT id, title, event_date, venue, organizer FROM events WHERE id = ?');
	$statement->bind_param('i', $selectedEventId);
	$statement->execute();
	$selectedEvent = $statement->get_result()->fetch_assoc();
	$statement->close();

	if ($selectedEvent) {
		$statement = $conn->prepare(
			'SELECT participants.name, participants.phone, COALESCE(attendance.status, \'Pending\') AS attendance_status
			 FROM participants
			 LEFT JOIN attendance ON attendance.participant_id = participants.id AND attendance.event_id = participants.event_id
			 WHERE participants.event_id = ?
			 ORDER BY participants.name'
		);
		$statement->bind_param('i', $selectedEventId);
		$statement->execute();
		$participants = $statement->get_result();
		$statement->close();

		$statements = [
			'participants' => 'SELECT COUNT(*) AS total FROM participants WHERE event_id = ?',
			'present' => "SELECT COUNT(*) AS total FROM attendance WHERE event_id = ? AND status = 'Present'",
			'absent' => "SELECT COUNT(*) AS total FROM attendance WHERE event_id = ? AND status = 'Absent'",
		];

		foreach ($statements as $key => $query) {
			$statement = $conn->prepare($query);
			$statement->bind_param('i', $selectedEventId);
			$statement->execute();
			$stats[$key] = (int) $statement->get_result()->fetch_assoc()['total'];
			$statement->close();
		}
	}
}

function escapeEventDashboardValue($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Event Dashboard | Hospital Event Management</title>
	<link rel="stylesheet" href="../assets/css/style.css">
	<style>
		.event-page { max-width: 1180px; margin: 0 auto; padding: 24px; }
		.event-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
		.event-header h1 { margin: 0 0 5px; color: #17324d; }
		.event-header p { margin: 0; color: #526579; }
		.admin-link { color: #1d7795; font-weight: bold; text-decoration: none; }
		.selector { display: flex; align-items: end; gap: 12px; }
		.selector div { flex: 1; }
		label { display: block; margin-bottom: 6px; font-weight: bold; color: #17324d; }
		select { width: 100%; padding: 11px; border: 1px solid #b7c5d1; border-radius: 4px; font: inherit; }
		.selector button { padding: 11px 20px; border: 0; border-radius: 4px; background: #1d7795; color: #fff; font-weight: bold; cursor: pointer; }
		.event-details { border-left: 4px solid #1d7795; }
		.event-details h2 { margin: 0 0 8px; color: #17324d; }
		.event-details p { margin: 0; color: #526579; }
		.stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
		.stat-card { border-top: 4px solid #1d7795; }
		.stat-card h2 { margin: 0 0 6px; color: #526579; font-size: 1rem; }
		.stat-number { font-size: 2rem; font-weight: bold; color: #17324d; }
		.table-wrap { overflow-x: auto; }
		table { width: 100%; border-collapse: collapse; }
		th, td { padding: 12px 10px; border-bottom: 1px solid #d9e3e8; text-align: left; white-space: nowrap; }
		th { background: #f3f7fa; color: #17324d; }
		tbody tr:hover { background: #f6fafb; }
		.empty-state { color: #526579; }
		@media (max-width: 650px) { .event-page { padding: 16px; } .event-header, .selector { align-items: flex-start; flex-direction: column; } .selector, .selector div, .selector button { width: 100%; } .stats { grid-template-columns: 1fr; } }
	</style>
</head>
<body>
	<main class="event-page">
		<header class="event-header">
			<div>
				<h1>Event Dashboard</h1>
				<p>Review event details, participants and attendance.</p>
			</div>
			<a class="admin-link" href="../admin/login.php">Admin area</a>
		</header>

		<section class="card">
			<form method="get" class="selector">
				<div>
					<label for="event_id">Select event</label>
					<select id="event_id" name="event_id" required>
						<option value="">Choose an event</option>
						<?php if ($events): while ($event = $events->fetch_assoc()): ?>
							<option value="<?php echo (int) $event['id']; ?>" <?php echo $selectedEventId === (int) $event['id'] ? 'selected' : ''; ?>><?php echo escapeEventDashboardValue($event['title'] . ' - ' . $event['event_date']); ?></option>
						<?php endwhile; endif; ?>
					</select>
				</div>
				<button type="submit">View dashboard</button>
			</form>
		</section>

		<?php if ($selectedEvent): ?>
			<section class="card event-details">
				<h2><?php echo escapeEventDashboardValue($selectedEvent['title']); ?></h2>
				<p><?php echo escapeEventDashboardValue($selectedEvent['event_date']); ?> &middot; <?php echo escapeEventDashboardValue($selectedEvent['venue']); ?> &middot; Organized by <?php echo escapeEventDashboardValue($selectedEvent['organizer']); ?></p>
			</section>

			<section class="stats" aria-label="Event summary">
				<div class="card stat-card"><h2>Total participants</h2><span class="stat-number"><?php echo $stats['participants']; ?></span></div>
				<div class="card stat-card"><h2>Present</h2><span class="stat-number"><?php echo $stats['present']; ?></span></div>
				<div class="card stat-card"><h2>Absent</h2><span class="stat-number"><?php echo $stats['absent']; ?></span></div>
			</section>

			<section class="card">
				<h2>Participant attendance</h2>
				<div class="table-wrap">
					<table>
						<thead><tr><th>Participant</th><th>Phone</th><th>Attendance</th></tr></thead>
						<tbody>
							<?php if ($participants && $participants->num_rows > 0): while ($participant = $participants->fetch_assoc()): ?>
								<tr>
									<td><?php echo escapeEventDashboardValue($participant['name']); ?></td>
									<td><?php echo escapeEventDashboardValue($participant['phone']); ?></td>
									<td><?php echo escapeEventDashboardValue($participant['attendance_status']); ?></td>
								</tr>
							<?php endwhile; else: ?><tr><td colspan="3" class="empty-state">No participants registered for this event.</td></tr><?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>
		<?php elseif ($selectedEventId > 0): ?>
			<p class="card empty-state">The selected event was not found.</p>
		<?php else: ?>
			<p class="card empty-state">Select an event to view the dashboard.</p>
		<?php endif; ?>
	</main>
</body>
</html>