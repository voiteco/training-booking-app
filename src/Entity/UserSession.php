<?php

namespace App\Entity;

use App\Repository\UserSessionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserSessionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class UserSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Device token cannot be empty')]
    private ?string $deviceToken = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Full name must be at least {{ limit }} characters long',
        maxMessage: 'Full name cannot be longer than {{ limit }} characters'
    )]
    private ?string $fullName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email(
        message: "The email '{{ value }}' is not a valid email address"
    )]
    private ?string $email = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Regex(
        pattern: "/^\+?[0-9\s-]{7,15}$/",
        message: 'Phone number must be valid (7-15 digits, may include +, spaces or hyphens)'
    )]
    private ?string $phone = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: 'Last visit date is required')]
    private ?\DateTimeInterface $lastVisit = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Created date is required')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->lastVisit = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDeviceToken(): ?string
    {
        return $this->deviceToken;
    }

    public function setDeviceToken(string $deviceToken): static
    {
        $this->deviceToken = $deviceToken;

        return $this;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(?string $fullName): static
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getLastVisit(): ?\DateTimeInterface
    {
        return $this->lastVisit;
    }

    public function setLastVisit(\DateTimeInterface $lastVisit): static
    {
        $this->lastVisit = $lastVisit;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
