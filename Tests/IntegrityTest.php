<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Q\Orm\Integrity;
use Q\Orm\SetUp;

class IntegrityTest extends TestCase
{
    private $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'qorm_test_');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    private function runCheck(string $code): string
    {
        $fullCode = <<<PHP
<?php
require_once 'vendor/autoload.php';
use Q\Orm\Model;
use Q\Orm\Field;
use Q\Orm\Integrity;
use Q\Orm\SetUp;

SetUp::\$engine = SetUp::SQLITE;

$code

Integrity::refuseAmbiguousModels();
echo "CHECK_PASSED";
PHP;
        file_put_contents($this->tempFile, $fullCode);
        return shell_exec("php " . $this->tempFile . " 2>&1");
    }

    public function testRejectsDuplicateNames()
    {
        $code = <<<PHP
class Model1 extends Model {
    public \$name;
    public static function schema(): array { return ['name' => Field::CharField()]; }
}
class Model2 extends Model {
    public \$name;
    public static function schema(): array { return ['name' => Field::CharField()]; }
}
PHP;
        $output = $this->runCheck($code);
        $this->assertStringContainsString('have exactly same attributes', $output);
        $this->assertStringNotContainsString('CHECK_PASSED', $output);
    }

    public function testRejectsDuplicateSignatures()
    {
        $code = <<<PHP
class ModelA extends Model {
    public \$f1;
    public static function schema(): array { return ['f1' => Field::CharField()]; }
}
class ModelB extends Model {
    public \$f2;
    public static function schema(): array { return ['f2' => Field::CharField()]; }
}
PHP;
        $output = $this->runCheck($code);
        $this->assertStringContainsString('have exactly same type signatures', $output);
        $this->assertStringNotContainsString('CHECK_PASSED', $output);
    }

    public function testAcceptsDifferentModels()
    {
        $code = <<<PHP
class ModelX extends Model {
    public \$name;
    public static function schema(): array { return ['name' => Field::CharField()]; }
}
class ModelY extends Model {
    public \$age;
    public static function schema(): array { return ['age' => Field::IntegerField()]; }
}
PHP;
        $output = $this->runCheck($code);
        $this->assertStringContainsString('CHECK_PASSED', $output);
    }

    public function testDifferentiatesByNullability()
    {
        $code = <<<PHP
class ModelNull extends Model {
    public \$f1;
    public static function schema(): array { return ['f1' => Field::CharField(function(\$c){ \$c->null = true; })]; }
}
class ModelNotNull extends Model {
    public \$f2;
    public static function schema(): array { return ['f2' => Field::CharField(function(\$c){ \$c->null = false; })]; }
}
PHP;
        $output = $this->runCheck($code);
        $this->assertStringContainsString('CHECK_PASSED', $output);
    }

    public function testDifferentiatesByForeignKeyTarget()
    {
        // Two junction-style models with same structure but different FK targets
        // should be accepted because their FK targets differ
        // Note: Each helper model must have a UNIQUE signature to avoid triggering the check
        $code = <<<PHP
use Q\Orm\Migration\Column;
use Q\Orm\Migration\ForeignKey;
use Q\Orm\Migration\Index;

class UserModel extends Model {
    public static function schema(): array { 
        return [
            'name' => Field::CharField(function(\$c){ \$c->size = 100; }),
            'email' => Field::CharField(function(\$c){ \$c->size = 200; }),
        ]; 
    }
}
class RoleModel extends Model {
    public static function schema(): array { 
        return [
            'title' => Field::CharField(function(\$c){ \$c->size = 50; }),
        ]; 
    }
}
class PostModel extends Model {
    public static function schema(): array { 
        return [
            'body' => Field::TextField(),
        ]; 
    }
}
class TagModel extends Model {
    public static function schema(): array { 
        return [
            'label' => Field::CharField(function(\$c){ \$c->size = 30; }),
            'color' => Field::CharField(function(\$c){ \$c->size = 7; }),
        ]; 
    }
}

class UserRole extends Model {
    public \$user;
    public \$role;
    public static function schema(): array {
        return [
            'user' => Field::ManyToOneField(UserModel::class, function(Column \$c){ \$c->null = false; }, Index::INDEX, ForeignKey::CASCADE),
            'role' => Field::ManyToOneField(RoleModel::class, function(Column \$c){ \$c->null = false; }, Index::INDEX, ForeignKey::CASCADE),
        ];
    }
}
class PostTag extends Model {
    public \$post;
    public \$tag;
    public static function schema(): array {
        return [
            'post' => Field::ManyToOneField(PostModel::class, function(Column \$c){ \$c->null = false; }, Index::INDEX, ForeignKey::CASCADE),
            'tag' => Field::ManyToOneField(TagModel::class, function(Column \$c){ \$c->null = false; }, Index::INDEX, ForeignKey::CASCADE),
        ];
    }
}
PHP;
        $output = $this->runCheck($code);
        $this->assertStringContainsString('CHECK_PASSED', $output);
    }
}
