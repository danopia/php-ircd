<?php

//
// IRCd (IRC Server) written in PHP
// written and copyright 2008 Daniel Danopia
//     (danopia) <me@danopia.net> http://danopia.net/
//
// originally MonoNet protocol version 0.1 server
// originally written/copyright 2008 Nick Markwell (RockerMONO/duck)
//
// feel free to edit and distribute as long as you leave all original
// credit for this
//
// config.php - June 15, 2008 by danopia
// Read over the whole file before you run the server!


$config = array(); // Array to store configoration values
////////////////////////////////////\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
//                            Simple stuff                            \\
////////////////////////////////////\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
//
// (string) Name of the server. Must be a valid domain name, doens't
// have to exist though. You might want to have it acutally resolve to
// the server IP. This is used in the onconnect greet, server notices,
// /links, /map, /who, etc.
//
// Examples: homebrew.danopia.no-ip.org
//           zeus.devnode.org
//           card.freenode.net
//           irc.hackthissite.org
//           locahost. (xD)
//
$config['name'] = 'homebrew.danopia.no-ip.org';

//
// (string) Name of the network. Should be global accross a network
// (once php-ircd links). Make it short and sweet! Can't have spaces.
//
// Examples: DevNet
//           DevNode
//           Freenode
//           HackThisSite
//           Localhost
//
$config['net'] = 'PHP-IRCd';

//
// (number) Maximum packet length, in bytes. 512 is the standard.
//
$config['max_len'] = 512;

//
// (number) Maximum amount of users that can be connected to the IRCd
// at ay time. When links work, this will only be local client but will
// include server links.
//
// Prefered range: 10-100
// Default: 25
//
$config['max_users'] = 25;

//
// (number) TCP/IP port that will be listened on for incoming clients.
//
// Range: Any TCP/IP port (1-51something)
// Default: 6667 (Standard)
//
$config['port'] = 6667;



$config['bind_ip'] = '0.0.0.0';
//
// (string) Line ending to be used in sockets. I use \n because it
// seems to work the best.
//
$config['line_ending'] = "\r\n";

//
// (string) Line ending to be used in muliline config values. I use a
// normal newline so it is consistant. You probably can leave this alone
//
$config['line_ending_conf'] = "
";

//
// (string) MOTD (Message of the Day). Shown to everyone when they
// connect and/or use the /motd command.
//
// You may use newlines.
//
$config['motd'] = "THIS IS THE MESSAGE OF THE DAY!

Welcome to my PHP IRCd test server! PHP IRCd is registered on SourceForge.net
<http://sf.net/projects/php-ircd>

Please submit any bugs you find.

#botters is the main channel on here!

/OPER john asdf";

////////////////////////////////////\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
//             Arrays (for linking, opering, and the sort)            \\
////////////////////////////////////\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
//
// ((string,string)[]) O:Lines
// O:Lines define opers. You oper with /oper <username> <password>
// (without the < and >, of course). You may deifne multiple O:Lines.
//
// Definition syntax:
// $config['opers']['username'] = 'password';
//
// Example:
// $config['opers']['john'] = 'asdf'; // Oper with /oper john adsf
//
$config['opers'] = array();
$config['opers']['john'] = 'asdf';
?>