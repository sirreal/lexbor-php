<?php

declare(strict_types=1);

namespace Lexbor\Dom;

final readonly class DomExceptionRecord
{
    /**
     * @var array<int, array{name: string, message: string}>
     */
    private const DATA = [
        0 => ['name' => 'Error', 'message' => ''],
        1 => ['name' => 'IndexSizeError', 'message' => 'Deprecated. Use RangeError instead.'],
        2 => ['name' => 'DOMStringSizeError', 'message' => ''],
        3 => ['name' => 'HierarchyRequestError', 'message' => 'The operation would yield an incorrect node tree.'],
        4 => ['name' => 'WrongDocumentError', 'message' => 'The object is in the wrong document.'],
        5 => ['name' => 'InvalidCharacterError', 'message' => 'The string contains invalid characters.'],
        6 => ['name' => 'NoDataAllowedError', 'message' => ''],
        7 => ['name' => 'NoModificationAllowedError', 'message' => 'The object can not be modified.'],
        8 => ['name' => 'NotFoundError', 'message' => 'The object can not be found here.'],
        9 => ['name' => 'NotSupportedError', 'message' => 'The operation is not supported.'],
        10 => ['name' => 'InUseAttributeError', 'message' => 'The attribute is in use by another element.'],
        11 => ['name' => 'InvalidStateError', 'message' => 'The object is in an invalid state.'],
        12 => ['name' => 'SyntaxError', 'message' => 'The string did not match the expected pattern.'],
        13 => ['name' => 'InvalidModificationError', 'message' => 'The object can not be modified in this way.'],
        14 => ['name' => 'NamespaceError', 'message' => 'The operation is not allowed by Namespaces in XML.'],
        15 => [
            'name' => 'InvalidAccessError',
            'message' => 'Deprecated. Use TypeError for invalid arguments, "NotSupportedError" DOMException for unsupported operations, and "NotAllowedError" DOMException for denied requests instead.',
        ],
        16 => ['name' => 'ValidationError', 'message' => ''],
        17 => ['name' => 'TypeMismatchError', 'message' => 'Deprecated. Use TypeError instead.'],
        18 => ['name' => 'SecurityError', 'message' => 'The operation is insecure.'],
        19 => ['name' => 'NetworkError', 'message' => 'A network error occurred.'],
        20 => ['name' => 'AbortError', 'message' => 'The operation was aborted.'],
        21 => ['name' => 'URLMismatchError', 'message' => 'Deprecated.'],
        22 => ['name' => 'QuotaExceededError', 'message' => 'Deprecated. Use the QuotaExceededError DOMException-derived interface instead.'],
        23 => ['name' => 'TimeoutError', 'message' => 'The operation timed out.'],
        24 => ['name' => 'InvalidNodeTypeError', 'message' => 'The supplied node is incorrect or has an incorrect ancestor for this operation.'],
        25 => ['name' => 'DataCloneError', 'message' => 'The object can not be cloned.'],
        26 => ['name' => 'EncodingError', 'message' => 'The encoding operation (either encoded or decoding) failed.'],
        27 => ['name' => 'NotReadableError', 'message' => 'The I/O read operation failed.'],
        28 => ['name' => 'UnknownError', 'message' => 'The operation failed for an unknown transient reason (e.g. out of memory).'],
        29 => ['name' => 'ConstraintError', 'message' => 'A mutation operation in a transaction failed because a constraint was not satisfied.'],
        30 => ['name' => 'DataError', 'message' => 'Provided data is inadequate.'],
        31 => ['name' => 'TransactionInactiveError', 'message' => 'A request was placed against a transaction which is currently not active, or which is finished.'],
        32 => ['name' => 'ReadOnlyError', 'message' => 'The mutating operation was attempted in a "readonly" transaction.'],
        33 => ['name' => 'VersionError', 'message' => 'An attempt was made to open a database using a lower version than the existing version.'],
        34 => ['name' => 'OperationError', 'message' => 'The operation failed for an operation-specific reason.'],
        35 => ['name' => 'NotAllowedError', 'message' => 'The request is not allowed by the user agent or the platform in the current context, possibly because the user denied permission.'],
        36 => ['name' => 'OptOutError', 'message' => 'The user opted out of the process.'],
    ];

    public function __construct(
        public string $message,
        public string $name,
        public ExceptionCode $code,
    ) {
    }

    public static function create(?string $message = null, ?string $name = null): self
    {
        $code = ($name !== null && $name !== '') ? self::codeByName($name) : ExceptionCode::Error;
        $data = self::data($code);

        return new self(
            ($message === null || $message === '') ? $data['message'] : $message,
            ($code !== ExceptionCode::Error || $name === null || $name === '') ? $data['name'] : $name,
            $code,
        );
    }

    public static function createByCode(?string $message, ExceptionCode $code): ?self
    {
        if ($code === ExceptionCode::Ok) {
            return null;
        }

        $data = self::data($code);

        return new self(
            ($message === null || $message === '') ? $data['message'] : $message,
            $data['name'],
            $code,
        );
    }

    public static function messageByCode(ExceptionCode $code): ?string
    {
        if ($code === ExceptionCode::Ok) {
            return null;
        }

        return self::data($code)['message'];
    }

    public static function nameByCode(ExceptionCode $code): ?string
    {
        if ($code === ExceptionCode::Ok) {
            return null;
        }

        return self::data($code)['name'];
    }

    public static function codeByName(string $name): ExceptionCode
    {
        foreach (self::DATA as $code => $data) {
            if (strcasecmp($data['name'], $name) === 0) {
                return ExceptionCode::from($code);
            }
        }

        return ExceptionCode::Error;
    }

    /**
     * @return array{name: string, message: string}
     */
    private static function data(ExceptionCode $code): array
    {
        return self::DATA[$code->value] ?? self::DATA[ExceptionCode::Error->value];
    }
}
