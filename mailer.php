<?php

define('SITE_URL', 'http://scans.kupoya.com');

require("phpmailer/class.phpmailer.php");
require("config.php");

$gm_worker = new GearmanWorker();
$gm_worker->addServer();
$gm_worker->addFunction('coupon_email_notification', 'notification_coupon_email');
$gm_worker->addFunction('email-alerts', 'email_alerts');
$gm_worker->addFunction('wedding_email_notification', 'notification_wedding_email');
$gm_worker->addFunction('registration_email_notification', 'notification_registration_email');
$gm_worker->addFunction('lottery_email_notification', 'notification_lottery_email');


echo 'waiting for job...';
while ($gm_worker->work()) {
	echo 'received job..';
	if ($gm_worker->returnCode() != GEARMAN_SUCCESS) {
		echo "return_code: " . $gm_worker->returnCode() . "\n";
		break;
	}
}


function db_connect() {

	global $db_host;
	global $db_port;
	global $db_user;
	global $db_pass;
	global $db_database;

	// $mysql = new pdomysql($db_host, $db_user, $db_pass);
	try {
		$dbh = new PDO("mysql:host=$db_host;dbname=$db_database", $db_user, $db_pass,
		array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
	} catch (PDOException $exception) {
		printf("Failed to connect to the  database. Error: %s",  $exception->getMessage());
		return false;
	}

	return $dbh;


}



function get_email_template($dbh, $key) {
	
	if (!$dbh)
		return false;
	
	if (!$key)
		return false;


	$sql = "SELECT text FROM notification_template WHERE name = ? LIMIT 1";
	$result = $dbh->prepare($sql);
	
	if (!$result)
		return false;
	
	$result->bindParam(1, $key);
	$result->execute();
	
	$result->setFetchMode(PDO::FETCH_ASSOC);
	$row = $result->fetch();

	if (!$row)
		return false; 
	
	$text = $row['text'];
	
	return $text;
	
}




function notification_registration_email($job) {

	$data_raw = $job->workload();

	if (!$data_raw)
		return false;

	$data = unserialize($data_raw);

	// initialize db connection
	$dbh = db_connect();

	$strategy_id = isset($data['strategy']['id']) ? $data['strategy']['id'] : false;
	$email_recipient = isset($data['registration_info']['email']) ? $data['registration_info']['email'] : '';

	if (!is_numeric($strategy_id) || empty($email_recipient))
		return false;
		
		
	if (isset($data['brand']['name']) && ($data['brand']['name'] != ''))
		$name = $data['brand']['name'];
	else
		$name = "campaign owner";

	if (isset($data['strategy']['picture']) && $data['strategy']['picture'] != '')
		$picture = site_url($data['strategy']['picture']);
	else
		$picture = 'http://scans.kupoya.com/assets/img/notifications/kupoya_medium.png';
		

	// get template for this strategy type
	$template = get_email_template($dbh, 'notification_registration_email');
	if (!$template)
		return false;
		
		
		
	/* model stuff */
	/* *********** */
	$sql = "SELECT COUNT(id) as total FROM registration WHERE strategy_id=? LIMIT 1";

	$result = $dbh->prepare($sql);
	if (!$result)
		return false;
	
	$result->bindParam(1, $strategy_id);
	$result->execute();

	$result->setFetchMode(PDO::FETCH_ASSOC);
	$row = $result->fetch();
	if (!$row)
		return false;

	$total = $row['total'];
	/* *********** */
	/* model stuff end */
	
	
	/* model stuff */
	/* *********** */
	$csv_file_attachment = '/tmp/'.$strategy_id.md5(time()) . '-' . uniqid().'.csv';
	 
	$sql = "select created_time, name, contact, text FROM registration WHERE strategy_id = ? ORDER BY name";
	$result = $dbh->prepare($sql);
	$result->bindParam(1, $strategy_id);
	$result->execute();

	$file_data = "Registration Date, Name, Contact, Personal Message\n";

	$result->setFetchMode(PDO::FETCH_ASSOC);
	while ($row = $result->fetch()) {
		$file_data .= trim($row['created_time']) . ',' . $row['name'] . ',' . $row['contact'] . ',' . $row['text'] . "\n";
	}

	file_put_contents($csv_file_attachment, $file_data);
	/* *********** */
	/* model stuff end */
		
		
	$values = array(
		$name,
		$data['time'],
		$total,
		$picture,
		$data['name'],
		$data['contact'],
	);
	
	$place_holders = array(
		'___BRAND_NAME___',
		'___DATE___',
		'___TOTAL_REGISTRATIONS___',
		'___STRATEGY_PICTURE___',
		'___REGISTRANT_NAME___',
		'___REGISTRANT_CONTACT___',
	);

	// apply token variables
	$template_text = metadata_alter($template, $values, $place_holders);
	if ($template_text === false) {
		unlink($csv_file_attachment);
		return false;
	}
	
	// email it
	$options['mail']['recipients'] = array($email_recipient);
	$options['mail']['subject'] = 'kupoya - registration notification';
	$options['mail']['message'] = $template_text;
	$options['mail']['attachments'] = array($csv_file_attachment);
	
	
	$email_res = email($options);
	if ($email_res !== true)
		echo "error occured sending out an email\n";
		
	unlink($csv_file_attachment);
		
	return false;

}




function notification_wedding_email($job) {

	$data_raw = $job->workload();

	if (!$data_raw)
		return false;

	$data = unserialize($data_raw);

	// initialize db connection
	$dbh = db_connect();

	$strategy_id = isset($data['strategy']['id']) ? $data['strategy']['id'] : false;
	$email_recipient = isset($data['wedding_info']['email']) ? $data['wedding_info']['email'] : '';

	if (!is_numeric($strategy_id) || empty($email_recipient))
		return false;
		
		
	if (isset($data['strategy']['name']) && ($data['strategy']['name'] != ''))
		$name = $data['strategy']['name'];
	else
		$name = "campaign owner";

	if (isset($data['strategy']['picture']) && $data['strategy']['picture'] != '')
		$picture = site_url($data['strategy']['picture']);
	else
		$picture = 'http://scans.kupoya.com/assets/img/notifications/kupoya_medium.png';
		

	// get template for this strategy type
	$template = get_email_template($dbh, 'notification_wedding_email');
	if (!$template)
		return false;
		
		
	
	/* model stuff */
	/* =========== */ 
	$csv_file_attachment = '/tmp/'.$strategy_id.md5(time()) . '-' . uniqid().'.csv';
	 
	//$sql = "select name, time, attending, attendees, message INTO OUTFILE '".$csv_file_attachments."' FIELDS TERMINATED BY ',' from wedding where strategy_id = ? order by name";
	$sql = "select name, time, IF(attending=1,'Yes','No') AS attending, attendees, message from wedding where strategy_id = ? order by name";
	$result = $dbh->prepare($sql);
	$result->bindParam(1, $strategy_id);
	$result->execute();

	$file_data = "Attendee name, Registration Date, Attendance Status, Number of (extra) Attendees, Personal Message\n";

	$result->setFetchMode(PDO::FETCH_ASSOC);
	while ($row = $result->fetch()) {
		$file_data .= trim($row['name']) . ',' . $row['time'] . ',' . $row['attending'] . ',' . $row['attendees'] . ',' . trim($row['message']) . "\n";
	}

	file_put_contents($csv_file_attachment, $file_data);



	//$sql = "select SUM(IF(attending=1,1,0)) as attending, SUM(IF(attending=0,1,0)) as not_attending, COUNT(attending) as total from wedding where strategy_id=? LIMIT 1";
	$sql = "select SUM(IF(attending=1,attendees+1,0)) as attending from wedding where strategy_id=? LIMIT 1";

	$result = $dbh->prepare($sql);
	$result->bindParam(1, $strategy_id);
	$result->execute();

	$result->setFetchMode(PDO::FETCH_ASSOC);
	$row = $result->fetch();

	$total_attending = $row['attending'];
	//$total_not_attending = $row['not_attending'];
	//$total_guests = $row['total'];

	$result->closeCursor();
	/* ================== */
	/* model stuff end    */



	$values = array(
		$name,
		$total_attending,
		date('F d'),
		$picture,
	);
	
	$place_holders = array(
		'___STRATEGY_NAME___',
		'___TOTAL_ATTENDING___',
		'___DATE___',
		'___STRATEGY_PICTURE___'
	);

	// apply token variables
	$template_text = metadata_alter($template, $values, $place_holders);
	if ($template_text === false) {
		unlink($csv_file_attachment);
		return false;
	}
	
	// email it
	$options['mail']['recipients'] = array($email_recipient);
	$options['mail']['subject'] = 'kupoya\'s Wedding Invitation update';
	$options['mail']['message'] = $template_text;
	$options['mail']['attachments'] = array($csv_file_attachment);
	
	
	$email_res = email($options);
	if ($email_res !== true)
		echo "error occured sending out an email\n";
		
	unlink($csv_file_attachment);
	
	return false;

}


function email_alerts($job) {

	$data_raw = $job->workload();
	$my_alert_email = 'liran.tal@gmail.com';

	if (!$data_raw)
	return false;

	$data = unserialize($data_raw);

	global $smtp_host;
	global $smtp_user;
	global $smtp_pass;
	global $smtp_port;

	$mail = new PHPMailer();

	// delcare using an smtp server information
	$mail->isSMTP(true);
	$mail->Host = $smtp_host;
	$mail->SMTPAuth = true;
	$mail->Port = $smtp_port;
	$mail->Username = $smtp_user;
	$mail->Password = $smtp_pass;

	$mail->SMTPKeepAlive = true;
	$mail->SMTPDebug  = 1;

	// html email ?
	$mail->isHTML(false);
	$mail->CharSet = 'UTF-8';


	$mail->AddAddress($my_alert_email);
	$mail->From = 'alerts@kupoya.com';
	$mail->FromName = 'kupoya';
	$mail->Subject = 'Application alerts';
	$mail->Body = ($data);

	if (!$mail->Send()) {
		return 'mail error: '.$mail->ErrorInfo;
	}

	return true;

}




function notification_lottery_email($job) {

	$data_raw = $job->workload();

	if (!$data_raw)
		return false;

	$data = unserialize($data_raw);

	// initialize db connection
	$dbh = db_connect();

	$strategy_id = isset($data['strategy']['id']) ? $data['strategy']['id'] : false;
	$email_recipient = isset($data['user']['email']) ? $data['user']['email'] : '';

	if (!is_numeric($strategy_id) || empty($email_recipient))
		return false;
		
		
	if (isset($data['strategy']['name']) && ($data['strategy']['name'] != ''))
		$name = $data['strategy']['name'];
	else
		$name = $data['brand']['name'];

	if (isset($data['strategy']['picture']) && $data['strategy']['picture'] != '')
		$picture = site_url($data['strategy']['picture']);
	elseif (isset($data['brand']['picture']) && $data['brand']['picture'] != '')
		$picture = $data['brand']['picture'];
	else
		$picture = 'http://scans.kupoya.com/assets/img/notifications/kupoya_medium.png';
		

	// get template for this strategy type
	$template = get_email_template($dbh, 'notification_lottery_email');
	if (!$template)
		return false;
		
	$data['brand']['picture'] = site_url($data['brand']['picture']);

	$values = array(
		$name,
		$data['strategy']['description'],
		$picture,
		$data['lottery']['serial'],
	);
	
	$place_holders = array(
		'___STRATEGY_NAME___',
		'___STRATEGY_DESCRIPTION___',
		'___BRAND_PICTURE___',
		'___LOTTERY_CODE___',
	);

	// apply token variables
	$template_text = metadata_alter($template, $values, $place_holders);
	if ($template_text === false)
		return false;
	
	// email it
	$options['mail']['recipients'] = array($email_recipient);
	$options['mail']['subject'] = 'kupoya - lottery notification';
	$options['mail']['message'] = $template_text;	
	
	$email_res = email($options);
	if ($email_res !== true)
		echo "error occured sending out an email\n";
		
	return false;
	
}


function notification_coupon_email($job) {
	
	$data_raw = $job->workload();

	if (!$data_raw)
		return false;

	$data = unserialize($data_raw);
	
	
	// initialize db connection
	$dbh = db_connect();

	$strategy_id = isset($data['strategy']['id']) ? $data['strategy']['id'] : false;
	$email_recipient = isset($data['user']['email']) ? $data['user']['email'] : '';

	if (!is_numeric($strategy_id) || empty($email_recipient))
		return false;
		
		
	if (isset($data['strategy']['name']) && ($data['strategy']['name'] != ''))
		$name = $data['strategy']['name'];
	else
		$name = $data['brand']['name'];

	if (isset($data['strategy']['picture']) && $data['strategy']['picture'] != '')
		$picture = site_url($data['strategy']['picture']);
	elseif (isset($data['brand']['picture']) && $data['brand']['picture'] != '')
		$picture = $data['brand']['picture'];
	else
		$picture = 'http://scans.kupoya.com/assets/img/notifications/kupoya_medium.png';
		

	// get template for this strategy type
	$template = get_email_template($dbh, 'notification_coupon_email');
	if (!$template)
		return false;
	
	$values = array(
		$name,
		$data['strategy']['description'],
		$picture,
		$data['coupon']['serial'],
	);
	
	$place_holders = array(
		'___STRATEGY_NAME___',
		'___STRATEGY_DESCRIPTION___',
		'___BRAND_PICTURE___',
		'___COUPON_CODE___'
	);

	// apply token variables
	$template_text = metadata_alter($template, $values, $place_holders);
	if ($template_text === false)
		return false;
	
	// email it
	$options['mail']['recipients'] = array($email_recipient);
	$options['mail']['subject'] = 'Redeem your coupon code';
	$options['mail']['message'] = $template_text;	
	
	$email_res = email($options);
	if ($email_res !== true)
		echo "error occured sending out an email\n";
		
	return false;
	
}





function email($options = array()) {

	if (!$options)
		return false;
		
	global $smtp_host;
	global $smtp_user;
	global $smtp_pass;
	global $smtp_port;
	
	$mail = new PHPMailer();

	// delcare using an smtp server information
	$mail->isSMTP(true);
	$mail->Host = $smtp_host;
	$mail->SMTPAuth = true;
	//	$mail->SMTPSecure = "tls";
	$mail->Port = $smtp_port;
	$mail->Username = $smtp_user;
	$mail->Password = $smtp_pass;

	$mail->SMTPKeepAlive = true;
	$mail->SMTPDebug  = 1;

	// set html email
	$mail->isHTML(true);
	$mail->CharSet = 'UTF-8';


	foreach($options['mail']['recipients'] as $recipient)
		$mail->AddAddress($recipient);
		
	if (isset($options['mail']['from']))
		$mail->From = $options['mail']['from'];
	else
		$mail->From = 'no-reply@kupoya.com';
		
	if (isset($options['mail']['from_name']))
		$mail->FromName = $options['mail']['from_name'];
	else
		$mail->FromName = 'kupoya';

	if (isset($options['mail']['subject']))
		$mail->Subject = $options['mail']['subject'];
	else
		$mail->Subject = 'kupoya campaign notification';
		
	if (isset($options['mail']['attachments'])) {
		foreach($options['mail']['attachments'] as $attachments)
			$mail->AddAttachment($attachments);
	}
		
	$mail->Body = $options['mail']['message'];

	if (!$mail->Send()) {
		return 'mail error: '.$mail->ErrorInfo;
	}

	return true;

}





function metadata_alter($haystack, $values, $place_holders) {

	$str_replaced = str_replace($place_holders, $values, $haystack);
	return $str_replaced;
}





/**
 * Site URL
 *
 * Create a local URL based on your basepath. Segments can be passed via the
 * first parameter either as a string or an array.
 *
 * @access	public
 * @param	string
 * @return	string
 */
function site_url($uri = '')
{

	// if we recieved a full path simply return it and do not change
	if ( (substr($uri, 0, 7) === 'http://') || (substr($uri, 0, 8) === 'https://') )
	return $uri;

	return SITE_URL.$uri;
}



