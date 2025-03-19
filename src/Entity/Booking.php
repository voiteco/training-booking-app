<?php

namespace App\Entity;

use App\Repository\BookingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Booking
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['booking:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Training cannot be null')]
    #[Groups(['booking:read'])]
    private ?Training $training = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Full name cannot be empty')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Full name must be at least {{ limit }} characters',
        maxMessage: 'Full name cannot be longer than {{ limit }} characters'
    )]
    #[Groups(['booking:read'])]
    private ?string $fullName = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Email cannot be empty')]
    #[Assert\Email(message: "The email '{{ value }}' is not a valid email")]
    #[Groups(['booking:read'])]
    private ?string $email = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Phone number cannot be empty')]
    #[Assert\Length(
        min: 5,
        max: 50,
        minMessage: 'Phone number must be at least {{ limit }} characters',
        maxMessage: 'Phone number cannot be longer than {{ limit }} characters'
    )]
    #[Groups(['booking:read'])]
    private ?string $phone = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(
        choices: [self::STATUS_ACTIVE, self::STATUS_CANCELLED],
        message: 'Status must be one of: active, cancelled'
    )]
    #[Groups(['booking:read'])]
    private ?string $status = self::STATUS_ACTIVE;

    #[ORM\Column(length: 255)]
    private ?string $confirmationToken = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Device token cannot be empty')]
    #[Groups(['booking:read'])]
    private ?string $deviceToken = null;

    #[ORM\Column]
    #[Groups(['booking:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['booking:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();

        // Generate confirmation token if not already set
        if (!$this->confirmationToken) {
            $this->confirmationToken = bin2hex(random_bytes(16));
        }
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTraining(): ?Training
    {
        return $this->training;
    }

    public function setTraining(?Training $training): static
    {
        $this->training = $training;

        return $this;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = $fullName;

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

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getConfirmationToken(): ?string
    {
        return $this->confirmationToken;
    }

    public function setConfirmationToken(string $confirmationToken): static
    {
        $this->confirmationToken = $confirmationToken;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
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
