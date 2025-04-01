<?php

namespace SCA\FedexApi;

use SCA\FedexApi\Exception\FedexAuthorizeErrorException;
use SCA\FedexApi\Exception\FedexBadResponseException;
use SCA\FedexApi\Exception\FedexException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Client {

    private string $apiKey;

    private string $apiSecret;

    private string $apiAccountNo;

    private string $env;

    private string $accessToken;

    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {}

    public function setCredentials(string $apiKey, string $apiSecret, string $apiAccountNo, string $env) {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->apiAccountNo = $apiAccountNo;
        $this->env = $env;
        $this->authorize();
    }

    public function makeRequest(string $type, string $endpoint, array $body, array $headers, $needsAuthorization = true) {
        if ($needsAuthorization) {
            $headers['authorization'] = 'Bearer ' . $this->getAccessToken();
        }

        if ($endpoint == Endpoints::UPLOAD_DOCUMENT->getEndpoint($this->env)) {
            $url = 'https://documentapitest.prod.fedex.com/sandbox/documents/v1/etds/upload';
        } else {
            $url = Endpoints::PROD_URL->getBaseUrl($this->env) . $endpoint;
        }

        if (isset($headers['Content-Type']) && $headers['Content-Type'] == 'application/json') {
            $body = json_encode($body);
        }

        $response  = $this->httpClient->request($type, $url, [
            'headers' => $headers,
            'body' => $body
        ]);

        return $this->handleResponse($response);
    }

    public function makeRequestUploadFile($data, $endpoint) {
        $client = HttpClient::create();
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

        if (in_array($status, [200, 201]) && json_validate($content)) {
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