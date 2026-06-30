<?php

declare(strict_types=1);

namespace Lexbor\Tests\Core;

use Lexbor\Core\ArrayList;
use Lexbor\Core\Status;
use PHPUnit\Framework\TestCase;

final class ArrayListTest extends TestCase
{
    public function testInit(): void
    {
        $array = ArrayList::create();
        $status = ArrayList::init($array, 32);

        self::assertSame(Status::Ok, $status);
        self::assertNull(ArrayList::destroy($array, true));
    }

    public function testInitNull(): void
    {
        self::assertSame(Status::ErrorObjectIsNull, ArrayList::init(null, 32));
    }

    public function testInitTooSmall(): void
    {
        self::assertSame(Status::ErrorTooSmallSize, ArrayList::init(ArrayList::create(), 0));
    }

    public function testInitStack(): void
    {
        $array = new ArrayList();

        self::assertSame(Status::Ok, ArrayList::init($array, 32));
        self::assertSame($array, ArrayList::destroy($array, false));
    }

    public function testClean(): void
    {
        $array = new ArrayList();
        ArrayList::init($array, 32);

        $array->push(1);
        self::assertSame(1, $array->length());

        $array->clean();
        self::assertSame(0, $array->length());

        ArrayList::destroy($array, false);
    }

    public function testPush(): void
    {
        $array = new ArrayList();
        ArrayList::init($array, 32);

        self::assertSame(0, $array->length());
        self::assertSame(Status::Ok, $array->push(1));
        self::assertSame(1, $array->length());
        self::assertSame(1, $array->get(0));

        ArrayList::destroy($array, false);
    }

    public function testPushNull(): void
    {
        $array = new ArrayList();
        ArrayList::init($array, 32);

        self::assertSame(Status::Ok, $array->push(null));
        self::assertSame(1, $array->length());
        self::assertNull($array->get(0));

        ArrayList::destroy($array, false);
    }

    public function testPop(): void
    {
        $array = new ArrayList();
        ArrayList::init($array, 32);

        $array->push(123);

        self::assertSame(123, $array->pop());
        self::assertSame(0, $array->length());

        ArrayList::destroy($array, false);
    }

    public function testPopIfEmpty(): void
    {
        $array = new ArrayList();
        ArrayList::init($array, 32);

        self::assertSame(0, $array->length());
        self::assertNull($array->pop());
        self::assertSame(0, $array->length());

        ArrayList::destroy($array, false);
    }

    public function testGet(): void
    {
        $array = new ArrayList();
        ArrayList::init($array, 32);

        self::assertNull($array->get(1));
        self::assertNull($array->get(0));

        $array->push(123);

        self::assertSame(123, $array->get(0));
        self::assertNull($array->get(1));
        self::assertNull($array->get(1000));

        ArrayList::destroy($array, false);
    }

    public function testSet(): void
    {
        $array = new ArrayList();
        ArrayList::init($array, 32);

        $array->push(123);

        self::assertSame(Status::Ok, $array->set(0, 456));
        self::assertSame(456, $array->get(0));
        self::assertSame(1, $array->length());

        ArrayList::destroy($array, false);
    }

    public function testSetNotExists(): void
    {
        $array = new ArrayList();
        ArrayList::init($array, 32);

        self::assertSame(Status::Ok, $array->set(10, 123));
        self::assertSame(123, $array->get(10));

        for ($i = 0; $i < 10; $i++) {
            self::assertNull($array->get($i));
        }

        self::assertSame(11, $array->length());

        ArrayList::destroy($array, false);
    }

    public function testInsert(): void
    {
        $array = new ArrayList();
        ArrayList::init($array, 32);

        self::assertSame(Status::Ok, $array->insert(0, 456));
        self::assertSame(456, $array->get(0));
        self::assertSame(1, $array->length());
        self::assertSame(32, $array->size());

        ArrayList::destroy($array, false);
    }

    public function testInsertEnd(): void
    {
        $array = new ArrayList();
        ArrayList::init($array, 32);

        self::assertSame(Status::Ok, $array->insert(32, 457));
        self::assertSame(457, $array->get(32));
        self::assertSame(33, $array->length());
        self::assertNotSame(32, $array->size());

        ArrayList::destroy($array, false);
    }

    public function testInsertOverflow(): void
    {
        $array = new ArrayList();
        ArrayList::init($array, 32);

        self::assertSame(Status::Ok, $array->insert(33, 458));
        self::assertSame(458, $array->get(33));
        self::assertSame(34, $array->length());
        self::assertNotSame(32, $array->size());

        ArrayList::destroy($array, false);
    }

    public function testInsertTo(): void
    {
        $array = new ArrayList();
        ArrayList::init($array, 32);

        for ($i = 1; $i <= 9; $i++) {
            self::assertSame(Status::Ok, $array->push($i));
        }

        self::assertSame(Status::Ok, $array->insert(4, 459));

        self::assertSame([1, 2, 3, 4, 459, 5, 6, 7, 8, 9], $this->arrayValues($array));
        self::assertSame(10, $array->length());

        ArrayList::destroy($array, false);
    }

    public function testInsertToEnd(): void
    {
        $array = new ArrayList();
        ArrayList::init($array, 9);

        for ($i = 1; $i <= 9; $i++) {
            self::assertSame(Status::Ok, $array->push($i));
        }

        self::assertSame(9, $array->length());
        self::assertSame(9, $array->size());
        self::assertSame(Status::Ok, $array->insert(4, 459));

        self::assertSame([1, 2, 3, 4, 459, 5, 6, 7, 8, 9], $this->arrayValues($array));
        self::assertSame(10, $array->length());
        self::assertNotSame(9, $array->size());

        ArrayList::destroy($array, false);
    }

    public function testDelete(): void
    {
        $array = new ArrayList();
        ArrayList::init($array, 32);

        for ($i = 0; $i < 10; $i++) {
            $array->push($i);
        }

        self::assertSame(10, $array->length());

        $array->delete(10, 100);
        self::assertSame(10, $array->length());

        $array->delete(100, 1);
        self::assertSame(10, $array->length());

        $array->delete(100, 0);
        self::assertSame(10, $array->length());

        for ($i = 0; $i < 10; $i++) {
            self::assertSame($i, $array->get($i));
        }

        $array->delete(4, 4);
        self::assertSame(6, $array->length());

        $array->delete(4, 0);
        self::assertSame(6, $array->length());

        $array->delete(0, 0);
        self::assertSame(6, $array->length());

        self::assertSame([0, 1, 2, 3, 8, 9], $this->arrayValues($array));

        $array->delete(0, 1);
        self::assertSame(5, $array->length());
        self::assertSame([1, 2, 3, 8, 9], $this->arrayValues($array));

        $array->delete(1, 1000);
        self::assertSame(1, $array->length());
        self::assertSame(1, $array->get(0));

        ArrayList::destroy($array, false);
    }

    public function testDeleteIfEmpty(): void
    {
        $array = new ArrayList();
        ArrayList::init($array, 32);

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

        ArrayList::destroy($array, false);
    }

    public function testExpand(): void
    {
        $array = new ArrayList();
        ArrayList::init($array, 32);

        self::assertNotNull($array->expand(128));
        self::assertSame(128, $array->size());

        ArrayList::destroy($array, false);
    }

    public function testDestroy(): void
    {
        $array = ArrayList::create();
        ArrayList::init($array, 32);

        self::assertNull(ArrayList::destroy($array, true));

        $array = ArrayList::create();
        ArrayList::init($array, 32);

        self::assertSame($array, ArrayList::destroy($array, false));
        self::assertNull(ArrayList::destroy($array, true));
        self::assertNull(ArrayList::destroy(null, false));
    }

    public function testDestroyStack(): void
    {
        $array = new ArrayList();
        ArrayList::init($array, 32);

        self::assertSame($array, ArrayList::destroy($array, false));
    }

    /**
     * @return list<mixed>
     */
    private function arrayValues(ArrayList $array): array
    {
        $values = [];

        for ($i = 0; $i < $array->length(); $i++) {
            $values[] = $array->get($i);
        }

        return $values;
    }
}
