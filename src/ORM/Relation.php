<?php

namespace Magma\ORM;

use Magma\ORM\Model;
use Magma\ORM\QueryBuilder;

final class Relation
{
    public function __construct(private QueryBuilder $query, private bool $many) {}

    public function getResults(): Model|array|null
    {
        return $this->many ? $this->query->get() : $this->query->first();
    }

    public function __call(string $method, array $args): mixed
    {
        $result = $this->query->$method(...$args);
        return $result instanceof QueryBuilder ? $this : $result;
    }
}