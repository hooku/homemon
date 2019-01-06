<?php
define('SMS_ALERT', 0);
define('VER', '0.2');
define('M9_UA', 'M9');

date_default_timezone_set('Asia/Shanghai');

header('Content-Type: text/plain');

$hm_data = array(
				'hmdata' => array(
					'stat' => 'stop',
					'id' => '1',
					'batt' => '66',
					'volt' => '4200',
					'temp' => '32',
					'time' => '20130506215013',
					'last' => '20130506215013',
				));

$url = explode('/', $_SERVER["REQUEST_URI"]);
$work_path = 'http://' . $_SERVER["SERVER_NAME"] . '/' . $url[1];

read_data();

if (isset($_GET['op']))
{
	switch ($_GET['op'])
	{
	case 'start':
		hm_start();
		break;
	case 'stop':
		hm_stop();
		break;
	case 'get':		// report phone status & send command to phone
		hm_get();
		break;
	case 'post':	// save camera picture & analysis
		hm_post();
		break;
	}
}
else
{
	hm_stat();
}

function hm_start()
{
	global $hm_data;

	$hm_data['hmdata']['stat'] = 'start';
	write_data();
	
	echo 'start-ok';
}

function hm_stop()
{
	global $hm_data;

	$hm_data['hmdata']['stat'] = 'stop';
	write_data();
	
	echo 'stop-ok';
}

function hm_get()
{
	global $hm_data;

	// report phone status
	if (isset($_GET['id']))
	{
		$hm_data['hmdata']['id'] = $_GET['id'];
		$hm_data['hmdata']['batt'] = $_GET['batt'];
		$hm_data['hmdata']['volt'] = $_GET['volt'];
		$hm_data['hmdata']['temp'] = $_GET['temp'];
		$hm_data['hmdata']['time'] = $_GET['time'];
		
		write_data();
	}
	
	// send command to phone
	
	/* time, command */
	echo date('YmdHis') . "\r\n";
	echo $hm_data['hmdata']['stat'] . "\r\n";
}

function hm_post()
{
	global $hm_data;

	// save capture time
	$hm_data['hmdata']['last'] = date('YmdHis');
	write_data();
	
	// save capture
	$pic_name = write_pic();
	
	// preprocess
	autocorrect_pic($pic_name);
	
	// do recognize
	//face_recognition($pic_name);
	
	echo 'post-ok';
}

function hm_stat()
{
	global $work_path;
	global $hm_data;
	
	$phpurl = $work_path . '/mon.php';
	
	echo "Motion Detector Server " . constant('VER') . "\r\n";
	echo "\r\n";
	echo "current status\t: " . $hm_data['hmdata']['stat'] . "\r\n";
	echo "device id\t: " . $hm_data['hmdata']['id'] . "\r\n";
	echo "battery\t\t: " . $hm_data['hmdata']['batt'] . "\r\n";
	echo "voltage\t\t: " . $hm_data['hmdata']['volt'] . "\r\n";
	echo "temperature\t: " . $hm_data['hmdata']['temp'] . "\r\n";
	
	
	$time_date = DateTime::createFromFormat('YmdHis', $hm_data['hmdata']['time']);
	$last_date = DateTime::createFromFormat('YmdHis', $hm_data['hmdata']['last']);
	
	$now = new DateTime('now');
	
	$time_diff = $time_date->diff($now);
	$last_diff = $last_date->diff($now);
	
	echo "last cmd\t: " . $time_diff->format("%d days %H:%I:%S") . " ago" . "\r\n";
	echo "last post\t: " . $last_diff->format("%d days %H:%I:%S") . " ago" . "\r\n";
	echo "\r\n";
	echo "Commands:" . "\r\n";
	echo "1.start home monitor:" . "\r\n";
	echo $phpurl . "?op=start" . "\r\n";
	echo "\r\n";
	echo "2.stop home monitor:" . "\r\n";
	echo $phpurl . "?op=stop" . "\r\n";
    echo "\r\n";
	echo "4.ping:" . "\r\n";
	echo $phpurl . "?op=ping" . "\r\n";
    echo "\r\n";
	echo "3.post picture:" . "\r\n";
	echo $phpurl . "?op=post" . "\r\n";
	echo "\r\n";
	
	
	if (strpos($_SERVER['HTTP_USER_AGENT'], constant('M9_UA')) !== false)
	{
		$last_ymd = $hm_data['hmdata']['last'];
		$last_his = $hm_data['hmdata']['last'];
	
		echo "Preview:" . "\r\n";
		echo "http://win2000.howeb.cn/?thumb=/mon/" . $last_ymd . "/" . $last_his . ".jpg";
	}
}

function write_pic()
{
	// check if directory is available
	$cap_path = date('Ymd');
	
	if (!file_exists($cap_path))
	{
		mkdir($cap_path);
	}
	
	$new_name = $cap_path . '/' . date('His') . '.jpg';
	
	move_uploaded_file($_FILES["file"]["tmp_name"], $new_name);
	
	return $new_name;
}

function autocorrect_pic($name)
{
	// rotate pic 90 degree
	system('pic_rotate_.py ' . $name);
}

function face_recognition($name)
{
	global $work_path;
	global $hm_data;

	// call opencv face detect script
	$facerecog_desc = system('pic_motion_detect_.py ' . $name, $facerecog_result);
	
	if (constant('SMS_ALERT') == 1)
	{
		// send sms alert
		if ($facerecog_result != 0)
		{
			$sms_api = 'http://win2000.howeb.cn/wsa/fetion.php';
			$from = '15901964306';
			$password = 'ee61e3b4';
			$to = '15901964306';
			
			$last_ymd = substr($hm_data['hmdata']['last'], 0, 8);
			$last_his = substr($hm_data['hmdata']['last'], 8, 6);
			$message = urlencode("Home Monitor Alert!!" . "\r\n" . $facerecog_desc . "\r\n" . $work_path . "/" . $last_ymd . "/" . $last_his . ".jpg");
			
			$url = "{$sms_api}?from={$from}&pw={$password}&to={$to}&msg={$message}";
			
			$curl = curl_init($url);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, FALSE);
			curl_setopt($curl, CURLOPT_NOBODY, TRUE);
			curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
			$data = curl_exec($curl);
			curl_close($curl);
		}
	}
}

function read_data()
{
	global $hm_data;
	
	$ini_data = parse_ini_file('mon.ini', true);
	foreach ($ini_data['hmdata'] as $ini => $value)
	{
		$hm_data['hmdata'][$ini] = $value;
	}
}

function write_data()
{
	global $hm_data;

	write_ini_file($hm_data, 'mon.ini');
}

function write_ini_file($assoc_arr, $path)
{
	$content = "";
	foreach ($assoc_arr as $key=>$elem)
	{ 
		$content .= "[".$key."]\r\n";
		foreach ($elem as $key=>$elem2)
		{
			if(is_array($elem2))
			{ 
				for($i=0;$i<count($elem2);$i++) 
				{
					$content .= $key."[] = ".$elem2[$i]."\r\n";
				}
			}
			else if($elem2=="") $content .= $key." = \r\n";
			else $content .= $key." = ".$elem2."\r\n";
		}
	}

	if (!$handle = fopen($path, 'w'))
	{
		return false;
	}
	if (!fwrite($handle, $content))
	{
		return false;
	}
	fclose($handle);
	return true;
}
