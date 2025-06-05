<?php

namespace App\Service;

use App\Entity\Booking;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private string $mailFrom,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Sends a booking confirmation email
     * 
     * @param Booking $booking The booking to send confirmation for
     * @return bool True if the email was sent successfully
     */
    public function sendBookingConfirmation(Booking $booking): bool
    {
        try {
            $training = $booking->getTraining();
            if (!$training) {
                $this->logError('Cannot send booking confirmation: Training not found for booking ID ' . $booking->getId());
                return false;
            }

            if (empty($booking->getEmail())) {
                $this->logError('Cannot send booking confirmation: Email address missing for booking ID ' . $booking->getId());
                return false;
            }

            $subject = "Booking Confirmation: {$training->getTitle()}";

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

            $body = $this->renderBookingConfirmationTemplate($booking, $training, $confirmationUrl, $cancelUrl);

            $email = (new Email())
                ->from($this->mailFrom)
                ->to($booking->getEmail())
                ->subject($subject)
                ->html($body);

            $this->mailer->send($email);
            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logError('Failed to send booking confirmation email: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            $this->logError('Error preparing booking confirmation email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sends a booking cancellation email
     * 
     * @param Booking $booking The booking that was cancelled
     * @return bool True if the email was sent successfully
     */
    public function sendBookingCancellation(Booking $booking): bool
    {
        try {
            $training = $booking->getTraining();
            if (!$training) {
                $this->logError('Cannot send booking cancellation: Training not found for booking ID ' . $booking->getId());
                return false;
            }

            if (empty($booking->getEmail())) {
                $this->logError('Cannot send booking cancellation: Email address missing for booking ID ' . $booking->getId());
                return false;
            }

            $subject = "Booking Cancellation: {$training->getTitle()}";

            $body = $this->renderBookingCancellationTemplate($booking, $training);

            $email = (new Email())
                ->from($this->mailFrom)
                ->to($booking->getEmail())
                ->subject($subject)
                ->html($body);

            $this->mailer->send($email);
            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logError('Failed to send booking cancellation email: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            $this->logError('Error preparing booking cancellation email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sends a training reminder email
     * 
     * @param Booking $booking The booking to send reminder for
     * @return bool True if the email was sent successfully
     */
    public function sendTrainingReminder(Booking $booking): bool
    {
        try {
            $training = $booking->getTraining();
            if (!$training) {
                $this->logError('Cannot send training reminder: Training not found for booking ID ' . $booking->getId());
                return false;
            }

            if (empty($booking->getEmail())) {
                $this->logError('Cannot send training reminder: Email address missing for booking ID ' . $booking->getId());
                return false;
            }

            $subject = "Training Reminder: {$training->getTitle()}";

            $body = $this->renderTrainingReminderTemplate($booking, $training);

            $email = (new Email())
                ->from($this->mailFrom)
                ->to($booking->getEmail())
                ->subject($subject)
                ->html($body);

            $this->mailer->send($email);
            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logError('Failed to send training reminder email: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            $this->logError('Error preparing training reminder email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Renders the booking confirmation email template
     */
    private function renderBookingConfirmationTemplate(Booking $booking, $training, string $confirmationUrl, string $cancelUrl): string
    {
        $dateFormatted = $training->getDate() ? $training->getDate()->format('d.m.Y') : 'N/A';
        $timeFormatted = $training->getTime() ? $training->getTime()->format('H:i') : 'N/A';
        $price = $training->getPrice() ?? 'N/A';

        return "
            <h2>Booking Confirmation</h2>
            <p>Hello, {$booking->getFullName()}!</p>
            <p>You have booked the following training:</p>
            <ul>
                <li><strong>Title:</strong> {$training->getTitle()}</li>
                <li><strong>Date:</strong> {$dateFormatted}</li>
                <li><strong>Time:</strong> {$timeFormatted}</li>
                <li><strong>Price:</strong> {$price}</li>
            </ul>
            <p>
                <a href='{$confirmationUrl}'>Confirm Booking</a> | 
                <a href='{$cancelUrl}'>Cancel Booking</a>
            </p>
            <p>Thank you for choosing our trainings!</p>
        ";
    }

    /**
     * Renders the booking cancellation email template
     */
    private function renderBookingCancellationTemplate(Booking $booking, $training): string
    {
        $dateFormatted = $training->getDate() ? $training->getDate()->format('d.m.Y') : 'N/A';
        $timeFormatted = $training->getTime() ? $training->getTime()->format('H:i') : 'N/A';

        return "
            <h2>Booking Cancellation</h2>
            <p>Hello, {$booking->getFullName()}!</p>
            <p>Your booking has been cancelled:</p>
            <ul>
                <li><strong>Title:</strong> {$training->getTitle()}</li>
                <li><strong>Date:</strong> {$dateFormatted}</li>
                <li><strong>Time:</strong> {$timeFormatted}</li>
            </ul>
            <p>You can book other available trainings on our website.</p>
            <p>Thank you for your understanding!</p>
        ";
    }

    /**
     * Renders the training reminder email template
     */
    private function renderTrainingReminderTemplate(Booking $booking, $training): string
    {
        $dateFormatted = $training->getDate() ? $training->getDate()->format('d.m.Y') : 'N/A';
        $timeFormatted = $training->getTime() ? $training->getTime()->format('H:i') : 'N/A';

        return "
            <h2>Training Reminder</h2>
            <p>Hello, {$booking->getFullName()}!</p>
            <p>This is a reminder about your upcoming training:</p>
            <ul>
                <li><strong>Title:</strong> {$training->getTitle()}</li>
                <li><strong>Date:</strong> {$dateFormatted}</li>
                <li><strong>Time:</strong> {$timeFormatted}</li>
            </ul>
            <p>Don't forget to bring necessary equipment and arrive early.</p>
            <p>See you at the training!</p>
        ";
    }

    /**
     * Logs an error message if a logger is available
     */
    private function logError(string $message): void
    {
        if ($this->logger) {
            $this->logger->error($message);
        }
    }
}
