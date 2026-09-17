<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
	header('Location: login.php');
	exit;
}

require_once __DIR__ . '/../includes/config.php';

$conn->query('ALTER TABLE beds ADD COLUMN IF NOT EXISTS patient_id INT NULL');

$message = '';
$error = '';
$statusOptions = ['Available', 'Occupied', 'Maintenance'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? '';
	$bedId = (int) ($_POST['bed_id'] ?? 0);

	if ($action === 'add') {
		$bedNumber = trim($_POST['bed_no'] ?? '');
		$ward = trim($_POST['ward'] ?? '');
		$status = $_POST['status'] ?? 'Available';

		if ($bedNumber === '' || $ward === '' || !in_array($status, $statusOptions, true)) {
			$error = 'Please complete all bed fields.';
		} else {
			$statement = $conn->prepare('INSERT INTO beds (bed_no, ward, status) VALUES (?, ?, ?)');
			$statement->bind_param('sss', $bedNumber, $ward, $status);
			$statement->execute();
			$statement->close();
			$message = 'Bed added successfully.';
		}
	} elseif ($action === 'update' && $bedId > 0) {
		$bedNumber = trim($_POST['bed_no'] ?? '');
		$ward = trim($_POST['ward'] ?? '');
		$status = $_POST['status'] ?? '';
		$patientId = (int) ($_POST['patient_id'] ?? 0);

		if ($bedNumber === '' || $ward === '' || !in_array($status, $statusOptions, true) || $patientId < 0) {
			$error = 'Please enter valid bed details.';
		} else {
			$statement = $conn->prepare('UPDATE beds SET patient_id = NULL WHERE patient_id = ? AND id <> ?');
			$statement->bind_param('ii', $patientId, $bedId);
			$statement->execute();
			$statement->close();
			if ($patientId > 0) {
				$status = 'Occupied';
			}
			$statement = $conn->prepare('UPDATE beds SET bed_no = ?, ward = ?, status = ?, patient_id = NULLIF(?, 0) WHERE id = ?');
			$statement->bind_param('sssii', $bedNumber, $ward, $status, $patientId, $bedId);
			$statement->execute();
			$statement->close();
			$message = 'Bed details updated.';
		}
	} elseif ($action === 'delete' && $bedId > 0) {
		$statement = $conn->prepare('DELETE FROM beds WHERE id = ?');
		$statement->bind_param('i', $bedId);
		$statement->execute();
		$statement->close();
		$message = 'Bed deleted.';
	}
}

$patients = $conn->query('SELECT id, name FROM patients ORDER BY name');
$patientOptions = [];
if ($patients) {
	while ($patient = $patients->fetch_assoc()) {
		$patientOptions[] = $patient;
	}
}
$beds = $conn->query('SELECT beds.id, beds.bed_no, beds.ward, beds.status, beds.patient_id, patients.name AS patient_name FROM beds LEFT JOIN patients ON patients.id = beds.patient_id ORDER BY beds.ward, beds.bed_no');
$summary = ['Available' => 0, 'Occupied' => 0, 'Maintenance' => 0];

foreach ($summary as $status => $count) {
	$statement = $conn->prepare('SELECT COUNT(*) AS total FROM beds WHERE status = ?');
	$statement->bind_param('s', $status);
	$statement->execute();
	$result = $statement->get_result()->fetch_assoc();
	$summary[$status] = (int) $result['total'];
	$statement->close();
}

function escapeBedValue($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Beds | Hospital Event Management</title>
	<link rel="stylesheet" href="../assets/css/style.css">
	<style>
		.page { max-width: 1180px; margin: 0 auto; padding: 24px; }
		.page-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
		.page-header h1 { margin: 0; color: #17324d; }
		.back-link { color: #1d7795; font-weight: bold; text-decoration: none; }
		.summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
		.summary-card { border-top: 4px solid #1d7795; }
		.summary-card h2 { margin: 0 0 8px; font-size: 1rem; color: #526579; }
		.summary-count { font-size: 2rem; font-weight: bold; color: #17324d; }
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
		.inline-form input, .inline-form select, .inline-form button { width: auto; min-width: 100px; padding: 7px; }
		.inline-form input { min-width: 110px; }
		.delete-button { background: #b42318; }
		.delete-button:hover { background: #8f1c14; }
		@media (max-width: 700px) { .page { padding: 16px; } .page-header { align-items: flex-start; flex-direction: column; } .summary-grid, .form-grid { grid-template-columns: 1fr; } }
	</style>
</head>
<body>
	<main class="page">
		<header class="page-header">
			<h1>Beds Management</h1>
			<a class="back-link" href="dashboard.php">Back to dashboard</a>
		</header>

		<?php if ($message !== ''): ?><p class="notice" role="status"><?php echo escapeBedValue($message); ?></p><?php endif; ?>
		<?php if ($error !== ''): ?><p class="error" role="alert"><?php echo escapeBedValue($error); ?></p><?php endif; ?>

		<section class="summary-grid" aria-label="Bed availability summary">
			<?php foreach ($summary as $status => $count): ?>
				<div class="card summary-card"><h2><?php echo escapeBedValue($status); ?></h2><span class="summary-count"><?php echo $count; ?></span></div>
			<?php endforeach; ?>
		</section>

		<section class="card">
			<h2>Add new bed</h2>
			<form method="post" class="form-grid">
				<input type="hidden" name="action" value="add">
				<div><label for="bed_no">Bed number</label><input id="bed_no" name="bed_no" type="text" placeholder="e.g. B-101" required></div>
				<div><label for="ward">Ward</label><input id="ward" name="ward" type="text" placeholder="e.g. General Ward" required></div>
				<div><label for="status">Status</label><select id="status" name="status"><?php foreach ($statusOptions as $status): ?><option value="<?php echo escapeBedValue($status); ?>"><?php echo escapeBedValue($status); ?></option><?php endforeach; ?></select></div>
				<button type="submit">Add bed</button>
			</form>
		</section>

		<section class="card">
			<h2>Bed list</h2>
			<div class="table-wrap">
				<table>
					<thead><tr><th>Bed number</th><th>Ward</th><th>Patient</th><th>Status</th><th>Actions</th></tr></thead>
					<tbody>
						<?php if ($beds && $beds->num_rows > 0): ?>
							<?php while ($bed = $beds->fetch_assoc()): ?>
								<?php $formId = 'update-bed-' . (int) $bed['id']; ?>
								<tr>
									<td><input form="<?php echo $formId; ?>" name="bed_no" type="text" value="<?php echo escapeBedValue($bed['bed_no']); ?>" aria-label="Bed number" required></td>
									<td><input form="<?php echo $formId; ?>" name="ward" type="text" value="<?php echo escapeBedValue($bed['ward']); ?>" aria-label="Ward" required></td>
									<td><select form="<?php echo $formId; ?>" name="patient_id" aria-label="Assigned patient"><option value="0">No patient</option><?php foreach ($patientOptions as $patient): ?><option value="<?php echo (int) $patient['id']; ?>" <?php echo (int) $patient['id'] === (int) $bed['patient_id'] ? 'selected' : ''; ?>><?php echo escapeBedValue($patient['name']); ?></option><?php endforeach; ?></select></td>
									<td><select form="<?php echo $formId; ?>" name="status" aria-label="Bed status"><?php foreach ($statusOptions as $status): ?><option value="<?php echo escapeBedValue($status); ?>" <?php echo $status === $bed['status'] ? 'selected' : ''; ?>><?php echo escapeBedValue($status); ?></option><?php endforeach; ?></select></td>
									<td>
										<form id="<?php echo $formId; ?>" method="post" class="inline-form"><input type="hidden" name="action" value="update"><input type="hidden" name="bed_id" value="<?php echo (int) $bed['id']; ?>"><button type="submit">Save</button></form>
										<form method="post" onsubmit="return confirm('Delete this bed?');">
											<input type="hidden" name="action" value="delete">
											<input type="hidden" name="bed_id" value="<?php echo (int) $bed['id']; ?>">
											<button class="delete-button" type="submit">Delete</button>
										</form>
									</td>
								</tr>
							<?php endwhile; ?>
						<?php else: ?><tr><td colspan="5">No beds found.</td></tr><?php endif; ?>
					</tbody>
				</table>
			</div>
		</section>
	</main>
</body>
</html>