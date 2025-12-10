<?php

namespace App\Mail;

use App\Models\Financer;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Log;
use stdClass;

class WelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * User data
     *
     * @var object User object with email property
     */
    public object $user;

    /**
     * Invited user ID
     */
    public ?string $invitedUserId;

    /**
     * Email locale determined from financer or user
     */
    public string $emailLocale;

    /**
     * Create a new message instance.
     *
     * @param  stdClass|object  $user  User data
     * @param  string|null  $invitedUserId  Invited user ID
     */
    public function __construct(object $user, ?string $invitedUserId = null)
    {
        $this->user = $user;
        $this->invitedUserId = $invitedUserId;
        $this->emailLocale = $this->determineLocale();
    }

    /**
     * Determine the locale for the email based on user's language preference in financer context.
     * Priority: Pivot language (financer_user.language) > Financer's available_languages[0] > User's locale > Default locale
     */
    private function determineLocale(): string
    {
        $defaultLocale = 'fr-FR';

        // If invitedUserId is provided, load the User and get financer from invitation_metadata
        if (! in_array($this->invitedUserId, [null, '', '0'], true)) {
            $invitedUser = User::find($this->invitedUserId);

            if ($invitedUser instanceof User) {
                // Try to get financer from invitation_metadata
                $financerId = $invitedUser->invitation_metadata['financer_id'] ?? null;

                if (! in_array($financerId, [null, '', '0'], true)) {
                    $financer = Financer::find($financerId);

                    if ($financer instanceof Financer) {
                        // Try to get pivot language from user's relationship with this financer
                        $financerWithPivot = $invitedUser->financers()
                            ->where('financer_id', $financer->id)
                            ->first();

                        // Priority 1: Pivot language (user's preference for this financer)
                        if ($financerWithPivot && isset($financerWithPivot->pivot->language)) {
                            return $financerWithPivot->pivot->language;
                        }

                        // Priority 2: Financer's default language
                        $availableLanguages = $financer->available_languages;

                        if (is_array($availableLanguages) && count($availableLanguages) > 0) {
                            return $availableLanguages[0];
                        }
                    }
                }

                // Fallback: Try to get financer from user's financers relation
                $financer = $invitedUser->financers()->first();

                if ($financer instanceof Financer) {
                    // Priority 1: Pivot language
                    if (isset($financer->pivot->language)) {
                        return $financer->pivot->language;
                    }

                    // Priority 2: Financer's default language
                    $availableLanguages = $financer->available_languages;

                    if (is_array($availableLanguages) && count($availableLanguages) > 0) {
                        return $availableLanguages[0];
                    }
                }
            }
        }

        // If user is a User model instance, get first active financer's language
        if ($this->user instanceof User) {
            $activeFinancer = $this->user->financers()
                ->wherePivot('active', true)
                ->first();

            if ($activeFinancer instanceof Financer) {
                // Priority 1: Pivot language (user's preference for this financer)
                if (isset($activeFinancer->pivot->language)) {
                    return $activeFinancer->pivot->language;
                }

                // Priority 2: Financer's default language
                if (property_exists($activeFinancer, 'available_languages') && $activeFinancer->available_languages !== null && is_array($activeFinancer->available_languages) && count($activeFinancer->available_languages) > 0) {
                    return $activeFinancer->available_languages[0];
                }
            }
        }

        // Fallback to user's locale if available
        if (isset($this->user->locale) && is_string($this->user->locale)) {
            return $this->user->locale;
        }

        return $defaultLocale;
    }

    /**
     * Build the message.
     */
    public function build(): WelcomeEmail
    {
        /** @phpstan-ignore-next-line */
        Log::info('Sending welcome email to '.$this->user->email, [
            'invited_user_id' => $this->invitedUserId,
            'user' => $this->user,
            'locale' => $this->emailLocale,
        ]);

        // Set the locale for this email
        App::setLocale($this->emailLocale);

        /** @var string $frontBenefUrl */
        $frontBenefUrl = config('app.front_beneficiary_url');

        if (! in_array($this->invitedUserId, [null, '', '0'], true)) {
            $url = $frontBenefUrl.'/invited-user/'.$this->invitedUserId;
        } else {
            /** @var string $email */
            $email = $this->user->email ?? '';

            /** @var string $cognitoId */
            $cognitoId = $this->user->cognito_id ?? '';

            $url = $frontBenefUrl.'/first-login?email='.$email.'&cognito_id='.$cognitoId;
        }

        // @phpstan-ignore-next-line
        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject(__('emails.welcome.subject'))
            ->view('emails.welcome')
            ->with([
                'user' => $this->user,
                'url' => $url,
                'emailLocale' => $this->emailLocale,
            ]);
    }
}
