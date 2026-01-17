<?php

namespace MarketDataApp\Exceptions;

/**
 * BadStatusCodeError class
 *
 * This exception is raised for permanent HTTP errors (4xx)
 * that should not trigger retry logic.
 */
class BadStatusCodeError extends \Exception
{
    /**
     * @var mixed The API response associated with this exception.
     */
    private $response;

    /**
     * BadStatusCodeError constructor.
     *
     * @param string          $message  The exception message.
     * @param int             $code     The exception code.
     * @param \Exception|null $previous The previous exception used for exception chaining.
     * @param mixed           $response The API response associated with this exception.
     */
    public function __construct($message = "", $code = 0, \Exception $previous = null, $response = null)
    {
        parent::__construct($message, $code, $previous);
        $this->response = $response;
    }

    /**
     * Get the API response associated with this exception.
     *
     * @return mixed The API response.
     */
    public function getResponse()
    {
        return $this->response;
    }
}
