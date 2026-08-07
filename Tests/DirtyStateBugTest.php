<?php

namespace Tests;

use Tests\Models\NullZeroModel;
use Tests\Models\PeculiarUser;
use Q\Orm\Connection;
use Q\Orm\QueryStack;

class DirtyStateBugTest extends QormTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $pdo = Connection::getInstance();
        $pdo->exec("DROP TABLE IF EXISTS null_zero_model");
        \Tests\Helpers\TestUtil::createTableFromModel(NullZeroModel::class);
        $pdo->exec("DROP TABLE IF EXISTS peculiar_user");
        \Tests\Helpers\TestUtil::createTableFromModel(PeculiarUser::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Connection::getInstance()->exec("DELETE FROM null_zero_model");
        Connection::getInstance()->exec("DELETE FROM peculiar_user");
    }

    public function testNullToZeroUpdate(): void
    {
        NullZeroModel::items()->create(['name' => 'n1']);
        $record = NullZeroModel::items()->filter(['name.eq' => 'n1'])->one();
        $this->assertNull($record->price);

        $record->price = 0;
        $record->save();

        $reloaded = NullZeroModel::items()->filter(['name.eq' => 'n1'])->one();
        $this->assertSame(0, $reloaded->price);
    }

    public function testNullToFloatZeroUpdate(): void
    {
        NullZeroModel::items()->create(['name' => 'n2']);
        $record = NullZeroModel::items()->filter(['name.eq' => 'n2'])->one();

        $record->price = 0.0;
        $record->save();

        $reloaded = NullZeroModel::items()->filter(['name.eq' => 'n2'])->one();
        // SQLite normalizes 0.0 to INTEGER under NUMERIC affinity; the
        // value persists correctly (0.0 == 0 for a DECIMAL column)
        $this->assertEquals(0.0, $reloaded->price);
    }

    public function testNullToFalseUpdate(): void
    {
        NullZeroModel::items()->create(['name' => 'n3']);
        $record = NullZeroModel::items()->filter(['name.eq' => 'n3'])->one();
        $this->assertNull($record->active);

        $record->active = false;
        $record->save();

        $reloaded = NullZeroModel::items()->filter(['name.eq' => 'n3'])->one();
        // SQLite stores booleans as 0/1 integers — PDO returns int, not bool
        $this->assertSame(0, $reloaded->active);
    }

    public function testEmptyStringToZeroUpdate(): void
    {
        NullZeroModel::items()->create(['name' => 'n4', 'price' => '']);
        $record = NullZeroModel::items()->filter(['name.eq' => 'n4'])->one();
        $this->assertSame('', $record->price);

        $record->price = 0;
        $record->save();

        $reloaded = NullZeroModel::items()->filter(['name.eq' => 'n4'])->one();
        $this->assertSame(0, $reloaded->price);
    }

    public function testZeroRemainsZeroNoChange(): void
    {
        NullZeroModel::items()->create(['name' => 'n5', 'price' => 0]);
        $record = NullZeroModel::items()->filter(['name.eq' => 'n5'])->one();

        $record->price = 0;
        $result = $record->save();

        $reloaded = NullZeroModel::items()->filter(['name.eq' => 'n5'])->one();
        $this->assertSame(0, $reloaded->price);
        $this->assertNotNull($result);
    }

    public function testPeculiarModelSaveWorks(): void
    {
        // Guard for the dynamic-PK change: save() on a custom-PK model must not throw
        PeculiarUser::items()->create(['name' => 'pec1']);
        $user = PeculiarUser::items()->filter(['name.eq' => 'pec1'])->one();

        $user->name = 'pec1-renamed';
        $user->save();

        $reloaded = PeculiarUser::items()->filter(['name.eq' => 'pec1-renamed'])->one();
        $this->assertSame('pec1-renamed', $reloaded->name);
    }

    public function testDeleteWithFalseFilter(): void
    {
        NullZeroModel::items()->create(['name' => 'd1', 'active' => false]);
        NullZeroModel::items()->create(['name' => 'd2', 'active' => false]);
        NullZeroModel::items()->create(['name' => 'd3', 'active' => true]);

        NullZeroModel::items()->filter(['active.eq' => false])->delete();

        $remaining = NullZeroModel::items()->count();
        $this->assertEquals(1, $remaining);
    }

    public function testCountWithFalseFilter(): void
    {
        NullZeroModel::items()->create(['name' => 'c1', 'active' => false]);
        NullZeroModel::items()->create(['name' => 'c2', 'active' => false]);
        NullZeroModel::items()->create(['name' => 'c3', 'active' => true]);

        $falseCount = NullZeroModel::items()->filter(['active.eq' => false])->count();
        $this->assertEquals(2, $falseCount);
    }

    public function testZeroToNullUpdate(): void
    {
        NullZeroModel::items()->create(['name' => 'z1', 'price' => 0]);
        $record = NullZeroModel::items()->filter(['name.eq' => 'z1'])->one();

        $record->price = null;
        $record->save();

        $reloaded = NullZeroModel::items()->filter(['name.eq' => 'z1'])->one();
        $this->assertNull($reloaded->price);
    }

    public function testFiveToNullUpdate(): void
    {
        NullZeroModel::items()->create(['name' => 'z2', 'price' => 5]);
        $record = NullZeroModel::items()->filter(['name.eq' => 'z2'])->one();

        $record->price = null;
        $record->save();

        $reloaded = NullZeroModel::items()->filter(['name.eq' => 'z2'])->one();
        $this->assertNull($reloaded->price);
    }

    public function testFalseToNullUpdate(): void
    {
        NullZeroModel::items()->create(['name' => 'z3', 'active' => false]);
        $record = NullZeroModel::items()->filter(['name.eq' => 'z3'])->one();

        $record->active = null;
        $record->save();

        $reloaded = NullZeroModel::items()->filter(['name.eq' => 'z3'])->one();
        $this->assertNull($reloaded->active);
    }

    public function testNullToNullSaveNoChange(): void
    {
        NullZeroModel::items()->create(['name' => 'z4']);
        $record = NullZeroModel::items()->filter(['name.eq' => 'z4'])->one();
        $this->assertNull($record->price);

        $record->price = null;
        $result = $record->save();

        $reloaded = NullZeroModel::items()->filter(['name.eq' => 'z4'])->one();
        $this->assertNull($reloaded->price);
        $this->assertNotNull($result);
    }

    public function testUnchangedValuePersistsWithoutRewrite(): void
    {
        NullZeroModel::items()->create(['name' => 'z5', 'price' => 5]);
        $record = NullZeroModel::items()->filter(['name.eq' => 'z5'])->one();

        $queriesBefore = QueryStack::get();
        $record->save();
        $queriesAfter = QueryStack::get();

        $newQueries = array_slice($queriesAfter, count($queriesBefore));
        foreach ($newQueries as $entry) {
            $this->assertStringNotContainsString('UPDATE', strtoupper($entry['query']));
        }

        $reloaded = NullZeroModel::items()->filter(['name.eq' => 'z5'])->one();
        $this->assertSame(5, $reloaded->price);
    }

    public function testNullThenValueThenNullCycle(): void
    {
        NullZeroModel::items()->create(['name' => 'z6', 'price' => 5]);
        $record = NullZeroModel::items()->filter(['name.eq' => 'z6'])->one();

        $record->price = null;
        $record->save();
        $record->price = 7;
        $record->save();
        $record->price = null;
        $record->save();

        $reloaded = NullZeroModel::items()->filter(['name.eq' => 'z6'])->one();
        $this->assertNull($reloaded->price);
    }

    public function testNumericStringChangePersists(): void
    {
        // '00123' vs '123' are float-equal — a numeric coercion would drop
        // this legitimate text edit (zip codes, SKUs, invoice refs).
        NullZeroModel::items()->create(['name' => '00123']);
        $record = NullZeroModel::items()->filter(['name.eq' => '00123'])->one();

        $record->name = '123';
        $record->save();

        $reloaded = NullZeroModel::items()->filter(['name.eq' => '123'])->one();
        $this->assertSame('123', $reloaded->name);
    }

    public function testPkMutationThrows(): void
    {
        PeculiarUser::items()->create(['name' => 'pk1']);
        $user = PeculiarUser::items()->filter(['name.eq' => 'pk1'])->one();

        $user->peculiar = $user->peculiar + 1;
        $this->expectException(\Error::class);
        $user->save();
    }

    public function testPkSameValueDifferentTypeDoesNotThrow(): void
    {
        PeculiarUser::items()->create(['name' => 'pk2']);
        $user = PeculiarUser::items()->filter(['name.eq' => 'pk2'])->one();

        // Same value, different PHP type (MySQL hydrates pks as strings) —
        // not a mutation, must not throw.
        $user->peculiar = (string) $user->peculiar;
        $result = $user->save();

        $this->assertNotNull($result);
        $this->assertSame('pk2', PeculiarUser::items()->filter(['name.eq' => 'pk2'])->one()->name);
    }

    public function testFreshManualPkSaveWarnsAndReturnsNull(): void
    {
        $warned = false;
        set_error_handler(function ($s, $msg) use (&$warned) { $warned = (strpos($msg, 'empty previous state') !== false); return true; });
        $fresh = new NullZeroModel();
        $fresh->id = 999;
        $fresh->name = 'manual';
        $result = $fresh->save();
        restore_error_handler();

        $this->assertTrue($warned);
        $this->assertNull($result);
        $this->assertEquals(0, NullZeroModel::items()->filter(['id.eq' => 999])->count());
    }
}
