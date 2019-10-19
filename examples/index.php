<?php
require __DIR__ . '/../vender/autoload.php';

$obj_curl = new \Pcurl\Pcurl("http://www.baidu.com/api.php");
$rules = array(
    ['id', 'int', true],
    ['uri', 'int', true],
    ['timestamp', 'int', true]
);
$param = array(
    'id'        => 47831,
    'uri'       => 'otm/Tea/teaPalInfo',
    'timestamp' => time(),
);
try {
    $res = $obj_curl->get($rules, $param);
    print_r($res);
} catch (\Exception $e) {
    echo $e->getMessage();
}