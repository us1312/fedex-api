<?php

namespace SCA\FedexApi\Validation;

class CountryValidator {

    private iterable $countries;

    public function __construct($countries) {
        $this->countries = $countries;
    }

    public function validateCountry($country): bool {
        foreach ($this->countries as $country) {
            if ($country->supports($country)) {
                return true;
            }
        }

        return false;
    }

    public function normalizeCountryToCode($country): ?string {
        foreach ($this->countries as $country) {
            if ($country->supports($country)) {
                return $country->getCountryCode();
            }
        }

        return null;
    }
}