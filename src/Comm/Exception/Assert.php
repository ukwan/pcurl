<?php

namespace PCurl\Comm\Exception;

/**
 * 断言错误
 *
 * @package    Exception
 * @subpackage comm
 */
class Assert extends Program
{
    public function __construct($message)
    {
        parent::__construct($message);
    }
}
