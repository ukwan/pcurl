<?php
require __DIR__ . '/../vendor/autoload.php';
$host = "http://otm.51talk.com/api.php";
$obj_curl = new \PCurl\Comm\PCurl($host);
$rules = array(
    ['tid', 'int', true],
    ['uri', 'string', true],
    ['timestamp', 'int', true]
);
$param = array(
    'uri'       => 'otm/Tea/teaPalInfo',
    'tid'        => 47831,
    'timestamp' => time(),
);
try {
    $res = $obj_curl->sendRequest($rules, $param);
    var_dump($res);
} catch (\PCurl\Comm\Exception\Api $e) {
    echo $e->getMessage();
}