#!/usr/bin/php
<?php
require_once(dirname(__FILE__).'/lib/common.php');

$cfg = readConfig();

/* Scan all adapters */

foreach($cfg['adapters'] as $adapter)
	{
	echo "\nTemps for ".$adapter['name'].": ".implode(', ', getTemps($adapter))."\n";
	$c = getCore($adapter);
	echo 'Clocks: '.$c['gpu']['cur'].'/'.$c['mem']['cur']."\n";
	echo 'GPU Load: '.$c['gpu']['load'].'%'."\n";
	$tmp = getFan($adapter);
	if ($tmp<-1) echo "Fan: Error\n";
	else if ($tmp==-1) echo "Fan: (Off)\n";
	else echo "Fan: ".$tmp."%\n";

	$st = getStatus($adapter);
	echo "Status: ".print_r($st, 1)."\n";

	if (time() - $st['timestamp']>30) echo "ERROR - Miner not running!\n";
	else if ($st['hashrate']<1) echo "ERROR - Miner hashrate 0!\n";
	}

die("\n");
