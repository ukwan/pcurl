<?php

namespace PCurl\Tool;

/**
 *
 * 断言
 *
 * 断言用于验证程序中不可能出现的情况。可以通过 \PCurl\Tool\Assert::as_* 系列方法调整断言的行为。
 * 默认行为为不输出任何内容。
 *
 */
class Assert
{
    static protected $assertType = 0;

    /**
     * 设置assert行为为不输出任何内容
     */
    public static function asDumb()
    {
        self::$assertType = 0;
    }

    /**
     *
     * 设置assert行为为触发warning
     */
    public static function asWarning()
    {
        self::$assertType = 1;
    }

    /**
     * 设置assert行为为抛出exception
     */
    public static function asException()
    {
        self::$assertType = 3;
    }

    /**
     *
     * 设置assert行为为触发error
     */
    public static function asError()
    {
        self::$assertType = 2;
    }

    /**
     * 验证条件是否为成立，如果不成立，则提示指定的message
     *
     * @param bool $condition
     * @param string $message
     */
    public static function true($condition, $message = null)
    {
        if (!$condition) {
            self::act($message);
        }
    }

    /**
     * 验证条件是否不成立，如果为成立，则提示指定的message
     * @param bool $condition
     * @param string $message
     */
    public static function false($condition, $message = null)
    {
        if ($condition) {
            self::act($message);
        }
    }

    /**
     *
     * @param string $message
     * @throws \PCurl\Comm\Exception\Assert
     */
    protected static function act($message)
    {
        switch (self::$assertType) {
            case 2:
                trigger_error($message, E_USER_ERROR);
                break;
            case 3:
                throw new \PCurl\Comm\Exception\Assert($message);
                break;
            case 1:
                trigger_error($message, E_USER_WARNING);
                break;
            default:
        }
    }
}
