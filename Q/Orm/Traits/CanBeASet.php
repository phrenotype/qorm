<?php

namespace Q\Orm\Traits;

use Q\Orm\Handler;


trait CanBeASet
{

    private function errorChecks($h2)
    {
        if ($this === $h2) {
            throw new \Error("Do not store base handler in variables when doing set operations.");
        }
        if ($this->as() == null || $h2->as() == null) {
            throw new \Error(sprintf("All handlers in set operations must be aliased."));
        }
    }

    public function union(Handler $h)
    {
        $this->errorChecks($h);
        //$this->distinct();
        $this->__set_operations__[] = ['union', $h];
        return $this;
    }

    public function intersect(Handler $h)
    {
        $this->errorChecks($h);
        $h->distinct();
        $this->__set_operations__[] = ['intersect', $h];
        return $this;
    }

    public function except(Handler $h)
    {
        $this->errorChecks($h);
        $h->distinct();
        $this->__set_operations__[] = ['except', $h];
        return $this;
    }
}
