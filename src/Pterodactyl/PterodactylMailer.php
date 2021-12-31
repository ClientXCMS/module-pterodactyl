<?php

namespace App\Pterodactyl;

use App\Admin\Entity\Server;
use App\Auth\User;
use App\Plesk\Entity\PleskAccount;
use App\Shop\Entity\Service;
use ClientX\Entity\EmailMessage;
use ClientX\Helpers\Str;
use ClientX\Notifications\Mailer\Support\DatabaseMailer;
use ClientX\Router;

class PterodactylMailer
{

    private Router $router;
    private DatabaseMailer $mailer;

    public function __construct(DatabaseMailer $mailer, Router $router)
    {
        $this->mailer = $mailer;
        $this->router = $router;
    }

    public function sendTo(User $user, Server $server, Service $service, string $password)
    {
        $url = $this->router->generateURIAbsolute('shop.services.panel', ['id' => $service->getId()]);
        $message = EmailMessage::forUser($user, "pterodactyl", [
            'user' => $user,
            'username' => Str::slugify($user->getName()) . $user->getId(),
            'password' => $password,
            'server' => $server,
            'service' => $service
        ], $url);
        return $this->mailer->send($message);
    }
}