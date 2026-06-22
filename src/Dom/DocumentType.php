<?php

declare(strict_types=1);

namespace Lexbor\Dom;

final class DocumentType extends Node
{
    public function __construct(
        private string $name = 'html',
        private ?string $publicId = null,
        private ?string $systemId = null,
        ?object $ownerDocument = null,
        ?int $localName = null,
    ) {
        parent::__construct(NodeType::DocumentType, $ownerDocument, $localName);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function publicId(): ?string
    {
        return $this->publicId;
    }

    public function systemId(): ?string
    {
        return $this->systemId;
    }

    public static function isValidName(string $name): bool
    {
        return $name !== '' && preg_match('/[\x00\x09\x0A\x0C\x0D\x20>]/', $name) !== 1;
    }
}
