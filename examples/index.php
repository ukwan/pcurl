<?php
require __DIR__ . '/../vendor/autoload.php';

$host = "http://www.baidu.com";
$obj_curl = new \PCurl\Comm\PCurl($host);
$rules = array(
    ['timestamp', 'int', true]
);
$param = array(
    'timestamp' => time(),
);
try {
    $res = $obj_curl->sendRequest($rules, $param);
    var_dump($res);
} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}