<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

class CognitoResetPasswordMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public ?string $code,
        public string $emailLocale
    ) {}

    public function build(): CognitoResetPasswordMail
    {
        // Set the locale for this email
        App::setLocale($this->emailLocale);

        /** @var string $fromAddress */
        $fromAddress = config('mail.from.address');

        /** @var string $fromName */
        $fromName = config('mail.from.name');

        return $this->from($fromAddress, $fromName)
            ->subject(__('emails.cognito.reset_password.subject'))
            ->view('emails.cognito.reset-password')
            ->with([
                'code' => $this->code,
                'locale' => $this->emailLocale,
            ]);
    }
}
