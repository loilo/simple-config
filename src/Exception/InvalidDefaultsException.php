<?php namespace Loilo\SimpleConfig\Exception;

/**
 * The default config values do not match the schema
 */
class InvalidDefaultsException extends SchemaViolationException
{
    /**
     * {@inheritdoc}
     */
    public function __construct($message = '', $code = 0, $previous = null, $errors = null)
    {
        parent::__construct(
            strlen($message) === 0
                ? "Config defaults do not match the required JSON schema:\n%s"
                : $message,
            $code,
            $previous,
            $errors
        );
    }
}
