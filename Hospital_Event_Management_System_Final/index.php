<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Hospital Event Management System</title>
	<link rel="stylesheet" href="assets/css/style.css">
	<style>
		.welcome-page { position: relative; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: linear-gradient(135deg, #093042 0%, #155b72 52%, #1d7795 100%); }
		.welcome-shell { width: min(100%, 980px); }
		.welcome-header { margin-bottom: 30px; color: #fff; text-align: center; }
		.welcome-header h1 { margin-bottom: 8px; color: #fff; }
		.welcome-header p { max-width: 620px; margin: 0 auto; color: #d9edf1; font-size: 1.05rem; }
		.booking-link { display: inline-block; margin-top: 18px; padding: 10px 15px; border: 1px solid rgba(255, 255, 255, .65); border-radius: 4px; color: #fff; font-weight: bold; text-decoration: none; }
		.booking-link:hover { background: rgba(255, 255, 255, .14); }
		.register-link { display: inline-block; margin: 18px 0 0 8px; padding: 10px 15px; border: 1px solid rgba(255, 255, 255, .65); border-radius: 4px; color: #fff; font-weight: bold; text-decoration: none; }
		.register-link:hover { background: rgba(255, 255, 255, .14); }
		.role-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
		.role-card { display: flex; min-height: 190px; flex-direction: column; justify-content: space-between; padding: 22px; border: 1px solid #c5d5dc; border-top: 5px solid #1d7795; border-radius: 8px; background: #fff; box-shadow: 0 10px 28px rgba(23, 50, 77, .08); color: #17324d; text-decoration: none; transition: transform .2s, box-shadow .2s; }
		.role-card:hover, .role-card:focus-visible { transform: translateY(-4px); box-shadow: 0 14px 32px rgba(23, 50, 77, .15); }
		.role-card h2 { margin: 0 0 10px; font-size: 1.2rem; }
		.role-card p { margin: 0; color: #526579; font-size: .92rem; }
		.role-action { margin-top: 20px; color: #1d7795; font-weight: bold; }
		.welcome-footer { margin-top: 26px; color: #e0f0f2; font-size: .9rem; }
		@media (max-width: 800px) { .role-grid { grid-template-columns: repeat(2, 1fr); } }
		@media (max-width: 500px) { .welcome-page { padding: 18px; } .role-grid { grid-template-columns: 1fr; } .role-card { min-height: 150px; } }
	</style>
</head>
<body>
	<main class="welcome-page">
		<div class="welcome-shell">
			<header class="welcome-header">
				<h1>Hospital Event Management System</h1>
				<p>Manage hospital services, appointments and community events from one place.</p>
				<a class="booking-link" href="appointment.php">Book an appointment &rarr;</a>
				<a class="register-link" href="register.php">New patient? Register here</a>
				<a class="register-link" href="patient/login.php">Patient login</a>
			</header>

			<section class="role-grid" aria-label="Select a portal">
				<a class="role-card" href="admin/login.php">
					<div><h2>Admin Portal</h2><p>Manage patients, doctors, staff, beds, billing and events.</p></div>
					<span class="role-action">Open admin login &rarr;</span>
				</a>
				<a class="role-card" href="doctor/dashboard.php">
					<div><h2>Doctor Portal</h2><p>Review doctor appointments and patient information.</p></div>
					<span class="role-action">Open doctor dashboard &rarr;</span>
				</a>
				<a class="role-card" href="patient/dashboard.php">
					<div><h2>Patient Portal</h2><p>View appointments, profile information and billing history.</p></div>
					<span class="role-action">Open patient dashboard &rarr;</span>
				</a>
				<a class="role-card" href="staff/dashboard.php">
					<div><h2>Staff Portal</h2><p>Monitor hospital operations, appointments and events.</p></div>
					<span class="role-action">Open staff dashboard &rarr;</span>
				</a>
			</section>

			<footer class="welcome-footer">Start by selecting the portal that matches your role.</footer>
		</div>
	</main>
</body>
</html>