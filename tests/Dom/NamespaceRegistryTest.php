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
}
