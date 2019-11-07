<?php

namespace PCurl\Tool;

class Ip
{
    /**
     * 取客户端IP地址
     *
     * @return null|string
     */
    public static function getClientIP()
    {
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    }
}
