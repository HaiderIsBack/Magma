<?php

namespace Magma\Http;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]  
abstract class Route {
    public function __construct(
        public string $path,
        public string $method
    ) {}
}
