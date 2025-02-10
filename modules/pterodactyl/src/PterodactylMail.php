<?php
/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */
namespace App\Modules\Pterodactyl;

use App\Models\Admin\EmailTemplate;
use App\Modules\Pterodactyl\DTO\PterodactylAccountDTO;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class PterodactylMail extends Notification
{
    use Queueable, SerializesModels;

    private string $ip;
    private string $username;
    private string $password;
    private string $panel_url;

    public function __construct(string $ip, PterodactylAccountDTO $accountDTO, string $panel_url)
    {
        $this->ip = $ip;
        $this->username = $accountDTO->username;
        $this->password = $accountDTO->password ?? _('global.already_set');
        $this->panel_url = $panel_url;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $context = [
            'ip' => $this->ip,
            'username' => $this->username,
            'password' => $this->password,
            'panel_url' => $this->panel_url,
            'can_reset_password' => $this->password !== _('global.already_set'),
            'reset_password_url' => $this->panel_url . "/auth/password",
        ];
        return EmailTemplate::getMailMessage("pterodactyl", $this->panel_url, $context, $notifiable);
    }
}
