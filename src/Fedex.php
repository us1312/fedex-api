<?php

namespace SCA\FedexApi;

use SCA\FedexApi\Error\Errors;
use SCA\FedexApi\Error\ErrorTypes;
use SCA\FedexApi\Exception\FedexAuthorizeErrorException;
use SCA\FedexApi\Exception\FedexBadResponseException;
use SCA\FedexApi\Exception\FedexException;
use SCA\FedexApi\Validation\CountryValidator;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mime\MimeTypes;
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
    
    public function setCredentials(string $apiKey, string $apiSecret, string $apiAccountNo, string $env) {
        $this->client->setCredentials($apiKey, $apiSecret, $apiAccountNo, $env);
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

        $body = json_encode($body);

        return $this->client->makeRequest('POST', Endpoints::ADDRESS->getEndpoint($env), $body, $headers);
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
            return $this->client->makeRequest('POST', Endpoints::CREATE_SHIPMENT->getEndpoint(), $data, $headers);
        } catch (FedexException $e) {
            $errors = json_decode($e->getMessage(), true);
            $exception = [];
            if ($errors['transactionId']) {
                $errors = json_decode($e->getMessage(), true);

                foreach ($errors as $error) {
                    $exception[$error['code']] = ErrorTypes::get($error['code']);
                }

                throw new FedexBadResponseException(json_encode($exception));
            }
        }
    }

    public function uploadDocument(string $filePath, $documentType = 'PRO_FORMA_INVOICE', $originCountryCode = 'PL', $destinationCountryCode = 'JP' ) {
        $mimeTypeService = MimeTypes::getDefault();

        $mimeType = $mimeTypeService->guessMimeType($filePath);
        $extension = $mimeTypeService->getExtensions($mimeType);
        $filename = substr(strtr(base64_encode(random_bytes(8)), '+/', ''), 0, 10) . '.' . $extension[array_key_first($extension)];
        $client = HttpClient::create();

        $fileHandle = fopen($filePath, 'r');
        stream_context_set_option($fileHandle, 'http', 'filename', $filename);
        stream_context_set_option($fileHandle, 'http', 'content-type', $mimeType);

        $documentData = [
            'workflowName'    => 'ETDPreshipment',           // or 'ETDPostshipment'
            'name'            => $filename,        // filename - MUST BE ENDED BY .mimeType
            'contentType'     => $mimeType,          // mimetype of file
            'meta' => [
                'shipDocumentType'     => $documentType,
                'originCountryCode'    => $originCountryCode,              // shipper country code
                'destinationCountryCode' => $destinationCountryCode             // receiver country code
            ]
        ];
        $body = [
            'document' => json_encode($documentData),
            'attachment' => $fileHandle,
        ];

        try {
            $results = $this->client->makeRequest('POST', Endpoints::UPLOAD_DOCUMENT->getEndpoint(), $body, []);

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

    public function retrieveASyncShipment(string $transactionId) {
        $headers = [
            'Content-Type' => 'application/json',
            'x-locale' => 'en_US',
        ];
        $results = $this->client->makeRequest('GET', Endpoints::RETRIEVE_ASYNC_SHIPMENT->getEndpoint(), [], $headers);
        $haha = $results;
    }
}