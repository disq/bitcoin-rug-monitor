#!/usr/bin/php
<?php
require_once(dirname(__FILE__).'/lib/common.php');

$cfg = readConfig('rrddir');

/* Scan all adapters */

foreach($cfg['adapters'] as $adapter)
	{
	if (isOff($adapter['rrd'])) continue;

	echo "Updating for ".$adapter['name']."... ";

	$fn = getRrdFile($adapter);
	$dfn = $cfg['rrddir'].'/'.$fn;
	if (!file_exists($dfn))
		{
		echo "Could not locate ".$fn.", did you run gen-rrd first?\n";
		continue;
		}

	$hashrate = $acc = $rej = 0;
	$st = getStatus($adapter);
	if (is_array($st) && time() - $st['timestamp']<3)
		{
		$hashrate = (int)$st['hashrate'];
		$acc = (int)$st['accepted'];
		$rej = (int)$st['rejected'];
		}

	$temp = array_shift(getTemps($adapter));
	if ($temp === false) $temp = 'U';

	$load = 'U';
	$c = getCore($adapter);
	if ($c !== false && is_numeric($c['gpu']['load'])) $load = $c['gpu']['load'];

	$fan = 0;
	$tmp = getFan($adapter);
	if ($tmp>-1) $fan = $tmp;

	$list = array('N', $hashrate, $load, $temp, $fan, $acc, $rej);
	echo implode(':', $list).'... ';

	$ret = shell_exec(escapeshellcmd('rrdtool update '.$dfn.' '.implode(':', $list)));
	if ($ret!='')
		echo 'ERROR: '.$ret."\n";
	else
		echo "OK!\n";
	}

die("\n");
