<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
	header('Location: login.php');
	exit;
}

require_once __DIR__ . '/../includes/config.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? '';
	$participantId = (int) ($_POST['participant_id'] ?? 0);
	$eventId = (int) ($_POST['event_id'] ?? 0);
	$name = trim($_POST['name'] ?? '');
	$phone = trim($_POST['phone'] ?? '');

	if (($action === 'add' || $action === 'update') && ($eventId < 1 || $name === '' || $phone === '')) {
		$error = 'Please complete all participant fields.';
	} elseif ($action === 'add') {
		$statement = $conn->prepare('INSERT INTO participants (event_id, name, phone) VALUES (?, ?, ?)');
		$statement->bind_param('iss', $eventId, $name, $phone);
		$statement->execute();
		$statement->close();
		$message = 'Participant added successfully.';
	} elseif ($action === 'update' && $participantId > 0) {
		$statement = $conn->prepare('UPDATE participants SET event_id = ?, name = ?, phone = ? WHERE id = ?');
		$statement->bind_param('issi', $eventId, $name, $phone, $participantId);
		$statement->execute();
		$statement->close();
		$message = 'Participant updated successfully.';
	} elseif ($action === 'delete' && $participantId > 0) {
		$statement = $conn->prepare('DELETE FROM participants WHERE id = ?');
		$statement->bind_param('i', $participantId);
		$statement->execute();
		$statement->close();
		$message = 'Participant deleted.';
	}
}

$events = $conn->query('SELECT id, title, event_date FROM events ORDER BY event_date DESC, title');
$participants = $conn->query(
	'SELECT participants.id, participants.event_id, participants.name, participants.phone,
			events.title AS event_title, events.event_date
	 FROM participants
	 LEFT JOIN events ON events.id = participants.event_id
	 ORDER BY events.event_date DESC, participants.name'
);
$countResult = $conn->query('SELECT COUNT(*) AS total FROM participants');
$participantCount = $countResult ? (int) $countResult->fetch_assoc()['total'] : 0;

function escapeParticipantValue($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Participants | Hospital Event Management</title>
	<link rel="stylesheet" href="../assets/css/style.css">
	<style>
		.page { max-width: 1180px; margin: 0 auto; padding: 24px; }
		.page-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
		.page-header h1 { margin: 0; color: #17324d; }
		.back-link { color: #1d7795; font-weight: bold; text-decoration: none; }
		.summary-card { border-top: 4px solid #1d7795; max-width: 250px; }
		.summary-card h2 { margin: 0 0 8px; font-size: 1rem; color: #526579; }
		.summary-count { font-size: 1.8rem; font-weight: bold; color: #17324d; }
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
		.inline-form { display: inline-flex; gap: 6px; align-items: center; }
		.inline-form input, .inline-form select, .inline-form button { width: auto; min-width: 105px; padding: 7px; }
		.inline-form select { min-width: 145px; }
		.delete-button { background: #b42318; }
		.delete-button:hover { background: #8f1c14; }
		@media (max-width: 800px) { .form-grid { grid-template-columns: 1fr 1fr; } }
		@media (max-width: 600px) { .page { padding: 16px; } .page-header { align-items: flex-start; flex-direction: column; } .form-grid { grid-template-columns: 1fr; } }
	</style>
</head>
<body>
	<main class="page">
		<header class="page-header">
			<h1>Participants Management</h1>
			<a class="back-link" href="dashboard.php">Back to dashboard</a>
		</header>

		<?php if ($message !== ''): ?><p class="notice" role="status"><?php echo escapeParticipantValue($message); ?></p><?php endif; ?>
		<?php if ($error !== ''): ?><p class="error" role="alert"><?php echo escapeParticipantValue($error); ?></p><?php endif; ?>

		<div class="card summary-card"><h2>Total participants</h2><span class="summary-count"><?php echo $participantCount; ?></span></div>

		<section class="card">
			<h2>Add participant</h2>
			<?php if ($events && $events->num_rows > 0): ?>
				<form method="post" class="form-grid">
					<input type="hidden" name="action" value="add">
					<div><label for="event_id">Event</label><select id="event_id" name="event_id" required><option value="">Select event</option><?php while ($event = $events->fetch_assoc()): ?><option value="<?php echo (int) $event['id']; ?>"><?php echo escapeParticipantValue($event['title'] . ' - ' . $event['event_date']); ?></option><?php endwhile; ?></select></div>
					<div><label for="name">Name</label><input id="name" name="name" type="text" placeholder="Participant name" required></div>
					<div><label for="phone">Phone</label><input id="phone" name="phone" type="tel" placeholder="Phone number" required></div>
					<button type="submit">Add participant</button>
				</form>
			<?php else: ?><p>Add an event before registering participants.</p><?php endif; ?>
		</section>

		<section class="card">
			<h2>Participant list</h2>
			<div class="table-wrap">
				<table>
					<thead><tr><th>Event</th><th>Name</th><th>Phone</th><th>Actions</th></tr></thead>
					<tbody>
						<?php if ($participants && $participants->num_rows > 0): ?>
							<?php while ($participant = $participants->fetch_assoc()): $formId = 'update-participant-' . (int) $participant['id']; $eventOptions = $conn->query('SELECT id, title FROM events ORDER BY title'); ?>
								<tr>
									<td><select form="<?php echo $formId; ?>" name="event_id" aria-label="Event" required><?php while ($event = $eventOptions->fetch_assoc()): ?><option value="<?php echo (int) $event['id']; ?>" <?php echo (int) $event['id'] === (int) $participant['event_id'] ? 'selected' : ''; ?>><?php echo escapeParticipantValue($event['title']); ?></option><?php endwhile; ?></select></td>
									<td><input form="<?php echo $formId; ?>" name="name" type="text" value="<?php echo escapeParticipantValue($participant['name']); ?>" aria-label="Participant name" required></td>
									<td><input form="<?php echo $formId; ?>" name="phone" type="tel" value="<?php echo escapeParticipantValue($participant['phone']); ?>" aria-label="Phone" required></td>
									<td>
										<form id="<?php echo $formId; ?>" method="post" class="inline-form"><input type="hidden" name="action" value="update"><input type="hidden" name="participant_id" value="<?php echo (int) $participant['id']; ?>"><button type="submit">Save</button></form>
										<form method="post" class="inline-form" onsubmit="return confirm('Delete this participant?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="participant_id" value="<?php echo (int) $participant['id']; ?>"><button class="delete-button" type="submit">Delete</button></form>
									</td>
								</tr>
							<?php endwhile; ?>
						<?php else: ?><tr><td colspan="4">No participants found.</td></tr><?php endif; ?>
					</tbody>
				</table>
			</div>
		</section>
	</main>
</body>
</html>