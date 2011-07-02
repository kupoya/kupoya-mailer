<?php

define('SITE_URL', 'http://scans.kupoya.com');

	require("phpmailer/class.phpmailer.php");

//	$smtp_host = 'out.bezeqint.out';
//	$smtp_port = 25;	
//	$smtp_user = 'lirantal1';
//	$smtp_pass = '***REMOVED***';

	$smtp_host = 'mail.kupoya.com';
	$smtp_port = 25;	
	$smtp_user = 'liran@kupoya.com';
	$smtp_pass = '***REMOVED***';


	$gm_worker = new GearmanWorker();
	$gm_worker->addServer();
	$gm_worker->addFunction('email-notification', 'email');
	$gm_worker->addFunction('email-alerts', 'email_alerts');

	echo 'waiting for job...';
	while ($gm_worker->work()) {

		echo 'received job..';
		if ($gm_worker->returnCode() != GEARMAN_SUCCESS) {
				echo "return_code: " . $gm_worker->returnCode() . "\n";
				break;
 			}
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

function email($job) {


	$data_raw = $job->workload();

	if (!$data_raw)
		return false;

//	error_log('processing_job');
//	echo 'processing job...';
	$data = unserialize($data_raw);
//	var_dump($data);

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
	
	
	$mail->AddAddress($data['user']['email']);
	$mail->From = 'no-reply@kupoya.com';
	$mail->FromName = 'kupoya';
	$mail->Subject = 'Redeem your coupon code';
//	$mail->Body = '<html><body><h1> this is a test for coupon code... received brand: '.$data['brand']['name'].'</h1> <p> bla bla lbla your coupon code is: '.$data['coupon']['serial'].'</p> </body></html>';
	
//	$mail->Body = file_get_contents('/home/liran/Desktop/email.html');
	$str = file_get_contents('/home/liran/Projects/kupoya/scans/scans-dev.kupoya.com/assets/templates/cupon-email-notification.html');
	$mail->Body = replace_metadata($str, &$data);

	// used for alternative body if message can't be received as HTML
	//$mail->AltBody = '';


	if (!$mail->Send()) {
		return 'mail error: '.$mail->ErrorInfo;
	}

	return true;

}




function replace_metadata($str, $data) {


	$data['brand']['picture'] = site_url($data['brand']['picture']);

	$values = array($data['strategy']['name'], $data['strategy']['description'], $data['brand']['picture'], $data['coupon']['serial']);
	$place_holders = array('___STRATEGY_NAME___', '___STRATEGY_DESCRIPTION___', '___BRAND_PICTURE___', '___COUPON_CODE___');

	$str_replaced = str_replace($place_holders, $values, $str);

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

