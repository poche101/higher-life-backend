<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/** The "reset your password" email: branded, with a link to our own reset page. */
class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        $name = trim((string) ($notifiable->first_name ?: $notifiable->name));

        return (new MailMessage)
            ->subject('Reset your Higher Life password')
            ->greeting($name !== '' ? "Hello {$name}," : 'Hello,')
            ->line('We received a request to reset the password for your Higher Life account.')
            ->action('Choose a new password', $url)
            ->line("This link works for {$minutes} minutes and can be used once.")
            ->line('If you did not ask for this, you can safely ignore this email. Your password will stay as it is.')
            ->salutation('With joy, the Higher Life team');
    }
}
