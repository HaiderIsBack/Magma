<?php

namespace Magma\ORM\Migrations;

abstract class Migration
{
    abstract public function up(): void;

    abstract public function down(): void;
}