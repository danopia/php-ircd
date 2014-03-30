<?php

/* 	// This code somehow epic failed.
  if ($new_sock = @socket_accept($sock))
  {
  // if (sizeof($conn) == MAX_USERS) {
  //	socket_write($x, 'Server full. Closing down.');
  //}
  //else
  //{
  $me = array(
  'nick'	=> null,
  'sock'	=> $new_sock,
  'buf'		=> '',
  'ip'		=> '',
  'host'	=> '',
  'cloak'	=> '');
  socket_getpeername($new_sock, $me['ip']);
  $me['host'] = dns_timeout($me['ip']);
  $conn[] = $me;
  socket_set_nonblock($new_sock);
  send($me, ':' . $config['name'] . ' NOTICE AUTH :*** Looking up your hostname...');
  send($me, ':' . $config['name'] . ' NOTICE AUTH :*** Found your hostname');
  //}
  }
 */


if ($x = @socket_accept($sock)) { // Is there a new socket?
    // if (sizeof($conn) == MAX_USERS) {
    //	socket_write($x, 'Server full. Closing down.');
    //}
    //else
    //{ 
    send(array('nick' => 'new user', 'sock' => $x), ':' . $config['name'] . ' NOTICE AUTH :*** Looking up your hostname...');
    socket_getpeername($x, $ip); // Get their IP
    $host = dns_timeout($ip); // Reverse DNS
    socket_set_nonblock($x); // Nonblocking FTW
    send(array('nick' => 'new user', 'sock' => $x), ':' . $config['name'] . ' NOTICE AUTH :*** Found your hostname');
    $conn[] = array(// Add the user
        'nick' => null,
        'sock' => $x,
        'buf' => '',
        'ip' => $ip,
        'ident' => null,
        'realname' => null,
        'host' => $host);
    $u_info[$x] = array(// Add the info array
        'oper' => false,
        'cloak' => $host);
    //}
}

foreach ($conn as &$me) { // Loop through connections
    while (false != $c = trim(@socket_normal_read($me['sock']))) { // Read packets
        echo $me['nick'] . ' <<< ' . $c . "\n"; // Log to console
        $args = explode(' ', $c); // Get params
        switch (strtolower($args[0])) { // yay tabbing jump. srsly reduces file size.
            case 'nick':
                $newnick = $args[1];
                $taken = false; // Is nick in use?
                // Sometimes clients send :nick instead of nick
                if (strpos($c, ' :') !== false)
                    $newnick = substr($c, strpos($c, ' :') + 2);

                if (!validate_nick($newnick)) { // Invalid nick
                    send($me, ':' . $config['name'] . ' 431 ' . $newnick . ' :Erroneous Nickname: You fail.');
                    break;
                }

                foreach ($conn as $him) { // Check if nick is in use
                    if (strtolower($him['nick']) == strtolower($newnick))
                        $taken = true;
                }

                if ($taken) { // it IS in use, after all.
                    send($me, ':' . $config['name'] . ' 433 ' . (($me['nick'] == null) ? '*' : $me['nick']) . ' ' . $newnick . ' :Nickname is already in use.');
                    break;
                }

                if ($me['nick'] == null) { // Is this the initial NICK?
                    $me['nick'] = $newnick;
                    send($me, 'PING :tu.madre'); // Send ping and wait for USER. The ping is highly random btw.
                } else {
                    $newme = $me; // Make a new user instance that will be inserted into channel nick lists
                    $newme['nick'] = $newnick;
                    $sentto = array(); // Who already got the NICK?
                    foreach ($channels as &$channel) { // Looking through the channels....
                        if (in_array($me, $channel['nicks'])) { // Is said user in this channel?
                            foreach ($channel['nicks'] as $user) { // Loop through the nicks in said channel
                                if (!in_array($user, $sentto)) { // User did not get the NICK yet
                                    send($user, ':' . $me['nick'] . '!' . $me['ident'] . '@' . $u_info[$me['sock']]['cloak'] . ' NICK ' . $newnick);
                                    $sentto[] = $user;
                                }
                            }
                            // Replace the channel listings with new nick. We need to search all the modes arrays too.
                            $arrs = array('nicks', 'owners', 'protected', 'oped', 'halfoped', 'voiced');
                            foreach ($arrs as $arr) {
                                if (in_array($me, $channel[$arr])) {
                                    nick_removal($me['nick'], $channel[$arr]);
                                    $channel[$arr][] = $newme;
                                }
                            }
                        }
                    }
                    $me['nick'] = $newnick;
                }
                break;

            case 'user':
                if (sizeof($args) < 5) { // Not enough params
                    send($me, ':' . $config['name'] . ' 461 :USER Not enough parameters');
                    break;
                }

                $newident = $args[1];
                $newrealname = $args[4];

                // Check for :
                if (strpos($c, ' :') !== false)
                    $newrealname = substr($c, strpos($c, ' :') + 2);

                if ($me['ident'] == null) { // You can only register once.
                    $me['ident'] = $newident;
                    $me['realname'] = $newrealname;
                    send($me, ':' . $config['name'] . ' 001 ' . $me['nick'] . ' :Welcome to the ' . $config['net'] . ' IRC Network ' . $me['nick'] . '!' . $me['ident'] . '@' . $me['host']);
                    send($me, ':' . $config['name'] . ' 002 ' . $me['nick'] . ' :Your host is ' . $config['name'] . ', running version Danoserv '.$config['version']);
                    send($me, ':' . $config['name'] . ' 003 ' . $me['nick'] . ' :This server was created Fri Jul 13 19:22:25 2007');
                    send($me, ':' . $config['name'] . ' 004 ' . $me['nick'] . ' ' . $config['name'] . ' Danoserv '.$config['version'].' iowghraAsORTVSxNCWqBzvdHtGp lvhopsmntikrRcaqOALQbSeIKVfMCuzNTGj');
                    send($me, ':' . $config['name'] . ' 005 ' . $newnick . ' CMDS=KNOCK,MAP,DCCALLOW,USERIP NAMESX SAFELIST HCN MAXCHANNELS=10 CHANLIMIT=#:10 MAXLIST=b:60,e:60,I:60 NICKLEN=30 CHANNELLEN=32 TOPICLEN=307 KICKLEN=307 AWAYLEN=307 MAXTARGETS=20 :are supported by this server');
                    send($me, ':' . $config['name'] . ' 005 ' . $newnick . ' WALLCHOPS WATCH=128 SILENCE=15 MODES=12 CHANTYPES=# PREFIX=(qaohv)~&@%+ CHANMODES=beI,kfL,lj,psmntirRcOAQKVCuzNSMTG NETWORK=' . $config['net'] . ' CASEMAPPING=ascii EXTBAN=~,cqnr ELIST=MNUCT STATUSMSG=~&@%+ EXCEPTS :are supported by this server');
                    send($me, ':' . $config['name'] . ' 005 ' . $me['nick'] . ' INVEX :are supported by this server');
                    send($me, ':' . $config['name'] . ' 251 ' . $me['nick'] . ' :There are ' . sizeof($conn) . ' users and 1 invisible on 1 servers');
                    send($me, ':' . $config['name'] . ' 252 ' . $me['nick'] . ' 1 :operator(s) online');
                    send($me, ':' . $config['name'] . ' 254 ' . $me['nick'] . ' ' . sizeof($channels) . ' :channels formed');
                    send($me, ':' . $config['name'] . ' 255 ' . $me['nick'] . ' :I have ' . (sizeof($conn) + 1) . ' clients and 0 servers');
                    send($me, ':' . $config['name'] . ' 265 ' . $me['nick'] . ' :Current Local Users: ' . (sizeof($conn) + 1) . '  Max: ' . (sizeof($conn) + 1));
                    send($me, ':' . $config['name'] . ' 266 ' . $me['nick'] . ' :Current Global Users: ' . (sizeof($conn) + 1) . '  Max: ' . (sizeof($conn) + 1));
                    send($me, ':' . $config['name'] . ' 375 ' . $me['nick'] . ' :- ' . $config['name'] . ' Message of the Day -');
                    send($me, ':' . $config['name'] . ' 372 ' . $me['nick'] . ' :- ' . implode("\r\n:" . $config['name'] . ' 372 ' . $me['nick'] . ' :- ', explode($config['line_ending_conf'], $config['motd'])));
                    send($me, ':' . $config['name'] . ' 376 ' . $me['nick'] . ' :End of /MOTD command.');
                } else { // Initial USER was already sent.
                    send($me, ':' . $config['name'] . ' 462 :You may not reregister');
                }
                break;

            case 'motd':
                send($me, ':' . $config['name'] . ' 375 ' . $me['nick'] . ' :- ' . $config['name'] . ' Message of the Day -');
                send($me, ':' . $config['name'] . ' 372 ' . $me['nick'] . ' :- ' . implode("\r\n:" . $config['name'] . ' 372 ' . $me['nick'] . ' :- ', explode($config['line_ending_conf'], $config['motd'])));
                send($me, ':' . $config['name'] . ' 376 ' . $me['nick'] . ' :End of /MOTD command.');
                break;

            case 'lusers':
                send($me, ':' . $config['name'] . ' 251 ' . $me['nick'] . ' :There are ' . sizeof($conn) . ' users and 1 invisible on 1 servers');
                send($me, ':' . $config['name'] . ' 252 ' . $me['nick'] . ' 1 :operator(s) online');
                send($me, ':' . $config['name'] . ' 254 ' . $me['nick'] . ' ' . sizeof($channels) . ' :channels formed');
                send($me, ':' . $config['name'] . ' 255 ' . $me['nick'] . ' :I have ' . (sizeof($conn) + 1) . ' clients and 0 servers');
                send($me, ':' . $config['name'] . ' 265 ' . $me['nick'] . ' :Current Local Users: ' . (sizeof($conn) + 1) . '  Max: ' . (sizeof($conn) + 1));
                send($me, ':' . $config['name'] . ' 266 ' . $me['nick'] . ' :Current Global Users: ' . (sizeof($conn) + 1) . '  Max: ' . (sizeof($conn) + 1));
                break;

            case 'version':
                send($me, ':' . $config['name'] . ' 005 ' . $me['nick'] . ' Danoserv '.$config['version'].' ' . $config['name'] . ' FinWXOoZE3 [Windows Server 22003 Service Pack 2=2309');
                send($me, ':' . $config['name'] . ' 005 ' . $me['nick'] . ' CMDS=KNOCK,MAP,DCCALLOW,USERIP NAMESX SAFELIST HCN MAXCHANNELS=10 CHANLIMIT=#:10 MAXLIST=b:60,e:60,I:60 NICKLEN=30 CHANNELLEN=32 TOPICLEN=307 KICKLEN=307 AWAYLEN=307 MAXTARGETS=20 :are supported by this server');
                send($me, ':' . $config['name'] . ' 005 ' . $me['nick'] . ' WALLCHOPS WATCH=128 SILENCE=15 MODES=12 CHANTYPES=# PREFIX=(qaohv)~&@%+ CHANMODES=beI,kfL,lj,psmntirRcOAQKVCuzNSMTG NETWORK=' . $config['net'] . ' CASEMAPPING=ascii EXTBAN=~,cqnr ELIST=MNUCT STATUSMSG=~&@%+ EXCEPTS :are supported by this server');
                send($me, ':' . $config['name'] . ' 005 ' . $me['nick'] . ' INVEX :are supported by this server');
                break;

            case 'invite':
                $who = $args[1];
                $target = $args[2];

                if (isset($channels[strtolower($target)])) { // Does the channel even exist?
                    $channel = $channels[strtolower($target)];
                    if (!in_array($me, $channel['nicks'])) { // Is user even in the channel?
                        send($me, ':' . $config['name'] . ' 442 ' . $me['nick'] . ' ' . $target . ' :You\'re not on that channel');
                        break;
                    }
                    $found = false; // Make sure we find the target
                    foreach ($conn as $him) { // Find target
                        if (strtolower($him['nick']) == strtolower($who))
                            $found = $him;
                    }
                    if ($found === false) { // User offline
                        send($me, ':' . $config['name'] . ' 401 ' . $me['nick'] . ' ' . $who . ' :No such nick');
                        break;
                    }
                    if (in_array($found, $channel['nicks'])) { // Is target already in the channel?
                        send($me, ':' . $config['name'] . ' 443 ' . $me['nick'] . ' ' . $who . ' ' . $target . ' :is already on channel');
                        break;
                    }
                    // Invite to channel
                    send($found, ':' . $me['nick'] . '!' . $me['ident'] . '@' . $u_info[$me['sock']]['cloak'] . ' INVITE ' . $who . ' ' . $target);
                } else { // Channel does not exist!
                    send($me, ':' . $config['name'] . ' 403 ' . $me['nick'] . ' ' . $target . ' :No such nick/channel');
                    break;
                }
                break;

            case 'privmsg':
                $target = $args[1];
                $message = substr($c, strpos($c, ' :') + 2);
                if (isset($channels[strtolower($target)])) {
                    foreach ($channels[strtolower($target)]['nicks'] as $user) {
                        // User is not self?
                        if ($user !== $me)
                            send($user, ':' . $me['nick'] . '!' . $me['ident'] . '@' . $u_info[$me['sock']]['cloak'] . ' PRIVMSG ' . $target . ' :' . $message);
                    }
                }
                else {
                    foreach ($conn as $him) { // Find target
                        if (strtolower($him['nick']) == strtolower($target))
                            send($him, ':' . $me['nick'] . '!' . $me['ident'] . '@' . $u_info[$me['sock']]['cloak'] . ' PRIVMSG ' . $target . ' :' . $message);
                    }
                }
                break;

            case 'whois':
                $target = $args[1];
                foreach ($conn as $him) {
                    if (strtolower($him['nick']) == strtolower($target)) { // Find target
                        send($me, ':' . $config['name'] . ' 311 ' . $me['nick'] . ' ' . $him['nick'] . ' ' . $him['ident'] . ' ' . $u_info[$him['sock']]['cloak'] . ' * :' . $him['realname']);

                        //send($me, ':' . $config['name'] . ' 307 ' . $me['nick'] . ' ' . $him['nick'] . ' :is a registered nick');

                        $chans = array();
                        foreach ($channels as $channel => &$channel_arr) { // Looking through the channels....
                            if (in_array($him, $channel_arr['nicks'])) { // Is said user in this channel?
                                $chans[] = $channel;
                            }
                        }
                        if (count($chans) > 0) {
                            send($me, ':' . $config['name'] . ' 319 ' . $me['nick'] . ' ' . $him['nick'] . ' :' . implode(' ', $chans));
                        }

                        send($me, ':' . $config['name'] . ' 312 ' . $me['nick'] . ' ' . $him['nick'] . ' ' . $config['name'] . ' :' . $config['name']);

                        if ($u_info[$him['sock']]['oper']) {
                            send($me, ':' . $config['name'] . ' 313 ' . $me['nick'] . ' ' . $him['nick'] . ' :is a Network Administrator');
                        }

                        //send($me, ':' . $config['name'] . ' 310 ' . $me['nick'] . ' ' . $him['nick'] . ' :is available for help.

                        send($me, ':' . $config['name'] . ' 318 ' . $me['nick'] . ' ' . $him['nick'] . ' :End of /WHOIS list.');
                    }
                }
                break;

            case 'oper':
                if (count($args) == 3) {
                    $user = $args[1];
                    $pass = $args[2];
                    if ((isset($config['opers'][$user])) && ($config['opers'][$user] == $pass)) {
                        $u_info[$me['sock']]['oper'] = true;
                        send($me, ':' . $config['name'] . ' 381 ' . $me['nick'] . ' :You have entered... the Twilight Zone!');
                        break;
                    }
                }
                send($me, ':' . $config['name'] . ' 491 ' . $me['nick'] . ' :Only few of mere mortals may try to enter the twilight zone');
                break;

            case 'kill':
                if ($u_info[$me['sock']]['oper']) { // You have to be opered!
                    if (count($args) >= 3) { // We need a reason
                        $target = $args[1];
                        $reason = $args[2];
                        if (strpos($c, ' :') !== false)
                            $reason = substr($c, strpos($c, ' :') + 2);

                        foreach ($conn as $him) { // Find target
                            if (strtolower($him['nick']) == strtolower($target))
                                kill($him, 'Killed (' . $me['nick'] . ' (' . $reason . '))');
                        }
                    }
                    else {
                        send($me, ':' . $config['name'] . ' 461 ' . $me['nick'] . ' KILL :Not enough parameters');
                    }
                } else {
                    send($me, ':' . $config['name'] . ' 481 ' . $me['nick'] . ' :Permission Denied- You do not have the correct IRC operator privileges');
                }
                break;

            case 'chghost':
                if ($u_info[$me['sock']]['oper']) { // You have to be opered!
                    if (count($args) >= 3) { // We need a target and mask
                        $target = $args[1];
                        $newmask = $args[2];

                        foreach ($conn as $him) { // Find target
                            if (strtolower($him['nick']) == strtolower($target))
                                $u_info[$him['sock']]['cloak'] = $newmask;
                        }
                    }
                    else {
                        send($me, ':' . $config['name'] . ' 461 ' . $me['nick'] . ' CHGHOST :Not enough parameters');
                    }
                } else {
                    send($me, ':' . $config['name'] . ' 481 ' . $me['nick'] . ' :Permission Denied- You do not have the correct IRC operator privileges');
                }
                break;

            case 'rehash':
                if ($u_info[$me['sock']]['oper']) { // You have to be opered!
                    include('config.php');
                    send($me, ':' . $config['name'] . ' 382 ' . $me['nick'] . ' config.php :Rehashing');
                } else {
                    send($me, ':' . $config['name'] . ' 481 ' . $me['nick'] . ' :Permission Denied- You do not have the correct IRC operator privileges');
                }
                break;

            case 'notice':
                $target = $args[1];
                $message = substr($c, strpos($c, ' :') + 2);
                if (isset($channels[strtolower($target)])) {
                    foreach ($channels[strtolower($target)]['nicks'] as $user) { // Find target
                        if (!($user == $me))
                            send($user, ':' . $me['nick'] . '!' . $me['ident'] . '@' . $u_info[$me['sock']]['cloak'] . ' NOTICE ' . $target . ' :' . $message);
                    }
                }
                else {
                    foreach ($conn as $him) { // Find target
                        if (strtolower($him['nick']) == strtolower($target))
                            send($him, ':' . $me['nick'] . '!' . $me['ident'] . '@' . $u_info[$me['sock']]['cloak'] . ' NOTICE ' . $target . ' :' . $message);
                    }
                }
                break;

            case 'join':
                $targets = explode(',', $args[1]); // This line allows channel lists (like JOIN #a,#b,#c)

                foreach ($targets as $target) { // Loop through each supplied channel
                    if (!validate_chan($target)) { // Valid name?
                        send($me, ':' . $config['name'] . ' 403 ' . $target . ' :No such channel');
                        break;
                    }

                    if (isset($channels[strtolower($target)])) { // Does the channel exist yet?
                        $channel = $channels[strtolower($target)];
                        if (in_array($me, $channel['nicks'])){
                            break; // Is user already in the channel?
                        }    

                        $names = $me['nick'];
                        foreach ($channel['nicks'] as $user) { // Inform everyone and also build up /names
                            send($user, ':' . $me['nick'] . '!' . $me['ident'] . '@' . $u_info[$me['sock']]['cloak'] . ' JOIN ' . $target);

                            $chars = ''; // Handle modes
                            $search = array('~' => 'owners', '&' => 'owners', '@' => 'oped', '%' => 'halfoped', '+' => 'voiced');
                            foreach ($search as $search_char => $search_name)
                                if (in_array($user, $channel[$search_name])) {
                                    $chars .= $search_char;
                                }
                            $names .= ' ' . $chars . $user['nick'];
                        }
                    } else { // Make a new channel
                        $channel = array('nicks' => array(), 'bans' => array(), 'excepts' => array(), 'invites' => array(), 'owners' => array(), 'protected' => array(), 'oped' => array($me), 'halfoped' => array(), 'voiced' => array(), 'modes' => 'nt');
                        $channels[strtolower($target)] = $channel;
                        $names = '@' . $me['nick']; // User is oped
                    }
                    $channel['nicks'][] = $me; // Add user to nicklist
                    send($me, ':' . $me['nick'] . '!' . $me['ident'] . '@' . $u_info[$me['sock']]['cloak'] . ' JOIN ' . $target);
                    if (isset($channel['topic'])) { // Send topic, if any
                        send($me, ':' . $config['name'] . ' 332 ' . $me['nick'] . ' ' . $target . ' :' . $channel['topic']);
                        send($me, ':' . $config['name'] . ' 333 ' . $me['nick'] . ' ' . $target . ' ' . $channel['topic_who'] . ' ' . $channel['topic_time']);
                    }
                    send($me, ':' . $config['name'] . ' 353 ' . $me['nick'] . ' = ' . $target . ' :' . $names);
                    send($me, ':' . $config['name'] . ' 366 ' . $me['nick'] . ' ' . $target . ' :End of /NAMES list.');
                    $channels[strtolower($target)] = $channel;
                }
                break;

            case 'topic':
                $target = $args[1];
                if (isset($channels[strtolower($target)])) { // Does the channel exist?
                    $channel = $channels[strtolower($target)];
                    if (strpos($c, ' :') === false) { // Not setting topic.
                        if (isset($channel['topic'])) { // Send topic, damnit!
                            send($me, ':' . $config['name'] . ' 332 ' . $me['nick'] . ' ' . $target . ' :' . $channel['topic']);
                            send($me, ':' . $config['name'] . ' 333 ' . $me['nick'] . ' ' . $target . ' ' . $channel['topic_who'] . ' ' . $channel['topic_time']);
                        } else { // There isn't a topic yet.
                            send($me, ':' . $config['name'] . ' 331 ' . $me['nick'] . ' ' . $target . ' :No topic is set.');
                        }
                    } else { // Setting the topic! fun!
                        $message = substr($c, strpos($c, ' :') + 2);
                        $channel['topic'] = $message;
                        $channel['topic_time'] = time();
                        $channel['topic_who'] = $me['nick'];
                        foreach ($channel['nicks'] as $user) { // Inform of new topic
                            send($user, ':' . $me['nick'] . '!' . $me['ident'] . '@' . $u_info[$me['sock']]['cloak'] . ' TOPIC ' . $target . ' :' . $message);
                        }
                        $channels[strtolower($target)] = $channel;
                    }
                } else { // No such channel.
                    send($me, ':' . $config['name'] . ' 403 ' . $me['nick'] . ' ' . $target . ' :No such channel');
                }
                break;

            case 'mode': // TODO: Code mode setting!
                $target = $args[1];
                if (isset($channels[strtolower($target)])) {// Channel exist?
                    $channel = $channels[strtolower($target)];
                    if (sizeof($args) == 2) { // Getting modes, yay
                        send($me, ':' . $config['name'] . ' 324 ' . $me['nick'] . ' ' . $target . ' +' . $channel['modes']);
                        send($me, ':' . $config['name'] . ' 329 ' . $me['nick'] . ' ' . $target . ' ' . time());
                    } else { // Still working on this, settings modes. ATM dones't do much.
//var_dump($args);
                        $mode_args = array_slice($args, 3);
                        $modes = $args[2];
//var_dump($mode_args);
                        $mode_changes = '';
                        $mode_change_args = array();
                        $mode_change_sign = -1;
                        $sign = true;

                        $oped = in_array($me, $channel['oped']);

                        for ($i = 0; $i < strlen($modes); $i++) { // Loop through each charactor in the mode request
                            $char = $modes{$i};
                            switch ($char) { // Go by which char it is!
                                case '+': // Set sign to ON
                                    $sign = true;
                                    break;
                                case '-': // Set sign to OFF
                                    $sign = false;
                                    break;
                                case 'o': // Op or deop
                                    if ($oped) {
                                        $found = false;
                                        $mode_target = strtolower(array_shift($mode_args));
                                        foreach ($channel['nicks'] as $user) { // Look for target
                                            if (strtolower($user['nick']) == $mode_target) {
                                                $found = $user;
                                            }
                                        }
                                        if ($found !== false) {
                                            if ($mode_change_sign == $sign) {
                                                $mode_changes = 'o';
                                            }
                                            if ($mode_change_sign == !$sign) {
                                                $mode_change_sign == $sign;
                                                $mode_changes = ($sign ? '+' : '-') . 'o';
                                            }
                                            if ($mode_change_sign == -1) {
                                                $mode_change_sign == $sign;
                                                $mode_changes = ($sign ? '+' : '-') . 'o';
                                            }
                                            $mode_change_args[] = $found['nick'];
                                            if ($sign && !in_array($found, $channel['oped'])) {
                                                $channel['oped'][] = $found;
                                            }
                                            if (!$sign && in_array($found, $channel['oped'])) {
                                                nick_removal($found['nick'], $channel['oped']);
                                            }
                                        }
                                    }
                                    break;
                                case 'v': // Voice/devoice
                                    if ($oped) {
                                        $found = false;
                                        $mode_target = strtolower(array_shift($mode_args));
                                        foreach ($channel['nicks'] as $user) { // Look for target
                                            if (strtolower($user['nick']) == $mode_target) {
                                                $found = $user;
                                            }
                                        }
                                        if ($found !== false) {
                                            if ($mode_change_sign == $sign) {
                                                $mode_changes = 'v';
                                            }
                                            if ($mode_change_sign == !$sign) {
                                                $mode_change_sign == $sign;
                                                $mode_changes = ($sign ? '+' : '-') . 'v';
                                            }
                                            if ($mode_change_sign == -1) {
                                                $mode_change_sign == $sign;
                                                $mode_changes = ($sign ? '+' : '-') . 'v';
                                            }
                                            $mode_change_args[] = $found['nick'];
                                            if ($sign && !in_array($found, $channel['voiced'])) {
                                                $channel['voiced'][] = $found;
                                            }
                                            if (!$sign && in_array($found, $channel['voiced'])) {
                                                nick_removal($found['nick'], $channel['voiced']);
                                            }
                                        }
                                    }
                                    break;
                            }
                        }

                        /* $message = substr($c, strpos($c, ' :') + 2);
                          $channel['topic'] = $message;
                          $channel['topic_time'] = time();
                          $channel['topic_who'] = $me['nick']; */
                        if ($mode_changes != '') {
                            foreach ($channel['nicks'] as $user) {
                                send($user, ':' . $me['nick'] . '!' . $me['ident'] . '@' . $u_info[$me['sock']]['cloak'] . ' MODE ' . $target . ' ' . $mode_changes . ' ' . implode(' ', $mode_change_args));
                            }
                            $channels[strtolower($target)] = $channel;
                        }
                    }
                } else { // No such channel (nick mdoes are not coded for yet)
                    send($me, ':' . $config['name'] . ' 403 ' . $me['nick'] . ' ' . $target . ' :No such channel');
                }
                break;

            case 'samode': // TODO: Code mode setting!
                if ($u_info[$me['sock']]['oper']) { // You have to be opered!
                    if (count($args) >= 3) { // We need a target and modes
                        $target = $args[1];

                        if (isset($channels[strtolower($target)])) {// Channel exist?
                            $channel = $channels[strtolower($target)];
                            if (sizeof($args) == 2) { // Getting modes, yay
                                send($me, ':' . $config['name'] . ' 324 ' . $me['nick'] . ' ' . $target . ' +' . $channel['modes']);
                                send($me, ':' . $config['name'] . ' 329 ' . $me['nick'] . ' ' . $target . ' ' . time());
                            } else { // Still working on this, settings modes. ATM dones't do much.
//var_dump($args);
                                $mode_args = array_slice($args, 3);
                                $modes = $args[2];
//var_dump($mode_args);
                                $mode_changes = '';
                                $mode_change_args = array();
                                $mode_change_sign = -1;
                                $sign = true;
                                for ($i = 0; $i < strlen($modes); $i++) { // Loop through each charactor in the mode request
                                    $char = $modes{$i};
                                    switch ($char) { // Go by which char it is!
                                        case '+': // Set sign to ON
                                            $sign = true;
                                            break;
                                        case '-': // Set sign to OFF
                                            $sign = false;
                                            break;
                                        case 'o': // Op or deop
                                            $found = false;
                                            $mode_target = strtolower(array_shift($mode_args));
                                            foreach ($channel['nicks'] as $user) { // Look for target
                                                if (strtolower($user['nick']) == $mode_target) {
                                                    $found = $user;
                                                }
                                            }
                                            if ($found !== false) {
                                                if ($mode_change_sign == $sign) {
                                                    $mode_changes = 'o';
                                                }
                                                if ($mode_change_sign == !$sign) {
                                                    $mode_change_sign == $sign;
                                                    $mode_changes = ($sign ? '+' : '-') . 'o';
                                                }
                                                if ($mode_change_sign == -1) {
                                                    $mode_change_sign == $sign;
                                                    $mode_changes = ($sign ? '+' : '-') . 'o';
                                                }
                                                $mode_change_args[] = $found['nick'];
                                                if ($sign && !in_array($found, $channel['oped'])) {
                                                    $channel['oped'][] = $found;
                                                }
                                                if (!$sign && in_array($found, $channel['oped'])) {
                                                    nick_removal($found['nick'], $channel['oped']);
                                                }
                                            }
                                            break;
                                        case 'v': // Voice/devoice
                                            $found = false;
                                            $mode_target = strtolower(array_shift($mode_args));
                                            foreach ($channel['nicks'] as $user) { // Look for target
                                                if (strtolower($user['nick']) == $mode_target) {
                                                    $found = $user;
                                                }
                                            }
                                            if ($found !== false) {
                                                if ($mode_change_sign == $sign) {
                                                    $mode_changes = 'v';
                                                }
                                                if ($mode_change_sign == !$sign) {
                                                    $mode_change_sign == $sign;
                                                    $mode_changes = ($sign ? '+' : '-') . 'v';
                                                }
                                                if ($mode_change_sign == -1) {
                                                    $mode_change_sign == $sign;
                                                    $mode_changes = ($sign ? '+' : '-') . 'v';
                                                }
                                                $mode_change_args[] = $found['nick'];
                                                if ($sign && !in_array($found, $channel['voiced'])) {
                                                    $channel['voiced'][] = $found;
                                                }
                                                if (!$sign && in_array($found, $channel['voiced'])) {
                                                    nick_removal($found['nick'], $channel['voiced']);
                                                }
                                            }
                                            break;
                                    }
                                }

                                /* $message = substr($c, strpos($c, ' :') + 2);
                                  $channel['topic'] = $message;
                                  $channel['topic_time'] = time();
                                  $channel['topic_who'] = $me['nick']; */
                                if ($mode_changes != '') {
                                    foreach ($channel['nicks'] as $user) {
                                        send($user, ':' . $config['name'] . ' MODE ' . $target . ' ' . $mode_changes . ' ' . implode(' ', $mode_change_args));
                                    }
                                    $channels[strtolower($target)] = $channel;
                                }
                            }
                        } else { // No such channel (nick mdoes are not coded for yet)
                            send($me, ':' . $config['name'] . ' 403 ' . $me['nick'] . ' ' . $target . ' :No such channel');
                        }
                    } else {
                        send($me, ':' . $config['name'] . ' 461 ' . $me['nick'] . ' SAMODE :Not enough parameters');
                    }
                } else {
                    send($me, ':' . $config['name'] . ' 481 ' . $me['nick'] . ' :Permission Denied- You do not have the correct IRC operator privileges');
                }
                break;

            case 'quit': // Nice and simple due to the kill() function. FUNCTIONS FTW!
                $message = 'Client exited';
                if (strpos($c, ' :') !== false)
                    $message = 'Quit: ' . substr($c, strpos($c, ' :') + 2);
                kill($me, $message);
                break;

            case 'userhost': // Return user's hostname.
                send($me, ':' . $config['name'] . ' 302 ' . $me['nick'] . ' :' . $me['nick'] . '=+' . $me['ident'] . '@' . $me['host']);
                break;

            case 'part': // TODO: Code lists (#a,#b,#c)
                $target = $args[1];
                $message = '';
                if (strpos($c, ' :') !== false)
                    $message = substr($c, strpos($c, ' :'));

                // O RLY? (Does the channel even exist?)
                if (isset($channels[strtolower($target)])) { // YA RLY
                    $channel = $channels[strtolower($target)];
                    if (!in_array($me, $channel['nicks'])) { // Make sure the user is there....
                        send($me, ':' . $config['name'] . ' 442 ' . $me['nick'] . ' ' . $target . ' :You\'re not on that channel');
                        break;
                    }

                    foreach ($channel['nicks'] as $user) { // Tell everyone about the part! But not about the whole.
                        send($user, ':' . $me['nick'] . '!' . $me['ident'] . '@' . $u_info[$me['sock']]['cloak'] . ' PART ' . $target . $message);
                    }
                } else { // NO RLY
                    send($me, ':' . $config['name'] . ' 403 ' . $me['nick'] . ' ' . $target . ' :No such channel');
                    break;
                }

                // Remove from channel listings. We need to search all the modes arrays too.
                $arrs = array('nicks', 'owners', 'protected', 'oped', 'halfoped', 'voiced');
                foreach ($arrs as $arr) {
                    nick_removal($me['nick'], $channel[$arr]);
                }

                $channels[strtolower($target)] = $channel; // Might not be needed if I used a & up there ^^ but who cares?
                break;

            case 'names':
                $target = $args[1];
                $names = '';
                if (isset($channels[strtolower($target)])) { // If channel exists....
                    $channel = $channels[strtolower($target)];
                    foreach ($channel['nicks'] as $user) { // Build list.
                        $chars = '';
                        $search = array('~' => 'owners', '&' => 'owners', '@' => 'oped', '%' => 'halfoped', '+' => 'voiced');
                        foreach ($search as $search_char => $search_name)
                            if (in_array($user, $channel[$search_name])) {
                                $chars .= $search_char;
                            }
                        $names .= $chars . $user['nick'] . ' ';
                    }
                }

                send($me, ':' . $config['name'] . ' 353 ' . $me['nick'] . ' = ' . $target . ' :' . $names);
                send($me, ':' . $config['name'] . ' 366 ' . $me['nick'] . ' ' . $target . ' :End of /NAMES list.');
                break;

            case 'ping': // PONG DAMNIT!
                if (strpos($args[1], ':') === false)
                    $args[1] = ':' . $args[1];

                send($me, ':' . $config['name'] . ' PONG ' . $config['name'] . ' ' . $args[1]);
                break;

// tabbing jumps
        } // select
    } // while
    // closed?
    $error = socket_last_error($me['sock']);
//echo $error;
    if (($error == 10053) || ($error == 10054) || ($error === false)) { // he failed.
        echo $me['nick'] . " has died.\r\n";
        kill($me, 'Something failed...');
        array_removal($me, $conn);
    }
} // foreach

sleep(1); // 100% CPU ftl
?>