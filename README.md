<div align="center">
  <img alt="Simple Config logo: two interleaved gears, representing a typical &quot;settings&quot; icon" src="simple-config.svg" width="280" height="220">
</div>

# Simple Config
[![Test status on Travis](https://badgen.net/travis/loilo/simple-config?label=tests&icon=travis)](https://travis-ci.org/loilo/simple-config)
[![Version on packagist.org](https://badgen.net/packagist/v/loilo/simple-config)](https://packagist.org/packages/loilo/simple-config)

> Simple persistent configuration for your app or module, heavily inspired by Sindre Sorhus' [conf](https://www.npmjs.com/package/conf) package.

## Installation
```bash
composer require loilo/simple-config
```

## Usage
```php
use Loilo\SimpleConfig\Config;

$config = new Config();

$config->set('foo', 'bar');
$config->get('foo') === 'bar';
 
// Use dot notation to access nested options
$config->set('baz.qux', true);
$config->get('baz') === [ 'qux' => true ];
 
$config->delete('foo');
$config->get('foo') === null;
```

### Methods
There are four methods on the `Config` object that you may use to work with the data store — `get`, `set`, `has` and `delete`:

* To **check for presence of an option**, use `has`:

  ```php
  $config->has('option')
  ```

* To **read an option**, use `get`:

  ```php
  $config->get('option')
  ```

  You may also pass a second argument to use as a fallback if the option is not found (defaults to `null`):

  ```php
  $config->get('nonexistent_option', 'fallback value')
  ```

* To **read the whole configuration**, use `get` with no arguments:

  ```php
  $config->get()
  ```

* To **write an option** (immediately synced with the config file), use `set`:

  ```php
  $config->set('option', 'value')
  ```

* To **write multiple options**, use `set` with an associative array:

  ```php
  $config->set([
      'option-1' => 'value-1',
      'option-2' => 'value-2'
  ])
  ```

* To **remove an option**, use `delete`:

  ```php
  $config->delete('option')
  ```

* To **clear the config file**, use `delete` with no arguments:

  ```php
  $config->delete()
  ```

### Options
The `Config` object can be initialized with an associative array of options documented below.

```php
$config = new Config([
  // options go here
]);
```

### `defaults`

**Type:** `array`<br>
**Default:** `null`

Default values for config items

> **Note 1:** Default values are applied with shallow (not recursive) merging: only missing *top level* options in the data are supplemented by defaults.
>
> **Note 2:** Defaults are never written to the config file so if you change your app's defaults and users have not overridden them explicitly, they will also change for all users.

### `schema`

**Type:** `array`<br>
**Default:** `null`

A [JSON Schema](https://json-schema.org) to validate your config data. [JSON Schema draft-07](http://json-schema.org/latest/json-schema-validation.html) is used as far as it's supported by the underlying [validator package](https://github.com/justinrainbow/json-schema).

> **Note 1:** Your top-level schema definition is enforced to be of `"type": "object"`.
>
> **Note 2:** Default values defined in your schema are applied during validation, but are not returned when requested via `Config::get()`. This is a limitation of the underlying validator, as there's currently no JSON schema validator in PHP that applies default values to validated data and then allows access to them.
>
> **Note 3:** If your schema defines any mandatory top-level fields, you'll need to provide [defaults](#defaults) that satisfy the schema. This avoids schema violation errors when initializing an empty configuration.

### `configName`

**Type:** `string`<br>
**Default:** `"config"`

Name of the config file (without extension).

Useful if you need multiple config files for your app or module (e.g. different config files between two major versions).

### `projectName`

**Type:** `string`<br>
**Default:** The `name` field in the `composer.json` closest to where `new Config()` is called.

You only need to specify this if you don't have a `composer.json` file in your project.

### `configDir`

**Type:** `string`<br>
**Default:** System default [user config directory](https://github.com/loilo/storage-paths#result)

The place where your config file is stored. You may override this to store configuration locally in your app's folder.

> **Note:** If you define this, the `projectName` option will be ignored.

### `format`
**Type:** `array`<br>

Settings regarding the configuration format. This is an associative array with the following possible keys:

* `serialize`
  
  **Type:** `callable`<br>
  **Default:** `function ($data) { return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); }`
  
  Function to serialize the config object to a UTF-8 string when writing the config file.
  
  You would usually not need this, but it could be useful if you want to use a format other than JSON.
  
* `deserialize`
  
  **Type:** `callable`<br>
  **Default:** `function ($string) {  return json_decode($string, true); }`
  
  Function to deserialize the config object from a UTF-8 string when reading the config file.
  
  You would usually not need this, but it could be useful if you want to use a format other than JSON.

* `extension`
  
  **Type:** `string`<br>
  **Default:** `"json"`
  
  File extension of the config file. This may be reasonable to set if you changed your `serialize`/`deserialize` options.

  > **Note:** If you encrypt the configuration with a [password](#password), the config file will be saved in a binary format and this option will be ignored.

### `password`

**Type:** `string`<br>
**Default:** `null`

A password to encrypt/decrypt the configuration file with. This can secure sensitive data, however it's naturally only as secure as your way of managing the password itself.

> **Note:** If you encrypt the configuration with a password, the config file will be saved in a binary format and the [`format.extension`](#format) option will be ignored.

### `dotNotation`
**Type:** `boolean`<br>
**Default:** `true`

Whether to access options by dot notation.

```js
$config->set([
    'foo' => [
        'bar' => [
            'foobar' => 'qux'
        ]
    ]
]);

// With dot notation enabled:
$config->get('foo.bar.foobar') === 'qux';
$config->get('foo')['bar']['foobar'] === 'qux';

// With dot notation disabled:
$config->get('foo.bar.foobar') === null;
$config->get('foo')['bar']['foobar'] === 'qux';
```

### `clearInvalidConfig`

**Type:** `boolean`<br>
**Default:** `true`

If set to `true`, the configuration is cleared if reading it raises an exception of any sort:

Exception | Cause
-|-
| [`IOException`](https://github.com/symfony/symfony/blob/4.0/src/Symfony/Component/Filesystem/Exception/IOException.php) | The config file exists but could not be read.
[`EnvironmentIsBrokenException`](https://github.com/defuse/php-encryption/blob/v2.0.0/src/Exception/EnvironmentIsBrokenException.php)<br>[`WrongKeyOrModifiedCiphertextException`](https://github.com/defuse/php-encryption/blob/v2.0.0/src/Exception/WrongKeyOrModifiedCiphertextException.php) | File decryption failed (when using a [password](#password)).
[`DeserializationException`](src/Exception/DeserializationException.php) | Deserialization failed (e.g. if the [deserialization function](#deserialize) changed or if someone or something meddled with the file).
[`InvalidConfigException`](src/Exception/InvalidConfigException.php) | The configuration is invalid according to the [schema](#schema).

Enabling this option is a good default, as the config file is not intended to be hand-edited, so this usually means the config is corrupt and there's nothing your app can do about it anyway. However, if you let the user edit the config file directly, mistakes might happen and it could be useful to throw an error when the config is invalid instead of clearing it.

Disabling this option will cause the exceptions listed above to be re-thrown and handled manually.
