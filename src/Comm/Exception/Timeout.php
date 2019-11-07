<?php

/**
 * 接口异常类
 *
 * @package    Exception
 * @copyright  copyright(2016) 51talk.com all rights reserved
 * @author     talk.com php team
 * @version    2015-09-25 10:14:36
 */

namespace PCurl\Comm\Exception;

class Timeout extends Program
{
    public $errorCode;

    public function __construct($message, $code = '')
    {
        $this->errorCode = $code;
        $getcode = (is_numeric($this->errorCode)) ? $this->errorCode : '100001';
        parent::__construct($message, $getcode);
    }

    public function debugCode()
    {
        return $this->errorCode;
    }
}
