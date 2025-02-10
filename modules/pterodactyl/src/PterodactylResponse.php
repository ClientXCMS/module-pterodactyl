<?php
/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */
namespace App\Modules\Pterodactyl;

use App\Models\Provisioning\Server;
use Illuminate\Http\Client\Response;

class PterodactylResponse
{
    private Response $response;
    private Server $server;

    public function __construct(Response $response, Server $server)
    {
        $this->response = $response;
        $this->server = $server;
    }

    public function toArray()
    {
        return [
            'data' => json_decode($this->response->body()),
            'status' => $this->status(),
            'successful' => $this->successful(),
        ];
    }

    public function toJson()
    {
        return json_decode($this->response->body());
    }

    public function successful(bool $success = false)
    {
        if ($this->server->type === 'wisp' && $success) {
            return $this->response->status() >= 200 && $this->response->status() < 300 && property_exists($this->toJson(), 'attributes');
        }
        return $this->response->status() >= 200 && $this->response->status() < 300;
    }

    public function status()
    {
        return $this->response->status();
    }

    public function getResponse(): Response
    {
        return $this->response;
    }


    public function formattedErrors()
    {
        return collect($this->toJson()->errors)->map(function ($error) {
            return $error->detail ?? $error->code;
        })->implode(', ');
    }

    public function isExternalIdAlreadyUsed(): bool
    {
        $errors = ['The external id field has already been taken', 'The external id has already been taken.'];
        $error = $this->toJson()->errors[0];
        return $this->response->status() === 422 && in_array($error->detail ?? $error->code, $errors);
    }
}
