<?php

namespace App\Entity;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use ApiPlatform\Metadata\ApiProperty;
use App\Dto\RegisterUserDto;
use App\State\RegisterUserProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use DH\Auditor\User\UserInterface as AuditorUserInterface;
use DH\Auditor\User\UserProviderInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]

#[ApiResource(
normalizationContext: ['groups' => ['user:read']],
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['user:list']],
            security: 'is_granted("ROLE_ADMIN")'),
        new Get(
            normalizationContext: ['groups' => ['user:read']],
            security: 'is_granted("ROLE_USER")'),
        new Post(
            uriTemplate: '/register',
            input: RegisterUserDto::class,
            output: User::class,
            processor: RegisterUserProcessor::class,
            denormalizationContext: ['groups' => ['register:write']],
            normalizationContext: ['groups' => ['user:read']],
            validationContext: ['groups' => ['register:write']],
            security: 'is_granted("ROLE_ADMIN")'
        ),
        new Put(
            denormalizationContext: ['groups' => ['user:write']],
            normalizationContext: ['groups' => ['user:read']],
            securityPostDenormalize: "is_granted('ROLE_ADMIN')",
            validationContext: ['groups' => ['register:write']],
        ),
        new Delete(
            denormalizationContext: ['groups' => ['user:write']],
            normalizationContext: ['groups' => ['user:read']],
            securityPostDenormalize: "is_granted('ROLE_ADMIN')"
        ),
        new Patch(
            denormalizationContext: ['groups' => ['user:write']],
            normalizationContext: ['groups' => ['user:read']],
            securityPostDenormalize: "is_granted('ROLE_ADMIN') or (object.owner == user and previous_object.owner == user)",
            validationContext: ['groups' => ['register:write', 'user:write']],

            #securityPostDenormalize: "is_granted('ROLE_ADMIN') or (object.owner == user and previous_object.owner == user)"
        )
    ]
)]


class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['user:read','user:list'])]
    private ?Uuid $id;

    #[ORM\Column(length: 180)]
    #[Groups(['user:read','user:write','user:list'])]
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
        groups: ['register:write', 'user:write']
    )]
    private ?string $email = null;

    /**
     * @var string|null The given name of the person.
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user:read','user:write','user:list'])]   
    private ?string $givenName = null;

    /**
     * @var string|null The family name of the person.
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    private ?string $familyName = null;


    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    #[Groups(['user:read','user:write'])]
    private array $roles = [];


    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    #[Groups(['user:write'])]
    #[ApiProperty(readable: false, writable: true)]
    private ?string $password = null;

    /**
     * @var string|null The telephone number of the person.
     */
    #[ORM\Column( length: 50, unique: true, nullable: true)]
    #[Groups(['user:read','user:write','user:list'])]
    #[Assert\Unique(fields: ['telephone'], message: 'This telephone number is already in use.')]
    private ?string $telephone = null;

    /**
     * @var string|null The job title of the person.
     */
    #[ORM\Column( length: 255, nullable: true)]
    #[Groups(['user:read','user:write','user:list'])]
    private ?string $jobTitle = null;

    /**
     * @var string|null The department of the person.
     */
    #[ORM\Column( length: 255, nullable: true)]
    #[Groups(['user:read','user:write'])]
    private ?string $department = null;

    #[ORM\Column]
    #[Groups(['user:read','user:write','user:list'])]
    private ?bool $active = null;

    #[ORM\OneToOne(inversedBy: 'user', cascade: ['persist', 'remove'])]
    private ?Customer $customer = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['user:read','user:list'])]
    private ?\DateTime $createdOn = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['user:read','user:write','user:list'])]
    private ?bool $localAccount = null;


    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user:read','user:write','user:list'])]
    private ?string $googleId = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['user:read','user:write','user:list'])]
    private ?string $avatar = null;

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function setGoogleId(?string $googleId): static
    {
        $this->googleId = $googleId;
        return $this;
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function setUsername(string $username): self
    {
        $this->givenName = $username;
        return $this;
    }


    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getGivenName(): ?string { return $this->givenName; }
    public function setGivenName(?string $givenName): self { $this->givenName = $givenName; return $this; }

    public function getFamilyName(): ?string { return $this->familyName; }
    public function setFamilyName(?string $familyName): self { $this->familyName = $familyName; return $this; }
    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(?string $telephone): self { $this->telephone = $telephone; return $this; }

    public function getJobTitle(): ?string { return $this->jobTitle; }
    public function setJobTitle(?string $jobTitle): self { $this->jobTitle = $jobTitle; return $this; }

    public function getDepartment(): ?string { return $this->department; }
    public function setDepartment(?string $department): self { $this->department = $department; return $this; }


    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
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

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    public function getCreatedOn(): ?\DateTime
    {
        return $this->createdOn;
    }

    public function setCreatedOn(?\DateTime $createdOn): static
    {
        $this->createdOn = $createdOn;

        return $this;
    }

    public function isLocalAccount(): ?bool
    {
        return $this->localAccount;
    }

    public function setLocalAccount(?bool $localAccount): static
    {
        $this->localAccount = $localAccount;

        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }



}
