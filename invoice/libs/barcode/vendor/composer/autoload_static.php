<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitc88b6a41d3299eeefd99d566e9e4f1b2
{
    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'Picqer\\Barcode\\' => 15,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Picqer\\Barcode\\' => 
        array (
            0 => __DIR__ . '/..' . '/picqer/php-barcode-generator/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitc88b6a41d3299eeefd99d566e9e4f1b2::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitc88b6a41d3299eeefd99d566e9e4f1b2::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitc88b6a41d3299eeefd99d566e9e4f1b2::$classMap;

        }, null, ClassLoader::class);
    }
}
