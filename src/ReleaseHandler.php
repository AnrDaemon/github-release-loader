<?php

namespace AnrDaemon\Net\GhLoader;

use AnrDaemon\Net\Url;

class ReleaseHandler {
    private ReleaseLoader $loader;
    private ReleaseAssetDownloader $downloader;
    private string $cacheDirectory;
    private array $filesFilters;

    /**
     * @param array{
     *  mark-read: bool,
     *  include: array<string, array<int, string>>,
     *  exclude: array<string, array<int, string>>,
     *  cache-directory: string,
     * } $options
     */
    public function __construct(
        ReleaseLoader $loader,
        ReleaseAssetDownloader $downloader,
        array $options
    ) {
        if (!\is_dir($options["cache-directory"])) {
            throw new \LogicException("Cache directory not found: {$options["cache-directory"]}");
        }

        $this->loader = $loader;
        $this->downloader = $downloader;
        $this->cacheDirectory = \realpath($options["cache-directory"]);
        $this->filesFilters = $options['filter'];
    }

    /**
     * URL is like "https://api.github.com/repos/GTNewHorizons/TinkersConstruct/releases/313690125"
     *
     * @param string $url The endpoint URL address.
     * @param array{
     *  repo: string,
     *  mask: array<int, string>,
     *  filter: array<int, string>,
     * } $options
     */
    public function __invoke(string $url, array $options): void {
        \set_error_handler(
            function ($s, $m, $f, $l, $c = \null) {
                if ($s & (\E_WARNING)) {
                    throw new \ErrorException($m, 0, $s, $f, $l);
                }
            }
        );

        try {
            $include = $this->masksToFilter($options['mask']);
            $exclude = $this->masksToFilter($options['filter']);

            $this->handle((new Url($url))->path, $include, $exclude);
        } finally {
            \restore_error_handler();
        }
    }

    private function handle(string $endpoint, string $include, string $exclude): void {
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

        $cachePath = "{$this->cacheDirectory}/{$ta['owner']}/{$ta['repo']}";
        if (!\is_dir($cachePath)) {
            \mkdir($cachePath, 0777, \true);
        }

        $release = $this->loader->load($endpoint);
        if ($release["prerelease"] || !$this->filterRelease($release["tag_name"])) {
            return;
        }

        $name = $release["name"] ?: $release["tag_name"];
        $releaseFile = "$cachePath/Release-{$ta['id']}.nobrain";

        \file_put_contents("$releaseFile", "{$release["html_url"]}\r\n\r\n# {$name}\r\n\r\n{$release["body"]}");
        \touch("$releaseFile", \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $release["published_at"])->getTimestamp());
        \array_map(fn($asset) => $this->download($asset, $cachePath, $release["tag_name"]), \array_filter($release["assets"] ?? [], fn($asset) => $this->filterAssets($asset, $include, $exclude)));
    }

    private function download(array $asset, string $cachePath, string $tagName): void {
        $assetName = new \SplFileInfo($asset['name']);
        $version = \basename(\preg_replace("{[\\/<>?*:|\"\\000\t\r\n]}", '', $tagName));
        if (!\stristr($assetName->getBasename('.' . $assetName->getExtension()), \ltrim($tagName, 'v.'), true)) {
            $assetName = $assetName->getBasename('.' . $assetName->getExtension()) . "-{$version}.{$assetName->getExtension()}";
        }

        $mdate = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $asset["updated_at"])->getTimestamp() & ~1;
        if (
            \file_exists("$cachePath/$assetName")
            && (\filemtime("$cachePath/$assetName") & ~1) === $mdate
            && \filesize("$cachePath/$assetName") === $asset["size"]
        ) {
            return;
        }

        \file_put_contents("$cachePath/$assetName", $this->downloader->load($asset['url']));
        \touch("$cachePath/$assetName", $mdate);
    }

    private function masksToFilter(array $filter): string {
        $list = \array_merge(...\array_map(fn($mask) => $this->filesFilters[$mask] ?? [$mask], $filter));

        return '{^(' . \join('|', $list) . ')$}ui';
    }

    private function filterAssets(array $asset, string $include, string $exclude): bool {
        return \preg_match($include, $asset['name']) && !\preg_match($exclude, $asset['name']);
    }

    private function filterRelease(string $title): bool {
        return !\preg_match('{-pre$}iu', $title);
    }
}
