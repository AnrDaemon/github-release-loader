<?php

namespace AnrDaemon\Net\GhLoader;

use AnrDaemon\Net\Url;

/**
 * Main notifications loader class
 */
class NotificationLoader {

    private GitHubAdapter $adapter;

    public function __construct(GitHubAdapter $adapter) {
        $this->adapter = $adapter;
    }

    /**
     * @param \DateTimeInterface|null $before The date to load notfications up to.
     * @param \DateTimeInterface|null $since The date to load notifications starting from.
     * @param int $page Page number to load.
     * @param int $per_page Number of notifications to load per page.
     * @param bool $participating If `true`, only load notifications in which the user is directly participating or mentioned.
     * @param bool $all If `true`, include both `read` and `unread` notifications; `false` to load `unread` notications only.
     * @return array
     */
    public function load(
        ?\DateTimeInterface $before = null,
        ?\DateTimeInterface $since = null,
        int $page = 1,
        int $per_page = 50,
        bool $participating = false,
        bool $all = false
    ): array {
        $queryParams = [
            'all' => $all ? "true" : 'false',
            'participating' => $participating ? 'true' : "false",
            "page" => $page,
            "per_page" => $per_page,
        ];

        if (isset($since)) {
            $queryParams['since'] = $since->format('c');
        }

        if (isset($before)) {
            $queryParams['before'] = $before->format('c');
        }

        return  $this->adapter->request('notifications', $queryParams);
    }

    /**
     * @param string $id The thread ID.
     * @return array
     */
    public function read(string $id): array {
        return $this->adapter->request("notifications/threads/$id", [], 'PATCH');
    }
}
