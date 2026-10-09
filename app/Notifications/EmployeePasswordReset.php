<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

class EmployeePasswordReset extends Notification
{
    use Queueable;

    private const COMPANY_LOGO_CID = 'fabellion-company-logo.png';

    public function __construct(public readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset your Fabellon Construction account password')
            ->view('emails.password-reset', [
                'url' => route('password.reset', [
                    'token' => $this->token,
                    'email' => $notifiable->getEmailForPasswordReset(),
                ]),
                'logoCid' => 'cid:'.self::COMPANY_LOGO_CID,
            ])
            ->withSymfonyMessage(function (Email $message): void {
                $message->embedFromPath(
                    public_path('image/company-logo.png'),
                    self::COMPANY_LOGO_CID,
                    'image/png',
                );
            });
    }
}
