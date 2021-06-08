<?php
namespace App\Pterodactyl;

use ClientX\Database\Hydrator;
use ClientX\Support\Arrayable;
use ClientX\Support\Jsonable;
use GuzzleHttp\Psr7\Response;

class PterodactylResponse implements Jsonable, Arrayable
{

    private Response $response;

    public function __construct(Response $response)
    {
        $this->response = $response;
    }

    public function toArray()
    {
        return [
            'data' => json_decode($this->response->getBody()->__toString(), true),
            'status' => $this->status(),
            'successful' => $this->successful(),
        ];
    }

    public function toJson()
    {
        return json_encode($this->response->getBody()->__toString());
    }

    public function data(?string $entity = null)
    {
        if (!$entity) {
            return json_decode($this->response->getBody()->__toString());
        }
        return Hydrator::hydrate(json_decode($this->response->getBody()->__toString(), true), $entity);
    }

    public function successful()
    {
        return $this->response->getStatusCode() >= 200 && $this->response->getStatusCode() < 300;
    }
    
    public function status()
    {
        return $this->response->getStatusCode();
    }

    
    public function getResponse(): Response
    {
        return $this->response;
    }
}
