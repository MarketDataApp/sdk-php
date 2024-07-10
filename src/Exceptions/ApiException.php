<?php
namespace MarketDataApp\Exceptions;

class ApiException extends \Exception {
    private $response;

    public function __construct($message, $code = 0, \Exception $previous = null, $response = null) {
        parent::__construct($message, $code, $previous);
        $this->response = $response;
    }

    public function getResponse() {
        return $this->response;
    }
}
