<?php

namespace Tests;

use Tests\Models\MagicItem;
use Q\Orm\Connection;
use Q\Orm\QueryStack;

class MagicHydrationTest extends QormTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $pdo = Connection::getInstance();
        $pdo->exec("DROP TABLE IF EXISTS magic_item");
        \Tests\Helpers\TestUtil::createTableFromModel(MagicItem::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Connection::getInstance()->exec("DELETE FROM magic_item");
        MagicItem::items()->create(['name' => 'm1', 'price' => null, 'payment_status' => 'pending']);
    }

    public function testLoadCapturesCompletePrevState(): void
    {
        $item = MagicItem::items()->filter(['name.eq' => 'm1'])->one();

        $prev = $item->prevState();
        $this->assertArrayHasKey('name', $prev);
        $this->assertArrayHasKey('price', $prev);
        $this->assertArrayHasKey('payment_status', $prev);
        $this->assertNull($prev['price']);
    }

    public function testSaveDoesNotClobberConcurrentWrite(): void
    {
        $item = MagicItem::items()->filter(['name.eq' => 'm1'])->one();

        MagicItem::items()->filter(['name.eq' => 'm1'])->update(['price' => 0.00]);

        $item->payment_status = 'Paid';
        $item->save();

        $fresh = MagicItem::items()->filter(['name.eq' => 'm1'])->one();
        $this->assertSame(0, $fresh->price);
        $this->assertSame('Paid', $fresh->payment_status);
    }

    public function testMagicReadOfFalsyValues(): void
    {
        $item = MagicItem::items()->filter(['name.eq' => 'm1'])->one();
        $item->price = 0;
        $item->payment_status = '';
        $item->save();

        $fresh = MagicItem::items()->filter(['name.eq' => 'm1'])->one();
        $this->assertSame(0, $fresh->price);
        $this->assertSame('', $fresh->payment_status);
    }

    public function testNullToZeroSaveOnMagicModel(): void
    {
        $item = MagicItem::items()->filter(['name.eq' => 'm1'])->one();
        $this->assertNull($item->price);

        $item->price = 0;
        $item->save();

        $fresh = MagicItem::items()->filter(['name.eq' => 'm1'])->one();
        $this->assertSame(0, $fresh->price);
    }

    public function testZeroToNullSaveOnMagicModel(): void
    {
        MagicItem::items()->filter(['name.eq' => 'm1'])->update(['price' => 5]);
        $item = MagicItem::items()->filter(['name.eq' => 'm1'])->one();
        $this->assertSame(5, $item->price);

        $item->price = null;
        $item->save();

        $fresh = MagicItem::items()->filter(['name.eq' => 'm1'])->one();
        $this->assertNull($fresh->price);
    }

    public function testUnchangedMagicSaveNoRewrite(): void
    {
        $item = MagicItem::items()->filter(['name.eq' => 'm1'])->one();

        $queriesBefore = QueryStack::get();
        $item->save();
        $queriesAfter = QueryStack::get();

        $newQueries = array_slice($queriesAfter, count($queriesBefore));
        foreach ($newQueries as $entry) {
            $this->assertStringNotContainsString('UPDATE', strtoupper($entry['query']));
        }
    }

    public function testEmptyPrevStateUpdateWarnsAndSkips(): void
    {
        // PHPUnit's expectWarning() is deprecated in 9.6 and removed in 10 —
        // capture the E_USER_WARNING with a handler instead.
        $warned = false;
        set_error_handler(function ($severity, $message) use (&$warned) {
            $warned = (strpos($message, 'empty previous state') !== false);
            return true;
        });
        MagicItem::items()->filter(['name.eq' => 'm1'])->update(['price' => 9], []);
        restore_error_handler();
        $this->assertTrue($warned);

        $fresh = MagicItem::items()->filter(['name.eq' => 'm1'])->one();
        $this->assertNull($fresh->price);
    }

    public function testCallStyleAccessorReturnsFalsyValue(): void
    {
        $item = MagicItem::items()->filter(['name.eq' => 'm1'])->one();
        $item->price = 0;
        $this->assertSame(0, $item->price());
    }
}
