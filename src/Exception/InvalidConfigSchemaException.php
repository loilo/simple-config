<?php namespace Loilo\SimpleConfig\Exception;

/**
 * The JSON schema is invalid
 */
class InvalidConfigSchemaException extends SchemaViolationException
{
    /**
     * {@inheritdoc}
     */
    public function __construct($message = '', $code = 0, $previous = null, $errors = null)
    {
        parent::__construct(
            strlen($message) === 0
                ? "Configuration schema is not a valid JSON schema:\n%s"
                : $message,
            $code,
            $previous,
            $errors
        );
    }
}
