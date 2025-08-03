<?php

namespace SCA\FedexApi;

use SCA\FedexApi\Exception\FedexAuthorizeErrorException;
use SCA\FedexApi\Exception\FedexBadResponseException;
use SCA\FedexApi\Exception\FedexException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Client {

    private string $apiKey;

    private string $apiSecret;

    private string $apiAccountNo;

    private string $apiUrl;

    private string $accessToken;

    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {}

    public function setCredentials(string $apiKey, string $apiSecret, string $apiAccountNo, string $apiUrl) {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->apiAccountNo = $apiAccountNo;
        $this->apiUrl = $apiUrl;
        $this->authorize();
    }

    public function makeRequest(string $type, string $endpoint, array $body, array $headers, $needsAuthorization = true) {
        if ($needsAuthorization) {
            $headers['authorization'] = 'Bearer ' . $this->getAccessToken();
        }
        if (is_array($body) && in_array($headers['Content-Type'], ['application/json'])) {
            $body = json_encode($body);
        }
        if ($endpoint === Endpoints::UPLOAD_DOCUMENT->getEndpoint()) {
            $url = 'https://documentapitest.prod.fedex.com/sandbox/documents/v1/etds/upload';
        } else {
            $url = $this->apiUrl . '/' . $endpoint;
        }

        $response  = $this->httpClient->request($type, $url, [
            'headers' => $headers,
            'body' => $body
        ]);

        return $this->handleResponse($response);
    }

    private function setAccessToken(string $accessToken) {
        $this->accessToken = $accessToken;
    }

    private function getAccessToken() {
        return $this->accessToken;
    }

    private function authorize() {
        $type = 'POST';
        $endpoint = 'oauth/token';
        $body = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->apiKey,
            'client_secret' => $this->apiSecret,
        ];
        $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
        $results = $this->makeRequest($type, $endpoint, $body, $headers,false);

        $this->setAccessToken($results['access_token']);
    }

    private function handleResponse($response) {
        $status = $response->getStatusCode(false);
        $content = $response->getContent(false);

        if (200 === $status && json_validate($content)) {
            return json_decode($content, true);
        } else if (json_validate($content)) {
            $content = json_decode($content, true);
            $message = [];
            if (isset($content)) {
                throw new FedexBadResponseException(json_encode($content));
            }
            throw new FedexBadResponseException('Unknown error from Fedex authorizaton!');
        } else {
            throw new FedexBadResponseException('Unknown error from Fedex authorizaton!');
        }
    }

    public function getApiAccountNo(): string {
        return $this->apiAccountNo;
    }
}