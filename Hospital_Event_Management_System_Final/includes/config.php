<?php
mysqli_report(MYSQLI_REPORT_OFF);

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'hospital_event_db');
define('ADMIN_RESET_KEY', 'hospital-admin-reset');
define('SMS_API_URL', '');
define('SMS_API_KEY', '');
define('SMS_SENDER_ID', 'HOSPITAL');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

if ($conn->connect_errno) {
	http_response_code(500);
	exit('Unable to connect to the database. Please start MySQL and try again.');
}

if (!$conn->set_charset('utf8mb4')) {
	http_response_code(500);
	exit('Unable to configure the database connection.');
}
?>