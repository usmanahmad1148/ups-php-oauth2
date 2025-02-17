<?php
namespace UPS;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class UPSOAuth2 {
    private $clientId;
    private $clientSecret;
    private $accessToken;
    private $baseUrl;
    private $client;

    public function __construct($clientId, $clientSecret, $sandbox = true) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->baseUrl = $sandbox ? 'https://wwwcie.ups.com' : 'https://onlinetools.ups.com';
        $this->client = new Client();

        // Authenticate on initialization
        $this->authenticate();
    }

    /**
     * Authenticate with UPS API and get an OAuth token
     */
    private function authenticate() {
        $url = "{$this->baseUrl}/security/v1/oauth/token";
        $auth = base64_encode("{$this->clientId}:{$this->clientSecret}");
    
        try {
            $response = $this->client->post($url, [
                'headers' => [
                    'Authorization' => "Basic $auth",
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept' => 'application/json'
                ],
                'form_params' => ['grant_type' => 'client_credentials']
            ]);
    
            $data = json_decode($response->getBody(), true);
            
            if (!isset($data['access_token'])) {
                throw new \Exception('UPS Authentication Failed: No access token received.');
            }
    
            $this->accessToken = $data['access_token'];
    
        } catch (RequestException $e) {
            $errorBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : 'No response body';
            throw new \Exception('UPS Authentication Failed: ' . $errorBody);
        }
    }
    
    /**
     * General method to send requests to UPS API
     */
    private function request($endpoint, $method = 'GET', $body = []) {
        if (!$this->accessToken) {
            throw new \Exception('Missing UPS Access Token. Authentication failed.');
        }

        $url = "{$this->baseUrl}{$endpoint}";
        $headers = [
            'Authorization' => "Bearer {$this->accessToken}",
            'Content-Type' => 'application/json',
            'transId' => uniqid(),
            'transactionSrc' => 'MyApp'
        ];

        try {
            $options = ['headers' => $headers];
            if (!empty($body)) {
                $options['json'] = $body;
            }

            $response = $this->client->request($method, $url, $options);
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $errorResponse = json_decode($e->getResponse()->getBody()->getContents(), true);
                throw new \Exception('UPS API Error: ' . json_encode($errorResponse));
            }
            throw new \Exception('UPS API request failed: ' . $e->getMessage());
        }
    }

    /**
     * Track a shipment by tracking number
     */
    public function trackShipment($trackingNumber) {
        return $this->request("/api/track/v1/details/{$trackingNumber}", 'GET');
    }

    /**
     * Create a shipment and get a shipping label
     */
    public function createShipment($shipmentData) {
        return $this->request("/api/shipments/v1/ship", 'POST', $shipmentData);
    }

    /**
     * Get shipping rates based on provided shipment data
     */
    public function getRates($rateData) {
        return $this->request("/api/rating/v1/rate", 'POST', $rateData);
    }
}
