<?php

namespace SCA\FedexApi;


use SCA\FedexApi\Error\ErrorTypes;
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
        try {
            if (!$this->countryValidator->validateCountry($country)) {
                return false;
            }
            $country = $this->countryValidator->normalizeCountryToCode($country);
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
        } catch (FedexException $th) {
            $this->errorHandlerFunction($th);
        }

    }

    public function createShipment(array $data) {
        try {
            $headers = [
                'Content-Type' => 'application/json',
                'x-locale' => 'en_US',
            ];

            return $this->client->makeRequest('POST', Endpoints::CREATE_SHIPMENT->getEndpoint(), $data, $headers);;
        } catch (FedexException $e) {
            $this->errorHandlerFunction($e);
        }
    }

    public function uploadDocument(string $pdfFile, string $documentName) {
        $headers = [
            'Content-Type' => 'multipart/form-data',
        ];

        $body = [
            'document' => [
                'workflowName' => 'ETDPreshipment',
                'name' => basename($pdfFile),
                'contentType' => 'application/pdf',
                'meta' => [
                    'shipDocumentType' => 'PRO_FORMA_INVOICE', // COMMERCIAL_INVOICE
                    'originCountryCode' => 'PL',
                    'destinationCountryCode' => 'JP',
                ]
            ],
        ];
        $fOpen = fopen($pdfFile, 'r');
        $fRead = fread($fOpen, filesize($pdfFile));
        fclose($fOpen);

        $body['attachment'] = $fRead;
        $body['document'] = json_encode($body['document']);
        try {
            return $this->client->makeRequest('POST', Endpoints::UPLOAD_DOCUMENT->getEndpoint(), $body, $headers);
        } catch (FedexException $e) {
            $this->errorHandlerFunction($e);
        }
    }

    public function getShipmentRegulatoryDetails(array $body) {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        try {
            return $this->client->makeRequest('POST', Endpoints::SHIPMENT_REGULATORY_DETAILS->getEndpoint(), [], $headers);;
        } catch (FedexException $e) {
            $this->errorHandlerFunction($e);
        }
    }

    public function retrieveASyncShipment(string $transactionId) {
        $headers = [
            'Content-Type' => 'application/json',
            'x-locale' => 'en_US',
        ];

        return $this->client->makeRequest('GET', Endpoints::RETRIEVE_ASYNC_SHIPMENT->getEndpoint(), [], $headers);
    }

    public function calculateShipment($data) {
        try {
            $headers = [
                'Content-Type' => 'application/json',
                'x-locale' => 'en_US',
            ];
            return $this->client->makeRequest('POST', Endpoints::RATES_AND_TRANSIT_TIMES->getEndpoint(), $data, $headers);;
        } catch (FedexException $e) {
            $this->errorHandlerFunction($e);
        }
    }

    private function errorHandlerFunction($e) {
        $errors = json_decode($e->getMessage(), true);
        $exception = [];
        if ($errors['transactionId']) {
            try {
                $this->retrieveASyncShipment($errors['transactionId']);
            } catch (FedexException $e) {
                $errors = json_decode($e->getMessage(), true);
                foreach ($errors['errors'] as $error) {
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