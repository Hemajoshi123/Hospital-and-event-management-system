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
	$eventId = (int) ($_POST['event_id'] ?? 0);
	$title = trim($_POST['title'] ?? '');
	$eventDate = $_POST['event_date'] ?? '';
	$venue = trim($_POST['venue'] ?? '');
	$organizer = trim($_POST['organizer'] ?? '');

	if (($action === 'add' || $action === 'update') && ($title === '' || $eventDate === '' || $venue === '' || $organizer === '')) {
		$error = 'Please complete all event fields.';
	} elseif ($action === 'add') {
		$statement = $conn->prepare('INSERT INTO events (title, event_date, venue, organizer) VALUES (?, ?, ?, ?)');
		$statement->bind_param('ssss', $title, $eventDate, $venue, $organizer);
		$statement->execute();
		$statement->close();
		$message = 'Event added successfully.';
	} elseif ($action === 'update' && $eventId > 0) {
		$statement = $conn->prepare('UPDATE events SET title = ?, event_date = ?, venue = ?, organizer = ? WHERE id = ?');
		$statement->bind_param('ssssi', $title, $eventDate, $venue, $organizer, $eventId);
		$statement->execute();
		$statement->close();
		$message = 'Event updated successfully.';
	} elseif ($action === 'delete' && $eventId > 0) {
		$statement = $conn->prepare('DELETE FROM events WHERE id = ?');
		$statement->bind_param('i', $eventId);
		$statement->execute();
		$statement->close();
		$message = 'Event deleted.';
	}
}

$events = $conn->query('SELECT id, title, event_date, venue, organizer FROM events ORDER BY event_date DESC, title');
$countResult = $conn->query('SELECT COUNT(*) AS total FROM events');
$eventCount = $countResult ? (int) $countResult->fetch_assoc()['total'] : 0;

function escapeEventValue($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Events | Hospital Event Management</title>
	<link rel="stylesheet" href="../assets/css/style.css">
	<style>
		.page { max-width: 1180px; margin: 0 auto; padding: 24px; }
		.page-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
		.page-header h1 { margin: 0; color: #17324d; }
		.back-link { color: #1d7795; font-weight: bold; text-decoration: none; }
		.summary-card { border-top: 4px solid #1d7795; max-width: 250px; }
		.summary-card h2 { margin: 0 0 8px; font-size: 1rem; color: #526579; }
		.summary-count { font-size: 1.8rem; font-weight: bold; color: #17324d; }
		.form-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; align-items: end; }
		label { display: block; margin-bottom: 6px; font-weight: bold; color: #17324d; }
		input, button { box-sizing: border-box; width: 100%; padding: 10px; border: 1px solid #b7c5d1; border-radius: 4px; font: inherit; }
		button { border: 0; background: #1d7795; color: white; cursor: pointer; font-weight: bold; }
		button:hover { background: #155b72; }
		.notice { padding: 10px 12px; border-radius: 4px; background: #e7f5ed; color: #17643a; }
		.error { padding: 10px 12px; border-radius: 4px; background: #fdecec; color: #a32222; }
		.table-wrap { overflow-x: auto; }
		table { width: 100%; border-collapse: collapse; }
		th, td { padding: 12px 10px; border-bottom: 1px solid #d9e1e8; text-align: left; white-space: nowrap; }
		th { background: #f3f7fa; color: #17324d; }
		.inline-form { display: inline-flex; gap: 6px; align-items: center; }
		.inline-form input, .inline-form button { width: auto; min-width: 105px; padding: 7px; }
		.inline-form input[type="date"] { min-width: 135px; }
		.delete-button { background: #b42318; }
		.delete-button:hover { background: #8f1c14; }
		@media (max-width: 850px) { .form-grid { grid-template-columns: 1fr 1fr; } }
		@media (max-width: 600px) { .page { padding: 16px; } .page-header { align-items: flex-start; flex-direction: column; } .form-grid { grid-template-columns: 1fr; } }
	</style>
</head>
<body>
	<main class="page">
		<header class="page-header">
			<h1>Events Management</h1>
			<a class="back-link" href="dashboard.php">Back to dashboard</a>
		</header>

		<?php if ($message !== ''): ?><p class="notice" role="status"><?php echo escapeEventValue($message); ?></p><?php endif; ?>
		<?php if ($error !== ''): ?><p class="error" role="alert"><?php echo escapeEventValue($error); ?></p><?php endif; ?>

		<div class="card summary-card"><h2>Total events</h2><span class="summary-count"><?php echo $eventCount; ?></span></div>

		<section class="card">
			<h2>Add event</h2>
			<form method="post" class="form-grid">
				<input type="hidden" name="action" value="add">
				<div><label for="title">Title</label><input id="title" name="title" type="text" placeholder="Event title" required></div>
				<div><label for="event_date">Date</label><input id="event_date" name="event_date" type="date" required></div>
				<div><label for="venue">Venue</label><input id="venue" name="venue" type="text" placeholder="Event venue" required></div>
				<div><label for="organizer">Organizer</label><input id="organizer" name="organizer" type="text" placeholder="Organizer name" required></div>
				<button type="submit">Add event</button>
			</form>
		</section>

		<section class="card">
			<h2>Event list</h2>
			<div class="table-wrap">
				<table>
					<thead><tr><th>Title</th><th>Date</th><th>Venue</th><th>Organizer</th><th>Actions</th></tr></thead>
					<tbody>
						<?php if ($events && $events->num_rows > 0): ?>
							<?php while ($event = $events->fetch_assoc()): $formId = 'update-event-' . (int) $event['id']; ?>
								<tr>
									<td><input form="<?php echo $formId; ?>" name="title" type="text" value="<?php echo escapeEventValue($event['title']); ?>" aria-label="Event title" required></td>
									<td><input form="<?php echo $formId; ?>" name="event_date" type="date" value="<?php echo escapeEventValue($event['event_date']); ?>" aria-label="Event date" required></td>
									<td><input form="<?php echo $formId; ?>" name="venue" type="text" value="<?php echo escapeEventValue($event['venue']); ?>" aria-label="Venue" required></td>
									<td><input form="<?php echo $formId; ?>" name="organizer" type="text" value="<?php echo escapeEventValue($event['organizer']); ?>" aria-label="Organizer" required></td>
									<td>
										<form id="<?php echo $formId; ?>" method="post" class="inline-form"><input type="hidden" name="action" value="update"><input type="hidden" name="event_id" value="<?php echo (int) $event['id']; ?>"><button type="submit">Save</button></form>
										<form method="post" class="inline-form" onsubmit="return confirm('Delete this event?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="event_id" value="<?php echo (int) $event['id']; ?>"><button class="delete-button" type="submit">Delete</button></form>
									</td>
								</tr>
							<?php endwhile; ?>
						<?php else: ?><tr><td colspan="5">No events found.</td></tr><?php endif; ?>
					</tbody>
				</table>
			</div>
		</section>
	</main>
</body>
</html>