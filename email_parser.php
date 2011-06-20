<?php

include_once("config.inc.php");

$email = file_get_contents('php://stdin');

$oMP = new EmailParser($email);

// show the array, but hide some content (i.e. images) that may screw up the terminal:
$out = $oMP->getParsedData();
foreach ($out["_files"] as &$f) unset($f["content"]);
print_r($out);


unset($oMP);
