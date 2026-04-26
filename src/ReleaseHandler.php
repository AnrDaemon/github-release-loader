<?php

namespace AnrDaemon\Net\GhLoader;

use AnrDaemon\Net\Url;

class ReleaseHandler {
    private ReleaseLoader $loader;
    private ReleaseAssetDownloader $downloader;
    private string $cacheDirectory;

    public function __construct(
        ReleaseLoader $loader,
        ReleaseAssetDownloader $downloader,
        string $cacheDirectory
    ) {
        $this->loader = $loader;
        $this->downloader = $downloader;
        $this->cacheDirectory = \realpath($cacheDirectory);

        if (!\is_dir($cacheDirectory)) {
            throw new \LogicException("Cache directory not found: $cacheDirectory");
        }
    }

    /**
     * URL is like "https://api.github.com/repos/GTNewHorizons/TinkersConstruct/releases/313690125"
     */
    public function __invoke(string $url): void {
        \set_error_handler(
            function ($s, $m, $f, $l, $c = \null) {
                if ($s & (\E_WARNING)) {
                    throw new \ErrorException($m, 0, $s, $f, $l);
                }
            }
        );

        try {
            $this->handle((new Url($url))->path);
        } finally {
            \restore_error_handler();
        }
    }

    private function handle(string $endpoint): void {
        /**
         * @var array{
         *  owner: string,
         *  repo: string,
         *  id: string,
         * } $ta
         */
        if (!\preg_match('{^/?repos/(?P<owner>[^/]+)/(?P<repo>[^/]+)/releases/(?P<id>\d+)$}u', $endpoint, $ta, \PREG_UNMATCHED_AS_NULL)) {
            throw new \LogicException("Invalid URL format");
        }

        if (!isset($ta['owner'], $ta['repo'], $ta['id'])) {
            throw new \LogicException("Invalid URL format");
        }

        $cachePath = "{$this->cacheDirectory}/{$ta['owner']}/{$ta['repo']}-{$ta['id']}";
        if (!\is_dir($cachePath)) {
            \mkdir($cachePath, 0777, \true);
        }

        $release = $this->loader->load($endpoint);
        if (!$this->filterRelease($release["tag_name"])) {
            return;
        }

        $name = $release["name"] ?: $release["tag_name"];

        \file_put_contents("$cachePath/Release.nobrain", "{$release["html_url"]}\r\n\r\n# {$name}\r\n\r\n{$release["body"]}");
        \touch("$cachePath/Release.nobrain", \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $release["published_at"])->getTimestamp());
        \array_map(fn($asset) => $this->download($asset, $cachePath), \array_filter($release["assets"] ?? [], fn($asset) => $this->filterAssets($asset)));
    }

    private function download(array $asset, string $cachePath): void {
        \file_put_contents("$cachePath/{$asset['name']}", $this->downloader->load($asset['url']));
        \touch("$cachePath/{$asset['name']}", \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $asset["updated_at"])->getTimestamp());
    }

    private function filterAssets(array $asset): bool {
        return $asset["content_type"] === "application/java-archive" && !\preg_match('{-(api|dev|sources|preshadow)\.jar$}iu', $asset['name']);
    }

    private function filterRelease(string $title): bool {
        return !\preg_match('{-pre$}iu', $title);
    }
}
