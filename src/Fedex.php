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
        $body = [
            'requestedShipment' => [
                'pickupType' => 'DROPOFF_AT_FEDEX_LOCATION',
                'serviceType' => 'INTERNATIONAL_ECONOMY', //FEDEX_REGIONAL_ECONOMY, INTERNATIONAL_ECONOMY
                'packagingType' => 'YOUR_PACKAGING', //YOUR_PACKAGING / FEDEX_ENVELOPE / FEX_BOX / FEDEX_PAK / FEDEX_TUBE
                'total_weight' => 44.1,
                'totalDeclaredValue' => [
                    'amount' => '234.32',
                    'currency' => 'PLN',
                ],
                'shipper' => [
                    'contact' => [
                        'personName' => 'Ferdynand Kiepski',
                        'phoneNumber' => '48889658785',
                    ],
                    'address' => [
                        'streetLines' => [
                            'Piotra Skargi 3'
                        ],
                        'city' => 'Lębork',
                        'stateOrProvinceCode' => '',
                        'postalCode' => '84-300',
                        'countryCode' => 'PL',
                    ],

                ],
                'recipients' => [
                    [
                        'address' => [
                            'streetLines' => [
                                '1-23-45 Shibuya',
                                'Shibuya City'
                            ],
                            'city' => 'Tokyo',
                            'stateOrProvinceCode' => '',
                            'postalCode' => '150-0002',
                            'countryCode' => 'JP',
                        ],
                        'contact' => [
                            'personName' => 'Radosław Tadeja',
                            'phoneNumber' => '48609115648',
                        ]
                    ],
                ],
                'shippingChargesPayment' => [
                    'paymentType' => 'SENDER', // SENDER / RECIPIENT / THIRD_PARTY / COLLECT
                ],
                'shipmentSpecialServices' => [
                    'specialServiceTypes' => [
                        'ELECTRONIC_TRADE_DOCUMENTS',
                    ],
                    'etdDetail' => [
                        'attachedDocuments' => [
                            [
                                'documentId' => '1231231321313'
                            ]
                        ],
                        'requestedDocumentTypes' => [
                            'COMMERCIAL_INVOICE',
                        ]
                    ],
                ],
                'customsClearanceDetail' => [
                    'dutiesPayment' => [
                        'paymentType' => 'SENDER',
                    ],
                    'commodities' => [
                        [
                            'unitPrice' => [
                                'amount' => 234.32,
                                'currency' => 'PLN',
                            ],
                            'additionalMeasures' => [
                                [
                                    'quantity' => 1.0,
                                    'units' => 'KG',
                                ]
                            ],
                            'numberOfPieces' => 1,
                            'quantity' => 1,
                            'quantityUnits' => 'NO',
                            'weight' => [
                                'units' => 'KG',
                                'value' => 20.0,
                            ],
                            'quantityUnits' => 'KG',
                            'description' => 'item description',
                            'name' => 'Product Name',
                            'countryOfManufacture' => 'PL',
                            'harmonizedCode' => '0545', // THIS IS VERY IMPORTANT CODE, EVERY PRODUCT HAS TO HAVE ONE
                        ]
                    ],
                    'totalCustomsValue' => [
                        'amount' => '234.32',
                        'currency' => 'PLN',
                    ]
                ],
                'labelSpecification' => [
                    'labelStockType' => 'PAPER_4X6', // "PAPER_4X6" "STOCK_4X675" "PAPER_4X675" "PAPER_4X8" "PAPER_4X9" "PAPER_7X475" "PAPER_85X11_BOTTOM_HALF_LABEL" "PAPER_85X11_TOP_HALF_LABEL" "PAPER_LETTER" "STOCK_4X675_LEADING_DOC_TAB" "STOCK_4X8" "STOCK_4X9_LEADING_DOC_TAB" "STOCK_4X6" "STOCK_4X675_TRAILING_DOC_TAB" "STOCK_4X9_TRAILING_DOC_TAB" "STOCK_4X9" "STOCK_4X85_TRAILING_DOC_TAB" "STOCK_4X105_TRAILING_DOC_TAB"
                    'imageType' => 'PDF',
                ],
                'requestedPackageLineItems' => [
                    [
                        'sequenceNumber' => 1,
                        'weight' => [
                            'value' => 20.0,
                            'units' => 'KG',
                        ],
                        'itemDescriptionForClearance' => 'item description',
                    ]
                ]
            ],
            'labelResponseOptions' => 'URL_ONLY',
            'shopAction' => 'CONFIRM',
            'accountNumber' => [
                'value' => $this->client->getApiAccountNo()
            ],
        ];
        $body = json_encode($body);
        try {
            $results = $this->client->makeRequest('POST', Endpoints::VALIDATE_SHIPMENT->getEndpoint(), $body, $headers);
            $haha = $results;

            return $body;
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
            $data = json_encode($data);
            $results = $this->client->makeRequest('POST', Endpoints::CREATE_SHIPMENT->getEndpoint(), $data, $headers);
            $haha = $results;

            return $body;
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
        $filename = substr(strtr(base64_encode(random_bytes(8)), '+/', ''), 0, 10) . '.' . array_key_first($e);
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