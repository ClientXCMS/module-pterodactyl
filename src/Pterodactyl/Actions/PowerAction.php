<?php

namespace App\Pterodactyl\Actions;

use App\Admin\DatabaseAdminAuth;
use App\Shop\Services\ServiceService;
use App\Pterodactyl\Http;
use ClientX\Actions\Action;
use ClientX\Auth;
use ClientX\Session\FlashService;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface;

class PowerAction extends Action
{

    private ServiceService $service;
    private bool $inAdmin;
    private string $certif;

    public function __construct(ServiceService $service, Auth $auth, DatabaseAdminAuth $admin, FlashService $flash, string $certif)
    {
        $this->service = $service;
        $this->auth    = $auth;
        $this->flash = $flash;
        $this->certif = $certif;
        $this->inAdmin = $admin->getUser() != null;
    }

    public function __invoke(ServerRequestInterface $request)
    {
        $id = $request->getAttribute("id");
        $action = $request->getAttribute("power");
        $actions = ['stop', 'start', 'restart', 'kill'];
        if ($this->inAdmin) {
            $actions[] = "suspend";
            $actions[] = "unsuspend";
        }
        if (in_array($action, $actions) === false) {
            return new Response(404);
        }
        if ($this->inAdmin === false) {
            if ($service = $this->service->findService($id, $this->getUserId())) {
                if ($service === null) {
                    return new Response(404);
                }
                $service->server->setCertificate($this->certif);
                $serverResult = Http::callApi($service->server, 'servers/external/' . $service->getId());
                if (!$serverResult->successful()) {
                    return new Response(404);
                }
                $attributes = $serverResult->data()->attributes;
                $response = Http::callApi($service->server, 'servers/' . $attributes->identifier . "/power", ['signal' => $action], 'POST', true, 'client');
                if ($response->successful()) {
                    $this->success('Done!');
                } else {
                    $this->error('Error!');
                }
                return $this->back($request);
            }
        }
        
        $service = $this->service->findService($id);
            if ($service === null) {
                return new Response(404);
            }
            $service->server->setCertificate($this->certif);
            $serverResult = Http::callApi($service->server, 'servers/external/' . $service->getId());
            if (!$serverResult->successful()) {
                return new Response(404);
            }
            $attributes = $serverResult->data()->attributes;
            $response = Http::callApi($service->server, 'servers/' . $attributes->identifier . "/power", ['signal' => $action], 'POST', true, 'client');

            if ($response->successful()) {
                $this->success('Done!');
            } else {
                $this->error('Error!');
            }
            return $this->back($request);
    }
}
