<?php

namespace PCurl\Tool;

class Util
{

    /**
     * 判断php宿主环境是否是64bit
     *
     * ps: 在64bit下，php有诸多行为与32bit不一致，诸如mod、integer、jsonEncode/decode等，具体请自行google。
     *
     * @return bool
     */
    public static function is64bit()
    {
        return PHP_INT_SIZE == 8;
    }

    /**
     * 修正过的ip2long
     *
     * 可去除ip地址中的前导0。32位php兼容，若超出127.255.255.255，则会返回一个float
     *
     * for example: 02.168.010.010 => 2.168.10.10
     *
     * 处理方法有很多种，目前先采用这种分段取绝对值取整的方法吧……
     * @param string $ip
     * @return float 使用unsigned int表示的ip。如果ip地址转换失败，则会返回0
     */
    public static function ip2long($ip)
    {
        $ipChunks = explode('.', $ip, 4);
        foreach ($ipChunks as $i => $v) {
            $ipChunks[$i] = abs(intval($v));
        }

        return sprintf('%u', ip2long(implode('.', $ipChunks)));
    }

    /**
     * 判断是否是内网ip
     * @param string $ip
     * @return boolean
     */
    public static function isPrivateIP($ip)
    {
        $ipValue = self::ip2long($ip);

        return ($ipValue & 0xFF000000) === 0x0A000000 //10.0.0.0-10.255.255.255
            || ($ipValue & 0xFFF00000) === 0xAC100000 //172.16.0.0-172.31.255.255
            || ($ipValue & 0xFFFF0000) === 0xC0A80000 //192.168.0.0-192.168.255.255
            ;
    }

    /**
     * 使json_decode能处理32bit机器上溢出的数值类型
     *
     * @param string $value json字符串
     * @param bool $assoc
     * @return mixed
     */
    public static function jsonDecode($value, $assoc = true)
    {
        //PHP5.3以下版本不支持
        if (version_compare(PHP_VERSION, '5.3.0', '>') && defined('JSON_BIGINT_AS_STRING')) {
            return json_decode($value, $assoc, 512, JSON_BIGINT_AS_STRING);
        } else {
            $value = preg_replace("/\"(\w+)\":(\d+[\.\d+[e\+\d+]*]*)/", "\"\$1\":\"\$2\"", $value);
            return json_decode($value, $assoc);
        }
    }
}
