<?php

declare(strict_types=1);

namespace Lexbor\Html;

use Lexbor\Core\Status;

final class Parser
{
    private int $domOptions = Document::OPT_UNDEF;
    private ?Document $chunkDocument = null;
    private string $chunkHtml = '';

    public function domOptions(): int
    {
        return $this->domOptions;
    }

    public function setDomOptions(int $options): void
    {
        $this->domOptions = $options;
    }

    public function parse(string $html): Document
    {
        $document = new Document($this->domOptions);
        $document->parse($html);

        return $document;
    }

    public function parseChunkBegin(): Document
    {
        $this->chunkDocument = new Document($this->domOptions);
        $this->chunkHtml = '';

        return $this->chunkDocument;
    }

    public function parseChunkProcess(string $html): Status
    {
        if ($this->chunkDocument === null) {
            $this->parseChunkBegin();
        }

        $this->chunkHtml .= $html;

        return Status::Ok;
    }

    public function parseChunkEnd(): Status
    {
        if ($this->chunkDocument === null) {
            $this->parseChunkBegin();
        }

        $status = $this->chunkDocument->parse($this->chunkHtml);
        $this->chunkHtml = '';

        return $status;
    }
}
