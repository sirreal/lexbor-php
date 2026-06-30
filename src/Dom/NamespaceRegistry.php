<?php

declare(strict_types=1);

namespace Lexbor\Dom;

final class NamespaceRegistry
{
    /**
     * @var array<int, string>
     */
    private const KNOWN_LINKS = [
        NamespaceUri::UNDEF => '',
        NamespaceUri::ANY => '',
        NamespaceUri::HTML => 'http://www.w3.org/1999/xhtml',
        NamespaceUri::MATH => 'http://www.w3.org/1998/Math/MathML',
        NamespaceUri::SVG => 'http://www.w3.org/2000/svg',
        NamespaceUri::XLINK => 'http://www.w3.org/1999/xlink',
        NamespaceUri::XML => 'http://www.w3.org/XML/1998/namespace',
        NamespaceUri::XMLNS => 'http://www.w3.org/2000/xmlns/',
    ];

    /**
     * @var array<string, int>
     */
    private const KNOWN_LINK_IDS = [
        '#undef' => NamespaceUri::UNDEF,
        '#any' => NamespaceUri::ANY,
        'http://www.w3.org/1999/xhtml' => NamespaceUri::HTML,
        'http://www.w3.org/1998/math/mathml' => NamespaceUri::MATH,
        'http://www.w3.org/2000/svg' => NamespaceUri::SVG,
        'http://www.w3.org/1999/xlink' => NamespaceUri::XLINK,
        'http://www.w3.org/xml/1998/namespace' => NamespaceUri::XML,
        'http://www.w3.org/2000/xmlns/' => NamespaceUri::XMLNS,
    ];

    /**
     * @var array<int, string>
     */
    private static array $dynamicLinks = [];

    private static int $nextDynamicId = NamespaceUri::LAST_ENTRY + 1;

    /**
     * @var array<string, int>
     */
    private array $dynamicIds = [];

    public function lookupIdForLink(string $link): ?int
    {
        if ($link === '') {
            return null;
        }

        $normalized = strtolower($link);
        $knownId = self::KNOWN_LINK_IDS[$normalized] ?? null;

        if ($knownId !== null) {
            return $knownId;
        }

        return $this->dynamicIds[$normalized] ?? null;
    }

    public function idForLink(string $link): ?int
    {
        if ($link === '') {
            return null;
        }

        $knownId = $this->lookupIdForLink($link);

        if ($knownId !== null) {
            return $knownId;
        }

        $normalized = strtolower($link);
        $id = self::$nextDynamicId++;
        $this->dynamicIds[$normalized] = $id;
        self::$dynamicLinks[$id] = $normalized;

        return $id;
    }

    public static function linkById(int $namespaceId): ?string
    {
        return self::KNOWN_LINKS[$namespaceId] ?? self::$dynamicLinks[$namespaceId] ?? null;
    }
}
