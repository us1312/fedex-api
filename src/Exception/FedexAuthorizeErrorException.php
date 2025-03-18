<?php

namespace SCA\FedexApi\Exception;

class FedexAuthorizeErrorException extends FedexException {
    
    public function __construct($message) {
        $this->message = $message;
    }
    
}