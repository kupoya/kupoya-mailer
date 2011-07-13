<?php

define('SITE_URL', 'http://scans.kupoya.com');

	require("phpmailer/class.phpmailer.php");

	require("config.php");

	$gm_worker = new GearmanWorker();
	$gm_worker->addServer();
	$gm_worker->addFunction('email-notification', 'email');
	$gm_worker->addFunction('email-alerts', 'email_alerts');
	$gm_worker->addFunction('wedding-email-notification', 'wedding_email_notification');

	echo 'waiting for job...';
	while ($gm_worker->work()) {

		echo 'received job..';
		if ($gm_worker->returnCode() != GEARMAN_SUCCESS) {
				echo "return_code: " . $gm_worker->returnCode() . "\n";
				break;
 			}
	}



function wedding_email_notification($job) {

        $data_raw = $job->workload();
	    $my_alert_email = 'liran.tal@gmail.com';

        if (!$data_raw)
                return false;

        $data = unserialize($data_raw);

        global $smtp_host;
        global $smtp_user;
        global $smtp_pass;
        global $smtp_port;

        global $db_host;    
        global $db_port;
        global $db_user;
        global $db_pass;
        global $db_database;
        
        
        
        //$mysql = new pdomysql($db_host, $db_user, $db_pass);
        try {
            $dbh = new PDO("mysql:host=$db_host;dbname=$db_database", $db_user, $db_pass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
        } catch (PDOException $exception) {
            printf("Failed to connect to the  database. Error: %s",  $exception->getMessage());
            return false;
        }
        
        $strategy_id = $data['strategy']['id'];
        
        if (!is_numeric($strategy_id))
            return false;
       
        $csv_file_attachment = '/tmp/'.md5(time()) . '-' . uniqid().'.csv';
       
        //$strategy_id = mysql_real_escape_string($strategy_id);
        //$sql = "select name, time, attending, attendees, message INTO OUTFILE '".$csv_file_attachments."' FIELDS TERMINATED BY ',' from wedding where strategy_id = ? order by name";
        $sql = "select name, time, attending, attendees, message from wedding where strategy_id = ? order by name";
        $result = $dbh->prepare($sql);
        $result->bindParam(1, $strategy_id);
        $result->execute();
        
        $file_data = "Attendee name, Registration Date, Attending Status, Number of (extra) Attendees, Personal Message\n";
        
        $result->setFetchMode(PDO::FETCH_ASSOC);
        while ($row = $result->fetch()) {
            $file_data .= $row['name'] . ',' . $row['time'] . ',' . $row['attending'] . ',' . $row['attendees'] . ',' . $row['message'] . "\n";
        }
        
        file_put_contents($csv_file_attachment, $file_data);   
        
        
        
        $sql = "select SUM(IF(attending=1,1,0)) as attending, SUM(IF(attending=0,1,0)) as not_attending, COUNT(attending) as total from wedding where strategy_id=? LIMIT 1";
        
        $result = $dbh->prepare($sql);
        $result->bindParam(1, $strategy_id);
        $result->execute();
        
        //$result = $dbh->query($sql);
        $result->setFetchMode(PDO::FETCH_ASSOC);
        $row = $result->fetch();
        
        $total_attending = $row['attending'];
        $total_not_attending = $row['not_attending'];
        $total_guests = $row['total'];
                
        $result->closeCursor();
	
	
	
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
	
	    $mail->AddAddress($data['wedding_info']['email']);
//	    $mail->AddAddress('liran.tal@gmail.com');
	    $mail->From = 'no-reply@kupoya.com';
	    $mail->FromName = 'kupoya';
	    $mail->Subject = 'kupoya\'s Wedding Invitation update';
	    
	    $str = file_get_contents('/opt/kupoya-mailer/templates/wedding-email-notification.html');


        if (isset($data['strategy']['picture']) && $data['strategy']['picture'] != '')
    	    $picture = site_url($data['strategy']['picture']);
    	else
    	    $picture = 'http://scans.kupoya.com/assets/img/notifications/kupoya_medium.png';
	    
	    
	    $values = array($data['strategy']['name'], $total_attending, $total_guests, date('F d'), $picture);
	    $place_holders = array('___STRATEGY_NAME___', '___TOTAL_ATTENDING___', '___TOTAL___', '___DATE___', '___STRATEGY_PICTURE___');

	    $str_replaced = str_replace($place_holders, $values, $str);

	    $mail->Body = $str_replaced;
	    $mail->AddAttachment($csv_file_attachment);

	    // used for alternative body if message can't be received as HTML
	    //$mail->AltBody = '';

	    if (!$mail->Send()) {
	    	    // remove attachment from system
        	    unlink($csv_file_attachment);
		    return 'mail error: '.$mail->ErrorInfo;
	    }

	    // remove attachment from system
	    unlink($csv_file_attachment);

	    return true;

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
	$str = file_get_contents('/opt/kupoya-mailer/templates/cupon-email-notification.html');
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

