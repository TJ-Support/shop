<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit1daadd8f3ca124198bd053981fb36135
{
    public static $prefixLengthsPsr4 = array (
        'S' => 
        array (
            'Stripe\\' => 7,
            'SquareConnect\\' => 14,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Stripe\\' => 
        array (
            0 => __DIR__ . '/..' . '/stripe/stripe-php/lib',
        ),
        'SquareConnect\\' => 
        array (
            0 => __DIR__ . '/..' . '/square/connect/lib',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit1daadd8f3ca124198bd053981fb36135::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit1daadd8f3ca124198bd053981fb36135::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
