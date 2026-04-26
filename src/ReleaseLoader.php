<?php

namespace AnrDaemon\Net\GhLoader;

use AnrDaemon\Net\Url;

/**
 * Main notifications loader class
 */
class ReleaseLoader {

    private GitHubAdapter $adapter;

    public function __construct(GitHubAdapter $adapter) {
        $this->adapter = $adapter;
    }

    /**
     * @param string $url Endpoint path or full URL of the release location.
     * @return array
     */
    public function load(string $url): array {
        return $this->adapter->request((new Url($url))->path);
    }
}
