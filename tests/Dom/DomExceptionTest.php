<?php

declare(strict_types=1);

namespace Lexbor\Tests\Dom;

use Lexbor\Dom\DomExceptionRecord;
use Lexbor\Dom\ExceptionCode;
use PHPUnit\Framework\TestCase;

final class DomExceptionTest extends TestCase
{
    public function testCreateByName(): void
    {
        $exception = DomExceptionRecord::create('Simple error', 'MyError');

        self::assertSame('Simple error', $exception->message);
        self::assertSame('MyError', $exception->name);
        self::assertSame(ExceptionCode::Error, $exception->code);
    }

    public function testCreateByDefault(): void
    {
        $exception = DomExceptionRecord::create();

        self::assertSame(DomExceptionRecord::messageByCode(ExceptionCode::Error), $exception->message);
        self::assertSame(DomExceptionRecord::nameByCode(ExceptionCode::Error), $exception->name);
        self::assertSame(ExceptionCode::Error, $exception->code);
    }

    public function testCreateByCode(): void
    {
        $exception = DomExceptionRecord::createByCode('Simple error', ExceptionCode::NetworkError);

        self::assertNotNull($exception);
        self::assertSame('Simple error', $exception->message);
        self::assertSame(DomExceptionRecord::nameByCode(ExceptionCode::NetworkError), $exception->name);
        self::assertSame(ExceptionCode::NetworkError, $exception->code);
    }

    public function testCreateByCodeRejectsOkCode(): void
    {
        self::assertNull(DomExceptionRecord::createByCode(null, ExceptionCode::Ok));
    }

    public function testCreateUsingReservedName(): void
    {
        $exception = DomExceptionRecord::create(null, 'InvalidStateError');

        self::assertSame(DomExceptionRecord::messageByCode(ExceptionCode::InvalidStateError), $exception->message);
        self::assertSame(DomExceptionRecord::nameByCode(ExceptionCode::InvalidStateError), $exception->name);
        self::assertSame(ExceptionCode::InvalidStateError, $exception->code);
    }

    public function testLastError(): void
    {
        $exception = DomExceptionRecord::create(null, 'OptOutError');

        self::assertSame(DomExceptionRecord::messageByCode(ExceptionCode::OptOutError), $exception->message);
        self::assertSame(DomExceptionRecord::nameByCode(ExceptionCode::OptOutError), $exception->name);
        self::assertSame(ExceptionCode::OptOutError, $exception->code);
    }
}
