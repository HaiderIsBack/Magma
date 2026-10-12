<?php

namespace Magma\Http;

use Attribute;
use Magma\Http\Route;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]  
final class Get extends Route {
    public function __construct
    (
        public string $path
    ) {
        parent::__construct($path, 'GET');
    }
}
