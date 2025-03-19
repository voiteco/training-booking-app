<?php

namespace App\Service;

use App\Entity\Booking;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private string $mailFrom,
    ) {
    }

    public function sendBookingConfirmation(Booking $booking): void
    {
        $training = $booking->getTraining();

        $subject = "Подтверждение записи на тренировку: {$training->getTitle()}";

        $confirmationUrl = $this->urlGenerator->generate(
            'booking_confirmation',
            ['token' => $booking->getConfirmationToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $cancelUrl = $this->urlGenerator->generate(
            'booking_cancel',
            ['token' => $booking->getConfirmationToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $body = "
            <h2>Подтверждение записи на тренировку</h2>
            <p>Здравствуйте, {$booking->getFullName()}!</p>
            <p>Вы записались на тренировку:</p>
            <ul>
                <li><strong>Название:</strong> {$training->getTitle()}</li>
                <li><strong>Дата:</strong> {$training->getDate()->format('d.m.Y')}</li>
                <li><strong>Время:</strong> {$training->getTime()->format('H:i')}</li>
                <li><strong>Стоимость:</strong> {$training->getPrice()} руб.</li>
            </ul>
            <p>
                <a href='{$confirmationUrl}'>Подтвердить запись</a> | 
                <a href='{$cancelUrl}'>Отменить запись</a>
            </p>
            <p>Спасибо за выбор наших тренировок!</p>
        ";

        $email = (new Email())
            ->from($this->mailFrom)
            ->to($booking->getEmail())
            ->subject($subject)
            ->html($body);

        $this->mailer->send($email);
    }

    public function sendBookingCancellation(Booking $booking): void
    {
        $training = $booking->getTraining();

        $subject = "Отмена записи на тренировку: {$training->getTitle()}";

        $body = "
            <h2>Отмена записи на тренировку</h2>
            <p>Здравствуйте, {$booking->getFullName()}!</p>
            <p>Ваша запись на тренировку была отменена:</p>
            <ul>
                <li><strong>Название:</strong> {$training->getTitle()}</li>
                <li><strong>Дата:</strong> {$training->getDate()->format('d.m.Y')}</li>
                <li><strong>Время:</strong> {$training->getTime()->format('H:i')}</li>
            </ul>
            <p>Вы можете записаться на другие доступные тренировки на нашем сайте.</p>
            <p>Спасибо за понимание!</p>
        ";

        $email = (new Email())
            ->from($this->mailFrom)
            ->to($booking->getEmail())
            ->subject($subject)
            ->html($body);

        $this->mailer->send($email);
    }

    public function sendTrainingReminder(Booking $booking): void
    {
        $training = $booking->getTraining();

        $subject = "Напоминание о тренировке: {$training->getTitle()}";

        $body = "
            <h2>Напоминание о тренировке</h2>
            <p>Здравствуйте, {$booking->getFullName()}!</p>
            <p>Напоминаем вам о предстоящей тренировке:</p>
            <ul>
                <li><strong>Название:</strong> {$training->getTitle()}</li>
                <li><strong>Дата:</strong> {$training->getDate()->format('d.m.Y')}</li>
                <li><strong>Время:</strong> {$training->getTime()->format('H:i')}</li>
            </ul>
            <p>Не забудьте взять с собой необходимое снаряжение и приходите заранее.</p>
            <p>До встречи на тренировке!</p>
        ";

        $email = (new Email())
            ->from($this->mailFrom)
            ->to($booking->getEmail())
            ->subject($subject)
            ->html($body);

        $this->mailer->send($email);
    }
}
