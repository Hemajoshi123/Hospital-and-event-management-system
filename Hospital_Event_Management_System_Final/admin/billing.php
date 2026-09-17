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
	$billId = (int) ($_POST['bill_id'] ?? 0);

	if ($action === 'add' || $action === 'update') {
		$patientId = (int) ($_POST['patient_id'] ?? 0);
		$amount = (float) ($_POST['amount'] ?? 0);
		$billDate = $_POST['bill_date'] ?? '';

		if ($patientId < 1 || $amount <= 0 || $billDate === '') {
			$error = 'Please enter a patient, a valid amount and a bill date.';
		} elseif ($action === 'add') {
			$statement = $conn->prepare('INSERT INTO billing (patient_id, amount, bill_date) VALUES (?, ?, ?)');
			$statement->bind_param('ids', $patientId, $amount, $billDate);
			$statement->execute();
			$statement->close();
			$message = 'Bill added successfully.';
		} elseif ($billId > 0) {
			$statement = $conn->prepare('UPDATE billing SET patient_id = ?, amount = ?, bill_date = ? WHERE id = ?');
			$statement->bind_param('idsi', $patientId, $amount, $billDate, $billId);
			$statement->execute();
			$statement->close();
			$message = 'Bill updated successfully.';
		}
	} elseif ($action === 'delete' && $billId > 0) {
		$statement = $conn->prepare('DELETE FROM billing WHERE id = ?');
		$statement->bind_param('i', $billId);
		$statement->execute();
		$statement->close();
		$message = 'Bill deleted.';
	}
}

$patients = $conn->query('SELECT id, name FROM patients ORDER BY name');
$bills = $conn->query(
	'SELECT billing.id, billing.patient_id, billing.amount, billing.bill_date, patients.name AS patient_name
	 FROM billing
	 LEFT JOIN patients ON patients.id = billing.patient_id
	 ORDER BY billing.bill_date DESC, billing.id DESC'
);
$totalResult = $conn->query('SELECT COALESCE(SUM(amount), 0) AS total FROM billing');
$totalBilled = $totalResult ? (float) $totalResult->fetch_assoc()['total'] : 0;

function escapeBillingValue($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Billing | Hospital Event Management</title>
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
		.inline-form input, .inline-form select, .inline-form button { width: auto; min-width: 100px; padding: 7px; }
		.inline-form input[type="number"] { min-width: 110px; }
		.delete-button { background: #b42318; }
		.delete-button:hover { background: #8f1c14; }
		@media (max-width: 700px) { .page { padding: 16px; } .page-header { align-items: flex-start; flex-direction: column; } .form-grid { grid-template-columns: 1fr; } }
	</style>
</head>
<body>
	<main class="page">
		<header class="page-header">
			<h1>Billing Management</h1>
			<a class="back-link" href="dashboard.php">Back to dashboard</a>
		</header>

		<?php if ($message !== ''): ?><p class="notice" role="status"><?php echo escapeBillingValue($message); ?></p><?php endif; ?>
		<?php if ($error !== ''): ?><p class="error" role="alert"><?php echo escapeBillingValue($error); ?></p><?php endif; ?>

		<div class="card summary-card"><h2>Total billed</h2><span class="summary-count">&#8377; <?php echo number_format($totalBilled, 2); ?></span></div>

		<section class="card">
			<h2>Add bill</h2>
			<?php if ($patients && $patients->num_rows > 0): ?>
				<form method="post" class="form-grid">
					<input type="hidden" name="action" value="add">
					<div><label for="patient_id">Patient</label><select id="patient_id" name="patient_id" required><option value="">Select patient</option><?php while ($patient = $patients->fetch_assoc()): ?><option value="<?php echo (int) $patient['id']; ?>"><?php echo escapeBillingValue($patient['name']); ?></option><?php endwhile; ?></select></div>
					<div><label for="amount">Amount</label><input id="amount" name="amount" type="number" min="0.01" step="0.01" placeholder="0.00" required></div>
					<div><label for="bill_date">Bill date</label><input id="bill_date" name="bill_date" type="date" value="<?php echo date('Y-m-d'); ?>" required></div>
					<button type="submit">Add bill</button>
				</form>
			<?php else: ?><p>Add a patient before creating a bill.</p><?php endif; ?>
		</section>

		<section class="card">
			<h2>Bill list</h2>
			<div class="table-wrap">
				<table>
					<thead><tr><th>Patient</th><th>Amount</th><th>Bill date</th><th>Actions</th></tr></thead>
					<tbody>
						<?php if ($bills && $bills->num_rows > 0): ?>
							<?php while ($bill = $bills->fetch_assoc()): $formId = 'update-bill-' . (int) $bill['id']; ?>
								<tr>
									<td><select form="<?php echo $formId; ?>" name="patient_id" aria-label="Patient" required><?php $patientOptions = $conn->query('SELECT id, name FROM patients ORDER BY name'); while ($patient = $patientOptions->fetch_assoc()): ?><option value="<?php echo (int) $patient['id']; ?>" <?php echo (int) $patient['id'] === (int) $bill['patient_id'] ? 'selected' : ''; ?>><?php echo escapeBillingValue($patient['name']); ?></option><?php endwhile; ?></select></td>
									<td><input form="<?php echo $formId; ?>" name="amount" type="number" min="0.01" step="0.01" value="<?php echo escapeBillingValue($bill['amount']); ?>" aria-label="Amount" required></td>
									<td><input form="<?php echo $formId; ?>" name="bill_date" type="date" value="<?php echo escapeBillingValue($bill['bill_date']); ?>" aria-label="Bill date" required></td>
									<td>
										<form id="<?php echo $formId; ?>" method="post" class="inline-form"><input type="hidden" name="action" value="update"><input type="hidden" name="bill_id" value="<?php echo (int) $bill['id']; ?>"><button type="submit">Save</button></form>
										<form method="post" class="inline-form" onsubmit="return confirm('Delete this bill?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="bill_id" value="<?php echo (int) $bill['id']; ?>"><button class="delete-button" type="submit">Delete</button></form>
									</td>
								</tr>
							<?php endwhile; ?>
						<?php else: ?><tr><td colspan="4">No bills found.</td></tr><?php endif; ?>
					</tbody>
				</table>
			</div>
		</section>
	</main>
</body>
</html>