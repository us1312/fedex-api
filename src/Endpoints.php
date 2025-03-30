<?php

namespace SCA\FedexApi;

enum Endpoints {

    case TEST_URL;

    case PROD_URL;

    case ADDRESS;

    case CREATE_SHIPMENT;

    case VALIDATE_SHIPMENT;

    case RETRIEVE_ASYNC_SHIPMENT;

    case UPLOAD_DOCUMENT;

    case SHIPMENT_REGULATORY_DETAILS;
    
    public function getEndpoint(): string {
        return match ($this) {
            self::ADDRESS => 'address/v1/addresses/resolve',
            self::CREATE_SHIPMENT => 'ship/v1/shipments',
            self::VALIDATE_SHIPMENT => 'ship/v1/shipments/packages/validate',
            self::RETRIEVE_ASYNC_SHIPMENT => 'ship/v1/shipments/results',
            self::UPLOAD_DOCUMENT => 'documents/v1/etds/upload',
            self::SHIPMENT_REGULATORY_DETAILS => 'globaltrade/v1/shipments/regulatorydetails/retrieve',
        };
    }

    public function getBaseUrl($env): string {
        switch ($env) {
            case 'test':
                return 'https://apis-sandbox.fedex.com/';
            case 'prod':
                return 'https://apis.fedex.com/';

        }
    }
}