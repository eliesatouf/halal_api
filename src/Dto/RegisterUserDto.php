<?php
// src/Dto/RegisterUserDto.php
namespace App\Dto;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;


class RegisterUserDto
{
    #[Groups(['register:write'])]
    #[Assert\NotBlank]
    private ?string $givenName = null;

    #[Groups(['register:write'])]
    #[Assert\NotBlank]
    private ?string $password = null;

    
    #[Groups(['register:write'])]
    #[Assert\NotBlank(message: "Email cannot be blank")]
    #[Assert\Email(
        message: "The email '{{ value }}' is not valid.",
        mode: 'strict',
        groups: ['register:write', 'user:write']
    )]
    #[Assert\Length(
        min: 8,  // Increased from 7 to catch "a@s.m" (5 chars)
        max: 180,
        minMessage: "Email must be at least {{ limit }} characters long",
        maxMessage: "Email cannot be longer than {{ limit }} characters",
        groups: ['register:write']
    )]
    private ?string $email = null;

    #[Groups(['register:write'])]
    private array $roles = ['ROLE_USER'];


    #[Groups(['register:write'])]
    private ?bool $active = null;

    #[Groups(['register:write'])]
    private ?string $telephone = null;

    #[Groups(['register:write'])]
    private ?string $localAccount = null;


    // Getter and Setter for Username
    public function getGivenName(): string
    {
        return $this->givenName;
    }

    public function setGivenName(string $givenName): self
    {
        $this->givenName = $givenName;
        return $this;
    }

    // Getter and Setter for Password
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    // Getter and Setter for Email
    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }
    
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    private string $name;

    public function getName(): string
    {
        return $this->name;
    }

    public function getLocalAccount(): ?bool
    {
        return $this->localAccount;
    }

    public function setLocalAccount(bool $localAccount): static
    {
        $this->localAccount = $localAccount;

        return $this;
    }

}
