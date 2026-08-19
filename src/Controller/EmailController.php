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
use App\Service\EmailService;
use App\Service\OrderEmailService;

final class EmailController extends AbstractController
{
    #[Route('/sendmail', name: 'index', methods: ['POST'])]
    public function index(#[CurrentUser] ?User $user, TokenStorageInterface $tokenStorage, Request $request, EmailService $emailService): Response
    {
                
        $email = $request->getPayload()->get('email');
        $message = $request->getPayload()->get('message');
        $name = $request->getPayload()->get('name');
        $contactNumber = $request->getPayload()->get('phone');
        $subject = $request->getPayload()->get('subject');
        $guests = $request->getPayload()->get('guests');
        $time = $request->getPayload()->get('time');
        $date = $request->getPayload()->get('date');


        $error = null;

        if(!$email){
            $error = "missing email";
        }

        if(!$message){
            $error = "missing message";
        }

        if(!$name){
            $error = "missing name";
        }

        if(!$contactNumber){
            $error = "missing contactNumber";
        }

        if(!$subject){
            $error = "missing subject";
        }



        if($error){
            return $this->json(['result'  => $error]);
        }

        // $date="2025-07-30";
        // $time="2:13pm";
        $res=1;

        $body = 
        "<ul>
            <li>Name: $name</li>
            <li>Email: $email</li>
            <li>Contact number: $contactNumber</li>
            <li>Subject: $subject</li>
            <li>Message: $message</li>
        </ul>";


        $response=1;

        try{
            $emailService->sendMail($email,$subject, $body, $date,$time);

        }
        catch(TransportExceptionInterface $e)
        {
            if($e)
            {
                $response = $e;
            }
        }
        return $this->json([
            'result'  => $response,
        ]);

    }

    /**
     * @Route("/place-order", name="place_order")
     */
    public function placeOrder(OrderEmailService $orderEmailService): Response
    {
        // Process the order...
        $customerEmail = 'customer@example.com';
        $customerName = 'John Doe';
        $orderDetails = '<p>Your order details in HTML format</p>';
        
        // Send confirmation email
        $orderEmailService->sendOrderConfirmation($customerEmail, $customerName, $orderDetails);
        
        return new Response('Order placed successfully!');
    }


}