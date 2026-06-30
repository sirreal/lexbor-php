<?php

declare(strict_types=1);

namespace Lexbor\Tests\Core;

use Lexbor\Core\ObjectArray;
use Lexbor\Core\Status;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ObjectArrayTest extends TestCase
{
    private const STRUCT_SIZE = 16;

    public function testInit(): void
    {
        $array = ObjectArray::create();
        $status = ObjectArray::init($array, 32, self::STRUCT_SIZE);

        self::assertSame(Status::Ok, $status);
        self::assertNull(ObjectArray::destroy($array, true));
    }

    public function testInitNull(): void
    {
        self::assertSame(Status::ErrorObjectIsNull, ObjectArray::init(null, 32, self::STRUCT_SIZE));
    }

    public function testInitTooSmall(): void
    {
        self::assertSame(Status::ErrorTooSmallSize, ObjectArray::init(ObjectArray::create(), 0, self::STRUCT_SIZE));
        self::assertSame(Status::ErrorTooSmallSize, ObjectArray::init(ObjectArray::create(), 32, 0));
    }

    public function testInitStack(): void
    {
        $array = new ObjectArray();

        self::assertSame(Status::Ok, ObjectArray::init($array, 32, self::STRUCT_SIZE));
        self::assertSame(self::STRUCT_SIZE, $array->structSize());

        ObjectArray::destroy($array, false);
    }

    public function testClean(): void
    {
        $array = new ObjectArray();
        ObjectArray::init($array, 32, self::STRUCT_SIZE);

        self::assertInstanceOf(stdClass::class, $array->push());
        self::assertSame(1, $array->length());

        $array->clean();
        self::assertSame(0, $array->length());

        ObjectArray::destroy($array, false);
    }

    public function testPushReusesAndClearsSlotAfterClean(): void
    {
        $array = new ObjectArray();
        ObjectArray::init($array, 32, self::STRUCT_SIZE);

        $entry = $array->push();
        self::assertNotNull($entry);
        $entry->data = 'stale';

        $array->clean();

        $next = $array->push();
        self::assertSame($entry, $next);
        self::assertSame([], get_object_vars($next));

        ObjectArray::destroy($array, false);
    }

    public function testPushReusesAndClearsSlotAfterPop(): void
    {
        $array = new ObjectArray();
        ObjectArray::init($array, 32, self::STRUCT_SIZE);

        $entry = $array->push();
        self::assertNotNull($entry);
        $entry->data = 'stale';

        self::assertSame($entry, $array->pop());

        $next = $array->push();
        self::assertSame($entry, $next);
        self::assertSame([], get_object_vars($next));

        ObjectArray::destroy($array, false);
    }

    public function testPushWithoutClearReusesSlotAfterClean(): void
    {
        $array = new ObjectArray();
        ObjectArray::init($array, 32, self::STRUCT_SIZE);

        $entry = $array->push();
        self::assertNotNull($entry);
        $entry->data = 'stale';

        $array->clean();

        $next = $array->pushWithoutClear();
        self::assertSame($entry, $next);
        self::assertSame('stale', $next->data);

        ObjectArray::destroy($array, false);
    }

    public function testPush(): void
    {
        $array = new ObjectArray();
        ObjectArray::init($array, 32, self::STRUCT_SIZE);

        self::assertSame(0, $array->length());

        $entry = $array->push();
        self::assertNotNull($entry);
        self::assertSame(1, $array->length());
        self::assertSame($entry, $array->get(0));

        ObjectArray::destroy($array, false);
    }

    public function testPushNReturnsCurrentSlotWithoutClearing(): void
    {
        $array = new ObjectArray();
        ObjectArray::init($array, 32, self::STRUCT_SIZE);

        $entry = $array->push();
        self::assertNotNull($entry);
        $entry->data = 'first';

        $array->clean();

        $next = $array->pushN(2);
        self::assertSame($entry, $next);
        self::assertSame('first', $next->data);
        self::assertSame(2, $array->length());

        ObjectArray::destroy($array, false);
    }

    public function testPushNZeroReturnsCurrentStoredSlotWithoutChangingLength(): void
    {
        $array = new ObjectArray();
        ObjectArray::init($array, 32, self::STRUCT_SIZE);

        $entry = $array->pushN(0);
        self::assertNotNull($entry);
        $entry->data = 'current';

        self::assertSame(0, $array->length());
        self::assertSame($entry, $array->pushWithoutClear());
        self::assertSame('current', $entry->data);

        ObjectArray::destroy($array, false);
    }

    public function testLast(): void
    {
        $array = new ObjectArray();
        ObjectArray::init($array, 32, self::STRUCT_SIZE);

        self::assertNull($array->last());

        $first = $array->push();
        $second = $array->push();

        self::assertSame($second, $array->last());
        self::assertNotSame($first, $array->last());

        ObjectArray::destroy($array, false);
    }

    public function testPop(): void
    {
        $array = new ObjectArray();
        ObjectArray::init($array, 32, self::STRUCT_SIZE);

        $entry = $array->push();
        self::assertNotNull($entry);

        self::assertSame($entry, $array->pop());
        self::assertSame(0, $array->length());

        ObjectArray::destroy($array, false);
    }

    public function testPopIfEmpty(): void
    {
        $array = new ObjectArray();
        ObjectArray::init($array, 32, self::STRUCT_SIZE);

        self::assertSame(0, $array->length());
        self::assertNull($array->pop());
        self::assertSame(0, $array->length());

        ObjectArray::destroy($array, false);
    }

    public function testGet(): void
    {
        $array = new ObjectArray();
        ObjectArray::init($array, 32, self::STRUCT_SIZE);

        self::assertNull($array->get(1));
        self::assertNull($array->get(0));

        $entry = $array->push();
        self::assertNotNull($entry);

        self::assertSame($entry, $array->get(0));
        self::assertNull($array->get(1));
        self::assertNull($array->get(1000));

        ObjectArray::destroy($array, false);
    }

    public function testDeleteTailLeavesStaleSlotForNoClearReuse(): void
    {
        $array = new ObjectArray();
        ObjectArray::init($array, 32, self::STRUCT_SIZE);

        for ($i = 0; $i < 3; $i++) {
            $entry = $array->push();
            self::assertNotNull($entry);
            $entry->data = $i;
            $entry->len = $i;
        }

        $stale = $array->get(1);
        self::assertNotNull($stale);

        $array->delete(1, 100);
        self::assertSame(1, $array->length());

        $next = $array->pushWithoutClear();
        self::assertSame($stale, $next);
        $this->assertEntry($next, 1, 1);

        ObjectArray::destroy($array, false);
    }

    public function testDeleteMiddleCopiesContentsIntoDestinationSlots(): void
    {
        $array = new ObjectArray();
        ObjectArray::init($array, 32, self::STRUCT_SIZE);

        for ($i = 0; $i < 10; $i++) {
            $entry = $array->push();
            self::assertNotNull($entry);
            $entry->data = $i;
            $entry->len = $i;
        }

        $slotFour = $array->get(4);
        $slotFive = $array->get(5);
        $slotEight = $array->get(8);
        $slotNine = $array->get(9);

        $array->delete(4, 4);

        self::assertSame(6, $array->length());
        self::assertSame($slotFour, $array->get(4));
        self::assertSame($slotFive, $array->get(5));
        self::assertNotSame($slotEight, $array->get(4));
        self::assertNotSame($slotNine, $array->get(5));
        $this->assertEntry($array->get(4), 8, 8);
        $this->assertEntry($array->get(5), 9, 9);

        ObjectArray::destroy($array, false);
    }

    public function testDelete(): void
    {
        $array = new ObjectArray();
        ObjectArray::init($array, 32, self::STRUCT_SIZE);

        for ($i = 0; $i < 10; $i++) {
            $entry = $array->push();
            self::assertNotNull($entry);
            $entry->data = $i;
            $entry->len = $i;
        }

        self::assertSame(10, $array->length());

        $array->delete(10, 100);
        self::assertSame(10, $array->length());

        $array->delete(100, 1);
        self::assertSame(10, $array->length());

        $array->delete(100, 0);
        self::assertSame(10, $array->length());

        for ($i = 0; $i < 10; $i++) {
            $this->assertEntry($array->get($i), $i, $i);
        }

        $array->delete(4, 4);
        self::assertSame(6, $array->length());

        $array->delete(4, 0);
        self::assertSame(6, $array->length());

        $array->delete(0, 0);
        self::assertSame(6, $array->length());

        $this->assertEntries($array, [0, 1, 2, 3, 8, 9]);

        $array->delete(0, 1);
        self::assertSame(5, $array->length());
        $this->assertEntries($array, [1, 2, 3, 8, 9]);

        $array->delete(1, 1000);
        self::assertSame(1, $array->length());
        $this->assertEntry($array->get(0), 1, 1);

        ObjectArray::destroy($array, false);
    }

    public function testDeleteIfEmpty(): void
    {
        $array = new ObjectArray();
        ObjectArray::init($array, 32, self::STRUCT_SIZE);

        $array->delete(0, 0);
        self::assertSame(0, $array->length());

        $array->delete(1, 0);
        self::assertSame(0, $array->length());

        $array->delete(1, 1);
        self::assertSame(0, $array->length());

        $array->delete(100, 1);
        self::assertSame(0, $array->length());

        $array->delete(10, 100);
        self::assertSame(0, $array->length());

        ObjectArray::destroy($array, false);
    }

    public function testExpand(): void
    {
        $array = new ObjectArray();
        ObjectArray::init($array, 32, self::STRUCT_SIZE);

        self::assertNotNull($array->expand(128));
        self::assertSame(128, $array->size());

        ObjectArray::destroy($array, false);
    }

    public function testErase(): void
    {
        $array = new ObjectArray();
        ObjectArray::init($array, 32, self::STRUCT_SIZE);

        $array->push();
        $array->erase();

        self::assertSame(0, $array->length());
        self::assertSame(0, $array->size());
        self::assertSame(0, $array->structSize());
        self::assertNull($array->get(0));
    }

    public function testDestroy(): void
    {
        $array = ObjectArray::create();
        ObjectArray::init($array, 32, self::STRUCT_SIZE);

        self::assertNull(ObjectArray::destroy($array, true));

        $array = ObjectArray::create();
        ObjectArray::init($array, 32, self::STRUCT_SIZE);

        self::assertSame($array, ObjectArray::destroy($array, false));
        self::assertSame(self::STRUCT_SIZE, $array->structSize());
        self::assertNull(ObjectArray::destroy($array, true));
        self::assertNull(ObjectArray::destroy(null, false));
    }

    public function testDestroyStack(): void
    {
        $array = new ObjectArray();
        ObjectArray::init($array, 32, self::STRUCT_SIZE);

        self::assertSame($array, ObjectArray::destroy($array, false));
    }

    /**
     * @param list<int> $expected
     */
    private function assertEntries(ObjectArray $array, array $expected): void
    {
        foreach ($expected as $idx => $value) {
            $this->assertEntry($array->get($idx), $value, $value);
        }
    }

    private function assertEntry(?stdClass $entry, int $data, int $len): void
    {
        self::assertNotNull($entry);
        self::assertSame($data, $entry->data);
        self::assertSame($len, $entry->len);
    }
}
