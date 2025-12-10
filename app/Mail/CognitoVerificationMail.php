<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

class CognitoVerificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public ?string $code,
        public string $emailLocale
    ) {}

    public function build(): CognitoVerificationMail
    {
        // Set the locale for this email
        App::setLocale($this->emailLocale);

        /** @var string $fromAddress */
        $fromAddress = config('mail.from.address');

        /** @var string $fromName */
        $fromName = config('mail.from.name');

        return $this->from($fromAddress, $fromName)
            ->subject(__('emails.cognito.verification.subject'))
            ->view('emails.cognito.verification')
            ->with([
                'code' => $this->code,
                'locale' => $this->emailLocale,
            ]);
    }
}
