<?php

function getTemps($adapter)
{
$cmd = 'DISPLAY=%D aticonfig --odgt --adapter=%i';
$cmd = adapt($cmd, $adapter);

$ret = shell_exec(escapeshellcmd($cmd));

$ma = array();
$mi = preg_match_all('/^\s+Sensor ([0-9]+): Temperature - ([0-9\.]+) C$/m', $ret, $ma, PREG_SET_ORDER);
if ($mi<1) return(false);
$data = array();
foreach($ma as $match)
	{
	$data[$match[1]] = (float)$match[2];
	}
return($data);
}

function getCore($adapter)
{
$cmd = 'DISPLAY=%D aticonfig --odgc --adapter=%i';

$cmd = adapt($cmd, $adapter);
$ret = shell_exec(escapeshellcmd($cmd));

$data = array('gpu'=>array(), 'mem'=>array());

$ma = array();
$mi = preg_match('/Current Clocks :\s+([0-9]+)\s+([0-9]+)/', $ret, $ma);
if ($mi==1)
	{
	$data['gpu']['cur'] = (int)$ma[1];
	$data['mem']['cur'] = (int)$ma[2];
	}

$ma = array();
$mi = preg_match('/Current Peak :\s+([0-9]+)\s+([0-9]+)/', $ret, $ma);
if ($mi==1)
	{
	$data['gpu']['peak'] = (int)$ma[1];
	$data['mem']['peak'] = (int)$ma[2];
	}

$ma = array();
$mi = preg_match('/GPU load :\s+([0-9]+)%/', $ret, $ma);
if ($mi==1)
	{
	$data['gpu']['load'] = (int)$ma[1];
	}

return($data);
}

function getFan($adapter)
{
if (isOff($adapter['fan']) || !isset($adapter['display'])) return(-1);

$cmd = 'DISPLAY=%d aticonfig --pplib-cmd "get fanspeed 0"';

$cmd = adapt($cmd, $adapter);
$ret = shell_exec(escapeshellcmd($cmd));

$ma = array();
$mi = preg_match('/Fan Speed:\s*([0-9]+)%/', $ret, $ma);

if ($mi==1) return((int)$ma[1]);
return(-2);
}

function getStatus($adapter)
{
if (!isset($adapter['status_file']) || isOff($adapter['status_file']) || isOff($adapter['status_type'])) return(-1);
if (!file_exists($adapter['status_file'])) return(-2);

$ret = @file_get_contents($adapter['status_file']);
if ($ret === false) return(-3);

if ($adapter['status_type'] == 'phoenix')
	{
//[26/05/2011 21:10:28] [343.71 Mhash/sec] [29 Accepted] [0 Rejected] [RPC (+LP)]
	$prefixes = getPrefixes();

	$ma = array();
	$mi = preg_match('/^\[([\d:\/ ]+?)\] \[([\d.]+) (['.implode('',array_keys($prefixes)).'])hash\/sec\] \[(\d+) Accepted\] \[(\d+) Rejected\]/', $ret, $ma);
	if ($mi != 1) return(-4);

	$dt = DateTime::createFromFormat('d/m/Y H:i:s', $ma[1]);

	$data = array('hashrate'=>((float)$ma[2])*$prefixes[$ma[3]], 'timestamp'=>$dt->getTimestamp(), 'datetime'=>$ma[1], 'accepted'=>$ma[4], 'rejected'=>$ma[5]);
	}
else if ($adapter['status_type'] == 'hashrate')
	{
	$mt = filemtime($adapter['status_file']);
	$data = array('hashrate'=>(float)$ret, 'timestamp'=>$mt, 'datetime'=>date('d/m/Y H:i:s', $mt));
	}
else
	{
	return(-5);
	}

return($data);
}
