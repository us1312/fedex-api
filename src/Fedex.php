<?php

namespace SCA\FedexApi;


use Symfony\Contracts\HttpClient\HttpClientInterface;

class Fedex {

    private string $apiKey;

    private string $apiSecret;

    private string $apiUrl;

    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {}

    private string $accessToken;

    public function setCredentials(string $apiKey, string $apiSecret, string $apiUrl): string {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->apiUrl = $apiUrl;
    }
    private function setAccessToken(string $accessToken) {
        $this->accessToken = $accessToken;
    }

    private function authorize() {

        try {
            $response  = $this->httpClient->request('POST', $this->apiUrl, [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
                'body' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->apiKey,
                    'client_secret' => $this->apiSecret,
                ]
            ]);
            $this->handleResponse($response);
        } catch (\Exception $e) {

        }
    }

    private function handleResponse($response) {
        $status = $response->getStatusCode(false);
        $content = $response->getContent(false);

        return $content;

    }
    private function createClient() {

    }
    public function register() {
        return $this->authorize();
    }
}