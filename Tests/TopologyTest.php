<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Q\Orm\Migration\Topology;

class FakeTable
{
    public string $name;
    public array $foreignKeys = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function addDependency(string $targetTable)
    {
        $fk = new \stdClass();
        $fk->refTable = $targetTable;
        $this->foreignKeys[] = $fk;
    }
}

class TopologyTest extends TestCase
{
    public function testDeepDependencyChain()
    {
        // Chain: A <- B <- C <- D <- E
        // E depends on D, D depends on C, etc.
        // Creation Order MUST be: A, B, C, D, E

        $a = new FakeTable('A');

        $b = new FakeTable('B');
        $b->addDependency('A');

        $c = new FakeTable('C');
        $c->addDependency('B');

        $d = new FakeTable('D');
        $d->addDependency('C');

        $e = new FakeTable('E');
        $e->addDependency('D');

        // Worst case input order: Reverse of dependencies
        $tables = [$e, $d, $c, $b, $a];

        $sorted = Topology::sortTablesToCreate($tables);

        $names = array_map(function ($t) {
            return $t->name; }, $sorted);

        // Find indices
        $idxA = array_search('A', $names);
        $idxB = array_search('B', $names);
        $idxC = array_search('C', $names);
        $idxD = array_search('D', $names);
        $idxE = array_search('E', $names);

        $this->assertLessThan($idxB, $idxA, 'A should be before B');
        $this->assertLessThan($idxC, $idxB, 'B should be before C');
        $this->assertLessThan($idxD, $idxC, 'C should be before D');
        $this->assertLessThan($idxE, $idxD, 'D should be before E');
    }

    public function testComplexGraph()
    {
        // A complicated graph
        // A depends on nothing
        // B depends on A
        // C depends on A
        // D depends on B and C

        $a = new FakeTable('A');

        $b = new FakeTable('B');
        $b->addDependency('A');

        $c = new FakeTable('C');
        $c->addDependency('A');

        $d = new FakeTable('D');
        $d->addDependency('B');
        $d->addDependency('C');

        $tables = [$d, $c, $b, $a];

        $sorted = Topology::sortTablesToCreate($tables);
        $names = array_map(function ($t) {
            return $t->name; }, $sorted);

        $idxA = array_search('A', $names);
        $idxB = array_search('B', $names);
        $idxC = array_search('C', $names);
        $idxD = array_search('D', $names);

        $this->assertLessThan($idxB, $idxA);
        $this->assertLessThan($idxC, $idxA);
        $this->assertLessThan($idxD, $idxB);
        $this->assertLessThan($idxD, $idxC);
    }
}
