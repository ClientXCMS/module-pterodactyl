<?php

/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */

namespace App\Modules\Pterodactyl;

use App\Exceptions\ExternalApiException;
use App\Models\Provisioning\Server;
use RuntimeException;

class Http
{
    public const PER_PAGE = 100;

    private const MAX_PAGES = 500;

    public static function callApi(
        ?Server $server = null,
        string $endpoint = '',
        array $data = [],
        string $method = 'GET',
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
        if (! str_starts_with($ip, 'http') && ! str_starts_with($ip, 'https')) {
            $url = ($server->port == 443 ? 'https' : 'http').'://'.$url;
        }

        $url .= "/api/$type/".$endpoint;
        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer '.($type === 'application' ? $server->password : $server->username),
                'Content-Type' => 'application/json',
                'Accept' => 'application/vnd.pterodactyl.v1+json',
            ])->timeout(10)->send($method, $url, ['json' => $data]);

            return new PterodactylResponse($response, $server);
        } catch (\GuzzleHttp\Exception\ClientException $e) {

            $response = $e->getResponse();
            if (! $quit) {
                throw new RuntimeException($response);
            }
        }

        return new PterodactylResponse($response, $server);
    }

    // 100 per page is the most a 2.x panel accepts (422 above); MAX_PAGES bounds a panel that never stops paginating.
    public static function listAll(Server $server, string $endpoint, array $query = []): array
    {
        $items = [];
        $page = 1;
        do {
            $url = $endpoint.'?'.http_build_query($query + ['per_page' => self::PER_PAGE, 'page' => $page], '', '&', PHP_QUERY_RFC3986);
            $response = self::callApi($server, $url);
            $body = $response->toJson();
            if (! $response->successful() || ! isset($body->data) || ! is_array($body->data)) {
                throw new ExternalApiException("{$endpoint} : ".$response->status().' '.$response->formattedErrors());
            }
            array_push($items, ...$body->data);
            $lastPage = min((int) ($body->meta->pagination->total_pages ?? 1), self::MAX_PAGES);
        } while ($page++ < $lastPage);

        return $items;
    }
}
