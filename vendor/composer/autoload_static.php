<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit05581de6054a2cb6458eee5c389857bc
{
    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'Pcurl\\' => 6,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Pcurl\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit05581de6054a2cb6458eee5c389857bc::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit05581de6054a2cb6458eee5c389857bc::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}