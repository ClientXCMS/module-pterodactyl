<?php
/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */
namespace App\Modules\Pterodactyl;

use App\Models\Provisioning\Server;
use RuntimeException;

class Http
{

    public static function callApi(
        ?Server $server = null,
        string $endpoint = '',
        array $data = [],
        string $method = "GET",
        bool $quit = true,
        string $type = 'application'
    ): PterodactylResponse {
        if ($server == null) {
            $server = Server::whereIn('type', ['pterodactyl', 'wisp'])->first();
            if ($server == null) {
                throw new RuntimeException('No Pterodactyl server found');
            }
        }
        $ip = $server->hostname;
        $url = $ip;
        if (!str_starts_with('https', $ip) && !str_starts_with('http', $ip)) {
            $url = ($server->port == 443 ? 'https' : 'http' ). '://' . $url;
        }

        $url .= "/api/$type/" . $endpoint;
        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' .( $type === 'application' ? $server->password : $server->username),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/vnd.pterodactyl.v1+json',
            ])->timeout(10)->send($method, $url,['json' => $data]);
            return new PterodactylResponse($response, $server);
        } catch (\GuzzleHttp\Exception\ClientException $e) {

            $response = $e->getResponse();
            if (!$quit) {
                throw new RuntimeException($response);
            }
        }
        return new PterodactylResponse($response, $server);
    }
}
