<?php
// src/Dto/PasswordResetDto.php

namespace App\Dto;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\State\PasswordResetProcessor;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/password-reset',
            processor: PasswordResetProcessor::class,
            denormalizationContext: ['groups' => ['password_reset:write']],
            normalizationContext: ['groups' => ['password_reset:read']],
            validationContext: ['groups' => ['password_reset:write']]
        ),
    ]
)]
class PasswordResetDto
{
    #[Groups(['password_reset:write'])]
    #[Assert\NotBlank(message: "Token cannot be blank")]
    public ?string $token = null;

    #[Groups(['password_reset:write'])]
    #[Assert\NotBlank(message: "Password cannot be blank")]
    #[Assert\Length(
        min: 8,
        minMessage: "Password must be at least {{ limit }} characters long"
    )]
    public ?string $password = null;

    #[Groups(['password_reset:write'])]
    #[Assert\NotBlank(message: "Confirm password cannot be blank")]
    #[Assert\EqualTo(
        propertyPath: "password",
        message: "Passwords do not match"
    )]
    public ?string $confirmPassword = null;
}