<?php

declare(strict_types=1);

namespace Lexbor\Tests\Core;

use Lexbor\Core\Memory;
use Lexbor\Core\MemoryChunk;
use Lexbor\Core\Status;
use PHPUnit\Framework\TestCase;

final class MemoryTest extends TestCase
{
    public function testInit(): void
    {
        $memory = Memory::create();
        $status = Memory::init($memory, 1024);

        self::assertSame(Status::Ok, $status);

        $chunk = $memory->chunk();
        self::assertNotNull($chunk);
        self::assertSame($chunk, $memory->chunkFirst());
        self::assertTrue($chunk->hasData());
        self::assertNull($chunk->next());
        self::assertNull($chunk->prev());
        self::assertSame(0, $chunk->length());
        self::assertSame(1024, $chunk->size());
        self::assertSame(1, $memory->chunkLength());
        self::assertSame(1024, $memory->chunkMinSize());

        Memory::destroy($memory, true);
    }

    public function testInitNull(): void
    {
        self::assertSame(Status::ErrorObjectIsNull, Memory::init(null, 1024));
    }

    public function testInitStack(): void
    {
        $memory = new Memory();

        self::assertSame(Status::Ok, Memory::init($memory, 1024));

        Memory::destroy($memory, false);
    }

    public function testInitArgs(): void
    {
        $memory = new Memory();

        self::assertSame(Status::ErrorWrongArgs, Memory::init($memory, 0));
        Memory::destroy($memory, false);
    }

    public function testInitTooLargeReturnsMemoryAllocationError(): void
    {
        $memory = new Memory();

        self::assertSame(Status::ErrorMemoryAllocation, Memory::init($memory, PHP_INT_MAX));
    }

    public function testInitLargeRepresentableChunk(): void
    {
        $memory = new Memory();
        $size = 20 * 1024 * 1024;

        self::assertSame(Status::Ok, Memory::init($memory, $size));
        self::assertSame($size, $memory->chunkMinSize());

        Memory::destroy($memory, false);
    }

    public function testInitNearMemoryLimitReturnsMemoryAllocationError(): void
    {
        $limit = self::memoryLimitBytes();

        if ($limit === null) {
            self::assertTrue(true);
            return;
        }

        $requested = $limit - memory_get_usage(true);

        if ($requested <= 0) {
            self::assertTrue(true);
            return;
        }

        $memory = new Memory();

        self::assertSame(Status::ErrorMemoryAllocation, Memory::init($memory, $requested));
    }

    public function testVirtualChunksRespectFiniteMemoryLimitCumulatively(): void
    {
        $previousLimit = (string) ini_get('memory_limit');
        $chunkSize = 20 * 1024 * 1024;
        $temporaryLimit = memory_get_usage(true) + (72 * 1024 * 1024);

        ini_set('memory_limit', (string) $temporaryLimit);

        try {
            $memory = Memory::create();
            self::assertSame(Status::Ok, Memory::init($memory, $chunkSize));

            $accepted = 0;

            for ($i = 0; $i < 20; $i++) {
                if (Memory::chunkMake($memory, $chunkSize) === null) {
                    break;
                }

                $accepted++;
            }

            self::assertLessThan(20, $accepted);

            Memory::destroy($memory, true);
        } finally {
            ini_set('memory_limit', $previousLimit);
        }
    }
    public function testAlignHelpers(): void
    {
        $step = Memory::ALIGN_STEP;

        self::assertSame(0, Memory::align(0));
        self::assertSame($step, Memory::align(1));
        self::assertSame($step, Memory::align($step));
        self::assertSame($step * 2, Memory::align($step + 1));
        self::assertSame(0, Memory::align(PHP_INT_MAX));

        self::assertSame(0, Memory::alignFloor(0));
        self::assertSame(0, Memory::alignFloor(1));
        self::assertSame($step, Memory::alignFloor($step));
        self::assertSame($step, Memory::alignFloor($step + 1));
    }

    public function testChunkMakeTooLargeReturnsNull(): void
    {
        $memory = Memory::create();
        Memory::init($memory, 1024);

        self::assertNull(Memory::chunkMake($memory, PHP_INT_MAX - 7));

        Memory::destroy($memory, true);
    }

    public function testChunkMakeTooLargeWithUnlimitedMemoryLimitReturnsNull(): void
    {
        $previousLimit = (string) ini_get('memory_limit');
        ini_set('memory_limit', '-1');

        try {
            $memory = Memory::create();
            Memory::init($memory, 1024);

            self::assertNull(Memory::chunkMake($memory, PHP_INT_MAX - 7));
            self::assertNull(Memory::chunkMake($memory, PHP_INT_MAX - (2 * 1024 * 1024)));

            $chunk = Memory::chunkMake($memory, 600 * 1024 * 1024);
            self::assertNotNull($chunk);
            self::assertNull(Memory::chunkMake($memory, 600 * 1024 * 1024));

            Memory::destroy($memory, true);
        } finally {
            ini_set('memory_limit', $previousLimit);
        }
    }

    public function testStandaloneChunkBudgetSurvivesDestroyAndReinit(): void
    {
        $previousLimit = (string) ini_get('memory_limit');
        ini_set('memory_limit', '-1');

        try {
            $memory = Memory::create();
            self::assertSame(Status::Ok, Memory::init($memory, 1024));

            $chunk = Memory::chunkMake($memory, 600 * 1024 * 1024);
            self::assertNotNull($chunk);

            self::assertSame($memory, Memory::destroy($memory, false));
            self::assertSame(Status::Ok, Memory::init($memory, 1024));
            self::assertNull(Memory::chunkMake($memory, 600 * 1024 * 1024));

            Memory::chunkDestroy($memory, $chunk, true);

            $replacement = Memory::chunkMake($memory, 600 * 1024 * 1024);
            self::assertNotNull($replacement);

            Memory::chunkDestroy($memory, $replacement, true);
            Memory::destroy($memory, true);
        } finally {
            ini_set('memory_limit', $previousLimit);
        }
    }

    public function testChunkMakeLargeRepresentableChunk(): void
    {
        $memory = Memory::create();
        Memory::init($memory, 1024);

        $chunk = Memory::chunkMake($memory, 20 * 1024 * 1024);

        self::assertNotNull($chunk);
        self::assertSame((20 * 1024 * 1024) + 1024, $chunk->size());

        Memory::chunkDestroy($memory, $chunk, true);
        Memory::destroy($memory, true);
    }

    public function testMemAlloc(): void
    {
        $memory = new Memory();
        Memory::init($memory, 1024);

        self::assertNotNull($memory->alloc(12));

        Memory::destroy($memory, false);
    }

    public function testMemAllocTooLargeReturnsNull(): void
    {
        $memory = new Memory();
        Memory::init($memory, 1024);

        self::assertNull($memory->alloc(PHP_INT_MAX - 7));

        Memory::destroy($memory, false);
    }

    public function testMemAllocN(): void
    {
        $memory = new Memory();
        Memory::init($memory, 1024);

        for ($i = 0; $i < 32; $i++) {
            self::assertNotNull($memory->alloc(32));
        }

        self::assertSame(1, $memory->chunkLength());

        Memory::destroy($memory, false);
    }

    public function testMemAllocOverflow(): void
    {
        $memory = new Memory();
        Memory::init($memory, 31);

        self::assertNotNull($memory->alloc(1047));
        self::assertSame(2, $memory->chunkLength());

        Memory::destroy($memory, false);
    }

    public function testMemCalloc(): void
    {
        $length = 12;
        $memory = new Memory();
        Memory::init($memory, 1024);

        $data = $memory->calloc($length);
        self::assertNotNull($data);

        for ($i = 0; $i < $length; $i++) {
            self::assertSame(0x00, $data->byteAt($i));
        }

        self::assertSame(1, $memory->chunkLength());

        Memory::destroy($memory, false);
    }

    public function testMemCallocOverflow(): void
    {
        $length = 1027;
        $memory = new Memory();
        Memory::init($memory, 31);

        $data = $memory->calloc($length);
        self::assertNotNull($data);

        for ($i = 0; $i < $length; $i++) {
            self::assertSame(0x00, $data->byteAt($i));
        }

        self::assertSame(2, $memory->chunkLength());

        Memory::destroy($memory, false);
    }

    public function testClean(): void
    {
        $memory = new Memory();
        Memory::init($memory, 12);

        for ($i = 0; $i < 32; $i++) {
            self::assertNotNull($memory->alloc(24));
        }

        $memory->clean();

        self::assertSame(1, $memory->chunkLength());
        self::assertNotNull($memory->chunk());
        self::assertSame($memory->chunkFirst(), $memory->chunk());
        self::assertSame(0, $memory->chunk()->length());
        self::assertSame($memory->chunkMinSize(), $memory->chunk()->size());

        Memory::destroy($memory, false);
    }

    public function testDestroy(): void
    {
        $memory = Memory::create();
        Memory::init($memory, 1024);

        self::assertNull(Memory::destroy($memory, true));

        $memory = Memory::create();
        Memory::init($memory, 1021);

        self::assertSame($memory, Memory::destroy($memory, false));
        self::assertNull(Memory::destroy($memory, true));
        self::assertNull(Memory::destroy(null, false));
    }

    public function testDestroyStack(): void
    {
        $memory = new Memory();
        Memory::init($memory, 1023);

        self::assertSame($memory, Memory::destroy($memory, false));
    }

    public function testChunkInit(): void
    {
        $chunk = new MemoryChunk();
        $memory = Memory::create();
        Memory::init($memory, 1024);

        $chunkData = Memory::chunkInit($memory, $chunk, 0);
        self::assertNotNull($chunkData);
        self::assertTrue($chunk->hasData());
        self::assertSame(0, $chunk->length());
        self::assertSame($memory->chunkMinSize(), $chunk->size());

        Memory::chunkDestroy($memory, $chunk, false);
        Memory::destroy($memory, true);
    }

    public function testChunkInitOverflow(): void
    {
        $chunk = new MemoryChunk();
        $memory = Memory::create();
        Memory::init($memory, 1024);

        $chunkData = Memory::chunkInit($memory, $chunk, 2049);
        self::assertNotNull($chunkData);
        self::assertTrue($chunk->hasData());
        self::assertSame(0, $chunk->length());
        self::assertSame(Memory::align(2049) + Memory::align(1024), $chunk->size());

        Memory::chunkDestroy($memory, $chunk, false);
        Memory::destroy($memory, true);
    }

    public function testChunkMake(): void
    {
        $memory = Memory::create();
        Memory::init($memory, 1024);

        $chunk = Memory::chunkMake($memory, 0);
        self::assertNotNull($chunk);
        self::assertTrue($chunk->hasData());
        self::assertSame(0, $chunk->length());
        self::assertSame($memory->chunkMinSize(), $chunk->size());

        Memory::chunkDestroy($memory, $chunk, true);
        Memory::destroy($memory, true);
    }

    public function testChunkMakeOverflow(): void
    {
        $memory = Memory::create();
        Memory::init($memory, 1024);

        $chunk = Memory::chunkMake($memory, 2049);
        self::assertNotNull($chunk);
        self::assertTrue($chunk->hasData());
        self::assertSame(0, $chunk->length());
        self::assertSame(Memory::align(2049) + Memory::align(1024), $chunk->size());

        Memory::chunkDestroy($memory, $chunk, true);
        Memory::destroy($memory, true);
    }

    public function testChunkDestroy(): void
    {
        $memory = Memory::create();
        Memory::init($memory, 1024);

        $chunk = Memory::chunkMake($memory, 0);
        $chunk = Memory::chunkDestroy($memory, $chunk, true);
        self::assertNull($chunk);

        $chunk = Memory::chunkMake($memory, 0);
        $chunk = Memory::chunkDestroy($memory, $chunk, false);
        self::assertNotNull($chunk);
        self::assertFalse($chunk->hasData());

        $chunk = Memory::chunkDestroy($memory, $chunk, true);
        self::assertNull($chunk);

        $chunk = Memory::chunkMake($memory, 0);
        self::assertNotNull($chunk);

        $chunkNull = Memory::chunkDestroy(null, $chunk, false);
        self::assertNull($chunkNull);

        $chunk = Memory::chunkDestroy($memory, $chunk, true);
        self::assertNull($chunk);

        $chunk = Memory::chunkDestroy($memory, null, false);
        self::assertNull($chunk);

        $chunk = Memory::chunkDestroy(null, null, false);
        self::assertNull($chunk);

        Memory::destroy($memory, true);
    }

    private static function memoryLimitBytes(): ?int
    {
        $raw = trim((string) ini_get('memory_limit'));

        if ($raw === '' || $raw === '-1') {
            return null;
        }

        $unit = strtolower($raw[strlen($raw) - 1]);
        $number = (int) $raw;
        $multiplier = match ($unit) {
            'g' => 1024 * 1024 * 1024,
            'm' => 1024 * 1024,
            'k' => 1024,
            default => 1,
        };

        if ($number <= 0) {
            return null;
        }

        if ($number > intdiv(PHP_INT_MAX, $multiplier)) {
            return PHP_INT_MAX;
        }

        return $number * $multiplier;
    }
}
