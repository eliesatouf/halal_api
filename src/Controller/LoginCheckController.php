<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\HttpFoundation\JsonResponse;



final class LoginCheckController extends AbstractController
{
    #[Route('/api/logincheck', name: 'api_logincheck', methods: ['POST'] )]
    public function index(#[CurrentUser] ?User $user,Request $request,EntityManagerInterface $entityManager,UserPasswordHasherInterface $passwordHasher): Response
    {
        return $this->json([
                    'message' => $user]);

    }
}