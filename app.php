#!/usr/bin/env php
<?php

use AnrDaemon\Net\GhLoader\GitHubAdapter;
use AnrDaemon\Net\GhLoader\NotificationLoader;
use AnrDaemon\Net\GhLoader\ReleaseAssetDownloader;
use AnrDaemon\Net\GhLoader\ReleaseHandler;
use AnrDaemon\Net\GhLoader\ReleaseLoader;

require __DIR__ . "/vendor/autoload.php";

try {
    $adapter = new GitHubAdapter(getenv('GITHUB_TOKEN'));
    $loader = new NotificationLoader($adapter);
    $releaseLoader = new ReleaseLoader($adapter);
    $assetDownloader = new ReleaseAssetDownloader($adapter);
    $releaseHandler = new ReleaseHandler($releaseLoader, $assetDownloader, '.cache/');

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

            switch ($event["repository"]["owner"]["login"]) {
                case 'GTNewHorizons':
                    break;

                case 'TeamJM':
                    switch ($event["repository"]["name"]) {
                        case "journeymap-legacy":
                            break 2;

                        default:
                            continue 3;
                    }

                case 'MalTeeez':
                    switch ($event["repository"]["name"]) {
                        case "ChromatiFixes":
                            break 2;

                        default:
                            continue 3;
                    }

                case 'LegacyModdingMC':
                    switch ($event["repository"]["name"]) {
                        case "UniMixins":
                            break 2;

                        default:
                            continue 3;
                    }

                case 'Nolij':
                    switch ($event["repository"]["name"]) {
                        case "Zume":
                            break 2;

                        default:
                            continue 3;
                    }

                case 'DarkShadow44':
                    switch ($event["repository"]["name"]) {
                        case "DistantHorizonsStandalone":
                            break 2;

                        default:
                            continue 3;
                    }

                case 'unilock':
                    switch ($event["repository"]["name"]) {
                        case "DragonFixes":
                            break 2;

                        default:
                            continue 3;
                    }

                case 'FalsePattern':
                    switch ($event["repository"]["name"]) {
                        case "FalseTweaks":
                        case "FalsePatternLib":
                            break 2;

                        default:
                            continue 3;
                    }

                default:
                    continue 2;
            }

            switch ($event["subject"]["type"]) {
                case "Release":
                    $releaseHandler($event["subject"]["url"]);
                    break;

                default:
                    continue 2;
            }

            $read = $loader->read($event["id"]);
        }
    } while (is_array($notifications) && !(count($notifications) < $per_page));
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
    exit(1);
}
