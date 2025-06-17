<?php

namespace SCA\FedexApi;


use SCA\FedexApi\Error\Errors;
use SCA\FedexApi\Error\ErrorTypes;
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
    
    public function setCredentials(string $apiKey, string $apiSecret, string $apiAccountNo, string $apiUrl) {
        $this->client->setCredentials($apiKey, $apiSecret, $apiAccountNo, $apiUrl);
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

    public function validateShipment(array $data) {
        $headers = [
            'Content-Type' => 'application/json',
            'x-locale' => 'en_US',
        ];

        try {
            $results = $this->client->makeRequest('POST', Endpoints::VALIDATE_SHIPMENT->getEndpoint(), $data, $headers);

            return $results;
        } catch (FedexException $e) {
            $errors = json_decode($e->getMessage(), true);
            $exception = [];
            foreach ($errors as $error) {
                $exception[$error['code']] = ErrorTypes::get($error['code']);
            }

            throw new FedexBadResponseException(json_encode($exception));
        }
    }

    public function createShipment(array $data) {
        try {
            $headers = [
                'Content-Type' => 'application/json',
                'x-locale' => 'en_US',
            ];
            $results = $this->client->makeRequest('POST', Endpoints::CREATE_SHIPMENT->getEndpoint(), $data, $headers);

            return $results;
        } catch (FedexException $e) {
            $errors = json_decode($e->getMessage(), true);
            $exception = [];
            if ($errors['transactionId']) {
                try {
                    $this->retrieveASyncShipment($errors['transactionId']);
                } catch (FedexException $e) {
                    $errors = json_decode($e->getMessage(), true);
                    foreach ($errors as $error) {
                        $exception[$error['code']] = ErrorTypes::get($error['code']);
                    }
                    throw new FedexBadResponseException(json_encode($exception));
                }

            }
            foreach ($errors as $error) {
                $exception[$error['code']] = ErrorTypes::get($error['code']);
            }

            throw new FedexBadResponseException(json_encode($exception));
        }
    }

    public function uploadDocument(string $pdfFile, string $documentName, string $originCountryCode, string $destinationCountryCode) {
        $encoded = base64_encode(file_get_contents($pdfFile));
        $headers = [
            'Content-Type' => 'multipart/form-data',
        ];

        $body = [
            'document' => [
                'workflowName' => 'ETDPreshipment',
                'name' => basename($pdfFile),
                'contentType' => 'application/pdf',
                'meta' => [
                    'shipDocumentType' => 'PRO_FORMA_INVOICE',
                    'originCountryCode' => $originCountryCode,
                    'destinationCountryCode' => $destinationCountryCode
                ]
            ],
            'attachment' => fopen($pdfFile, 'r'),
        ];
        $file = fopen($pdfFile, 'r');
        $a = fread($file, filesize($pdfFile));
        fclose($file);

        $body['attachment'] = $a;
        $body['document'] = json_encode($body['document']);
        try {
            $results = $this->client->makeRequest('POST', Endpoints::UPLOAD_DOCUMENT->getEndpoint(), $body, $headers);
            $haha = $results;

            return $results;
        } catch (FedexException $e) {
            $errors = json_decode($e->getMessage(), true);
            $exception = [];
            if ($errors['transactionId']) {
                try {
                    $this->retrieveASyncShipment($errors['transactionId']);
                } catch (FedexException $e) {
                    $errors = json_decode($e->getMessage(), true);
                    foreach ($errors as $error) {
                        $exception[$error['code']] = ErrorTypes::get($error['code']);
                    }
                    throw new FedexBadResponseException(json_encode($exception));
                }

            }
            foreach ($errors as $error) {
                $exception[$error['code']] = ErrorTypes::get($error['code']);
            }

            throw new FedexBadResponseException(json_encode($exception));
        }

    }

    public function getShipmentRegulatoryDetails() {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        $body = [
            'serviceType' => 'INTERNATIONAL_ECONOMY',
            'originAddress' => [
                'countryCode' => 'PL',
            ],
            'destinationAddress' => [
                'countryCode' => 'JP',
            ],
            'carrierCode' => 'FDXE',
        ];

        try {
            $results = $this->client->makeRequest('POST', Endpoints::SHIPMENT_REGULATORY_DETAILS->getEndpoint(), [], $headers);

            return $results;
        } catch (FedexException $e) {
            $errors = json_decode($e->getMessage(), true);
            $exception = [];
            if ($errors['transactionId']) {
                try {
                    $this->retrieveASyncShipment($errors['transactionId']);
                } catch (FedexException $e) {
                    $errors = json_decode($e->getMessage(), true);
                    foreach ($errors as $error) {
                        $exception[$error['code']] = ErrorTypes::get($error['code']);
                    }
                    throw new FedexBadResponseException(json_encode($exception));
                }

            }
            foreach ($errors as $error) {
                $exception[$error['code']] = ErrorTypes::get($error['code']);
            }

            throw new FedexBadResponseException(json_encode($exception));
        }
    }

    public function retrieveASyncShipment(string $transactionId) {
        $headers = [
            'Content-Type' => 'application/json',
            'x-locale' => 'en_US',
        ];
        $results = $this->client->makeRequest('GET', Endpoints::RETRIEVE_ASYNC_SHIPMENT->getEndpoint(), [], $headers);
        $haha = $results;
    }

    public function calculateShipment($data) {

        try {
            $headers = [
                'Content-Type' => 'application/json',
                'x-locale' => 'en_US',
            ];
            $results = $this->client->makeRequest('POST', Endpoints::RATES_AND_TRANSIT_TIMES->getEndpoint(), $data, $headers);

            return $results;
        } catch (FedexException $e) {
            $errors = json_decode($e->getMessage(), true);
            $exception = [];
            if ($errors['transactionId']) {
                try {
                    $this->retrieveASyncShipment($errors['transactionId']);
                } catch (FedexException $e) {
                    $errors = json_decode($e->getMessage(), true);
                    foreach ($errors as $error) {
                        $exception[$error['code']] = ErrorTypes::get($error['code']);
                    }
                    throw new FedexBadResponseException(json_encode($exception));
                }

            }
            foreach ($errors as $error) {
                $exception[$error['code']] = ErrorTypes::get($error['code']);
            }

            throw new FedexBadResponseException(json_encode($exception));
        }
    }
}