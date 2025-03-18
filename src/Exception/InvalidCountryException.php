<?php

namespace SCA\FedexApi\Exception;

use SCA\FedexApi\Exception\FedexException;

class InvalidCountryException extends FedexException {

    public function __construct($message) {
        $this->message = $message;
    }
}