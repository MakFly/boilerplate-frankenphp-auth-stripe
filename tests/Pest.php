<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

use Tests\TestCase;
use Tests\FeatureTestCase;

uses(TestCase::class)->in('Unit');
uses(FeatureTestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

expect()->extend('toBeValidEntity', function () {
    $validator = static::getContainer()->get('validator');
    $errors = $validator->validate($this->value);
    
    expect($errors)->toHaveCount(0);
    
    return $this;
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

function something()
{
    // ..
}

function createAuthenticatedClient($user = null)
{
    $client = test()->getClient();
    
    if ($user) {
        $client->loginUser($user);
    }
    
    return $client;
}
