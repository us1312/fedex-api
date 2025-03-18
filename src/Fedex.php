<?php

namespace SCA\FedexApi;


use SCA\FedexApi\Exception\FedexAuthorizeErrorException;
use SCA\FedexApi\Exception\FedexBadResponseException;
use SCA\FedexApi\Exception\FedexException;
use SCA\FedexApi\Validation\CountryValidator;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Fedex {

    private CountryValidator $countryValidator;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[TaggedIterator('carriers.country')] private $countries
    ) {
        $this->client = new Client($this->httpClient);
        $this->countryValidator = new CountryValidator($countries);
    }
    
    public function setCredentials(string $apiKey, string $apiSecret, string $apiUrl) {
        $this->client->setCredentials($apiKey, $apiSecret, $apiUrl);
    }

    public function validateAddress(array $street, string $country, string $city = '', string $state = '', string $postal = '', ) {
        $this->countryValidator->validateCountry($country);
        $country = $this->countryValidator->normalizeCountryToCode();
        $headers = [
            'Content-Type' => 'application/json',
            'x-locale' => 'en_US',
        ];
        $body = [
            'addressesToValidate' => [
                [
                    'address' => [
                        'streetLines' => $street,
                        'city' => $city,
                        'stateOrProvinceCode' => $state,
                        'postalCode' => $postal,
                        'countryCode' => $country,
                    ],
                ]
            ]
        ];
        return $this->client->makeRequest('POST', Endpoints::ADDRESS->getEndpoint(), $body, $headers);
    }
}