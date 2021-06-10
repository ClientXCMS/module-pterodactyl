<?php
namespace App\Pterodactyl;

use App\Admin\Entity\Server;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use RuntimeException;

class Http
{
    
    public static function callApi(
        Server $server,
        string $endpoint,
        array $data = [],
        string $method = "GET",
        bool $quit = true,
        string $type = 'application'
    ) {
        $ip = $server->getIpaddress();
        $url = $ip;

        if (!str_starts_with('https', $ip) && !str_starts_with('http', $ip)) {
            $url = ($server->isSecure() ? 'https' : 'http' ). '://' . $url;
        }

        $url .= "/api/$type/" . $endpoint;
        
        try {
            $request = new Request($method, $url, [
                'Authorization' => 'Bearer ' .( $type === 'application' ? $server->getPassword() : $server->getUsername()),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/vnd.pterodactyl.v1+json',
            ], json_encode($data));
            $response = (new Client())->send($request);
        
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            
            $response = $e->getResponse();
            if (!$quit) {
                throw new RuntimeException($response);
            }
        }
        $response = new PterodactylResponse($response);
        if (!$response->successful()) {
            if (!$quit) {
                throw new RuntimeException();
            }
        }
        return $response;
    }
}
