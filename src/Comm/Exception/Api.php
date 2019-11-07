<?php
namespace PCurl\Comm\Exception;

/**
 * 接口异常类
 *
 * @package    Exception
 */
class Api extends Program
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
