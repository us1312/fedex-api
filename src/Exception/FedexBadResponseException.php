<?php

namespace SCA\FedexApi\Exception;

class FedexBadResponseException extends FedexException {

    public function __construct($message) {
        $this->message = $message;
    }
    
}