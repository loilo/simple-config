<?php namespace Loilo\SimpleConfig\Test;

use InvalidArgumentException;
use Loilo\SimpleConfig\Config;
use Loilo\SimpleConfig\Exception\DeserializationException;
use Loilo\SimpleConfig\Exception\InvalidConfigException;
use Loilo\SimpleConfig\Exception\InvalidConfigSchemaException;
use Loilo\SimpleConfig\Exception\InvalidDefaultsException;
use Loilo\XFilesystem\XFilesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Exception\IOException;

/**
 * Test the Config class
 */
class ConfigTest extends TestCase
{
    /**
     * @var string
     */
    protected static $fixture = 'fixture';

    /**
     * @var XFilesystem
     */
    protected static $fs;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Config
     */
    protected $configWithoutDotNotation;

    /**
     * Create a temporary directory and return its path
     * @see https://stackoverflow.com/a/30010928/2048874
     *
     * @param string|null $dir         Base directory under which to create temp dir.
     *                                 If null, the default system temp dir (sys_get_temp_dir()) will be used.
     * @param string      $prefix      String with which to prefix created dirs.
     * @param int         $mode        Octal file permission mask for the newly-created dir.
     *                                 Should begin with a 0.
     * @param int         $maxAttempts Maximum attempts before giving up (to prevent infinite loops)
     * @return string Full path to newly-created dir
     */
    private function tempdir($dir = null, $prefix = 'tmp_', $mode = 0700, $maxAttempts = 1000)
    {
        if (is_null($dir)) {
            $dir = sys_get_temp_dir();
        }

        $dir = rtrim($dir, DIRECTORY_SEPARATOR);

        if (!is_dir($dir) || !is_writable($dir)) {
            throw new IOException('Temporary directory does not exist or is not writable');
        }

        if (strpbrk($prefix, '\\/:*?"<>|') !== false) {
            throw new InvalidArgumentException('Prefix contains invalid characters');
        }

        $attempts = 0;
        do {
            $path = sprintf('%s%s%s%s', $dir, DIRECTORY_SEPARATOR, $prefix, mt_rand(100000, mt_getrandmax()));
        } while (!mkdir($path, $mode) && $attempts++ < $maxAttempts);

        if (!is_dir($path)) {
            throw new IOException('Could not create temporary folder after multiple attempts');
        }

        return $path;
    }

    public static function setUpBeforeClass(): void
    {
        static::$fs = new XFilesystem();
    }

    public function setUp(): void
    {
        $this->config = new Config([
            'configDir' => $this->tempdir()
        ]);

        $this->configWithoutDotNotation = new Config([
            'configDir' => $this->tempdir(),
            'dotNotation' => false
        ]);
    }

    public function testGet()
    {
        $this->assertSame(null, $this->config->get('foo'));
        $this->assertSame('default', $this->config->get('foo', 'default'));
        $this->config->set('foo', static::$fixture);
        $this->assertSame(static::$fixture, $this->config->get('foo'));
    }

    public function testSet()
    {
        $this->config->set('foo', static::$fixture);
        $this->config->set('baz.boo', static::$fixture);
        $this->assertSame(static::$fixture, $this->config->get('foo'));
        $this->assertSame(static::$fixture, $this->config->get('baz.boo'));
    }

    public function testSetWithObject()
    {
        $this->config->set([
            'foo1' => 'bar1',
            'foo2' => 'bar2',
            'baz' => [
                'boo' => 'foo',
                'foo' => [
                    'bar' => 'baz'
                ]
            ]
        ]);
        $this->assertSame($this->config->get('foo1'), 'bar1');
        $this->assertSame($this->config->get('foo2'), 'bar2');
        $this->assertEquals($this->config->get('baz'), [
            'boo' => 'foo',
            'foo' => [
                'bar' => 'baz'
            ]
        ]);
        $this->assertSame($this->config->get('baz.boo'), 'foo');
        $this->assertEquals($this->config->get('baz.foo'), [
            'bar' => 'baz'
        ]);
        $this->assertSame($this->config->get('baz.foo.bar'), 'baz');
    }

    public function testSetInvalidKey()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->config->set(1, 'unicorn');
    }

    public function testHas()
    {
        $this->config->set('foo', static::$fixture);
        $this->config->set('baz.boo', static::$fixture);
        $this->assertTrue($this->config->has('foo'));
        $this->assertTrue($this->config->has('baz.boo'));
        $this->assertFalse($this->config->has('missing'));
    }

    public function testDelete()
    {
        $this->config->set('foo', 'bar');
        $this->config->set('baz.boo', true);
        $this->config->set('baz.foo.bar', 'baz');
        $this->config->delete('foo');
        $this->assertSame(null, $this->config->get('foo'));
        $this->config->delete('baz.boo');
        $this->assertNotSame(true, $this->config->get('baz.boo'));
        $this->config->delete('baz.foo');
        $this->assertNotSame([ 'bar' => 'baz' ], $this->config->get('baz.foo'));
        $this->config->set('foo.bar.baz', [ 'awesome' => 'icecream' ]);
        $this->config->set('foo.bar.zoo', [ 'awesome' => 'redpanda' ]);
        $this->config->delete('foo.bar.baz');
        $this->assertSame('redpanda', $this->config->get('foo.bar.zoo.awesome'));
    }

    public function testClear()
    {
        $this->config->set('foo', 'bar');
        $this->config->set('foo1', 'bar1');
        $this->config->set('baz.boo', true);
        $this->config->delete();
        $this->assertSame(0, sizeof($this->config));
    }

    public function testSize()
    {
        $this->config->set('foo', 'bar');
        $this->assertSame(1, sizeof($this->config));
    }

    public function testStore()
    {
        $this->config->set('foo', 'bar');
        $this->config->set('baz.boo', true);
        $this->assertEquals([
            'foo' => 'bar',
            'baz' => [
                'boo' => true
            ]
        ], $this->config->get());
    }

    public function testDefaultsOption()
    {
        $config = new Config([
            'configDir' => $this->tempdir(),
            'defaults' => [
                'foo' => 'bar'
            ]
        ]);

        $this->assertSame('bar', $config->get('foo'));
    }

    public function testConfigNameOption()
    {
        $configName = 'alt-config';
        $config = new Config([
            'configDir' => $this->tempdir(),
            'configName' => $configName
        ]);
        $this->assertSame(null, $config->get('foo'));
        $config->set('foo', static::$fixture);
        $this->assertSame(static::$fixture, $config->get('foo'));
        $this->assertSame($configName, basename($config->getFilePath(), '.json'));
    }

    public function testSuffix()
    {
        $config = new Config();
        $this->assertTrue(strpos($config->getFilePath(), '-php') !== false);
    }

    public function testFileExtensionOption()
    {
        $fileExtension = 'alt-ext';
        $config = new Config([
            'configDir' => $this->tempdir(),
            'format' => [
                'extension' => $fileExtension
            ]
        ]);
        $this->assertSame(null, $config->get('foo'));
        $config->set('foo', static::$fixture);
        $this->assertSame(static::$fixture, $config->get('foo'));
        $this->assertSame(".$fileExtension", '.' . pathinfo($config->getFilePath(), PATHINFO_EXTENSION));
    }

    public function testFileExtensionOptionEmptyString()
    {
        $configName = 'unicorn';
        $config = new Config([
            'configDir' => $this->tempdir(),
            'format' => [ 'extension' => '' ],
            'configName' => $configName
        ]);
        $this->assertSame($configName, basename($config->getFilePath()));
    }

    public function testSerializeAndDeserializeOptions()
    {
        $assertions = 0;
        $serialized = 'foo:' . static::$fixture;
        $deserialized = [ 'foo' => static::$fixture ];
        $serialize = function ($value) use ($serialized, $deserialized, &$assertions) {
            $this->assertSame($value, $deserialized);
            $assertions++;
            return $serialized;
        };

        $deserialize = function ($value) use ($serialized, $deserialized, &$assertions) {
            $this->assertSame($value, $serialized);
            $assertions++;
            return $deserialized;
        };

        $config = new Config([
            'configDir' => $this->tempdir(),
            'format' => [
                'serialize' => $serialize,
                'deserialize' => $deserialize
            ]
        ]);

        $this->assertEquals([], $config->get());
        $assertions++;

        // Does not trigger a serialization because no file write will happen
        // (empty store did not change through clearing)
        $config->delete();

        $config->set($deserialized);

        $this->assertEquals($deserialized, $config->get());
        $assertions++;

        $this->assertSame(3, $assertions);
    }

    public function testProjectNameOption()
    {
        $projectName = 'conf-fixture-project-name';
        $config = new Config([
            'projectName' => $projectName
        ]);
        $this->assertSame(null, $config->get('foo'));
        $config->set('foo', static::$fixture);
        $this->assertSame(static::$fixture, $config->get('foo'));
        $this->assertTrue(strpos($config->getFilePath(), $projectName) !== false);
        static::$fs->remove($config->getFilePath());
    }

    public function testEnsureStoreIsAlwaysAnObject()
    {
        $configDir = $this->tempdir();
        $config = new Config([
            'configDir' => $configDir
        ]);

        static::$fs->remove($configDir);

        $config->get('foo');
        $this->addToAssertionCount(1);
    }

    public function testInstanceIsIterable()
    {
        $data = [
            'foo' => static::$fixture,
            'bar' => static::$fixture
        ];

        $this->config->set($data);

        foreach ($this->config as $key => $value) {
            $this->assertTrue(isset($data[$key]));
            $this->assertSame($data[$key], $value);
        }
    }

    public function testAutomaticProjectNameInference()
    {
        $config = new Config();
        $config->set('foo', static::$fixture);
        $this->assertSame(static::$fixture, $config->get('foo'));
        $this->assertTrue(strpos($config->getFilePath(), 'conf') !== false);
        static::$fs->remove($config->getFilePath());
    }

    public function testConfigDirOptionOverridesProjectNameOption()
    {
        $configDir = $this->tempdir();

        $config = new Config([
            'configDir' => $configDir,
            'projectName' => ''
        ]);

        $this->assertSame(
            $configDir,
            substr($config->getFilePath(), 0, strlen($configDir))
        );
        $this->assertSame(null, $config->get('foo'));
        $config->set('foo', static::$fixture);
        $this->assertSame(static::$fixture, $config->get('foo'));
        static::$fs->remove($config->getFilePath());
    }

    public function testEncryption()
    {
        $config = new Config([
            'configDir' => $this->tempdir(),
            'password' => 'abc123'
        ]);
        $this->assertSame(null, $config->get('foo'));
        $this->assertSame('default', $config->get('foo', 'default'));
        $config->set('foo', static::$fixture);
        $config->set('baz.boo', static::$fixture);
        $this->assertSame(static::$fixture, $config->get('foo'));
        $this->assertSame(static::$fixture, $config->get('baz.boo'));
    }

    public function testEncryptionUpgrade()
    {
        $configDir = $this->tempdir();

        $before = new Config([ 'configDir' => $configDir ]);
        $before->set('foo', static::$fixture);
        $this->assertSame(static::$fixture, $before->get('foo'));

        $after = new Config([
            'configDir' => $configDir,
            'password' => 'abc123'
        ]);
        $this->assertSame(static::$fixture, $after->get('foo'));
    }

    public function testEncryptionCorruptFile()
    {
        $configDir = $this->tempdir();

        $before = new Config([
            'configDir' => $configDir,
            'password' => 'abc123'
        ]);
        $before->set('foo', static::$fixture);
        $this->assertSame(static::$fixture, $before->get('foo'));

        static::$fs->appendToFile($configDir . '/config', 'corrupt file');

        $after = new Config([
            'configDir' => $configDir,
            'password' => 'abc123'
        ]);
        $this->assertSame(null, $after->get('foo'));
    }

    public function testClearInvalidConfigOptionWithInvalidData()
    {
        $dir = $this->tempdir();

        $config = new Config([
            'configDir' => $dir,
            'clearInvalidConfig' => false
        ]);

        static::$fs->dumpFile($config->getFilePath(), 'fixture');

        $this->expectException(DeserializationException::class);

        new Config([
            'configDir' => $dir,
            'clearInvalidConfig' => false
        ]);
    }

    public function testClearInvalidConfigOptionWithValidData()
    {
        $config = new Config([
            'configDir' => $this->tempdir(),
            'clearInvalidConfig' => false
        ]);
        $config->set('foo', 'bar');
        $this->assertEquals([ 'foo' => 'bar' ], $config->get());
    }

    public function testSchemaShouldBeAnObject()
    {
        $this->expectException(InvalidConfigSchemaException::class);

        new Config([
            'configDir' => $this->tempdir(),
            'schema' => 'object'
        ]);
    }

    public function testSchemaValidSet()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'foo' => [
                    'type' => 'object',
                    'properties' => [
                        'bar' => [
                            'type' => 'number'
                        ],
                        'foobar' => [
                            'type' => 'number',
                            'maximum' => 100
                        ]
                    ]
                ]
            ]
        ];

        $config = new Config([
            'configDir' => $this->tempdir(),
            'schema' => $schema
        ]);

        $config->set('foo', [
            'bar' => 1,
            'foobar' => 2
        ]);

        $this->addToAssertionCount(1);
    }

    public function testSchemaOneViolation()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'foo' => [
                    'type' => 'string'
                ]
            ]
        ];
        $config = new Config([
            'configDir' => $this->tempdir(),
            'schema' => $schema
        ]);
        $this->expectException(InvalidConfigException::class);
        $config->set('foo', 1);
    }

    public function testSchemaMultipleViolations()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'foo' => [
                    'type' => 'object',
                    'properties' => [
                        'bar' => [
                            'type' => 'number'
                        ],
                        'foobar' => [
                            'type' => 'number',
                            'maximum' => 100
                        ]
                    ]
                ]
            ]
        ];

        $config = new Config([
            'configDir' => $this->tempdir(),
            'schema' => $schema
        ]);
        $this->expectException(InvalidConfigException::class);
        $config->set('foo', [
            'bar' => '1',
            'foobar' => 101
        ]);
    }

    public function testComplexSchema()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'foo' => [
                    'type' => 'string',
                    'maxLength' => 3,
                    'pattern' => '[def]+'
                ],
                'bar' => [
                    'type' => 'array',
                    'uniqueItems' => true,
                    'maxItems' => 3,
                    'items' => [
                        'type' => 'integer'
                    ]
                ]
            ]
        ];
        $config = new Config([
            'configDir' => $this->tempdir(),
            'schema' => $schema
        ]);

        $this->expectException(InvalidConfigException::class);
        $config->set('foo', 'abca');

        $this->expectException(InvalidConfigException::class);
        $config->set('bar', [1, 1, 2, 'a']);
    }

    public function testSchemaValidateConfigDefault()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'foo' => [
                    'type' => 'string'
                ]
            ]
        ];

        $this->expectException(InvalidDefaultsException::class);
        new Config([
            'configDir' => $this->tempdir(),
            'defaults' => [
                'foo' => 1
            ],
            'schema' => $schema
        ]);
    }

    public function testGetWithoutDotNotation()
    {
        $this->assertSame(null, $this->configWithoutDotNotation->get('foo'));
        $this->assertSame('default', $this->configWithoutDotNotation->get('foo', 'default'));
        $this->configWithoutDotNotation->set('foo', static::$fixture);
        $this->assertSame(static::$fixture, $this->configWithoutDotNotation->get('foo'));
    }

    public function testSetWithoutDotNotation()
    {
        $this->configWithoutDotNotation->set('foo', static::$fixture);
        $this->configWithoutDotNotation->set('baz.boo', static::$fixture);
        $this->assertSame(static::$fixture, $this->configWithoutDotNotation->get('foo'));
        $this->assertSame(static::$fixture, $this->configWithoutDotNotation->get('baz.boo'));
    }

    public function testSetWithArrayWithoutDotNotation()
    {
        $this->configWithoutDotNotation->set([
            'foo1' => 'bar1',
            'foo2' => 'bar2',
            'baz' => [
                'boo' => 'foo',
                'foo' => [
                    'bar' => 'baz'
                ]
            ]
        ]);
        $this->assertSame('bar1', $this->configWithoutDotNotation->get('foo1'));
        $this->assertSame('bar2', $this->configWithoutDotNotation->get('foo2'));
        $this->assertEquals([
            'boo' => 'foo',
            'foo' => [
                'bar' => 'baz'
            ]
        ], $this->configWithoutDotNotation->get('baz'));
        $this->assertSame(null, $this->configWithoutDotNotation->get('baz.boo'));
        $this->assertSame(null, $this->configWithoutDotNotation->get('baz.foo.bar'));
    }

    public function testHasWithoutDotNotation()
    {
        $this->configWithoutDotNotation->set('foo', static::$fixture);
        $this->configWithoutDotNotation->set('baz.boo', static::$fixture);
        $this->assertTrue($this->configWithoutDotNotation->has('foo'));
        $this->assertTrue($this->configWithoutDotNotation->has('baz.boo'));
        $this->assertFalse($this->configWithoutDotNotation->has('missing'));
    }

    public function testDeleteWithoutDotNotation()
    {
        $config = $this->configWithoutDotNotation;

        $config->set('foo', 'bar');
        $config->set('baz.boo', true);
        $config->set('baz.foo.bar', 'baz');
        $config->delete('foo');
        $this->assertSame(null, $config->get('foo'));
        $config->delete('baz.boo');
        $this->assertNotSame($config->get('baz.boo'), true);
        $config->delete('baz.foo');
        $this->assertNotSame([ 'bar' => 'baz' ], $config->get('baz.foo'));
        $config->set('foo.bar.baz', [
            'awesome' => 'icecream'
        ]);
        $config->set('foo.bar.zoo', [
            'awesome' => 'redpanda'
        ]);
        $config->delete('foo.bar.baz');
        $this->assertEquals([
            'awesome' => 'redpanda'
        ], $config->get('foo.bar.zoo'));
    }

    public function testSubclassStaticConfig()
    {
        TestableStaticConfig::$creations = 0;
        TestableStaticConfig::$options = [
            'configDir' => $this->tempdir()
        ];

        $this->assertInstanceOf(
            Config::class,
            TestableStaticConfig::getInstance()
        );

        TestableStaticConfig::set('foo', 'bar');
        $this->assertSame('bar', TestableStaticConfig::get('foo'));

        $this->assertSame(1, TestableStaticConfig::$creations);
    }
}
