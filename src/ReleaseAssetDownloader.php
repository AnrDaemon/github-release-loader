<?php

namespace AnrDaemon\Net\GhLoader;

use AnrDaemon\Net\Url;

/**
 * The binary blob downloader class
 */
class ReleaseAssetDownloader {

    private GitHubAdapter $adapter;

    public function __construct(GitHubAdapter $adapter) {
        $this->adapter = $adapter;
    }

    /**
     * @param string $url Endpoint path or full URL of the release location.
     * @return string Binary blob.
     */
    public function load(string $url): string {
        return $this->adapter->download((new Url($url))->path);
    }
}
