<?php

namespace PCurl\Comm\Exception;

/**
 * 程序构建期间、程序员认为导致发生的错误，涵盖：
 *  参数错误
 *  参数缺失
 *  调用方式错误
 *  etc...
 * 抛出该异常意味着错误应该在构建期间得到解决
 *
 * @package    comm
 * @subpackage exception
 * @version    2015-09-24 11:22:33
 */
class Program extends \Exception
{

}
