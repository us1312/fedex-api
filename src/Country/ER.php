<?php

namespace SCA\FedexApi\Country;

use SCA\FedexApi\Country\CountryInterface;

class ER implements CountryInterface {

    private string $countryCode = 'ER';
    
    private string $countryName = 'Eritrea';
    
	private array $fields = [
		'streetLines',
		'city',
		'postalCode',
		'countryCode',
	];

    public function getCountryCode(): string {
        return $this->countryCode;
    }
    
    public function getCountryName(): string {
        return $this->countryName;
    }
    
	public function getFields(): array {
		return $this->fields;
	}

    public function supports(string $country): bool {
        return $country === $this->countryCode || $country === $this->countryName;
    }
}