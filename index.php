<?php

//inspired by: https://medium.com/@sqrendk/how-you-can-use-facebook-to-track-your-friends-sleeping-habits-505ace7fffb6#.vdumf15vq

require_once('facebook.php');

$app_secret = '00000a4dea0385ac7f3d8b8c73e00000'; //register an app on https://developers.facebook.com/
$app_id = '000007920200000'; //get this from https://developers.facebook.com/ as well

$user_name = 'hans.dampf.76'; //get this from your facebook profile URL
$user_id = '000005259400000'; //get this from http://findmyfbid.com/

$app_xs_cookie = '00%3ABQV1qXvqk5R9eA%3A2%3A1456858876%3A000'; //inspect your cookies with browser developer tools to find the value of 'xs'

//connect or create the database and tables
try {
	$db = new PDO('sqlite:'.dirname(__FILE__).'/activity.db');
	$db->exec("CREATE TABLE IF NOT EXISTS activity (
		id integer PRIMARY KEY NOT NULL,
		user_id varchar(128),
		last_active integer(128)
	);CREATE UNIQUE INDEX IF NOT EXISTS NewIndex0 ON activity (user_id, last_active);
	CREATE TABLE IF NOT EXISTS users (
		id integer PRIMARY KEY NOT NULL,
		user_id varchar(128),
		user_name varchar(255)
	);
	CREATE UNIQUE INDEX IF NOT EXISTS NewIndex1 ON users (user_id, user_name);");
} catch(PDOException $e) {
	print 'Exception : '.$e->getMessage();
	die('cannot connect to or open the database');
}

date_default_timezone_set('Europe/Berlin');
header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8" />
	<title>FB Activity</title>
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="format-detection" content="telephone=no" />
	<style>
		html {
			font: 100%/1.5 Helvetica, Arial, sans-serif;
		}

		.table-id {
			display: none;
		}

		#activity-graph {
			width: 100%;
			background: #eee;
			position: relative;
			height: 2em;
		}

		.daymarker,
		.hourmarker,
		.bar {
			display: inline-block;
			width: 1px;
			height: 2em;
			background: #ccc;
			position: absolute;
			left: 0;
		}

		.hourmarker {
			background: #fff;
		}

		.bar {
			background: #007aff;
		}

		form#search {
			margin: 2em 0;
		}

		#go {
			margin-left: 1em;
			background: #007aff;
			padding: 0.2em 0.5em;
			color: #fff;
			text-decoration: none;
		}
	</style>
</head>
<body>
<?php

if(isset($_GET['update'])):
	//$ch = curl_init("https://www.messenger.com");
	$ch = curl_init("https://www.facebook.com/");

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept-Charset: utf-8','Accept-Language: en-us,en;q=0.7,bn-bd;q=0.3','Accept: text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5'));
	curl_setopt($ch, CURLOPT_COOKIEFILE, getcwd ());
	curl_setopt($ch, CURLOPT_COOKIEJAR, getcwd ());
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_USERAGENT, "user_agent");
	curl_setopt($ch, CURLOPT_REFERER, "http://m.facebook.com");
	curl_setopt($ch, CURLOPT_COOKIE, 'xs='.$app_xs_cookie.'; c_user='.$user_id);
	curl_setopt($ch, CURLOPT_VERBOSE, true);
	curl_setopt($ch, CURLOPT_TIMEOUT_MS, 80000);

	$data = curl_exec($ch);
	$curl_errno = curl_errno($ch);
	$curl_error = curl_error($ch);

	curl_close($ch);

	preg_match('/\"lastActiveTimes\":\{(.*?)\}/',$data,$m);

	if(strlen($m[0]) > 25) {
		//found something

		$activity = json_decode('{'.$m[0].'}', true);
		echo('update');

		$qry = $db->prepare('INSERT INTO activity (user_id, last_active) VALUES (?, ?)');

		foreach($activity['lastActiveTimes'] as $user_id => $last_active) {
			$qry->execute(array($user_id, $last_active));
		}

	} else {
		echo('Regex problem?');
	}

elseif(isset($_GET['user'])):

	//single user mode
	$manual_offset = 0;
	$days = 7;
	$days_past = 60*60*24*$days;

	if(!empty($_GET['user'])) {
		$query = "SELECT * FROM activity a LEFT JOIN users u ON a.user_id = u.user_id WHERE a.user_id = ".$db->quote($_GET['user'])." AND a.last_active > ".(time()-$days_past)." ORDER BY a.last_active ASC";
		$result = array();

		$start_ts = time();
		$end_ts = $start_ts-$days_past;
		$duration = $start_ts - $end_ts;

		echo('<div id="activity-graph">');
		foreach($db->query($query) as $row) {
			$active = $row['last_active']+$manual_offset;
			$difference_from_start = $start_ts-$active;
			$percentage = ($difference_from_start / $duration) * 100;

			$result[] = array(
				'id' => $row['user_id'],
				'name' => $row['user_name'],
				'active' => $active,
				'percent' => $percentage
			);

			echo('<span title="'.date('d.m.Y H:i', $active).', '.(100 - round($percentage)).'%" class="bar" style="left: '.number_format((float)$percentage, 2, '.', '').'%;"></span>');
		}

		for($i=0; $i<$days; $i++) {
			$ts = $start_ts - ($i*60*60*24);
			$new_day = mktime(0,0,0,date('m', $ts), date('d', $ts), date('Y', $ts));

			$difference_from_start = $start_ts-$new_day;
			$percentage = ($difference_from_start / $duration) * 100;

			echo('<span title="'.date('d.m.Y H:i', $new_day).'" class="daymarker" style="left: '.number_format((float)$percentage, 2, '.', '').'%;"></span>');

			for($j=0; $j<3; $j++) {
				$new_hour = mktime(($j*6+6),0,0,date('m', $ts), date('d', $ts), date('Y', $ts));
				$difference_from_start = $start_ts-$new_hour;
				$percentage = ($difference_from_start / $duration) * 100;
				echo('<span title="'.date('d.m.Y H:i', $new_hour).'" class="hourmarker" style="left: '.number_format((float)$percentage, 2, '.', '').'%;"></span>');
			}
		}

		echo('</div>');

		echo('<h2>'.$result[0]['name'].'</h2>');
		echo('<a href="https://facebook.com/'.$result[0]['id'].'">Facebook Profile</a><br /><br />');
		echo('Start: '.date('d.m.Y H:i', $start_ts).'<br />');
		echo('End: '.date('d.m.Y H:i', $end_ts).'<br /><br />');
		echo('Total activity: '.sizeof($result).'<br /><br />');
		echo('First user activity: '.date('d.m.Y H:i', $result[0]['active']).'<br />');
		echo('Last user activity: '.date('d.m.Y H:i', $result[sizeof($result)-1]['active']).'<br />');

		echo('<br /><a href="./index.php">Overview</a>');
	};

else:
	//view mode

	//search field for all users
	$query = "SELECT * FROM users ORDER BY user_name ASC";
	echo('<form id="search"><input list="users" name="user" id="search_user" size="35" /></label>');
	echo('<datalist id="users">');
	foreach($db->query($query) as $row) {
		echo('<option data-id="'.$row['user_id'].'" value="'.$row['user_name'].'" />');
	}
	echo('</datalist>');
	echo('<a id="go" href="javascript:var user=document.getElementById(\'search_user\').value; var userid = document.querySelector(\'option[value=\\042\'+user+\'\\042]\').getAttribute(\'data-id\'); window.location.href += \'?user=\'+userid;">Go</a>');
	echo('</form>');

	//look up the most recent users
	$query = "SELECT a.user_id, u.user_name, max(a.last_active) AS active, count(a.last_active) AS total FROM activity a LEFT JOIN users u ON a.user_id = u.user_id GROUP BY a.user_id ORDER BY a.last_active DESC LIMIT 50";
	$result = array();
	$unknown_users = array();
	foreach($db->query($query) as $row) {
		$result[$row['user_id']] = array(
			'id' => $row['user_id'],
			'name' => $row['user_name'],
			'active' => $row['active'],
			'count' => $row['total']
		);

		if(empty($row['user_name'])) {
			//look up this user later
			$unknown_users[] = $row['user_id'];
		}
	}

	$users_string = implode(',', $unknown_users);

	if(!empty($unknown_users)) {
		$facebook = new Facebook(array(
			'appId'  => $app_id ,
			'secret' => $app_secret,
		));
		$fb_access_token = $facebook->getAccessToken();

		$ch = curl_init("https://graph.facebook.com/v2.4/?ids=".$users_string."&fields=id,name,timezone,verified&access_token=".$fb_access_token);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT_MS, 80000);

		$data = curl_exec($ch);
		$curl_errno = curl_errno($ch);
		$curl_error = curl_error($ch);

		curl_close($ch);

		if($curl_errno > 0) {
			echo "cURL Error ($curl_errno): $curl_error\n";
		}

		$decoded = json_decode($data, true);
		$qry = $db->prepare('INSERT INTO users (user_id, user_name) VALUES (?, ?)');

		foreach($decoded as $user) {
			$result[$user['id']]['name'] = $user['name']; //fill in the missing name

			echo('<p>needed to look up user: '.$user['name'].' ('.$user['id'].')</p>');

			if(!empty($user['name']) && !empty($user['id'])) $qry->execute(array($user['id'], $user['name']));
		}
	}

	function compare_by($a, $b, $name) {
		return ($a[$name] < $b[$name]) ? -1 : 1;
	}
	usort($result, 'compare_by', 'active');

	echo('<table id="recent">');
	foreach($result as $user) {
		$manual_offset = 0;
		$activetime = $user['active']+$manual_offset;
		$time = date('d.m.Y H:i:s', $activetime);
		if(date('Ymd') == date('Ymd', $activetime)) $time = 'Heute';
		if(date('Ymd', strtotime('yesterday')) == date('Ymd', $activetime)) $time = 'Gestern';
		if($time == 'Heute' || $time == 'Gestern') $time .= ' '.date('H:i', $activetime);

		if((time()-$activetime) < 6*60*60) $time = 'vor '.round((time()-$activetime)/60/60).' h ('.date('H:i', $activetime).')';
		if((time()-$activetime) < 60*60) $time = 'vor '.round((time()-$activetime)/60).' min ('.date('H:i', $activetime).')';

		if(!empty($user['name'])) echo('<tr><td class="table-id"><data value="'.$user['id'].'">'.$user['id'].'</data></td><td><a href="?user='.$user['id'].'" title="'.$user['count'].' records">'.$user['name'].'</a></td><td><time data-timestamp="'.$activetime.'" datetime="'.date('Y-m-d H:i:s', $activetime).'">'.$time.'</time></td></tr>');
	}
	echo('</table>');

endif;

$db = NULL;
?>
</body>
</html>