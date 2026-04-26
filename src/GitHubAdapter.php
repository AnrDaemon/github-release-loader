<?php

namespace AnrDaemon\Net\GhLoader;

use AnrDaemon\Net\Browser;
use AnrDaemon\Net\Url;

/**
 * Адаптер для работы с GitHub API
 */
class GitHubAdapter {
    private Browser $browser;
    private string $baseUrl;
    private string $token;

    /**
     * The API version adapter is expecting to consume
     *
     * Ref: https://docs.github.com/en/rest?apiVersion=2026-03-10
     *
     * @var string API_VERSION Consumed API version.
     */
    public const API_VERSION = "2026-03-10";

    /**
     * @param string $token The GitHub authorization token (classic).
     */
    public function __construct(string $token) {
        $this->browser = new Browser();
        $this->baseUrl = 'https://api.github.com/';
        $this->token = $token;

        $this->browser->setOpt(\CURLOPT_FOLLOWLOCATION, true);
    }

    /**
     * Performs the request to the GitHub API to retrieve JSON message
     *
     * @param string $endpoint The endpoint path only.
     * @param array $queryParams API query parameters.
     * @return array The query result.
     * @throws \Exception When error occurs.
     */
    public function request(string $endpoint, array $queryParams = [], string $method = 'GET'): array {
        $url = new Url($this->baseUrl . self::normalizeEndpoint($endpoint), $queryParams);
        $this->browser->setOpt(\CURLOPT_HTTPHEADER, $this->headers('application/vnd.github+json'));
        $responseContent = $this->browser->get($url, $method);
        $data = \strlen($responseContent) ? \json_decode($responseContent, true, 512, \JSON_THROW_ON_ERROR) : [];

        return $data;
    }

    /**
     * Performs the request to the GitHub API to retrieve binary asset
     *
     * @param string $endpoint The endpoint path only.
     * @param array $queryParams API query parameters.
     * @return string The query result.
     * @throws \Exception When error occurs.
     */
    public function download(string $endpoint, array $queryParams = [], string $method = 'GET'): string {
        $url = new Url($this->baseUrl . self::normalizeEndpoint($endpoint), $queryParams);
        $this->browser->setOpt(\CURLOPT_HTTPHEADER, $this->headers('application/octet-stream'));
        $responseContent = $this->browser->get($url, $method);

        return $responseContent;
    }

    /**
     * Trims potential leading slashes from endpoint path.
     *
     * @param string $path
     * @return string Normalized endpoint location.
     */
    private static function normalizeEndpoint(string $path): string {
        return \ltrim($path, "/");
    }

    /**
     * @param string $accept The Accept header value (expected content-type of the response).
     * @return array Request headers.
     */
    private function headers(string $accept): array {
        return [
            "X-GitHub-Api-Version: " . self::API_VERSION,
            "User-Agent: " . self::class,
            'Accept: ' . $accept,
            'Authorization: token ' . $this->token,
        ];
    }
}
