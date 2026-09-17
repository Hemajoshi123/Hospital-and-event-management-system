<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
	header('Location: login.php');
	exit;
}

require_once __DIR__ . '/../includes/config.php';

$message = '';
$error = '';
$statusOptions = ['Present', 'Absent', 'Pending'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? '';
	$attendanceId = (int) ($_POST['attendance_id'] ?? 0);

	if ($action === 'add') {
		$participantId = (int) ($_POST['participant_id'] ?? 0);
		$eventId = (int) ($_POST['event_id'] ?? 0);
		$status = $_POST['status'] ?? 'Pending';

		if ($participantId < 1 || $eventId < 1 || !in_array($status, $statusOptions, true)) {
			$error = 'Please complete all attendance fields.';
		} else {
			$statement = $conn->prepare('INSERT INTO attendance (participant_id, event_id, status) VALUES (?, ?, ?)');
			$statement->bind_param('iis', $participantId, $eventId, $status);
			$statement->execute();
			$statement->close();
			$message = 'Attendance record added successfully.';
		}
	} elseif ($action === 'status' && $attendanceId > 0) {
		$status = $_POST['status'] ?? '';

		if (!in_array($status, $statusOptions, true)) {
			$error = 'Invalid attendance status.';
		} else {
			$statement = $conn->prepare('UPDATE attendance SET status = ? WHERE id = ?');
			$statement->bind_param('si', $status, $attendanceId);
			$statement->execute();
			$statement->close();
			$message = 'Attendance status updated.';
		}
	} elseif ($action === 'delete' && $attendanceId > 0) {
		$statement = $conn->prepare('DELETE FROM attendance WHERE id = ?');
		$statement->bind_param('i', $attendanceId);
		$statement->execute();
		$statement->close();
		$message = 'Attendance record deleted.';
	}
}

$events = $conn->query('SELECT id, title, event_date FROM events ORDER BY event_date DESC, title');
$participants = $conn->query(
	'SELECT participants.id, participants.name, participants.event_id, events.title AS event_title
	 FROM participants
	 LEFT JOIN events ON events.id = participants.event_id
	 ORDER BY participants.name'
);
$attendance = $conn->query(
	'SELECT attendance.id, attendance.status, participants.name AS participant_name,
			events.title AS event_title, events.event_date
	 FROM attendance
	 LEFT JOIN participants ON participants.id = attendance.participant_id
	 LEFT JOIN events ON events.id = attendance.event_id
	 ORDER BY events.event_date DESC, participants.name'
);

function escapeAttendanceValue($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Attendance | Hospital Event Management</title>
	<link rel="stylesheet" href="../assets/css/style.css">
	<style>
		.page { max-width: 1180px; margin: 0 auto; padding: 24px; }
		.page-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
		.page-header h1 { margin: 0; color: #17324d; }
		.back-link { color: #1d7795; font-weight: bold; text-decoration: none; }
		.form-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; align-items: end; }
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
		@media (max-width: 700px) { .page { padding: 16px; } .page-header { align-items: flex-start; flex-direction: column; } .form-grid { grid-template-columns: 1fr; } }
	</style>
</head>
<body>
	<main class="page">
		<header class="page-header">
			<h1>Attendance Management</h1>
			<a class="back-link" href="dashboard.php">Back to dashboard</a>
		</header>

		<?php if ($message !== ''): ?><p class="notice" role="status"><?php echo escapeAttendanceValue($message); ?></p><?php endif; ?>
		<?php if ($error !== ''): ?><p class="error" role="alert"><?php echo escapeAttendanceValue($error); ?></p><?php endif; ?>

		<section class="card">
			<h2>Record attendance</h2>
			<?php if ($events && $events->num_rows > 0 && $participants && $participants->num_rows > 0): ?>
				<form method="post" class="form-grid">
					<input type="hidden" name="action" value="add">
					<div>
						<label for="event_id">Event</label>
						<select id="event_id" name="event_id" required>
							<option value="">Select event</option>
							<?php while ($event = $events->fetch_assoc()): ?>
								<option value="<?php echo (int) $event['id']; ?>"><?php echo escapeAttendanceValue($event['title'] . ' - ' . $event['event_date']); ?></option>
							<?php endwhile; ?>
						</select>
					</div>
					<div>
						<label for="participant_id">Participant</label>
						<select id="participant_id" name="participant_id" required>
							<option value="">Select participant</option>
							<?php while ($participant = $participants->fetch_assoc()): ?>
								<option value="<?php echo (int) $participant['id']; ?>"><?php echo escapeAttendanceValue($participant['name'] . ' - ' . ($participant['event_title'] ?? 'Unknown event')); ?></option>
							<?php endwhile; ?>
						</select>
					</div>
					<div>
						<label for="status">Status</label>
						<select id="status" name="status">
							<?php foreach ($statusOptions as $status): ?><option value="<?php echo escapeAttendanceValue($status); ?>"><?php echo escapeAttendanceValue($status); ?></option><?php endforeach; ?>
						</select>
					</div>
					<button type="submit">Save attendance</button>
				</form>
			<?php else: ?>
				<p>Add at least one event and one participant before recording attendance.</p>
			<?php endif; ?>
		</section>

		<section class="card">
			<h2>Attendance records</h2>
			<div class="table-wrap">
				<table>
					<thead><tr><th>Participant</th><th>Event</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
					<tbody>
						<?php if ($attendance && $attendance->num_rows > 0): ?>
							<?php while ($record = $attendance->fetch_assoc()): ?>
								<tr>
									<td><?php echo escapeAttendanceValue($record['participant_name'] ?? 'Unknown participant'); ?></td>
									<td><?php echo escapeAttendanceValue($record['event_title'] ?? 'Unknown event'); ?></td>
									<td><?php echo escapeAttendanceValue($record['event_date'] ?? ''); ?></td>
									<td>
										<form method="post" class="inline-form">
											<input type="hidden" name="action" value="status">
											<input type="hidden" name="attendance_id" value="<?php echo (int) $record['id']; ?>">
											<select name="status" aria-label="Attendance status">
												<?php foreach ($statusOptions as $status): ?>
													<option value="<?php echo escapeAttendanceValue($status); ?>" <?php echo $status === $record['status'] ? 'selected' : ''; ?>><?php echo escapeAttendanceValue($status); ?></option>
												<?php endforeach; ?>
											</select>
											<button type="submit">Save</button>
										</form>
									</td>
									<td>
										<form method="post" onsubmit="return confirm('Delete this attendance record?');">
											<input type="hidden" name="action" value="delete">
											<input type="hidden" name="attendance_id" value="<?php echo (int) $record['id']; ?>">
											<button class="delete-button" type="submit">Delete</button>
										</form>
									</td>
								</tr>
							<?php endwhile; ?>
						<?php else: ?>
							<tr><td colspan="5">No attendance records found.</td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</section>
	</main>
</body>
</html>