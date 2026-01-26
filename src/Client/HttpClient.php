<?php

declare(strict_types=1);

namespace AxiTrace\Client;

use AxiTrace\Api\Response;
use AxiTrace\Config;
use AxiTrace\Exception\ApiException;
use AxiTrace\Exception\AuthenticationException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;

/**
 * HTTP client wrapper for API communication.
 */
class HttpClient
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var Client
     */
    private Client $client;

    /**
     * Client IP address to forward (end-user's IP).
     *
     * @var string|null
     */
    private ?string $clientIp = null;

    /**
     * Client user agent to forward (end-user's browser).
     *
     * @var string|null
     */
    private ?string $clientUserAgent = null;

    /**
     * @param Config $config
     * @param Client|null $client
     */
    public function __construct(Config $config, ?Client $client = null)
    {
        $this->config = $config;
        $this->client = $client ?? $this->createClient();
    }

    /**
     * Set client IP to forward in requests.
     *
     * @param string|null $clientIp
     * @return self
     */
    public function setClientIp(?string $clientIp): self
    {
        $this->clientIp = $clientIp;
        return $this;
    }

    /**
     * Set client user agent to forward in requests.
     *
     * @param string|null $clientUserAgent
     * @return self
     */
    public function setClientUserAgent(?string $clientUserAgent): self
    {
        $this->clientUserAgent = $clientUserAgent;
        return $this;
    }

    /**
     * Create Guzzle HTTP client.
     *
     * @return Client
     */
    private function createClient(): Client
    {
        return new Client([
            'base_uri' => $this->config->getBaseUrl(),
            'timeout' => $this->config->getTimeout(),
            'verify' => $this->config->shouldVerifySsl(),
            'http_errors' => false, // We handle errors ourselves
            'headers' => [
                'User-Agent' => $this->config->getUserAgent(),
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Send a POST request.
     *
     * @param string $endpoint
     * @param array<string, mixed> $data
     * @return Response
     * @throws ApiException
     * @throws AuthenticationException
     */
    public function post(string $endpoint, array $data): Response
    {
        return $this->request('POST', $endpoint, $data);
    }

    /**
     * Send a GET request.
     *
     * @param string $endpoint
     * @param array<string, mixed> $query
     * @return Response
     * @throws ApiException
     * @throws AuthenticationException
     */
    public function get(string $endpoint, array $query = []): Response
    {
        return $this->request('GET', $endpoint, [], $query);
    }

    /**
     * Send HTTP request.
     *
     * @param string $method
     * @param string $endpoint
     * @param array<string, mixed> $data
     * @param array<string, mixed> $query
     * @return Response
     * @throws ApiException
     * @throws AuthenticationException
     */
    private function request(string $method, string $endpoint, array $data = [], array $query = []): Response
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        // Forward client IP and user agent via custom headers
        // These allow the ingestion API to use the real client data instead of the SDK's
        if ($this->clientIp !== null) {
            $headers['X-Client-IP'] = $this->clientIp;
        }
        if ($this->clientUserAgent !== null) {
            $headers['X-Client-User-Agent'] = $this->clientUserAgent;
        }

        $options = [
            RequestOptions::AUTH => [$this->config->getSecretKey(), ''],
            RequestOptions::HEADERS => $headers,
        ];

        if (!empty($data)) {
            $options[RequestOptions::JSON] = $data;
        }

        if (!empty($query)) {
            $options[RequestOptions::QUERY] = $query;
        }

        try {
            $response = $this->client->request($method, $endpoint, $options);
            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();

            // Handle authentication errors
            if ($statusCode === 401) {
                throw AuthenticationException::unauthorized();
            }

            if ($statusCode === 403) {
                throw AuthenticationException::forbidden();
            }

            // Handle other errors
            if ($statusCode >= 400) {
                throw ApiException::fromResponse($statusCode, $body);
            }

            return Response::fromJson($body, $statusCode);
        } catch (ConnectException $e) {
            throw ApiException::connectionError($e->getMessage());
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $statusCode = $response->getStatusCode();
                $body = (string) $response->getBody();

                if ($statusCode === 401) {
                    throw AuthenticationException::unauthorized();
                }

                throw ApiException::fromResponse($statusCode, $body);
            }

            throw ApiException::connectionError($e->getMessage());
        } catch (GuzzleException $e) {
            throw ApiException::connectionError($e->getMessage());
        }
    }

    /**
     * Get the configuration.
     *
     * @return Config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Get the underlying Guzzle client.
     *
     * @return Client
     */
    public function getGuzzleClient(): Client
    {
        return $this->client;
    }
}
