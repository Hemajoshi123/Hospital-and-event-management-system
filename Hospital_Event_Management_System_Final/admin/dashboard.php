<?php
session_start();

if (isset($_GET['logout'])) {
	$_SESSION = [];
	session_destroy();
	header('Location: login.php');
	exit;
}

if (!isset($_SESSION['admin_id'])) {
	header('Location: login.php');
	exit;
}

require_once __DIR__ . '/../includes/config.php';

$modules = [
	['title' => 'Patients', 'description' => 'Manage patient records', 'file' => 'patients.php', 'table' => 'patients', 'image' => 'https://images.unsplash.com/photo-1538108149393-fbbd81895907?auto=format&fit=crop&w=800&q=80'],
	['title' => 'Doctors', 'description' => 'Manage doctors and specialties', 'file' => 'doctors.php', 'table' => 'doctors', 'image' => 'https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?auto=format&fit=crop&w=800&q=80'],
	['title' => 'Staff', 'description' => 'Manage hospital staff', 'file' => 'staff.php', 'table' => 'staff', 'image' => 'https://images.unsplash.com/photo-1582750433449-648ed127bb54?auto=format&fit=crop&w=800&q=80'],
	['title' => 'Appointments', 'description' => 'Review patient appointments', 'file' => 'appointments.php', 'table' => 'appointments', 'image' => 'https://images.unsplash.com/photo-1516841273335-e39b37888115?auto=format&fit=crop&w=800&q=80'],
	['title' => 'Beds', 'description' => 'Track bed availability', 'file' => 'beds.php', 'table' => 'beds', 'image' => 'https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?auto=format&fit=crop&w=800&q=80'],
	['title' => 'Billing', 'description' => 'Manage patient billing', 'file' => 'billing.php', 'table' => 'billing', 'image' => 'https://images.unsplash.com/photo-1554224155-6726b3ff858f?auto=format&fit=crop&w=800&q=80'],
	['title' => 'Events', 'description' => 'Create and manage events', 'file' => 'events.php', 'table' => 'events', 'image' => 'https://images.unsplash.com/photo-1505373877841-8d25f7d46678?auto=format&fit=crop&w=800&q=80'],
	['title' => 'Participants', 'description' => 'Manage event participants', 'file' => 'participants.php', 'table' => 'participants', 'image' => 'https://images.unsplash.com/photo-1529156069898-49953e39b3ac?auto=format&fit=crop&w=800&q=80'],
	['title' => 'Attendance', 'description' => 'Track event attendance', 'file' => 'attendance.php', 'table' => 'attendance', 'image' => 'https://images.unsplash.com/photo-1543269865-cbf427effbad?auto=format&fit=crop&w=800&q=80'],
];

foreach ($modules as &$module) {
	$table = $conn->real_escape_string($module['table']);
	$countResult = $conn->query("SELECT COUNT(*) AS total FROM `$table`");
	$module['count'] = $countResult ? (int) $countResult->fetch_assoc()['total'] : 0;
}
unset($module);

$adminUsername = htmlspecialchars($_SESSION['admin_username'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Admin Dashboard | Hospital Event Management</title>
	<link rel="stylesheet" href="../assets/css/style.css">
	<style>
		body:has(.dashboard) { background: linear-gradient(135deg, #dcebed 0%, #f4f9fa 52%, #c9e0e4 100%); }
		.dashboard { max-width: 1180px; margin: 0 auto; padding: 24px; }
		.dashboard-header { position: relative; display: flex; justify-content: center; align-items: center; gap: 20px; margin-bottom: 24px; }
		.dashboard-header > div { width: 100%; text-align: center; }
		.dashboard-header h1 { margin: 0 0 8px; color: #17324d; font-size: clamp(2rem, 5vw, 3rem); }
		.dashboard-header p { margin: 0; color: #3083dc; }
		.logout { color: #191211; font-weight: bold; text-decoration: none; }
		.dashboard-header .logout { position: absolute; top: 50%; right: 0; transform: translateY(-50%); }
		.module-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
		.module-card { display: block; padding: 20px; border-left: 4px solid #2a748d; color: #17324d; text-decoration: none; transition: transform .2s, box-shadow .2s; }
		.module-card:hover, .module-card:focus { transform: translateY(-2px); box-shadow: 0 5px 16px rgba(23, 50, 77, .14); }
		.module-image { display: block; width: calc(100% + 20px); height: 125px; margin: -20px -20px 18px; object-fit: cover; border-radius: 4px 4px 0 0; }
		.module-content { display: flex; min-height: 112px; flex-direction: column; }
		.module-card h2 { margin: 0 0 8px; font-size: 1.15rem; }
		.module-card p { margin: 0 0 18px; color: #1d64b0; font-size: .92rem; }
		.module-count { font-size: 1.8rem; font-weight: bold; color: #507480; }
		@media (max-width: 600px) {
			.dashboard { padding: 16px; }
			.dashboard-header { align-items: flex-start; flex-direction: column; }
			.dashboard-header > div { width: 100%; text-align: left; }
			.dashboard-header .logout { position: static; transform: none; }
			.module-image { height: 110px; }
		}
	</style>
</head>
<body>
	<main class="dashboard">
		<header class="dashboard-header">
			<div>
				<h1>Hospital &amp; Event Dashboard</h1>
				<p>Welcome, <?php echo $adminUsername; ?>. Select a module to continue.</p>
			</div>
			<a class="logout" href="dashboard.php?logout=1">Log out</a>
		</header>

		<section class="module-grid" aria-label="Management modules">
			<?php foreach ($modules as $module): ?>
				<a class="card module-card" href="<?php echo htmlspecialchars($module['file'], ENT_QUOTES, 'UTF-8'); ?>">
					<img class="module-image" src="<?php echo htmlspecialchars($module['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($module['title'], ENT_QUOTES, 'UTF-8'); ?>">
					<div class="module-content">
					<h2><?php echo htmlspecialchars($module['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
					<p><?php echo htmlspecialchars($module['description'], ENT_QUOTES, 'UTF-8'); ?></p>
					<span class="module-count"><?php echo $module['count']; ?></span>
					</div>
				</a>
			<?php endforeach; ?>
		</section>
	</main>
</body>
</html>