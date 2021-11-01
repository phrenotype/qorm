<?php

namespace Q\Orm\Traits;

use Q\Orm\Handler;


trait CanBeASet
{

    /*
    public function union(Handler $h)
    {
        $h->distinct();
        $this->__set_operations__[] = ['union', $h];
        return $this;
    }

    public function intersect(Handler $h)
    {
        $h->distinct();
        $this->__set_operations__[] = ['intersect', $h];
        return $this;
    }

    public function except(Handler $h)
    {
        $h->distinct();
        $this->__set_operations__[] = ['except', $h];
        return $this;
    }
    */
}
