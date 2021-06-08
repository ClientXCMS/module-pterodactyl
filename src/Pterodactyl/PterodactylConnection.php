<?php
namespace App\Pterodactyl;

use App\Admin\Entity\Server;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use RuntimeException;

trait PterodactylConnection
{
    
    private function callApi(
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
            $response = (new Client())->request(
                $method,
                $url,
                [
                    RequestOptions::JSON => $data,
                    'headers' => [
                        'Authorization' => 'Bearer ' .( $type === 'application' ? $server->getPassword() : $server->getUsername()),
                        'Content-Type'  => 'application/json',
                        'Accept'        => 'application/vnd.pterodactyl.v1+json',
                    ], 'verify' => $server->getCertificate()
                ]
            );
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            
            $response = $e->getResponse();
            $value = sprintf("Pterodactyl-CLIENTXCMS : %s => %s", $e->getMessage(), $e->getTraceAsString());
            $this->logger->critical($value);
            if (!$quit) {
                throw new RuntimeException($response);
            }
        }
        $response = new PterodactylResponse($response);
        if (!$response->successful()) {
            $value = sprintf("Pterodactyl-CLIENTXCMS : %sd => %s", $response->status(), $response->toJson());
            $this->logger->critical($value);
            if (!$quit) {
                throw new RuntimeException();
            }
        }
        return $response;
    }
}
