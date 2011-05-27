#!/usr/bin/php
<?php
require_once(dirname(__FILE__).'/lib/common.php');

$configfile = dirname(__FILE__).'/config.json';

$to_stdout = $_SERVER['argv'][1] == '-';
if (!$to_stdout && file_exists($configfile))
	{
	die($configfile." already exists! Delete first, or run with \"".$_SERVER['argv'][0]." -\"\n");
	}

$gen_cmd = 'aticonfig --list-adapters';

$ret = shell_exec(escapeshellcmd($gen_cmd));

$ma = array();
$mi = preg_match_all('/^\*?\s+([0-9]+)\. [0-9]+:[0-9]+\.[0-9]+ (.+?)\s*$/m', $ret, $ma, PREG_SET_ORDER);
if ($mi<1)
	{
	die('No valid adapters found (or "'.$gen_cmd.'" failed for some reason)'."\n");
	}

$cfg = array('id'=>'mining-rug', 'name'=>'Mining Rug', 'default_display'=>':0', 'adapters'=>array());
$cnt_5970 = 0;
$last_display = '';
foreach($ma as $adapter)
	{
	if (!$to_stdout) echo "Adapter: ".$adapter[1]." = ".$adapter[2]."\n";
	$ln = array('id'=>$adapter[1], 'name'=>$adapter[2], 'fan'=>'yes');

	if ($cnt_5970 == 0) $ln['display'] = ":0.".$adapter[1];
	else if ($cnt_5970 == 1 && $last_display!='') $ln['display'] = $last_display;

	$last_display = $ln['display'];

	if (strpos($adapter[2], ' HD 5900 ')!==false)
		{
		$cnt_5970++;
		if ($cnt_5970==2) $cnt_5970 = 0;
		}
	else
		{
		$cnt_5970 = 0;
		}
	if ($cnt_5970 == 0) $last_display = '';

	$ln['status_file'] = 'Off';
	$ln['status_type'] = 'phoenix';

	$cfg['adapters'][] = $ln;
	}

$out = indent(json_encode($cfg))."\n";
if ($to_stdout) die($out);

if (file_put_contents($configfile, $out)!==false)
	echo "Wrote config.json\n";
else
	echo "Error writing config.json\n";
die();
