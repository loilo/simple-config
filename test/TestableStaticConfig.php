<?php namespace Loilo\SimpleConfig\Test;

use Loilo\SimpleConfig\StaticConfig;
use Loilo\SimpleConfig\Config;

class TestableStaticConfig extends StaticConfig
{
    public static $options = [];
    public static $creations = 0;

    public static function createConfig(): Config
    {
        static::$creations++;

        return new Config(static::$options);
    }
}
