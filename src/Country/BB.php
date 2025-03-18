<?php

namespace SCA\FedexApi\Country;

use SCA\FedexApi\Country\CountryInterface;

class BB implements CountryInterface {

    private string $countryCode = 'BB';
    
    private string $countryName = 'Barbados';
    
	private array $fields = [
		'streetLines',
		'city',
		'stateOrProvinceCode',
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