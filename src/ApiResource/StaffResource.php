<?php
// src/ApiResource/StaffResource.php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\State\StaffProvider;

#[ApiResource(
    shortName: 'Staff',
    operations: [
        new GetCollection(
            uriTemplate: '/staff',
            provider: StaffProvider::class,
            security: 'is_granted("ROLE_ADMIN") or is_granted("ROLE_STAFF")',
        ),
    ],
)]
class StaffResource
{
    public ?string $id = null;
    public ?string $email = null;
    public ?string $givenName = null;
    public ?string $familyName = null;
    public array $roles = [];
}