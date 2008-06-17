<?php
// IRCd (IRC Server) written in PHP
// written and copyright 2008 Daniel Danopia (danopia) <me.phpircd@danopia.net> http://danopia.net/
// originally MonoNet protocol version 0.1 server
// originally written and copyright 2008 Nick Markwell (RockerMONO/duck)
// feel free to edit and distribute as long as you leave all original credit for this

include('config.php'); // Open config file, read it over before you run the server!

$conn = array(); // Array of connections/clients
$channels = array(); // Info on each channel

/*
 * Info on each user. Referenced by objects in $conn.
 * Used to bugfix a problem concerning outdated user arrays in channels.
 * Bug reported by b4, June 16, 2008 while testing /oper
 * Bug fixed by danopia, June 16, 2008
 */
$u_info = array();
          

// socket_noprmal_read pasted off php.net, thanks to the
// poster. used to correctly read to newlines on win32
$sockets = array();
$queues = array();
$sock_num = 0;
function socket_normal_read($socket)
{
	global $config, $sockets, $queues, $sock_num;
	for ($i = 0; isset ($sockets[$i]) && $socket != $sockets[$i]; $i++);

	if (!isset ($sockets[$i])) {
		$sockets [$sock_num] = $socket;
		$queues [$sock_num++] = "";
	}

	$recv = socket_read ($socket, $config['max_len']);
//$recv = str_replace($recv, "\r", "");
	if ($recv === "") {
		if (strpos ($queues[$i], $config['line_ending']) === false)
			return false;
	}
	else if ($recv !== false) {
		$queues[$i] .= $recv;
	}

	$pos = strpos ($queues[$i], $config['line_ending']);
	if ($pos === false)
		return "";
	$ret = substr ($queues[$i], 0, $pos);
	$queues[$i] = substr ($queues[$i], $pos+1);

	return $ret;
}

// Validates a nick using regex (also controls max nick length)
function validate_nick($nick)
{
	return preg_match('/^[a-zA-Z\[\]_|`^][a-zA-Z0-9\[\]_|`^]{0,29}$/', $nick);
}

// Validates a channel name using regex (also controls max channel length)
function validate_chan($chan)
{
	return preg_match('/^#[a-zA-Z0-9`~!@#$%^&*\(\)\'";|}{\]\[.<>?]{0,29}$/', $chan);
}

// Removes a value from array, thanks to duck for providing this function
function array_removal($val, &$arr)
{
	$i = array_search($val, $arr);
	if($i === false) return false;
	$arr = array_merge(array_slice($arr, 0, $i), array_slice($arr, $i + 1));
	return true;
}

// Removes a client with a certian nick from an array of clients, using array_removal
function nick_removal($nick, &$arr)
{
	foreach($arr as $id => $him)
	{
		if(strtolower($him['nick']) == strtolower($nick))
			return array_removal($him, $arr);
	}
}

// Level of error reporting in console
//error_reporting(E_ERROR | E_PARSE);
error_reporting(E_ALL);

set_time_limit(0); // Run forever

$version = 0.0; // Server version
$ver_str = (string)$version;
if(!stristr($ver_str, '.'))
{
	$version = number_format($version, 1);
	$ver_str = (string)$version;
}

// Create listen socket and bind
$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_bind($sock, '0.0.0.0', $config['port']);
if (socket_last_error() != 0)
	echo socket_last_error() . ': ' . socket_strerror(socket_last_error());
socket_listen($sock, $config['max_users']);

// Nonblocking socket
socket_set_nonblock($sock);

// Old code, can be used to see the server status, removed as soon as danopia saw it xD
//file_put_contents("status.txt", "up");

echo "Running...";

// Main loop
while (true)
	include('connection_handler.php');

// Lookup a DNS record, with a timeout.  Namely used for reverse DNS.
function dns_timeout($ip) {
	$res = `nslookup -timeout=3 -retry=1 $ip`;
	if (preg_match('/\nName:(.*)\n/', $res, $out)) {
		return trim($out[1]);
	} else {
		return $ip;
	}
}

// Kills a certian user, used by /kill, QUIT, netsplits, etc.
function kill($who, $reason)
{
	global $channels, $conn, $u_info;
	$sentto = array($who); // Who received a QUIT packet already?
	foreach($channels as &$channel) // Loop through channels
	{
		if(in_array($who, $channel['nicks'])) // Was user x in this channel?
		{
			foreach($channel['nicks'] as $user) // Loop through channel nick list
			{
				if(!in_array($user, $sentto)) // Make sure user didn't get QUIT yet
				{
					send($user, ':' . $who['nick'] . '!' . $who['ident'] . '@' . $u_info[$who['sock']]['cloak'] . ' QUIT :' . $reason);
					$sentto[] = $user;
				}
			}
			nick_removal($who['nick'], $channel['nicks']); // Remove the killed user from the channel
		}
	}
	send($who, 'ERROR :Closing Link: ' . $who['nick'] . '[' . $who['host'] . '] (' . $reason . ')');
	socket_close($who['sock']); // Close socket
	array_removal($u_info[$who['sock']], $u_info); // Remove from the info array - part of the bugfix stated above
	array_removal($who, $conn); // Remove socket from listing
}

// Send a packet to a user and log to console
function send($who, $text)
{
	socket_write($who['sock'], $text . "\r\n");
	echo $who['nick'] . ' >>> ' . $text . "\r\n";
	// if (socket_last_error($who['sock']) != 0)
		// echo socket_last_error($who['sock']) . ': ' . socket_strerror(socket_last_error($who['sock'])) . "\r\n";
}

?>
