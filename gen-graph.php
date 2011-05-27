#!/usr/bin/php
<?php
require_once(dirname(__FILE__).'/lib/common.php');

$cfg = readConfig(array('rrddir', 'graphdir', 'otherdir'));

$allcfg = array();
$allcfg[] = array('id'=>$cfg['id'], 'name'=>$cfg['name'], 'adapters'=>$cfg['adapters'], 'rrddir'=>$cfg['rrddir']);
$alladapters = $cfg['adapters'];

$dirs = @scandir($cfg['otherdir']);
if (is_array($dirs)) foreach($dirs as $dir)
	{
	if ($dir[0] == '.') continue;
	$realdir = $cfg['otherdir'].'/'.$dir;
	if (!is_dir($realdir)) continue;
	$cjson = $realdir.'/config.json';
	if (!file_exists($cjson))
		{
		echo 'config.json not found in '.$realdir.", skipping\n";
		continue;
		}

	$tmp = @file_get_contents($cjson);
	$tmp = @json_decode($tmp, true);

	if (!is_array($tmp))
		{
		echo 'Error reading config.json in '.$realdir.", skipping\n";
		continue;
		}

	$allcfg[] = array('id'=>$tmp['id'], 'name'=>$tmp['name'], 'adapters'=>$tmp['adapters'], 'rrddir'=>$realdir.'/rrd');
	$alladapters = array_merge($alladapters, $tmp['adapters']);
	}

function graph_internal($cfg, $fn, $fnSuffix, $extraOpts, $data, $wm='rug-monitor')
{
$dfn = $cfg['graphdir'].'/'.$fn.$fnSuffix.'.png';

//echo $data."\n\n";

$ret = trim(shell_exec(escapeshellcmd('rrdtool graph '.$dfn.' '.$extraOpts.' --interlaced --imgformat PNG --width 600
 -c BACK#FFFFFF
 -c CANVAS#FFFFFF
 -c SHADEA#FFFFFF
 -c SHADEB#FFFFFF
 -c ARROW#666666
 -c GRID#999999
 -c MGRID#666666
 -c FONT#666666 -W "'.$wm.'" '.$data)));

echo 'graph: '.$fn.' ('.$fnSuffix.'): '.$ret."\n";
}

function getColor($reset=false)
{
static $id;
if ($reset || !isset($id)) $id=0;

//$colorwheel = array('#33FF33', '#3333FF', '#33FFFF');
//$colorwheel = array('#556270', '#4ECDC4', '#C7F464', '#FF6B6B', '#C44D58'); //from http://www.colourlovers.com/palette/1930/cheer_up_emo_kid
$colorwheel = array('#1F77B4', '#FF7F0E', '#2CA02C', '#D62728');

$ourid = $id;

$id++;
if ($id>=count($colorwheel)) $id=0;

return($colorwheel[$ourid]);
}

function doGraph($cfg, $allcfg, $alladapters, $fnSuffix='', $extraOpts='')
{
$stacks = array('hashrate'=>array('Hashrate',' %s'), 'load'=>array('GPU Load', ' %%'));

foreach($stacks as $ds=>$dsdata)
	{
//separate hash and gpu load
	foreach($allcfg as $rig)
		{
		$def = $cdef = $gr = $gr2 = $gp = array();
		$first = true;
		foreach($rig['adapters'] as $adapter)
			{
			if (isOff($adapter['rrd'])) continue;
			$fn = getRrdFile($adapter);
			$dfn = $cfg['rrddir'].'/'.$fn;
			if (!file_exists($dfn)) continue;
			$def[] = 'DEF:'.$ds.$adapter['id'].'='.$dfn.':'.$ds.':AVERAGE';
			$cdef[] = 'CDEF:'.$ds.'u'.$adapter['id'].'='.$ds.$adapter['id'].',UN,0,'.$ds.$adapter['id'].',IF';
			$cdef[] = 'VDEF:'.$ds.'x'.$adapter['id'].'='.$ds.$adapter['id'].',LAST';
			$col = getColor($first);
			$gr[] = 'AREA:'.$ds.'u'.$adapter['id'].$col.'C0:'.($first?'':':STACK');
			$gr2[] = 'LINE2:'.$ds.'u'.$adapter['id'].$col.':"'.$adapter['nameP'].'"'.($first?'':':STACK');
			$gr2[] = 'GPRINT:'.$ds.'x'.$adapter['id'].':"%4.2lf'.$dsdata[1].'\c"';

			$first = false;
			}

		graph_internal($cfg, $ds.'_'.$rig['id'], $fnSuffix, $extraOpts, '-t "'.$dsdata[0].'" '.implode(' ', $def).' '.implode(' ', $cdef).' '.implode(' ', $gr).' '.implode(' ', $gr2).' '.implode(' ', $gp), $rig['name']);
		}

//overall hash and gpu load (by device)
	$def = $cdef = $gr = $gr2 = $gp = array();
	$first = true;
	$unid = 0;
	foreach($alladapters as $adapter)
		{
		if (isOff($adapter['rrd'])) continue;
		$fn = getRrdFile($adapter);
		$dfn = $cfg['rrddir'].'/'.$fn;
		if (!file_exists($dfn)) continue;
		$def[] = 'DEF:'.$ds.$unid.'='.$dfn.':'.$ds.':AVERAGE';
		$cdef[] = 'CDEF:'.$ds.'u'.$unid.'='.$ds.$unid.',UN,0,'.$ds.$unid.',IF';
		$cdef[] = 'VDEF:'.$ds.'x'.$unid.'='.$ds.$unid.',LAST';
		$col = getColor($first);
		$gr[] = 'AREA:'.$ds.'u'.$unid.$col.'C0:'.($first?'':':STACK');
		$gr2[] = 'LINE2:'.$ds.'u'.$unid.$col.':"'.$adapter['nameP'].'"'.($first?'':':STACK');
		$gr2[] = 'GPRINT:'.$ds.'x'.$unid.':"%4.2lf'.$dsdata[1].'\c"';

		$first = false;
		$unid++;
		}
	
	graph_internal($cfg, $ds.'_bydevice', $fnSuffix, $extraOpts, '-t "'.$dsdata[0].'" '.implode(' ', $def).' '.implode(' ', $cdef).' '.implode(' ', $gr).' '.implode(' ', $gr2).' '.implode(' ', $gp));

//overall hash and gpu load (by rig)
	$def = $cdef = $gr = $gr2 = $gp = array();
	$first = true;
	$rigid = 0;
	foreach($allcfg as $rig)
		{
		$unid = 0;
		foreach($rig['adapters'] as $adapter)
			{
			if (isOff($adapter['rrd'])) continue;
			$fn = getRrdFile($adapter);
			$dfn = $cfg['rrddir'].'/'.$fn;
			if (!file_exists($dfn)) continue;
			$def[] = 'DEF:'.$ds.$rigid.'_'.$unid.'='.$dfn.':'.$ds.':AVERAGE';
			$cdef[] = 'CDEF:'.$ds.'u'.$rigid.'_'.$unid.'='.$ds.$rigid.'_'.$unid.',UN,0,'.$ds.$rigid.'_'.$unid.',IF';

			$unid++;
			}

		if ($unid == 0) continue;
		$tmp = array();
		$j = 0;
		for($i=0;$i<$unid;$i++)
			{
			$tmp[] = $ds.'u'.$rigid.'_'.$i;
			$j++;
			if ($j==2)
				{
				$tmp[] = '+';
				$j = 1;
				}
			}

		$cdef[] = 'CDEF:'.$ds.'u'.$rigid.'='.implode(',',$tmp);
		$cdef[] = 'VDEF:'.$ds.'x'.$rigid.'='.$ds.'u'.$rigid.',LAST';

		$col = getColor($first);
		$gr[] = 'AREA:'.$ds.'u'.$rigid.$col.'C0:'.($first?'':':STACK');
		$gr2[] = 'LINE2:'.$ds.'u'.$rigid.$col.':"'.$rig['nameP'].'"'.($first?'':':STACK');
		$gr2[] = 'GPRINT:'.$ds.'x'.$rigid.':"%4.2lf'.$dsdata[1].'\c"';

		$first = false;

		$rigid++;
		}

	graph_internal($cfg, $ds.'_byrig', $fnSuffix, $extraOpts, '-t "'.$dsdata[0].'" '.implode(' ', $def).' '.implode(' ', $cdef).' '.implode(' ', $gr).' '.implode(' ', $gr2).' '.implode(' ', $gp));
	}


// combined shares (accepted vs. rejected)
foreach($allcfg as $rig)
	{
	$def = $cdef = $gr = $gr2 = $gp = array();
	$first = true;
	foreach($rig['adapters'] as $adapter)
		{
		if (isOff($adapter['rrd'])) continue;
		$fn = getRrdFile($adapter);
		$dfn = $cfg['rrddir'].'/'.$fn;
		if (!file_exists($dfn)) continue;
		$def[] = 'DEF:acc'.$adapter['id'].'='.$dfn.':accepted:AVERAGE:step=600';
		$def[] = 'DEF:rej'.$adapter['id'].'='.$dfn.':rejected:AVERAGE:step=600';
		$cdef[] = 'CDEF:accu'.$adapter['id'].'=acc'.$adapter['id'].',UN,0,acc'.$adapter['id'].',IF';
		$cdef[] = 'CDEF:reju'.$adapter['id'].'=rej'.$adapter['id'].',UN,0,rej'.$adapter['id'].',IF';
		$cdef[] = 'CDEF:rejn'.$adapter['id'].'=reju'.$adapter['id'].',-1,*';
		$col = getColor($first);

		$gr[] = 'AREA:accu'.$adapter['id'].$col.':"'.$adapter['name'].'"'.($first?'':':STACK');
		if ($first) $gr2[] = 'COMMENT:\\s';
	//	$gr2[] = 'AREA:rejn'.$adapter['id'].$col.'80:"Rejected"'.($first?'':':STACK');
		$gr2[] = 'AREA:rejn'.$adapter['id'].$col.'80:'.($first?'':':STACK');

		$first = false;
		}

	graph_internal($cfg, 'shares_'.$rig['id'], $fnSuffix, $extraOpts, '--vertical-label "Shares" -t "Shares Submitted" '.implode(' ', $def).' '.implode(' ', $cdef).' '.implode(' ', $gp).' '.implode(' ', $gr).' '.implode(' ', $gr2), $rig['name']);
	}

//overall shares (accepted vs. rejected) (by device)
$def = $cdef = $gr = $gr2 = $gp = array();
$first = true;
$unid = 0;
foreach($alladapters as $adapter)
	{
	if (isOff($adapter['rrd'])) continue;
	$fn = getRrdFile($adapter);
	$dfn = $cfg['rrddir'].'/'.$fn;
	if (!file_exists($dfn)) continue;
	$def[] = 'DEF:acc'.$unid.'='.$dfn.':accepted:AVERAGE:step=600';
	$def[] = 'DEF:rej'.$unid.'='.$dfn.':rejected:AVERAGE:step=600';
	$cdef[] = 'CDEF:accu'.$unid.'=acc'.$unid.',UN,0,acc'.$unid.',IF';
	$cdef[] = 'CDEF:reju'.$unid.'=rej'.$unid.',UN,0,rej'.$unid.',IF';
	$cdef[] = 'CDEF:rejn'.$unid.'=reju'.$unid.',-1,*';
	$col = getColor($first);

	$gr[] = 'AREA:accu'.$unid.$col.':"'.$adapter['name'].'"'.($first?'':':STACK');
	if ($first) $gr2[] = 'COMMENT:\\s';
//	$gr2[] = 'AREA:rejn'.$unid.$col.'80:"Rejected"'.($first?'':':STACK');
	$gr2[] = 'AREA:rejn'.$unid.$col.'80:'.($first?'':':STACK');

	$first = false;
	$unid++;
	}

graph_internal($cfg, 'shares_bydevice', $fnSuffix, $extraOpts, '--vertical-label "Shares" -t "Shares Submitted" '.implode(' ', $def).' '.implode(' ', $cdef).' '.implode(' ', $gp).' '.implode(' ', $gr).' '.implode(' ', $gr2));

// load/temp/fan for each adapter
foreach($allcfg as $rig)
	{
	foreach($rig['adapters'] as $adapter)
		{
		if (isOff($adapter['rrd'])) continue;
		$fn = getRrdFile($adapter);
		$dfn = $cfg['rrddir'].'/'.$fn;
		if (!file_exists($dfn)) continue;
		$fan = !(isOff($adapter['fan']) || !isset($adapter['display']));

		$data = '';
		if ($fan) $data .= '
		DEF:fan='.$dfn.':fan:AVERAGE
		VDEF:fanl=fan,LAST
		VDEF:fana=fan,AVERAGE
		';
	 	$data .= ' DEF:temp='.$dfn.':temp:AVERAGE
		DEF:load='.$dfn.':load:AVERAGE
		CDEF:tempoff=temp,50,-,0,MAX
		VDEF:templ=temp,LAST
		VDEF:tempa=temp,AVERAGE
		VDEF:loadl=load,LAST
		VDEF:loada=load,AVERAGE ';

		if ($fan) $data .= '
		LINE2:fan#1F77B4:" Fan"
		GPRINT:fanl:"Last\: %2.0lf %%  "
		GPRINT:fana:"Avg\: %4.2lf %%\c"
		';

	 	$data .= 'AREA:load#2CA02C33:"Load"
		GPRINT:loadl:"Last\: %2.0lf %%  "
		GPRINT:loada:"Avg\: %4.2lf %%\c"
	 	LINE2:tempoff#D62728CC:"Temp"
		GPRINT:templ:"Last\: %3.1lf C"
		GPRINT:tempa:"Avg\: %3.1lf C \c"';

		graph_internal($cfg, 'temps_'.$rig['id'].'_d'.$adapter['id'], $fnSuffix, $extraOpts, '--right-axis 1:50 --right-axis-label "Temperature" --vertical-label "Percentage" -t "'.$adapter['name'].'" '.$data, $rig['name']);
		}
	}

}

//void main ;)

$maxlen = 0;
foreach($alladapters as $adapter)
	{
	$l = strlen($adapter['name']);
	if ($l > $maxlen) $maxlen = $l;
	}
foreach($alladapters as $i=>$adapter)
	{
	$l = strlen($adapter['name']);
	$alladapters[$i]['nameP'] = $adapter['name'].($l < $maxlen?str_repeat(' ', $maxlen-$l):'');
	}
$rmaxlen = 0;
$ridlist = array();
foreach($allcfg as $i=>$rig)
	{
	$l = strlen($rig['name']);
	if ($l > $rmaxlen) $rmaxlen = $l;

	foreach($rig['adapters'] as $j=>$adapter)
		{
		$l = strlen($adapter['name']);
		$rig['adapters'][$j]['nameP'] = $adapter['name'].($l < $maxlen?str_repeat(' ', $maxlen-$l):'');
		}
	$allcfg[$i] = $rig;
	if ($rig['id']!='') $ridlist[] = $rig['id'];
	}

$ridcnt = 1;
foreach($allcfg as $i=>$rig)
	{
	$l = strlen($rig['name']);
	$allcfg[$i]['nameP'] = $rig['name'].($l < $rmaxlen?str_repeat(' ', $rmaxlen-$l):'');
	if ($rig['id']=='')
		{
		while(in_array($ridcnt, $ridlist)) $ridcnt++;
		$ridlist[] = $ridcnt;
		$allcfg[$i]['id'] = 'rig'.$ridcnt;
		}
	else if (is_numeric($rig['id'])) $allcfg[$i]['id'] = 'rig'.$rig['id'];
	}

doGraph($cfg, $allcfg, $alladapters, '-0');

if ($_SERVER['argv'][1] == 'all')
	{
	doGraph($cfg, $allcfg, $alladapters, '-1', '--start -2d --end -1d');
	doGraph($cfg, $allcfg, $alladapters, '-2', '--start -3d --end -2d');
	doGraph($cfg, $allcfg, $alladapters, '-3', '--start -4d --end -3d');
	doGraph($cfg, $allcfg, $alladapters, '-4', '--start -5d --end -4d');
	doGraph($cfg, $allcfg, $alladapters, '-5', '--start -6d --end -5d');
	doGraph($cfg, $allcfg, $alladapters, '-6', '--start -7d --end -6d');
	doGraph($cfg, $allcfg, $alladapters, '-w', '--start -1w');
	doGraph($cfg, $allcfg, $alladapters, '-m', '--start -1m');
	}

die("\n");
