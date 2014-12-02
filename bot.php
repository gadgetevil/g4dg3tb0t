<?php
set_time_limit(0);
ini_set('display_errors', 'off');
date_default_timezone_set("America/New_York");
$serverFile = file("servers.txt");
$server     = str_replace(array(chr(10),chr(13),':'), '', $serverFile[rand(0, count($serverFile) - 1)]);
function checkOP($userStr)
{
	//[CONFIG] ADMIN HOSTS
	$op    = array(
		'207.hsd1.ct.comcast.net',
		'162.dhcp.oxfr.ma.charter.com'
	);
	$match = 0;
	foreach ($op as $host) { if (strpos($userStr, $host) !== false) { $match++; } }
	if ($match <> 0) { return true; } else { return false; }
}
//[CONFIG] SERVER / CHANNEL / BOT NICK
$config = array(
	'server' 	=> $server,
	'port' 		=> '6667',
	'channel' 	=> array('#g4dg3t', '#flava'),
	'name' 		=> '5ecret',
	'nick' 		=> 'GehtoRoy',
	'nickrand' 	=> 'GehtoRoy?',
	'pass' 		=> ''
);
class IRCBot
{
	//[START] DO NOT TOUCH
	var $sv = 'b2014.11.30';
	var $socket;
	var $ex = array();
	var $ulist;
	var $sTime;
	var $online = 0;
	var $nickerr = 1;
	var $me;
	var $getnicktimer;
	//[END] DO NOT TOUCH
	var $cycletime = 100000; // 1/10 of a second [1000000=1s]
	var $writeLog 	= 1;
	var $writeIndex = 1;
	var $echoIndex 	= 0;
	var $echoLog 	= 1;
	var $hyplnkConv = 1;
	var $hyplnkChan = array('#g4dg3t', '#flava');
	var $db = array(
			'ip' 		=> 'localhost',
			'username' 	=> 'root',
			'password' 	=> '',
			'name' 		=> 'ircbot'
	);
	var $getnick	= 'flipa';
	function main($config)
	{
		$data = fgets($this->socket, 512);
		flush();
		$this->ex = explode(' ', $data);
		if (isset($this->ex[0])) {
			switch ($this->ex[0]) {
				case "PING":
					$this->send_dataQ('PONG', $this->ex[1]);
					break;
				default:
					if ($this->ex[0] != "") { if ($this->ex[1] != "433") { $this->log($data); } }
					break;
			}
		}
		if (isset($this->ex[1])) {
			switch ($this->ex[1]) {
				case "001":
					$this->online = 1;
					$this->me     = str_replace(array(chr(10),chr(13),":"), "", $this->ex[2]);
					if ($this->me == $this->getnick) { $this->nickerr = 0; }
					if ($this->me == $config['nick']) { $this->nickerr = 1; }
					$nID['server'] = substr($this->ex[0], 1, strlen($this->ex[0]) - 1);
					$this->log('Connected to ' . $nID['server'] . ' in ' . (time() - $this->sTime) . ' seconds.');
					break;
				case "332":
					$nID['chan']  = $this->ex[3];
					$nID['TOPIC'] = "";
					for ($i = 4; $i < count($this->ex); $i++) { $nID['TOPIC'] .= $this->ex[$i] . " "; }
					$nID['TOPIC'] = substr($nID['TOPIC'], 1, strlen($nID['TOPIC']) - 1);
					$this->index('[<b>' . $nID['chan'] . '</b>] :TOPIC: <b>' . $nID['TOPIC'] . '</b>');
					break;
				case "333":
					$nID = $this->parseID($this->ex[4]);
					$this->index('Set by: <b>' . $nID['nick'] . '</b>.');
					break;
				case "353":
					$this->me    = str_replace(array(chr(10),chr(13),":"), "", $this->ex[3]);
					$nID['chan'] = $this->ex[4];
					for ($i = 5; $i < count($this->ex); $i++) {
						if ($i == 5) { $this->ulist .= substr($this->ex[$i], 1, strlen($this->ex[$i]) - 1) . " "; }
						else 		 { $this->ulist .= $this->ex[$i] . " "; }
					}
					break;
				case "366":
					$this->me    = str_replace(array(chr(10),chr(13),":"), "", $this->ex[2]);
					$nID['chan'] = $this->ex[3];
					$this->ulist = str_replace("  ", " ", $this->ulist);
					$this->index("Users in " . $nID['chan'] . ": " . $this->ulist);
					$this->ulist = "";
					break;
				case "376":
					$this->join_channel($config['channel']);
					break;
				case "433":
					if ($this->online == "0") {
						switch ($this->nickerr) {
							case "0":
								$this->send_data('NICK', $this->getnick);
								$this->nickerr = 1;
								break;
							case "1":
								$this->send_data('NICK', $config['nick']);
								$this->nickerr = 2;
								break;
							case "2":
								$rNick = '';
								for ($i = 0; $i <= (strlen($config['nickrand'])); $i++) {
									if (substr($config['nickrand'], $i, 1) == '?') { $rNick = $rNick . rand(0, 9); }
									else { $rNick = $rNick . substr($config['nickrand'], $i, 1); }
								}
								$this->send_data('NICK', $rNick);
								break;
						}
					}
					break;
				case "451":
					break;
				case ':Closing':
					if ($this->ex[2] == 'Link:') {
						echo "<meta http-equiv=\"refresh\" content=\"5\">";
					}
					exit;
				case "JOIN":
					$join         = $this->parseID($this->ex[0]);
					$join['chan'] = str_replace(array(chr(10),chr(13),':'), '', $this->ex[2]);
					if ($join['nick'] == $this->me) {
					} elseif (checkOP($this->ex[0]) === true) {
						$this->send_data('MODE ' . $join['chan'] . ' +o ' . $join['nick']);
						$this->send_data("NOTICE", $join['nick'] . ' Hello.');
					}
					$this->index('<b>' . $join['nick'] . '</b> joined <b>' . $join['chan'] . '</b>.');
					break;
				case "KICK":
					$nID          = $this->parseID($this->ex[0]);
					$nID['chan']  = str_replace(array(chr(10),chr(13),':'), '', $this->ex[2]);
					$nID['user2'] = str_replace(array(chr(10),chr(13),':'), '', $this->ex[3]);
					$nID['msg']   = "";
					if (isset($this->ex[4])) { for ($i = 4; $i < count($this->ex); $i++) { $nID['msg'] .= $this->ex[$i] . " "; } }
					$nID['msg'] = substr($nID['msg'], 1, strlen($nID['msg']) - 1);
					if (strtolower($nID['user2']) == strtolower($this->me)) { $this->join_channel($nID['chan']); }
					$this->index('<<b>' . $nID['nick'] . '</b>:' . $nID['chan'] . '> kicked <b>' . $nID['user2'] . '</b> : <b>' . $nID['msg'] . '</b>');
					break;
				case "MODE":
					$mode         = $this->parseID($this->ex[0]);
					$mode['chan'] = str_replace(array(chr(10),chr(13),':'), '', $this->ex[2]);
					$mode['mode'] = str_replace(array(chr(10),chr(13),':'), '', $this->ex[3]);
					$mode['user'] = "";
					if (isset($this->ex[4])) { for ($i = 4; $i < count($this->ex); $i++) { $mode['user'] .= $this->ex[$i] . " "; } }
					$this->index('<<b>' . $mode['nick'] . '</b>:' . $mode['chan'] . '> <b>' . $mode['mode'] . ' ' . $mode['user'] . '</b>.');
					break;
				case "NICK":
					$nID          = $this->parseID($this->ex[0]);
					$nID['nickN'] = substr($this->ex[2], 1, strlen($this->ex[2]) - 1);
					if (strtolower($nID['nick']) != strtolower($this->me)) {
						switch ($this->nickerr) {
							case "0":
								break;
							case "1":
								if (strtolower($nID['nick']) == strtolower($this->getnick)) { $this->send_data('NICK', $this->getnick); }
								break;
							case "2":
								if (strtolower($nID['nick']) == strtolower($this->getnick)) { $this->send_data('NICK', $this->getnick); }
								elseif (strtolower($nID['nick']) == strtolower($config['nick'])) { $this->send_data('NICK', $config['nick']); }
								break;
						}
					} else {
						$this->me = str_replace(array(chr(10),chr(13),':'), '', $nID['nickN']);
						if (strtolower($this->me) == strtolower($this->getnick)) { $this->nickerr = 0; }
						elseif (strtolower($this->me) == strtolower($config['nick'])) { $this->nickerr = 1; }
						else { $this->nickerr = 2; }
					}
					$this->index('<b>' . $nID['nick'] . '</b> change to <b>' . $nID['nickN'] . '</b>.');
					break;
				case "NOTICE":
					if (isset($this->ex[3])) {
						$nID         = $this->parseID($this->ex[0]);
						$nID['chan'] = $this->ex[2];
						$nID['msg']  = "";
						for ($i = 3; $i < count($this->ex); $i++) {
								$nID['msg'] .= $this->ex[$i] . " ";
						}
						$nID['msg'] = substr($nID['msg'], 1, strlen($nID['msg']) - 1);
						$this->index('<<b>' . $nID['nick'] . '</b>:' . $nID['chan'] . '>:NOTICE: <b>' . $nID['msg'] . '</b>');
					}
					break;
				case "PART":
					$part         = $this->parseID($this->ex[0]);
					$part['chan'] = str_replace(array(
						chr(10),
						chr(13),
						':'
					), '', $this->ex[2]);
					$this->index('<b>' . $part['nick'] . '</b> left <b>' . $part['chan'] . '</b>.');
					break;
				case "PRIVMSG":
					if (checkOP($this->ex[0]) === true) {
						$command = str_replace(array(
							chr(10),
							chr(13)
						), '', $this->ex[3]);
						switch ($command) {
							case ':!sv':
								$nID = $this->parseMSG($data);
								if (substr($nID['chan'], 0, 1) == "#") {
									$nID['nick'] = $nID['chan'];
								}
								$this->send_data('PRIVMSG ' . $nID['nick'] . ' :' . ' [' . $this->sv . ']' . ' T[' . time_elapsed_A(time() - $this->sTime) . ']' . ' Lvl[' . $this->nickerr . ']' . ' G[' . $this->getnick . ']' . ' B[' . $config['nick'] . ']' . ' N[' . $this->me . ']');
								break;
							case ':!r':
								$nID        = $this->parseMSG($data);
								$nID['msg'] = substr($nID['msg'], 3, strlen($nID['msg']) - 3);
								$this->send_data($nID['msg']);
								break;
							case ':!j':
								$this->send_data('JOIN ' . $this->ex[4]);
								break;
							case ':!p':
								$this->send_data('PART ' . $this->ex[4]);
								break;
							case ':!op':
								$nID = $this->parseMSG($data);
								if (isset($this->ex[5])) { $this->send_data('MODE ' . $this->ex[4] . ' +o ' . $this->ex[5]); }
								elseif (isset($this->ex[4]) && substr($nID['chan'], 0, 1) == "#") { $this->send_data('MODE ' . $nID['chan'] . ' +o ' . $this->ex[4]); }
								elseif (substr($nID['chan'], 0, 1) == "#") { $this->send_data('MODE ' . $nID['chan'] . ' +o ' . $nID['nick']); }
								break;
							case ':!dop':
								$nID = $this->parseMSG($data);
								if (isset($this->ex[5])) { $this->send_data('MODE ' . $this->ex[4] . ' -o ' . $this->ex[5]); }
								elseif (isset($this->ex[4]) && substr($nID['chan'], 0, 1) == "#") { $this->send_data('MODE ' . $nID['chan'] . ' -o ' . $this->ex[4]); }
								elseif (substr($nID['chan'], 0, 1) == "#") { $this->send_data('MODE ' . $nID['chan'] . ' -o ' . $nID['nick']); }
								break;
							case ":!m":
								$nID        = $this->parseID($this->ex[0]);
								$nID['msg'] = "";
								for ($i = 5; $i < count($this->ex); $i++) { $nID['msg'] .= $this->ex[$i] . " "; }
								$this->send_data('PRIVMSG ' . $this->ex[4] . ' :' . $nID['msg']);
								break;
							case ':!s':
								$nID        = $this->parseMSG($data);
								$nID['msg'] = substr($nID['msg'], 3, strlen($nID['msg']) - 3);
								if ($nID['chan'] == $this->me) { $nID['chan'] = $nID['nick']; }
								$this->send_data('PRIVMSG ' . $nID['chan'] . ' :' . $nID['msg']);
								break;
							case ':!dlog':
								$this->deleteLog();
								break;
							case ':!dchat':
								$this->deleteChat();
								break;
							case ':!ddb':
								$this->deleteChat();
								$this->deleteLog();
								break;
							case ':!reboot':
								$nID        = $this->parseMSG($data);
								$nID['msg'] = substr($nID['msg'], 8, strlen($nID['msg']) - 8);
								$this->send_data('QUIT :' . $nID['msg']);
								echo "<meta http-equiv=\"refresh\" content=\"5\">";
								exit;
							case ':!q':
								$nID        = $this->parseMSG($data);
								$nID['msg'] = substr($nID['msg'], 3, strlen($nID['msg']) - 3);
								$this->send_data('QUIT :' . $nID['msg']);
								exit;
							default:
								if ($this->ex[1] != "") {
									$nID = $this->parseMSG($data);
									$this->index('<<b>' . $nID['nick'] . '</b>:' . $nID['chan'] . '> <b>' . $nID['msg'] . '</b>');
								}
								break;
						}
					} else {
						$nID = $this->parseMSG($data);
						$this->index('<<b>' . $nID['nick'] . '</b>:' . $nID['chan'] . '> <b>' . $nID['msg'] . '</b>');
					}
					break;
				case "QUIT":
					$nID = $this->parseID($this->ex[0]);
					switch ($this->nickerr) {
						case "0":
							break;
						case "1":
							if (strtolower($nID['nick']) == strtolower($this->getnick)) { $this->send_data('NICK', $this->getnick); }
							break;
						case "2":
							if (strtolower($nID['nick']) == strtolower($this->getnick)) { $this->send_data('NICK', $this->getnick); }
							elseif (strtolower($nID['nick']) == strtolower($config['nick'])) { $this->send_data('NICK', $config['nick']); }
							break;
					}
					$this->index('<b>' . $nID['nick'] . '</b> quit.');
					break;
				case "TOPIC":
					if (isset($this->ex[3])) {
						$nID = $this->parseMSG($data);
						$this->index('<<b>' . $nID['nick'] . '</b>:' . $nID['chan'] . '> sets Topic to: <b>' . $nID['msg'] . '</b>');
					}
					break;
			}
		}
		if ($this->online == 0) {
			if ((time() - $this->sTime) >= 20) {
				$this->log('Server Connection Timeout.. Restarting. ' . $this->sTime . ' ' . (time() - $this->sTime));
				echo "<meta http-equiv=\"refresh\" content=\"5\">";
				exit;
			}
		} elseif ($this->nickerr != 0) {
			if ((time() - $this->getnicktimer) >= 60) {
				switch ($this->nickerr) {
					case "0":
						$this->getnicktimer = time();
						break;
					case "1":
						$this->getnicktimer = time();
						$this->send_dataQ('NICK', $this->getnick);
						break;
					case "2":
						$this->getnicktimer = time();
						$this->send_dataQ('NICK', $config['nick']);
						$this->send_dataQ('NICK', $this->getnick);
						break;
				}
			}
		}
		usleep($this->cycletime);
		$this->main($config);
	}
	function __construct($config)
	{
		$this->sTime        = time();
		$this->getnicktimer = time();
		$this->log('Bot Initiated by ip ' . $_SERVER['REMOTE_ADDR'] . ' ' . $this->sTime);
		$this->socket = fsockopen($config['server'], $config['port']);
		$this->login($config);
		$this->main($config);
	}
	function login($config)
	{
		$this->send_dataQ('USER', $config['nick'] . ' 5ecret ' . $config['nick'] . ' :' . $config['name']);
		$this->send_dataQ('NICK', $this->getnick);
	}
	function writeDB($sql)
	{
		$conn = new mysqli($this->db['ip'], $this->db['username'], $this->db['password'], $this->db['name']);
		if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }
		$conn->query($sql);
		$conn->close();
	}
	function log($data)
	{
		if ($this->writeLog == 1) {
			$sql = "INSERT INTO ChatLog (date, time, text)
					VALUES ('" . date("Y/m/d") . "', '" . date("h:i:sa") . "', '" . $data . "')";
			$this->writeDB($sql);
		}
		if ($this->echoLog == 1) { echo ($data); }
	}
	function index($data)
	{
		if ($this->writeIndex == 1) {
			$sql = "INSERT INTO Chat (date, time, text)
					VALUES ('" . date("Y/m/d") . "', '" . date("h:i:sa") . "', '" . $data . "')";
			$this->writeDB($sql);
		}
		if ($this->echoIndex == 1) { echo ($data); }
	}
	function deleteLog()
	{
		$sql = "TRUNCATE TABLE ChatLog";
		$this->writeDB($sql);
	}
	function deleteChat()
	{
		$sql = "TRUNCATE TABLE Chat";
		$this->writeDB($sql);
	}
	function send_data($cmd, $msg = null)
	{
		if ($msg == null) {
			fputs($this->socket, $cmd . "\r\n");
			$this->log($cmd . "\r\n");
		} else {
			fputs($this->socket, $cmd . ' ' . $msg . "\r\n");
			$this->log($cmd . ' ' . $msg . "\r\n");
		}
	}
	function send_dataQ($cmd, $msg = null)
	{
		if ($msg == null) { fputs($this->socket, $cmd . "\r\n"); }
		else { fputs($this->socket, $cmd . ' ' . $msg . "\r\n"); }
	}
	function join_channel($channel)
	{
		if (is_array($channel)) { foreach ($channel as $chan) { $this->send_data('JOIN', $chan); } }
		else { $this->send_data('JOIN', $channel); }
	}
	function parseID($id)
	{
		$nID['nick'] = substr($id, 0, strpos($id, '!'));
		if (substr($nID['nick'], 0, 1) == ":") { $nID['nick'] = substr($nID['nick'], 1, strlen($nID['nick']) - 1); }
		$nID['ident'] = substr($id, strpos($id, '!') + 1, strpos($id, '@') - strpos($id, '!') - 1);
		$nID['host']  = substr($id, strpos($id, '@') + 1, strlen($id) - strpos($id, '@') - 1);
		return $nID;
	}
	function parseMSG($data)
	{
		$exx         = explode(' ', $data);
		$nID         = $this->parseID($exx[0]);
		$nID['chan'] = $exx[2];
		$nID['msg']  = "";
		for ($i = 3; $i < count($exx); $i++) {
			$tmpStr = $exx[$i];
			if ($hyplnk = findUrl($tmpStr)) {
				if ($this->hyplnkConv) { $tmpStr = $hyplnk[2]; }
				if ($nID['chan'] == $this->me) { $this->send_data('PRIVMSG ' . $nID['nick'] . ' : ' . getTitle($hyplnk[1])); }
				else {
					if (is_array($this->hyplnkChan)) {
						foreach ($this->hyplnkChan as $channel) {
							if ($channel == $nID['chan']) {
								$this->send_data('PRIVMSG ' . $nID['chan'] . ' : ' . getTitle($hyplnk[1]));
							}
						}
					} else {
						if ($this->hyplnkChan == $nID['chan']) {
							$this->send_data('PRIVMSG ' . $nID['chan'] . ' : ' . getTitle($hyplnk[1]));
						}
					}
				}
			}
			$nID['msg'] .= $tmpStr . " ";
		}
		$nID['msg'] = substr($nID['msg'], 1, strlen($nID['msg']) - 1);
		return $nID;
	}
}
$bot = new IRCBot($config);
function time_elapsed_A($secs)
{
	$bit = array(
		'y' => $secs / 31556926 % 12,
		'w' => $secs / 604800 % 52,
		'd' => $secs / 86400 % 7,
		'h' => $secs / 3600 % 24,
		'm' => $secs / 60 % 60,
		's' => $secs % 60
	);
	foreach ($bit as $k => $v)
		if ($v > 0)
			$ret[] = $v . $k;
	return join(' ', $ret);
}
function time_elapsed_B($secs)
{
	$bit = array(
		' year' => $secs / 31556926 % 12,
		' week' => $secs / 604800 % 52,
		' day' => $secs / 86400 % 7,
		' hour' => $secs / 3600 % 24,
		' minute' => $secs / 60 % 60,
		' second' => $secs % 60
	);
	foreach ($bit as $k => $v) {
		if ($v > 1)
			$ret[] = $v . $k . 's';
		if ($v == 1)
			$ret[] = $v . $k;
	}
	array_splice($ret, count($ret) - 1, 0, 'and');
	$ret[] = 'ago.';
	return join(' ', $ret);
}
function findUrl($text)
{
	$reg_exUrl = "/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/";
	if (preg_match($reg_exUrl, $text, $url)) {
		$rtn[0] = 1;
		$rtn[1] = $url[0];
		$rtn[2] = preg_replace($reg_exUrl, '<a href="' . $url[0] . '" target="_Blank" title="' . getTitle($url[0]) . '">' . $url[0] . '</a>', $text);
		$rtn[3] = $text;
		return $rtn;
	} else {
		return 0;
	}
}
function getTitle($Url)
{
	$str = file_get_contents($Url);
	if (strlen($str) > 0) {
		preg_match("/\<title\>(.*)\<\/title\>/", $str, $title);
		return $title[1];
	}
}
?>