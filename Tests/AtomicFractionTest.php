<?php

namespace Tests;

use Tests\Models\StockItem;
use Q\Orm\Connection;

class AtomicFractionTest extends QormTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $pdo = Connection::getInstance();
        $pdo->exec("DROP TABLE IF EXISTS stock_item");
        \Tests\Helpers\TestUtil::createTableFromModel(StockItem::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Connection::getInstance()->exec("DELETE FROM stock_item");
        StockItem::items()->create([
            'name' => 'Widget',
            'qty' => 10.5,
            'cost' => 4.25,
        ]);
    }

    public function testIncrementFractionalDeltaBelowOne(): void
    {
        StockItem::items()->filter(['name.eq' => 'Widget'])->increment(['qty' => 0.25]);
        $item = StockItem::items()->filter(['name.eq' => 'Widget'])->one();
        $this->assertEquals(10.75, $item->qty);
    }

    public function testIncrementFractionalDeltaAboveOne(): void
    {
        StockItem::items()->filter(['name.eq' => 'Widget'])->increment(['qty' => 1.5]);
        $item = StockItem::items()->filter(['name.eq' => 'Widget'])->one();
        $this->assertEquals(12.0, $item->qty);
    }

    public function testDecrementFractionalDelta(): void
    {
        StockItem::items()->filter(['name.eq' => 'Widget'])->decrement(['qty' => 0.5]);
        $item = StockItem::items()->filter(['name.eq' => 'Widget'])->one();
        $this->assertEquals(10.0, $item->qty);
    }

    public function testMultiplyFractionalDelta(): void
    {
        StockItem::items()->filter(['name.eq' => 'Widget'])->multiply(['qty' => 1.5]);
        $item = StockItem::items()->filter(['name.eq' => 'Widget'])->one();
        $this->assertEquals(15.75, $item->qty);
    }

    public function testDivideFractionalDelta(): void
    {
        StockItem::items()->filter(['name.eq' => 'Widget'])->divide(['qty' => 1.5]);
        $item = StockItem::items()->filter(['name.eq' => 'Widget'])->one();
        $this->assertEquals(7.0, $item->qty);
    }

    public function testStringNumericDelta(): void
    {
        StockItem::items()->filter(['name.eq' => 'Widget'])->increment(['qty' => '0.5']);
        $item = StockItem::items()->filter(['name.eq' => 'Widget'])->one();
        $this->assertEquals(11.0, $item->qty);
    }

    public function testZeroDeltaIsNoOp(): void
    {
        StockItem::items()->filter(['name.eq' => 'Widget'])->increment(['qty' => 0]);
        $item = StockItem::items()->filter(['name.eq' => 'Widget'])->one();
        $this->assertEquals(10.5, $item->qty);
    }

    public function testNonNumericStringDeltaThrows(): void
    {
        $this->expectException(\Error::class);
        StockItem::items()->filter(['name.eq' => 'Widget'])->increment(['qty' => 'abc']);
    }

    public function testMixedDeltasInOneCall(): void
    {
        StockItem::items()->filter(['name.eq' => 'Widget'])->increment(['qty' => 3, 'cost' => 0.5]);
        $item = StockItem::items()->filter(['name.eq' => 'Widget'])->one();
        $this->assertEquals(13.5, $item->qty);
        $this->assertEquals(4.75, $item->cost);
    }
}
