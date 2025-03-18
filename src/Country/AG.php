<?php

namespace SCA\FedexApi\Country;

use SCA\FedexApi\Country\CountryInterface;

class AG implements CountryInterface {

    private string $countryCode = 'AG';
    
    private string $countryName = 'Antigua, Barbuda';
    
    public function getCountryCode(): string {
        return $this->countryCode;
    }
    
    public function getCountryName(): string {
        return $this->countryName;
    }
    
    public function supports(string $country): bool {
        return $country === $this->countryCode || $country === $this->countryName;
    }
}