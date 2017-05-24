<?php

namespace Weareenvoy\CraftGreenhouse;

use Throwable;

/**
 * Class Exception
 *
 * @package Weareenvoy\CraftGreenhouse
 */
class Exception extends \Exception
{
    /**
     * @var array
     */
    protected $errors;

    /**
     * Exception constructor.
     *
     * @param array          $errors
     * @param string         $message
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct($errors = [], $message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->errors = $errors;
    }

    /**
     * @return array
     */
    public function getErrorMessages(): array
    {
        return $this->errors;
    }
}
