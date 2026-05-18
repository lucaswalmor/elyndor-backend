<?php

namespace App\Providers;

use App\Events\MatchFinished;
use App\Listeners\ApplyMatchProgressionRewards;
use App\Listeners\ApplyPlayerMatchStats;
use App\Listeners\ApplyRankedMatchOutcome;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $expireMin = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        ResetPassword::toMailUsing(function (object $notifiable, string $token) use ($expireMin) {
            $base = rtrim((string) config('app.frontend_url'), '/');
            $url = $base.'/reset-password?token='.urlencode($token)
                .'&email='.urlencode($notifiable->getEmailForPasswordReset());

            return (new MailMessage)
                ->subject('Redefinição de senha — Elyndor')
                ->line('Recebemos um pedido para redefinir a senha da sua conta.')
                ->action('Definir nova senha', $url)
                ->line("Este link expira em {$expireMin} minutos.")
                ->line('Se não foi você, pode ignorar este e-mail.');
        });

        Broadcast::routes(['middleware' => ['auth:sanctum']]);
        require base_path('routes/channels.php');

        Event::listen(MatchFinished::class, ApplyMatchProgressionRewards::class);
        Event::listen(MatchFinished::class, ApplyRankedMatchOutcome::class);
        Event::listen(MatchFinished::class, ApplyPlayerMatchStats::class);
    }
}
