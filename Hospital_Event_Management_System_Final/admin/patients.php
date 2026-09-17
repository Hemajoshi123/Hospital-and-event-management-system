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
$genderOptions = ['Male', 'Female', 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? '';
	$patientId = (int) ($_POST['patient_id'] ?? 0);
	$name = trim($_POST['name'] ?? '');
	$gender = $_POST['gender'] ?? '';
	$dob = $_POST['dob'] ?? '';
	$phone = trim($_POST['phone'] ?? '');
	$address = trim($_POST['address'] ?? '');

	if (($action === 'add' || $action === 'update') && ($name === '' || !in_array($gender, $genderOptions, true) || $dob === '' || $phone === '' || $address === '')) {
		$error = 'Please complete all patient fields.';
	} elseif ($action === 'add') {
		$statement = $conn->prepare('INSERT INTO patients (name, gender, dob, phone, address) VALUES (?, ?, ?, ?, ?)');
		$statement->bind_param('sssss', $name, $gender, $dob, $phone, $address);
		$statement->execute();
		$statement->close();
		$message = 'Patient added successfully.';
	} elseif ($action === 'update' && $patientId > 0) {
		$statement = $conn->prepare('UPDATE patients SET name = ?, gender = ?, dob = ?, phone = ?, address = ? WHERE id = ?');
		$statement->bind_param('sssssi', $name, $gender, $dob, $phone, $address, $patientId);
		$statement->execute();
		$statement->close();
		$message = 'Patient updated successfully.';
	} elseif ($action === 'delete' && $patientId > 0) {
		$statement = $conn->prepare('UPDATE beds SET patient_id = NULL WHERE patient_id = ?');
		$statement->bind_param('i', $patientId);
		$statement->execute();
		$statement->close();
		$statement = $conn->prepare('DELETE FROM patients WHERE id = ?');
		$statement->bind_param('i', $patientId);
		$statement->execute();
		$statement->close();
		$message = 'Patient deleted.';
	}
}

$patients = $conn->query('SELECT patients.id, patients.name, patients.gender, patients.dob, patients.phone, patients.address, beds.bed_no, beds.ward FROM patients LEFT JOIN beds ON beds.patient_id = patients.id ORDER BY patients.name');
$countResult = $conn->query('SELECT COUNT(*) AS total FROM patients');
$patientCount = $countResult ? (int) $countResult->fetch_assoc()['total'] : 0;

function escapePatientValue($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Patients | Hospital Event Management</title>
	<link rel="stylesheet" href="../assets/css/style.css">
	<style>
		.page { max-width: 1180px; margin: 0 auto; padding: 24px; }
		.page-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
		.page-header h1 { margin: 0; color: #17324d; }
		.back-link { color: #1d7795; font-weight: bold; text-decoration: none; }
		.summary-card { border-top: 4px solid #1d7795; max-width: 250px; }
		.summary-card h2 { margin: 0 0 8px; font-size: 1rem; color: #526579; }
		.summary-count { font-size: 1.8rem; font-weight: bold; color: #17324d; }
		.form-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 14px; align-items: end; }
		.address-field { grid-column: span 2; }
		label { display: block; margin-bottom: 6px; font-weight: bold; color: #17324d; }
		input, select, textarea, button { box-sizing: border-box; width: 100%; padding: 10px; border: 1px solid #b7c5d1; border-radius: 4px; font: inherit; }
		textarea { min-height: 42px; resize: vertical; }
		button { border: 0; background: #1d7795; color: white; cursor: pointer; font-weight: bold; }
		button:hover { background: #155b72; }
		.notice { padding: 10px 12px; border-radius: 4px; background: #e7f5ed; color: #17643a; }
		.error { padding: 10px 12px; border-radius: 4px; background: #fdecec; color: #a32222; }
		.table-wrap { overflow-x: auto; }
		table { width: 100%; border-collapse: collapse; }
		th, td { padding: 12px 10px; border-bottom: 1px solid #d9e1e8; text-align: left; white-space: nowrap; }
		th { background: #f3f7fa; color: #17324d; }
		.patient-photo { display: block; width: 52px; height: 52px; border: 3px solid #e1eef2; border-radius: 50%; object-fit: cover; }
		.inline-form { display: inline-flex; gap: 6px; align-items: center; }
		.inline-form input, .inline-form select, .inline-form textarea, .inline-form button { width: auto; min-width: 100px; padding: 7px; }
		.inline-form textarea { min-width: 150px; }
		.delete-button { background: #b42318; }
		.delete-button:hover { background: #8f1c14; }
		@media (max-width: 900px) { .form-grid { grid-template-columns: 1fr 1fr; } .address-field { grid-column: auto; } }
		@media (max-width: 600px) { .page { padding: 16px; } .page-header { align-items: flex-start; flex-direction: column; } .form-grid { grid-template-columns: 1fr; } }
	</style>
</head>
<body>
	<main class="page">
		<header class="page-header">
			<h1>Patients Management</h1>
			<a class="back-link" href="dashboard.php">Back to dashboard</a>
		</header>

		<?php if ($message !== ''): ?><p class="notice" role="status"><?php echo escapePatientValue($message); ?></p><?php endif; ?>
		<?php if ($error !== ''): ?><p class="error" role="alert"><?php echo escapePatientValue($error); ?></p><?php endif; ?>

		<div class="card summary-card"><h2>Total patients</h2><span class="summary-count"><?php echo $patientCount; ?></span></div>

		<section class="card">
			<h2>Add patient</h2>
			<form method="post" class="form-grid">
				<input type="hidden" name="action" value="add">
				<div><label for="name">Name</label><input id="name" name="name" type="text" placeholder="Patient name" required></div>
				<div><label for="gender">Gender</label><select id="gender" name="gender" required><option value="">Select gender</option><?php foreach ($genderOptions as $gender): ?><option value="<?php echo escapePatientValue($gender); ?>"><?php echo escapePatientValue($gender); ?></option><?php endforeach; ?></select></div>
				<div><label for="dob">Date of birth</label><input id="dob" name="dob" type="date" required></div>
				<div><label for="phone">Phone</label><input id="phone" name="phone" type="tel" placeholder="Phone number" required></div>
				<div class="address-field"><label for="address">Address</label><textarea id="address" name="address" placeholder="Patient address" required></textarea></div>
				<button type="submit">Add patient</button>
			</form>
		</section>

		<section class="card">
			<h2>Patient list</h2>
			<div class="table-wrap">
				<table>
					<thead><tr><th>Photo</th><th>Name</th><th>Gender</th><th>Date of birth</th><th>Phone</th><th>Address</th><th>Bed</th><th>Actions</th></tr></thead>
					<tbody>
						<?php if ($patients && $patients->num_rows > 0): ?>
							<?php while ($patient = $patients->fetch_assoc()): $formId = 'update-patient-' . (int) $patient['id']; ?>
								<tr>
									<td><img class="patient-photo" src="https://ui-avatars.com/api/?name=<?php echo rawurlencode($patient['name']); ?>&background=1d7795&color=ffffff&size=96" alt="Photo of <?php echo escapePatientValue($patient['name']); ?>" loading="lazy"></td>
									<td><input form="<?php echo $formId; ?>" name="name" type="text" value="<?php echo escapePatientValue($patient['name']); ?>" aria-label="Patient name" required></td>
									<td><select form="<?php echo $formId; ?>" name="gender" aria-label="Gender" required><?php foreach ($genderOptions as $gender): ?><option value="<?php echo escapePatientValue($gender); ?>" <?php echo $gender === $patient['gender'] ? 'selected' : ''; ?>><?php echo escapePatientValue($gender); ?></option><?php endforeach; ?></select></td>
									<td><input form="<?php echo $formId; ?>" name="dob" type="date" value="<?php echo escapePatientValue($patient['dob']); ?>" aria-label="Date of birth" required></td>
									<td><input form="<?php echo $formId; ?>" name="phone" type="tel" value="<?php echo escapePatientValue($patient['phone']); ?>" aria-label="Phone" required></td>
									<td><textarea form="<?php echo $formId; ?>" name="address" aria-label="Address" required><?php echo escapePatientValue($patient['address']); ?></textarea></td>
									<td><?php echo $patient['bed_no'] ? escapePatientValue($patient['bed_no'] . ' - ' . $patient['ward']) : 'Not assigned'; ?></td>
									<td>
										<form id="<?php echo $formId; ?>" method="post" class="inline-form"><input type="hidden" name="action" value="update"><input type="hidden" name="patient_id" value="<?php echo (int) $patient['id']; ?>"><button type="submit">Save</button></form>
										<form method="post" class="inline-form" onsubmit="return confirm('Delete this patient?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="patient_id" value="<?php echo (int) $patient['id']; ?>"><button class="delete-button" type="submit">Delete</button></form>
									</td>
								</tr>
							<?php endwhile; ?>
						<?php else: ?><tr><td colspan="8">No patients found.</td></tr><?php endif; ?>
					</tbody>
				</table>
			</div>
		</section>
	</main>
</body>
</html>