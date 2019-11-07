<?php
require __DIR__ . '/../vendor/autoload.php';

$host = "http://otm.51talk.com/api.php?uri=otm/Tea/teaPalInfo";
$obj_curl = new \PCurl\Comm\PCurl($host);
$rules = array(
    ['tid', 'int', true],
    ['timestamp', 'int', true]
);
$param = array(
    'tid'       => 47831,
    'timestamp' => time(),
);
try {
    $res = $obj_curl->sendRequest($rules, $param);
    var_dump($res);
} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}