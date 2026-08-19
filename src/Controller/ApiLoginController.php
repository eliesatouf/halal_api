<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\User;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class ApiLoginController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function login(#[CurrentUser] ?User $user, TokenStorageInterface $tokenStorage, Request $request): Response
    {
        
        //return $this->redirectToRoute('api_doc', [], Response::HTTP_MOVED_PERMANENTLY);

        if ($request->getMethod() === 'OPTIONS') {
        return new Response(null, 204, [
            'Access-Control-Allow-Origin' => 'https://testadmin.granderoyalerestaurante.com',
            'Access-Control-Allow-Methods' => 'POST, OPTIONS',
            'Access-Control-Allow-Headers' => '*'
            ]);
        }

        if (null === $user) {
            return $this->json([
                 'message' => 'missing credentials',
                 'token' => $tokenStorage,
             ], Response::HTTP_UNAUTHORIZED);
        }

        $token ="123";

        return $this->json([

            'user'  => $user->getUserIdentifier(),
            'token' => $tokenStorage,
          ]);

        $response = new JsonResponse(['data'=>'Welcome to your new controller!', 'user'=>$user]);
        return $response;
    }


}
