<?php

declare(strict_types=1);

namespace Lexbor\Dom;

final class Text extends Node
{
    public string $processedData;
    private string $processedObservedData;

    public function __construct(
        public string $data,
        ?object $ownerDocument = null,
        ?int $localName = null,
    ) {
        parent::__construct(NodeType::Text, $ownerDocument, $localName);
        $this->processedData = $data;
        $this->processedObservedData = $data;
    }

    public function processRawDataForStyleEvents(): void
    {
        $this->processedData = $this->data;
        $this->processedObservedData = $this->data;
    }

    public function syncProcessedDataForStyleEvents(): void
    {
        if ($this->data !== $this->processedObservedData) {
            $this->processRawDataForStyleEvents();
        }
    }

    public function observeRawDataWithoutProcessing(): void
    {
        $this->processedObservedData = $this->data;
    }

    /**
     * @internal Style matching temporarily swaps raw DOM text for processed text.
     */
    public function setDataForStyleSnapshot(string $data): void
    {
        $this->data = $data;
    }
}
