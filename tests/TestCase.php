<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Les tests ne dépendent pas de la compilation des styles (npm run build) ; la recette navigateur la vérifie.
        $this->withoutVite();
    }
}
