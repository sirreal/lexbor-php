<?php

declare(strict_types=1);

namespace Lexbor\Tests\Dom;

use Lexbor\Dom\NamespaceRegistry;
use Lexbor\Dom\NamespaceUri;
use PHPUnit\Framework\TestCase;

final class NamespaceRegistryTest extends TestCase
{
    public function testUpstreamNamespaceResourceLinks(): void
    {
        $registry = new NamespaceRegistry();

        foreach (self::upstreamNamespaceResourceLinks() as $link) {
            $namespaceId = $registry->lookupIdForLink($link);

            self::assertNotNull($namespaceId, "Namespace resource lookup failed for {$link}");
            self::assertLessThan(NamespaceUri::LAST_ENTRY, $namespaceId, "Namespace resource lookup allocated dynamic ID for {$link}");
            self::assertSame($link, NamespaceRegistry::linkById($namespaceId), "Namespace resource link mismatch for {$link}");
        }
    }

    public function testUpstreamNamespaceResourcePrefixes(): void
    {
        $registry = new NamespaceRegistry();

        foreach (self::upstreamNamespaceResourcePrefixes() as $prefix => $expectedNamespaceId) {
            $prefixId = $registry->lookupIdForPrefix($prefix);

            self::assertSame($expectedNamespaceId, $prefixId, "Namespace resource prefix lookup failed for {$prefix}");
            self::assertSame($prefix, NamespaceRegistry::prefixById($prefixId), "Namespace resource prefix mismatch for {$prefix}");
        }
    }

    public function testDynamicNamespacePrefixLookup(): void
    {
        $registry = new NamespaceRegistry();

        self::assertNull($registry->lookupIdForPrefix('custom'));
        self::assertNull($registry->idForPrefix(''));

        $prefixId = $registry->idForPrefix('Custom');

        self::assertNotNull($prefixId);
        self::assertGreaterThan(NamespaceUri::LAST_ENTRY, $prefixId);
        self::assertSame($prefixId, $registry->lookupIdForPrefix('custom'));
        self::assertSame($prefixId, $registry->lookupIdForPrefix('CUSTOM'));
        self::assertSame('custom', NamespaceRegistry::prefixById($prefixId));
    }

    /**
     * @return list<string>
     */
    private static function upstreamNamespaceResourceLinks(): array
    {
        return [
            'http://www.w3.org/1999/xhtml',
            'http://www.w3.org/1998/Math/MathML',
            'http://www.w3.org/2000/svg',
            'http://www.w3.org/1999/xlink',
            'http://www.w3.org/XML/1998/namespace',
            'http://www.w3.org/2000/xmlns/',
        ];
    }

    /**
     * @return array<string, int>
     */
    private static function upstreamNamespaceResourcePrefixes(): array
    {
        return [
            '#undef' => NamespaceUri::UNDEF,
            '#any' => NamespaceUri::ANY,
            'html' => NamespaceUri::HTML,
            'math' => NamespaceUri::MATH,
            'svg' => NamespaceUri::SVG,
            'xlink' => NamespaceUri::XLINK,
            'xml' => NamespaceUri::XML,
            'xmlns' => NamespaceUri::XMLNS,
        ];
    }
}
