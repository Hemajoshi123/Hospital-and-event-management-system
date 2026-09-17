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
	$doctorId = (int) ($_POST['doctor_id'] ?? 0);
	$name = trim($_POST['name'] ?? '');
	$specialization = trim($_POST['specialization'] ?? '');
	$phone = trim($_POST['phone'] ?? '');

	if (($action === 'add' || $action === 'update') && ($name === '' || $specialization === '' || $phone === '')) {
		$error = 'Please complete all doctor fields.';
	} elseif ($action === 'add') {
		$statement = $conn->prepare('INSERT INTO doctors (name, specialization, phone) VALUES (?, ?, ?)');
		$statement->bind_param('sss', $name, $specialization, $phone);
		$statement->execute();
		$statement->close();
		$message = 'Doctor added successfully.';
	} elseif ($action === 'update' && $doctorId > 0) {
		$statement = $conn->prepare('UPDATE doctors SET name = ?, specialization = ?, phone = ? WHERE id = ?');
		$statement->bind_param('sssi', $name, $specialization, $phone, $doctorId);
		$statement->execute();
		$statement->close();
		$message = 'Doctor updated successfully.';
	} elseif ($action === 'delete' && $doctorId > 0) {
		$statement = $conn->prepare('DELETE FROM doctors WHERE id = ?');
		$statement->bind_param('i', $doctorId);
		$statement->execute();
		$statement->close();
		$message = 'Doctor deleted.';
	}
}

$doctors = $conn->query('SELECT id, name, specialization, phone FROM doctors ORDER BY name');
$countResult = $conn->query('SELECT COUNT(*) AS total FROM doctors');
$doctorCount = $countResult ? (int) $countResult->fetch_assoc()['total'] : 0;

function escapeDoctorValue($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Doctors | Hospital Event Management</title>
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
		.inline-form input, .inline-form button { width: auto; min-width: 110px; padding: 7px; }
		.delete-button { background: #b42318; }
		.delete-button:hover { background: #8f1c14; }
		@media (max-width: 700px) { .page { padding: 16px; } .page-header { align-items: flex-start; flex-direction: column; } .form-grid { grid-template-columns: 1fr; } }
	</style>
</head>
<body>
	<main class="page">
		<header class="page-header">
			<h1>Doctors Management</h1>
			<a class="back-link" href="dashboard.php">Back to dashboard</a>
		</header>

		<?php if ($message !== ''): ?><p class="notice" role="status"><?php echo escapeDoctorValue($message); ?></p><?php endif; ?>
		<?php if ($error !== ''): ?><p class="error" role="alert"><?php echo escapeDoctorValue($error); ?></p><?php endif; ?>

		<div class="card summary-card"><h2>Total doctors</h2><span class="summary-count"><?php echo $doctorCount; ?></span></div>

		<section class="card">
			<h2>Add doctor</h2>
			<form method="post" class="form-grid">
				<input type="hidden" name="action" value="add">
				<div><label for="name">Name</label><input id="name" name="name" type="text" placeholder="Doctor name" required></div>
				<div><label for="specialization">Specialization</label><input id="specialization" name="specialization" type="text" placeholder="e.g. Cardiology" required></div>
				<div><label for="phone">Phone</label><input id="phone" name="phone" type="tel" placeholder="Phone number" required></div>
				<button type="submit">Add doctor</button>
			</form>
		</section>

		<section class="card">
			<h2>Doctor list</h2>
			<div class="table-wrap">
				<table>
					<thead><tr><th>Name</th><th>Specialization</th><th>Phone</th><th>Actions</th></tr></thead>
					<tbody>
						<?php if ($doctors && $doctors->num_rows > 0): ?>
							<?php while ($doctor = $doctors->fetch_assoc()): $formId = 'update-doctor-' . (int) $doctor['id']; ?>
								<tr>
									<td><input form="<?php echo $formId; ?>" name="name" type="text" value="<?php echo escapeDoctorValue($doctor['name']); ?>" aria-label="Doctor name" required></td>
									<td><input form="<?php echo $formId; ?>" name="specialization" type="text" value="<?php echo escapeDoctorValue($doctor['specialization']); ?>" aria-label="Specialization" required></td>
									<td><input form="<?php echo $formId; ?>" name="phone" type="tel" value="<?php echo escapeDoctorValue($doctor['phone']); ?>" aria-label="Phone" required></td>
									<td>
										<form id="<?php echo $formId; ?>" method="post" class="inline-form"><input type="hidden" name="action" value="update"><input type="hidden" name="doctor_id" value="<?php echo (int) $doctor['id']; ?>"><button type="submit">Save</button></form>
										<form method="post" class="inline-form" onsubmit="return confirm('Delete this doctor?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="doctor_id" value="<?php echo (int) $doctor['id']; ?>"><button class="delete-button" type="submit">Delete</button></form>
									</td>
								</tr>
							<?php endwhile; ?>
						<?php else: ?><tr><td colspan="4">No doctors found.</td></tr><?php endif; ?>
					</tbody>
				</table>
			</div>
		</section>
	</main>
</body>
</html>