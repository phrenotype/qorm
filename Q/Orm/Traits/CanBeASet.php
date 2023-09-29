<?php

namespace Q\Orm\Traits;

use Q\Orm\Handler;

/**
 * Confers the ability to perform set operations on Handlers and Humans alike.
 */
trait CanBeASet
{

    /**
     * Validates a Handler for set operations.
     * 
     * @param Handler $h2
     * 
     * @return void
     */
    private function errorChecks(Handler $h2): void
    {
        if ($this === $h2) {
            throw new \Error("Do not store base handler in variables when doing set operations.");
        }
        if ($this->as() == null || $h2->as() == null) {
            throw new \Error(sprintf("All handlers in set operations must be aliased."));
        }
    }


    /**
     * Perform a union between two Handlers.
     * 
     * @param Handler $h The second Handler.
     * 
     * @return Handler Returns the Handler it was called on.
     */
    public function union(Handler $h): Handler
    {
        $this->errorChecks($h);
        //$this->distinct();
        $this->__set_operations__[] = ['union', $h];
        return $this;
    }

    /**
     * Perform an instersection between two handlers.
     * 
     * @param Handler $h The second Handler.
     * 
     * @return Handler Returns the Handler it was called on.
     */
    public function intersect(Handler $h): Handler
    {
        $this->errorChecks($h);
        $h->distinct();
        $this->__set_operations__[] = ['intersect', $h];
        return $this;
    }

    /**
     * Perform a difference between two handlers.
     * 
     * @param Handler $h The second Handler.
     * 
     * @return Handler Returns the Handler it was called on.
     */
    public function except(Handler $h): Handler
    {
        $this->errorChecks($h);
        $h->distinct();
        $this->__set_operations__[] = ['except', $h];
        return $this;
    }
}
