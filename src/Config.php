<?php namespace Loilo\SimpleConfig;

use ArrayAccess;
use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use UnexpectedValueException;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Exception\IOException as CryptoIOException;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use Defuse\Crypto\File;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use Loilo\FindUp\Up;
use Loilo\SimpleConfig\Exception\DeserializationException;
use Loilo\SimpleConfig\Exception\InvalidConfigException;
use Loilo\SimpleConfig\Exception\InvalidConfigSchemaException;
use Loilo\SimpleConfig\Exception\InvalidDefaultsException;
use Loilo\SimpleConfig\Exception\SchemaViolationException;
use Loilo\SimpleConfig\Exception\SerializationException;
use Loilo\SimpleConfig\Store\DotAccessStore;
use Loilo\SimpleConfig\Store\PlainStore;
use Loilo\SimpleConfig\Store\StoreInterface;
use Loilo\StoragePaths\StoragePaths;
use Loilo\Traceback\Traceback;
use Loilo\XFilesystem\XFilesystem;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Exception\IOException;
use Webmozart\PathUtil\Path;

/**
 * The Config class - manages your app's or module's configuration
 */
class Config implements IteratorAggregate, Countable, ArrayAccess
{
    /**
     * @var string
     */
    protected $configFilePath;

    /**
     * @var StoreInterface
     */
    protected $store;

    /**
     * @var XFilesystem
     */
    protected $fs;

    /**
     * JSON Schema describing the acceptable structure of the configuration
     *
     * @var array|null
     */
    protected $schema;

    /**
     * Holds the last state of the store in which it matched the given schema
     *
     * @var array
     */
    protected $lastConsistentState;

    /**
     * Default valuesstore
     *
     * @var StoreInterface
     */
    protected $defaults;

    /**
     * The config file extension
     *
     * @var string
     */
    protected $formatExtension;

    /**
     * The config serializer
     *
     * @var callable
     */
    protected $formatSerialize;

    /**
     * The config deserializer
     *
     * @var callable
     */
    protected $formatDeserialize;

    /**
     * @var string
     */
    protected $password;

    /**
     * Create a new Config instance
     *
     * @param array $options The options to modify config behavior
     *
     * @throws IOException When the config file exists but could not be read
     * @throws CryptoIOException When the in-memory data stream is not readable (should *never* happen)
     * @throws EnvironmentIsBrokenException When an invalid password format is used
     * @throws WrongKeyOrModifiedCiphertextException When an invalid password is used for decryption
     * @throws DeserializationException When deserialization of a config file fails
     * @throws InvalidConfigSchemaException When the required schema is invalid
     * @throws InvalidDefaultsException When the default values do not macht the schema
     * @throws InvalidConfigException When the read config does not match the schema
     */
    public function __construct($options = [])
    {
        $defaultFormat = [
            'extension' => 'json',
            'serialize' => function ($data) {
                $serialized = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

                if (json_last_error()) {
                    throw new SerializationException(json_last_error_msg());
                }

                return $serialized;
            },
            'deserialize' => function ($string) {
                $data = json_decode($string, true);

                if (json_last_error()) {
                    throw new DeserializationException(json_last_error_msg());
                }

                return $data;
            }
        ];

        $options = array_merge([
            'defaults' => null,
            'schema' => null,
            'configName' => 'config',
            'projectName' => null,
            'configDir' => null,
            'format' => $defaultFormat,
            'password' => null,
            'dotNotation' => true,
            'clearInvalidConfig' => true
        ], $options);

        $this->validateFilename($options['configName']);

        // Project name
        if (is_null($options['projectName'])) {
            $options['projectName'] = $this->determineProjectNameFromTrace();
        } else {
            $this->validateFilename($options['projectName']);
        }


        // Password
        $this->password = $options['password'];


        // Format
        if (is_array($options['format'])) {
            $options['format'] = array_merge($defaultFormat, $options['format']);
        }

        if (!is_string($options['format']['extension'])) {
            throw new InvalidArgumentException('The format.extension option must be a string');
        }
        $this->formatExtension = $options['format']['extension'];

        if (!is_callable($options['format']['serialize'])) {
            throw new InvalidArgumentException('The format.serialize option must be a callable');
        }
        $this->formatSerialize = $options['format']['serialize'];

        if (!is_callable($options['format']['deserialize'])) {
            throw new InvalidArgumentException('The format.deserialize option must be a callable');
        }
        $this->formatDeserialize = $options['format']['deserialize'];


        // Schema
        if (!is_null($options['schema'])) {
            if (!is_array($options['schema']) || !isset($options['schema']['type']) || $options['schema']['type'] !== 'object') {
                throw new InvalidConfigSchemaException('Config schema is expected to be of "type": "object"');
            }
        }

        $this->schema = $options['schema'];

        // Validate defaults
        if (!is_null($this->schema)) {
            try {
                $this->validateDefaults($options['defaults'] ?? []);
            } catch (SchemaViolationException $e) {
                throw new InvalidDefaultsException('', 0, null, $e->getErrors());
            }
        }

        $this->fs = new XFilesystem();

        if (is_null($options['configDir'])) {
            $options['configDir'] = StoragePaths::for($options['projectName'])->config();
            $this->fs->mkdir($options['configDir']);
        } elseif (!is_dir($options['configDir'])) {
            throw new InvalidArgumentException(sprintf(
                'Configured configDir "%s" does not exist',
                $options['configDir']
            ));
        }

        $extension = '';
        $extensionIgnoringEncryption = '';

        if (strlen($options['format']['extension']) > 0) {
            $extensionIgnoringEncryption = '.' . $options['format']['extension'];

            if (is_null($options['password'])) {
                $extension = $extensionIgnoringEncryption;
            }
        }

        $this->configFilePath = Path::join(
            $options['configDir'],
            $options['configName'] . $extension
        );

        $configPathIgnoringEncryption = Path::join(
            $options['configDir'],
            $options['configName'] . $extensionIgnoringEncryption
        );

        if (!is_null($this->password) &&
            !$this->fs->exists($this->configFilePath) &&
            $this->fs->exists($configPathIgnoringEncryption)
        ) {
            $serializedData = $this->fs->readFile($configPathIgnoringEncryption);
        }

        if ($this->fs->exists($this->configFilePath)) {
            if (is_null($this->password)) {
                $serializedData = $this->fs->readFile($this->configFilePath);
            } else {
                $dataStream = fopen('php://memory', 'r+');
                $configFileHandle = fopen($this->configFilePath, 'r');

                if ($configFileHandle === false) {
                    throw new IOException(
                        sprintf(
                            'Could not read from file "%s".',
                            $this->configFilePath
                        ),
                        0,
                        null,
                        $this->configFilePath
                    );
                }

                try {
                    File::decryptResourceWithPassword(
                        $configFileHandle,
                        $dataStream,
                        $this->password
                    );
                } catch (CryptoIOException | EnvironmentIsBrokenException | WrongKeyOrModifiedCiphertextException $e) {
                    if ($options['clearInvalidConfig']) {
                        $this->removeInvalidConfig();

                        $serializedData = call_user_func($this->formatSerialize, []);
                    } else {
                        throw $e;
                    }
                } finally {
                    fclose($configFileHandle);

                    if (!isset($serializedData)) {
                        rewind($dataStream);
                        $serializedData = stream_get_contents($dataStream);
                    }

                    fclose($dataStream);
                }
            }
        }

        if (isset($serializedData)) {
            try {
                $data = call_user_func($this->formatDeserialize, $serializedData);
            } catch (DeserializationException $e) {
                if ($options['clearInvalidConfig']) {
                    $this->removeInvalidConfig();

                    $data = [];
                } else {
                    throw $e;
                }
            }

            if (!is_array($data)) {
                throw new DeserializationException(sprintf(
                    'Deserialized data must be an array, %s given',
                    gettype($data)
                ));
            }

            try {
                $this->validateData($data);
            } catch (InvalidConfigException $e) {
                if ($options['clearInvalidConfig']) {
                    $this->removeInvalidConfig();

                    $data = [];
                } else {
                    throw $e;
                }
            }
        } else {
            $data = [];
        }

        if ($options['dotNotation']) {
            $this->defaults = new DotAccessStore();
            $this->store = new DotAccessStore();
        } else {
            $this->defaults = new PlainStore();
            $this->store = new PlainStore();
        }

        $this->defaults->store($options['defaults'] ?? []);
        $this->lastConsistentState = $data;
        $this->store->store($data);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new ArrayIterator($this->get());
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return sizeof($this->get());
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException When the first argument is invalid
     * @throws CryptoIOException When the in-memory data stream is not readable (should *never* happen)
     * @throws EnvironmentIsBrokenException When an invalid password format is used
     * @throws WrongKeyOrModifiedCiphertextException When an invalid password is used for encryption
     */
    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    /**
     * {@inheritdoc}
     *
     * @throws CryptoIOException When the in-memory data stream is not readable (should *never* happen)
     * @throws EnvironmentIsBrokenException When an invalid password format is used
     * @throws WrongKeyOrModifiedCiphertextException When an invalid password is used for encryption
     */
    public function offsetUnset($offset)
    {
        $this->delete($offset);
    }

    /**
     * Remove the (invalid) config file
     */
    protected function removeInvalidConfig(): void
    {
        $this->fs->remove($this->configFilePath);
    }

    /**
     * Validate a filename to the lowest common cross-OS denominator
     * @see https://docs.microsoft.com/de-de/windows/win32/fileio/naming-a-file#naming_conventions
     *
     * @param mixed $filename The filename to validate
     *
     * @throws InvalidArgumentException When the filename is invalid
     */
    protected function validateFilename($filename): void
    {
        if (!is_string($filename) ||
            preg_match('/^(con|prn|aux|nul|com[0-9]|lpt[0-9])$/i', $filename) ||
            preg_match('@[<>:"/\\\\|?*\x{00}-\x{1F}]@', $filename)
        ) {
            throw new InvalidArgumentException('Invalid is not a valid file name');
        }
    }

    /**
     * Create a readable resource from a string
     *
     * @param string $string
     * @return resource
     */
    protected function createResourceFromString(string $string)
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $string);
        rewind($stream);

        return $stream;
    }

    /**
     * Determine the config's project's name from the stack trace
     *
     * @return string
     *
     * @throws FileNotFoundException If no composer.json can be found from the calling file upwards
     * @throws UnexpectedValueException if the found composer.json does not contain a "name"
     */
    protected function determineProjectNameFromTrace(): string
    {
        $callingDir = Traceback::dir();
        $composerJsonPath = Up::find('composer.json', $callingDir);

        if (is_null($composerJsonPath)) {
            throw new FileNotFoundException(sprintf(
                'Cannot find composer.json from "%s" upwards for project name detection, please provide a custom "projectName" option',
                $callingDir
            ));
        }

        $this->fs = new XFilesystem();
        $composerJsonData = $this->fs->readJsonFile($composerJsonPath);

        if (!isset($composerJsonData->name) ||
            !is_string($composerJsonData->name) ||
            empty($composerJsonData->name)
        ) {
            throw new UnexpectedValueException('Cannot find "name" in composer.json, please provide a custom "projectName" option');
        }

        return $this->sanitizeCacheName($composerJsonData->name);
    }

    /**
     * Sanitize a Composer package name to make it a feasible config project name
     *
     * @param string $name The package name to sanitize
     * @return string The sanitized config project name
     */
    protected function sanitizeCacheName(string $name): string
    {
        return preg_replace(
            '/_+_/',
            '_',
            preg_replace('/[^a-z0-9._-]/i', '-', $name)
        );
    }

    /**
     * Validate the current store state and write it to the filesystem
     *
     * @throws InvalidConfigException When the current store state does not match the required schema
     * @throws CryptoIOException When the in-memory data stream is not readable (should *never* happen)
     * @throws EnvironmentIsBrokenException When an invalid password format is used
     * @throws WrongKeyOrModifiedCiphertextException When an invalid password is used for encryption
     */
    protected function updateStore()
    {
        $data = $this->store->jsonSerialize();

        try {
            $this->validateData($data);
            $this->lastConsistentState = $data;
        } catch (InvalidConfigException $e) {
            // On schema violation, reset data in the store
            $this->store->store($this->lastConsistentState);
            throw $e;
        }

        $this->fs->mkdir(dirname($this->configFilePath));

        $serializedData = call_user_func($this->formatSerialize, $data);

        if (!is_string($serializedData)) {
            throw new SerializationException(sprintf(
                'Serialized data must be a string, %s given',
                gettype($serializedData)
            ));
        }

        if (!is_null($this->password)) {
            $dataStream = fopen('php://memory', 'r+');
            fwrite($dataStream, $serializedData);
            rewind($dataStream);

            $configFileStream = fopen($this->configFilePath, 'w');

            File::encryptResourceWithPassword(
                $dataStream,
                $configFileStream,
                $this->password
            );

            fclose($configFileStream);
            fclose($dataStream);
        } else {
            $this->fs->dumpFile($this->configFilePath, $serializedData);
        }
    }

    /**
     * Validate data against the config's schema
     * Omitting required top-level properties is ignored as those must be provided by the defaults
     *
     * @param array $data The data to validate
     *
     * @throws InvalidConfigException When the data does not match the required schema
     */
    protected function validateData(array $data)
    {
        if (!is_null($this->schema)) {
            $validator = new Validator();
            $validator->validate(
                $data,
                $this->schema,
                Constraint::CHECK_MODE_TYPE_CAST | Constraint::CHECK_MODE_APPLY_DEFAULTS
            );

            if (!$validator->isValid()) {
                $nonTopLevelRequiredErrors = array_filter($validator->getErrors(), function ($error) {
                    return !(
                        isset($error['constraint']) &&
                        $error['constraint'] === 'required' &&
                        isset($error['property']) &&
                        strpos($error['property'], '.') === false
                    );
                });

                if (sizeof($nonTopLevelRequiredErrors) > 0) {
                    throw new InvalidConfigException('', 0, null, $validator->getErrors());
                }
            }
        }
    }


    /**
     * Validate store defaults against the config's schema
     *
     * @param array $data The defaults to validate
     *
     * @throws InvalidConfigException When the defaults do not match the required schema
     */
    protected function validateDefaults(array $data)
    {
        if (!is_null($this->schema)) {
            $validator = new Validator();
            $validator->validate(
                $data,
                $this->schema,
                Constraint::CHECK_MODE_TYPE_CAST
            );

            if (!$validator->isValid()) {
                throw new InvalidConfigException('', 0, null, $validator->getErrors());
            }
        }
    }

    /**
     * Get the config file path
     *
     * @return string
     */
    public function getFilePath(): string
    {
        return $this->configFilePath;
    }

    /**
     * Check whether the store contains a value under the given key
     *
     * @param string $key The key in the store to look up
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->store->has($key) || (strpos($key, '.') === false && $this->defaults->has($key));
    }

    /**
     * Get a value from the store by key or return all values
     *
     * @param string|null $key     The key to look up in the store, yields the whole store if omitted
     * @param mixed       $default The value that is returned if the key can not be found in the store
     * @return mixed
     */
    public function get(?string $key = null, $default = null)
    {
        if (is_null($key)) {
            return array_merge($this->defaults->all(), $this->store->all());
        } else {
            $firstPart = explode('.', $key)[0];
            return $this->store->get(
                $key,
                !$this->store->has($firstPart)
                    ? $this->defaults->get($key, $default)
                    : $default
            );
        }
    }

    /**
     * Set one or multiple values in the store
     *
     * @param string|array $keyOrData Either the key to update or an array of key-value pairs to merge into the store
     *                                Note that if dot notation is enabled, keys of such key-value-pairs will be
     *                                resolved into actual arrays
     * @param mixed $value            The value to set, ignored if $keyOrData is passed an array
     *
     * @throws InvalidArgumentException When the first argument is invalid
     * @throws CryptoIOException When the in-memory data stream is not readable (should *never* happen)
     * @throws EnvironmentIsBrokenException When an invalid password format is used
     * @throws WrongKeyOrModifiedCiphertextException When an invalid password is used for encryption
     */
    public function set($keyOrData, $value = null): void
    {
        $previousData = $this->store->jsonSerialize();

        if (is_string($keyOrData)) {
            $this->store->set($keyOrData, $value);
        } elseif (is_array($keyOrData)) {
            $this->store->merge($keyOrData);
        } else {
            throw new InvalidArgumentException('Invalid first argument for Config::set(), must be either string or array');
        }

        if ($previousData !== $this->store->jsonSerialize()) {
            $this->updateStore();
        }
    }

    /**
     * Delete a value from the store or clear the store altogether
     *
     * @param string|null $key If passed a key, that key is deleted from the store
     *                         If the key is omitted, the whole store will be cleared
     *
     * @throws CryptoIOException When the in-memory data stream is not readable (should *never* happen)
     * @throws EnvironmentIsBrokenException When an invalid password format is used
     * @throws WrongKeyOrModifiedCiphertextException When an invalid password is used for encryption
     */
    public function delete(?string $key = null): void
    {
        $previousData = $this->store->jsonSerialize();

        if (is_null($key)) {
            $this->store->clear();
        } else {
            $this->store->delete($key);
        }

        if ($previousData !== $this->store->jsonSerialize()) {
            $this->updateStore();
        }
    }
}
