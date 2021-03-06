<?php
include("../config.php");
include("../global.php");

$payment_status = $_POST['txn_state'];
$payment_amount = $_POST['txn_total'];
$payment_currency = $_POST['txn_currency'];
$txn_id = $_POST['txn_id'];
$payer_email = $_POST['sender_email'];
$custom = $_POST['custom'];

if( $payment_status !== "completed" ) {
	die("ERROR: invalid IPN checksum.");
}
if( $payment_currency !== "eur" ) {
	die("ERROR: invalid IPN checksum.");
}
	
// Check if payment handler ID exists
if( $conn->query("SELECT * FROM payment_handlers WHERE id='" . mysqli_real_escape_string($conn, $custom) . "'")->num_rows == 0 ) {
	die("ERROR: invalid IPN checksum.");
}
	
$pHandler['received_parameters'] = explode(":", $conn->query("SELECT * FROM payment_handlers WHERE id='" . mysqli_real_escape_string($conn, $custom) . "'")->fetch_assoc()['parameters']);
	
// Get payment handler parameters
$discordid = $pHandler['received_parameters'][0];
$needed_level = intval($pHandler['received_parameters'][1]);
	
// Get current user level because it will be needed for some checks
$CurrentUserLevel = $conn->query("SELECT * FROM users WHERE discord_id='" . mysqli_real_escape_string($conn, $discordid) . "'")->fetch_assoc()['level'];
	
// Check if the needed level exists
$checkLevel = $conn->query("SELECT * FROM levels WHERE level=" . mysqli_real_escape_string($conn, $needed_level))->num_rows;
if( $checkLevel == 0 ) {
	die("ERROR: invalid IPN checksum.");
}
	
// Check if user have current level or higher
if( $CurrentUserLevel >= $needed_level ) {
	die("ERROR: invalid IPN checksum.");
}
	
// Get level info
$level_info = $conn->query("SELECT * FROM levels WHERE level=" . mysqli_real_escape_string($conn, $needed_level))->fetch_assoc();
	
// Check if user paid full amount or not
if( $payment_amount < $level_info['price'] ) {
	die("ERROR: invalid IPN checksum.");
}
	
// -------------- all checks were done! Now lets give the user his/her level.
if( $level_info['ismonthly'] == 0 ) {
	// This plan is a lifetime plan
	$conn->query("UPDATE users SET level=" . mysqli_real_escape_string($conn, $needed_level) . " WHERE discord_id='" . mysqli_real_escape_string($conn, $discordid) . "'");
	$conn->query("UPDATE users SET plan_expiry=0 WHERE discord_id='" . mysqli_real_escape_string($conn, $discordid) . "'");
} else {
	// This plan is a monthly plan
	$expiry_date = new DateTime(date("Y-m-d")); // Y-m-d
	$expiry_date->add(new DateInterval('P30D')); // 30 days + today's date
	$expiry_date = strtotime($expiry_date->format('Y-m-d'));
	$conn->query("UPDATE users SET level=" . mysqli_real_escape_string($conn, $needed_level) . " WHERE discord_id='" . mysqli_real_escape_string($conn, $discordid) . "'");
	$conn->query("UPDATE users SET plan_expiry=" . mysqli_real_escape_string($conn, $expiry_date) . " WHERE discord_id='" . mysqli_real_escape_string($conn, $discordid) . "'");
}
?>