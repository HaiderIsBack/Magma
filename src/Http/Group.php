<?php

namespace Magma\Http;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Group
{
    public function __construct(
        public string $prefix
    ) {}
}
