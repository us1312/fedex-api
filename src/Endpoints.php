<?php

namespace SCA\FedexApi;

enum Endpoints {

    case ADDRESS;
    
    public function getEndpoint(): string {
        return match ($this) {
            self::ADDRESS => 'address/v1/addresses/resolve',
        };
    }
}