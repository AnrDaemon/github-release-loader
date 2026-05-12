#!/usr/bin/env php
<?php

use AnrDaemon\Net\GhLoader\GitHubAdapter;
use AnrDaemon\Net\GhLoader\NotificationLoader;
use AnrDaemon\Net\GhLoader\ReleaseAssetDownloader;
use AnrDaemon\Net\GhLoader\ReleaseHandler;
use AnrDaemon\Net\GhLoader\ReleaseLoader;

require __DIR__ . "/vendor/autoload.php";

$convertMasks = function (string $param, array $config) {
    return array_merge(...array_map(
        fn($mask) => $config['filter'][$mask] ?? [\strtr(\preg_quote($mask), [
            '\?' => '.',
            '\*' => '.*',
        ])],
        preg_split(
            '{,}',
            $param,
            -1,
            \PREG_SPLIT_NO_EMPTY
        )
    ));
};

/**
 * @var array{
 *  mark-read: bool,
 *  filter: array<string, string>,
 *  cache-directory: string,
 * } $config
 *
 * @var array<string, array{
 *  repo: string,
 *  mask: array<string>,
 *  filter: array<string>,
 * }> $list
 */
$config = [
    'mark-read' => true,
    'filter' => [],
    'cache-directory' => __DIR__ . '/.cache/',
];
$list = [];
foreach (file(__DIR__ . '/repositories.list') as $n => $line) {
    $item = preg_split('{:}', trim($line), 3);
    if ($item[0] === "*config") {
        switch ($item[1]) {
            case "mark-read":
                $config[$item[1]] = \filter_var($item[2], \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
                break;

            case "filter":
                $pair = preg_split('{:}', $item[2], 2, \PREG_SPLIT_NO_EMPTY);
                if (!isset($pair[1])) {
                    throw new \RuntimeException("Invalid filter configuration: " . trim($line) . " at line " . ($n + 1));
                }

                // Form key names as '$name'
                $config[$item[1]]["\${$pair[0]}"] = $convertMasks($pair[1], $config);
                break;

            case 'cache-directory':
                $config[$item[1]] = $item[2];
                break;

            default:
                fwrite(\STDERR, "Uknown configuration key '{$item[1]}' at line " . ($n + 1));
                break;
        }
    } else {
        $list[] = [
            'repo' => $item[0],
            'mask' => $convertMasks($item[1], $config),
            'filter' => $convertMasks($item[2], $config),
        ];
    }
}

$list = array_column($list, null, 'repo');

/**
 * @var array{
 *  M: ?bool,
 *  mark-read: ?bool,
 *  N: ?bool,
 *  no-mark-read: ?bool,
 *  d: ?string,
 *  cache-directory: ?string,
 * } $options
 */
$options = getopt('MNd', ['mark-read', 'no-mark-read', 'cache-directory']) ?: [];

// The mark-read is configured or default true
$config['mark-read'] = $options['M'] ?? $options['mark-read'] ?? $config['mark-read'];
if (!is_null($options['N'] ?? $options['no-mark-read'] ?? null)) {
    // Or mark-read is false if disabled explicictly
    $config['mark-read'] = false;
}

// Cache directory is configured or default
$config['cache-directory'] = $options['cache-directory'] ?? $options['d'] ?? $config['cache-directory'];

try {
    $adapter = new GitHubAdapter(getenv('GITHUB_TOKEN'));
    $loader = new NotificationLoader($adapter);
    $releaseLoader = new ReleaseLoader($adapter);
    $assetDownloader = new ReleaseAssetDownloader($adapter);
    $releaseHandler = new ReleaseHandler($releaseLoader, $assetDownloader, $config);

    $per_page = 50;
    $date = new \DateTimeImmutable('now');
    $events = [];
    do {
        $notifications = $loader->load($date, null, 1, $per_page);
        foreach ($notifications as $event) {
            printf("%s:%s:%s:<%s>\n", $event["repository"]["full_name"], $event["subject"]["type"], $event["subject"]["title"], $event["subject"]["url"]);

            $e_date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $event['updated_at']);
            if ($e_date < $date) {
                $date = $e_date;
            }

            /**
             * @var array{
             *  repo: string,
             *  mask: array<string>,
             *  filter: array<string>,
             * } $param
             */
            $param = $list[$event["repository"]["full_name"]] ?? $list["{$event["repository"]["owner"]["login"]}/*"] ?? null;
            if (!isset($param)) {
                continue;
            }

            switch ($event["subject"]["type"]) {
                case "Release":
                    $releaseHandler($event["subject"]["url"], $param);
                    break;

                default:
                    continue 2;
            }

            if ($config['mark-read']) {
                $read = $loader->read($event["id"]);
            }
        }
    } while (is_array($notifications) && !(count($notifications) < $per_page));
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
    exit(1);
}
