<?php

declare(strict_types=1);

namespace Lexbor\Dom;

enum NodeType: int
{
    case Undefined = 0x00;
    case Element = 0x01;
    case Attribute = 0x02;
    case Text = 0x03;
    case CDataSection = 0x04;
    case EntityReference = 0x05;
    case Entity = 0x06;
    case ProcessingInstruction = 0x07;
    case Comment = 0x08;
    case Document = 0x09;
    case DocumentType = 0x0A;
    case DocumentFragment = 0x0B;
    case Notation = 0x0C;
    case CharacterData = 0x0D;
    case ShadowRoot = 0x0E;
}

