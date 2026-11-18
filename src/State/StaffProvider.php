<?php
// src/State/StaffProvider.php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\StaffResource;
use App\Entity\User;
use App\Repository\UserRepository;

class StaffProvider implements ProviderInterface
{
    public function __construct(private UserRepository $users) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $qb = $this->users->createQueryBuilder('u')
            ->orderBy('u.givenName', 'ASC');

        $all = $qb->getQuery()->getResult();

        $staff = array_filter($all, function (User $u) {
            $roles = $u->getRoles();
            return in_array('ROLE_ADMIN', $roles, true)
                || in_array('ROLE_STAFF', $roles, true);
        });

        return array_map(function (User $u) {
            $r = new StaffResource();
            $r->id = (string) $u->getId();
            $r->email = $u->getEmail();
            $r->givenName = $u->getGivenName();
            $r->familyName = $u->getFamilyName();
            $r->roles = array_values($u->getRoles());
            return $r;
        }, $staff);
    }
}