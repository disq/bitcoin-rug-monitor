<?php

function readConfig($required=array())
{
$cfgcontent = @file_get_contents(dirname(__FILE__).'/../config.json');
$cfg = @json_decode($cfgcontent, true);

if (!is_array($cfg))
	{
	die("Error reading config, did you run gen-config first?\n");	
	}

if (!is_array($required)) $required = array($required);
if (in_array('rrddir', $required))
	{
	$cfg['rrddir'] = $cfg['rrddir']=='' ? dirname(__FILE__).'/../rrd' : rtrim($cfg['rrddir'], '/\\');
	}
if (in_array('graphdir', $required))
	{
	$cfg['graphdir'] = $cfg['graphdir']=='' ? dirname(__FILE__).'/../graphs' : rtrim($cfg['graphdir'], '/\\');
	}
if (in_array('otherdir', $required))
	{
	$cfg['otherdir'] = $cfg['otherdir']=='' ? dirname(__FILE__).'/../other-rigs' : rtrim($cfg['otherdir'], '/\\');
	}

return($cfg);
}

function adapt($cmd, $adapter)
{
global $cfg; //ugly

$cmd = str_replace('%i', $adapter['id'], $cmd);
$cmd = str_replace('%d', $adapter['display'], $cmd);
$cmd = str_replace('%D', $cfg['default_display'], $cmd);
return($cmd);
}

function isOff($param)
{
return(in_array($param, array('0', 'false', 'off', 'no')));
}

function isOn($param)
{
return(in_array($param, array('1', 'true', 'on', 'yes')));
}

function getPrefixes()
{
static $prefixes;
if (is_array($prefixes)) return($prefixes);

$pflist = 'KMGTP';
$prefixes = array();
for($i=0;$i<strlen($pflist);$i++) $prefixes[$pflist[$i]] = pow(10,($i+1)*3);
return($prefixes);
}

function getRrdFile($adapter)
{
$fn = $adapter['rrdfile'];
if (!isset($fn)) $fn = $adapter['id'].'.rrd';
return($fn);	
}

/*SNIPPET, FROM http://recursive-design.com/blog/2008/03/11/format-json-with-php/ */
/**
 * Indents a flat JSON string to make it more human-readable.
 *
 * @param string $json The original JSON string to process.
 *
 * @return string Indented version of the original JSON string.
 */
function indent($json) {

    $result      = '';
    $pos         = 0;
    $strLen      = strlen($json);
    $indentStr   = '  ';
    $newLine     = "\n";
    $prevChar    = '';
    $outOfQuotes = true;

    for ($i=0; $i<=$strLen; $i++) {

        // Grab the next character in the string.
        $char = substr($json, $i, 1);

        // Are we inside a quoted string?
        if ($char == '"' && $prevChar != '\\') {
            $outOfQuotes = !$outOfQuotes;
        
        // If this character is the end of an element, 
        // output a new line and indent the next line.
        } else if(($char == '}' || $char == ']') && $outOfQuotes) {
            $result .= $newLine;
            $pos --;
            for ($j=0; $j<$pos; $j++) {
                $result .= $indentStr;
            }
        }
        
        // Add the character to the result string.
        $result .= $char;

        // If the last character was the beginning of an element, 
        // output a new line and indent the next line.
        if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
            $result .= $newLine;
            if ($char == '{' || $char == '[') {
                $pos ++;
            }
            
            for ($j = 0; $j < $pos; $j++) {
                $result .= $indentStr;
            }
        }
        
        $prevChar = $char;
    }

    return $result;
}
/* END SNIPPET */
