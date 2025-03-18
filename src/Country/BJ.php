<?php

namespace SCA\FedexApi\Country;

use SCA\FedexApi\Country\CountryInterface;

class BJ implements CountryInterface {

    private string $countryCode = 'BJ';
    
    private string $countryName = 'Benin';
    
	private array $fields = [
		'streetLines',
		'city',
		'stateOrProvinceCode',
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