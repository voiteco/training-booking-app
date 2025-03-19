<?php

namespace App\Tests\Service;

use App\Entity\Booking;
use App\Entity\Training;
use App\Service\EmailService;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EmailServiceTest extends KernelTestCase
{
    private MailerInterface&MockObject $mailer;
    private UrlGeneratorInterface&MockObject $urlGenerator;
    private EmailService $emailService;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $this->emailService = new EmailService(
            $this->mailer,
            $this->urlGenerator,
            'no-reply@example.com' // Sender email
        );
    }

    public function testSendBookingConfirmation(): void
    {
        // Create a training entity
        $training = new Training();
        $training->setTitle('Yoga Class');
        $training->setDate(new \DateTimeImmutable('2025-03-20'));
        $training->setTime(new \DateTimeImmutable('10:00:00'));

        $token = 'token123';

        // Create a booking entity
        $booking = new Booking();
        $booking->setFullName('John Doe');
        $booking->setEmail('john@example.com');
        $booking->setPhone('+123456789');
        $booking->setConfirmationToken($token);
        $booking->setTraining($training);

        $this->urlGenerator
            ->method('generate')
            ->willReturnCallback(function ($name, $parameters, $referenceType) use ($token) {
                return sprintf('http://example.com/%s', $token);
            });

        // Expect send method to be called once with an Email object
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                $this->assertEquals([new Address('john@example.com', '')], $email->getTo());
                $this->assertEquals([new Address('no-reply@example.com', '')], $email->getFrom());
                $this->assertStringContainsString('Yoga Class', $email->getHtmlBody());
                $this->assertStringContainsString('20.03.2025', $email->getHtmlBody());
                $this->assertStringContainsString('10:00', $email->getHtmlBody());
                $this->assertStringContainsString('John Doe', $email->getHtmlBody());
                $this->assertStringContainsString('token123', $email->getHtmlBody());

                return true;
            }));

        $this->emailService->sendBookingConfirmation($booking);
    }

    public function testSendBookingCancellation(): void
    {
        // Create a training entity
        $training = new Training();
        $training->setTitle('Pilates Class');
        $training->setDate(new \DateTimeImmutable('2025-03-22'));
        $training->setTime(new \DateTimeImmutable('15:00:00'));

        // Create a booking entity
        $booking = new Booking();
        $booking->setFullName('Jane Doe');
        $booking->setEmail('jane@example.com');
        $booking->setTraining($training);

        // Expect send method to be called once with an Email object
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                $this->assertEquals([new Address('jane@example.com', '')], $email->getTo());
                $this->assertEquals([new Address('no-reply@example.com', '')], $email->getFrom());
                $this->assertStringContainsString('Pilates Class', $email->getHtmlBody());
                $this->assertStringContainsString('22.03.2025', $email->getHtmlBody());
                $this->assertStringContainsString('15:00', $email->getHtmlBody());
                $this->assertStringContainsString('Jane Doe', $email->getHtmlBody());

                return true;
            }));

        $this->emailService->sendBookingCancellation($booking);
    }
}
