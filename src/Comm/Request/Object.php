<?php
/**
 * 基础model类,所有model继承
 *
 * @package     Comm\Base
 * @version     2016-06-07 10:29
 */

namespace PCurl\Comm\Request;

class Object
{
    private static $_models = array();

    /**
     * 返回调用的类
     *
     * @return self|static
     */
    public static function model()
    {
        $class = get_called_class();
        if (func_num_args() > 0) {
            return new $class(func_get_args());
        } elseif (isset(self::$_models[$class])) {
            return self::$_models[$class];
        } else {
            $model = self::$_models[$class] = new $class;
            return $model;
        }
    }
}
