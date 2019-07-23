<?php namespace Loilo\SimpleConfig\Exception;

use UnexpectedValueException;

/**
 * A JSON schema violation of some sort has occured
 */
class SchemaViolationException extends UnexpectedValueException
{
    /**
     * @var array|null
     */
    protected $errors;

    /**
     * SchemaViolationException constructor.
     *
     * @param string     $message  Error message
     * @param int        $code     Error code
     * @param \Throwable $previous Previous error
     * @param array      $errors   Occured validation errors
     */
    public function __construct($message = '', $code = 0, $previous = null, $errors = null)
    {
        if (is_array($errors)) {
            $errorsList = join("\n", array_map(function ($error) {
                if (isset($error['property']) && strlen($error['property']) > 0) {
                    return sprintf(
                        '- [%s]: %s',
                        $error['property'],
                        $error['message']
                    );
                } else {
                    return sprintf('- %s', $error['message']);
                }
            }, $errors));

            if (strlen($message) === 0) {
                $message = sprintf(
                    "Data does not match required JSON schema:\n%s",
                    $errorsList
                );
            } else {
                $message = sprintf($message, $errorsList);
            }
        }

        parent::__construct($message, $code, $previous);

        $this->errors = $errors;
    }

    /**
     * Get the JSON validation errors that led to this exception
     *
     * @return array|null
     */
    public function getErrors()
    {
        return $this->errors;
    }
}
