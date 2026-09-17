<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$username = trim($_POST['username'] ?? '');
	$password = $_POST['password'] ?? '';

	if ($username === '' || $password === '') {
		$error = 'Please enter both username and password.';
	} else {
		$statement = $conn->prepare('SELECT id, username, password FROM admins WHERE username = ? LIMIT 1');

		if ($statement) {
			$statement->bind_param('s', $username);
			$statement->execute();
			$result = $statement->get_result();
			$admin = $result->fetch_assoc();
			$statement->close();

			$passwordMatches = $admin && (
				password_verify($password, $admin['password']) ||
				hash_equals((string) $admin['password'], $password)
			);

			if ($passwordMatches) {
				session_regenerate_id(true);
				$_SESSION['admin_id'] = (int) $admin['id'];
				$_SESSION['admin_username'] = $admin['username'];
				header('Location: dashboard.php');
				exit;
			}
		}

		$error = 'Invalid username or password.';
	}
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Admin Login | Hospital Event Management</title>
	<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="login-page">
	<main class="card login-card">
		<div class="login-brand" aria-hidden="true">Hospital and Event Management</div>
		<!--<p class="login-eyebrow">Hospital Event Management</p>-->
		<h1>Welcome back</h1>
		<p class="login-subtitle">Sign in to manage hospital operations and events.</p>

		<?php if ($error !== ''): ?>
			<p class="login-error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
		<?php endif; ?>

		<form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>">
			<label for="username">Username</label>
			<input id="username" name="username" type="text" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="username" required autofocus>

			<label for="password">Password</label>
			<input id="password" name="password" type="password" autocomplete="current-password" required>

			<button type="submit">Login</button>
		</form>
		<a class="forgot-link" href="forgot_password.php">Forgot password?</a>
		<a class="login-home" href="../index.php">&larr; Back to portals</a>
	</main>
</body>
</html>