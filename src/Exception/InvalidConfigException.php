<?php namespace Loilo\SimpleConfig\Exception;

/**
 * The config values do not match the schema
 */
class InvalidConfigException extends SchemaViolationException
{
    /**
     * {@inheritdoc}
     */
    public function __construct($message = '', $code = 0, $previous = null, $errors = null)
    {
        parent::__construct(
            strlen($message) === 0
                ? "Configuration does not match JSON schema:\n%s"
                : $message,
            $code,
            $previous,
            $errors
        );
    }
}
