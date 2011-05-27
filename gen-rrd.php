#!/usr/bin/php
<?php
require_once(dirname(__FILE__).'/lib/common.php');

$cfg = readConfig('rrddir');

$step = 60;

$steps_in_hour = 3600 / $step;
$steps_in_day = 86400 / $step;

$keep_values_for = 86400 / $steps_in_hour; //keep all values for one day
$arch1_avg = $steps_in_hour; // keep hourly averages
$arch1_keepfor = 30*86400 / $arch1_avg; // keep for 30 days

$arch2_avg = $steps_in_day; // keep daily averages
$arch2_keepfor = 365*86400 / $arch2_avg; // keep for one year

$rrd = "--step ".$step." --no-overwrite
 DS:hashrate:GAUGE:180:0:U
 DS:load:GAUGE:180:0:100
 DS:temp:GAUGE:180:0:150
 DS:fan:GAUGE:180:0:100
 DS:accepted:DERIVE:180:0:U
 DS:rejected:DERIVE:180:0:U
 RRA:AVERAGE:0.5:1:".$keep_values_for."
 RRA:AVERAGE:0.5:".$arch1_avg.":".$arch1_keepfor."
 RRA:AVERAGE:0.5:".$arch2_avg.":".$arch2_keepfor."
";

/* Scan all adapters */

$log = array();
echo "RRD dir: ".$cfg['rrddir']."/\n";
foreach($cfg['adapters'] as $adapter)
	{
	$fn = getRrdFile($adapter);
	$dfn = $cfg['rrddir'].'/'.$fn;
	if (file_exists($dfn))
		{
		echo "Found ".$fn.", skipped (".$adapter['name'].")\n";
		continue;
		}
	echo "Creating RRD for ".$adapter['name']." (".$fn.")... ";
	$ret = shell_exec(escapeshellcmd('rrdtool create '.$dfn.' '.$rrd));
	if (file_exists($dfn))
		{
		echo "OK!\n";
		$log[] = date("Y/m/d H:i:s")."\tCreated RRD for ".$adapter['name']." (id ".$adapter['id'].") as ".$fn."\n";
		}
	else
		echo "Error:\n".$ret."\n";
	}
if (count($log)>0) file_put_contents($cfg['rrddir'].'/create.log', implode('', $log), FILE_APPEND);

die("\n");

