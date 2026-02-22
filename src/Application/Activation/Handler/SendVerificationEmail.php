<?php

declare(strict_types=1);

namespace App\Application\Activation\Handler;

use App\Application\Activation\Command\SendVerificationEmailCommand;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendVerificationEmail
{
    public function __construct(
        private readonly MailerInterface $mailer
    ) {}

    public function __invoke(SendVerificationEmailCommand $command): void
    {
        $email = (new TemplatedEmail())
            ->from('noreply@giftcard.local')
            ->to($command->email)
            ->subject('Kod weryfikacyjny aktywacji karty podarunkowej')
            ->htmlTemplate('emails/verification_code.html.twig')
            ->context([
                'verification_code' => $command->verificationCode,
            ]);

        $this->mailer->send($email);
    }
}
