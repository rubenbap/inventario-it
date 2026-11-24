<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as Base;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPassword extends Base
{
    public function toMail($notifiable): MailMessage
    {
        $minutes = config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Nova password · '.config('app.name'))
            ->greeting('Olá '.$notifiable->first_name.',')
            ->line('Pediste para definir uma nova password no '.config('app.name').'.')
            ->action('Definir nova password', url(route('password.reset', ['token' => $this->token, 'email' => $notifiable->email], false)))
            ->line("O link é válido durante {$minutes} minutos e só pode ser usado uma vez.")
            ->line('Se não foste tu, ignora este e-mail: a tua password não muda.')
            ->salutation('—');
    }
}
