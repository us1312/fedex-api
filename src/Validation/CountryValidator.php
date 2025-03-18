<?php

namespace SCA\FedexApi\Validation;

use SCA\FedexApi\Exception\InvalidCountryException;

class CountryValidator {

    private iterable $countries;

    public function __construct($countries) {
        $this->countries = $countries;
    }

    public function validateCountry($country) {
        foreach ($this->countries as $country) {
            if ($country->supports($country)) {
                return true;
            }
        }

        throw new InvalidCountryException();
    }

    public function normalizeCountryToCode($country) {
        foreach ($this->countries as $country) {
            if ($country->supports($country)) {
                return $country->getCountryCode();
            }
        }
    }
}