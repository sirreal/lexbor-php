<?php

declare(strict_types=1);

namespace Lexbor\Dom;

enum ExceptionCode: int
{
    case Ok = -1;
    case Error = 0;
    case IndexSizeError = 1;
    case DomStringSizeError = 2;
    case HierarchyRequestError = 3;
    case WrongDocumentError = 4;
    case InvalidCharacterError = 5;
    case NoDataAllowedError = 6;
    case NoModificationAllowedError = 7;
    case NotFoundError = 8;
    case NotSupportedError = 9;
    case InUseAttributeError = 10;
    case InvalidStateError = 11;
    case SyntaxError = 12;
    case InvalidModificationError = 13;
    case NamespaceError = 14;
    case InvalidAccessError = 15;
    case ValidationError = 16;
    case TypeMismatchError = 17;
    case SecurityError = 18;
    case NetworkError = 19;
    case AbortError = 20;
    case UrlMismatchError = 21;
    case QuotaExceededError = 22;
    case TimeoutError = 23;
    case InvalidNodeTypeError = 24;
    case DataCloneError = 25;
    case EncodingError = 26;
    case NotReadableError = 27;
    case UnknownError = 28;
    case ConstraintError = 29;
    case DataError = 30;
    case TransactionInactiveError = 31;
    case ReadOnlyError = 32;
    case VersionError = 33;
    case OperationError = 34;
    case NotAllowedError = 35;
    case OptOutError = 36;
}

