<?php

/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */

namespace Tests\Modules\Pterodactyl\Fixtures;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

// In-memory panel answering like Pterodactyl 1.15 or 2.0 beta, shapes taken from real responses of both versions.
class FakePterodactylPanel
{
    public array $users = [];

    public array $servers = [];

    public array $eggs = [];

    public array $nests = [];

    public array $tags = [];

    // Path regex => HTTP status, answered before any route (a panel that refuses a key, or is in maintenance).
    public array $forcedStatus = [];

    public bool $includeServersAuthorized = true;

    private int $nextServerId = 1000;

    public function __construct(public readonly string $version)
    {
        $this->nests = [1 => 'Minecraft', 3 => 'Voice Servers'];
        $this->tags = [
            1 => ['name' => 'Minecraft', 'slug' => 'minecraft', 'legacy_nest_id' => null],
            4 => ['name' => 'Voice Servers', 'slug' => 'voice', 'legacy_nest_id' => null],
            9 => ['name' => 'Sandbox', 'slug' => 'sandbox', 'legacy_nest_id' => null],
        ];
        $this->addEgg(5, 'Vanilla Minecraft', 1, [1]);
        $this->addEgg(12, 'Mumble Server', 3, [4, 9]);
    }

    public function addEgg(int $id, string $name, int $nest, array $tags): void
    {
        $this->eggs[$id] = ['id' => $id, 'name' => $name, 'nest' => $nest, 'tags' => $tags];
    }

    public function addUsers(int $count, string $domain = 'example.org'): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->addUser("user{$i}@{$domain}");
        }
    }

    public function addUser(string $email, bool $admin = false): int
    {
        $id = count($this->users) + 1;
        $this->users[$id] = ['id' => $id, 'external_id' => null, 'uuid' => 'u-'.$id, 'username' => 'user-'.$id, 'email' => $email, 'first_name' => 'First', 'last_name' => 'Last', 'root_admin' => $admin];

        return $id;
    }

    public function addServers(int $count, int $userId): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->addServer($userId);
        }
    }

    public function addServer(int $userId, ?string $externalId = null, int $egg = 12): int
    {
        $id = $this->nextServerId++;
        $this->servers[$id] = ['id' => $id, 'external_id' => $externalId, 'uuid' => 'uuid-'.$id, 'identifier' => 'id'.$id, 'name' => 'Server '.$id, 'description' => '', 'suspended' => false, 'user' => $userId, 'node' => 1, 'allocation' => 4, 'egg' => $egg, 'nest' => $this->version === '2' ? null : $this->eggs[$egg]['nest'], 'container' => ['startup_command' => './run.sh', 'image' => 'image:'.$egg, 'installed' => 1, 'environment' => ['OLD' => '1']]];

        return $id;
    }

    public function fake(string $host = 'panel.test'): void
    {
        Http::fake(["{$host}/*" => fn (Request $request) => $this->handle($request)]);
    }

    public function handle(Request $request): PromiseInterface
    {
        $parts = parse_url($request->url());
        parse_str($parts['query'] ?? '', $query);
        $path = $parts['path'] ?? '';
        if (str_starts_with($path, '/api/client')) {
            return Http::response(['object' => 'list', 'data' => []]);
        }
        $path = trim(substr($path, strlen('/api/application')), '/');
        foreach ($this->forcedStatus as $pattern => $status) {
            if (preg_match($pattern, $path)) {
                return $this->error($status, 'ForcedStatus', "forced {$status}");
            }
        }
        $perPage = (int) ($query['per_page'] ?? 50);
        if ($this->version === '2' && $perPage > 100) {
            return $this->error(422, 'ValidationException', 'The per page may not be greater than 100.');
        }

        return $this->route($request->method(), $path, $query, $perPage, $request->data()) ?? $this->notFound($path);
    }

    private function route(string $method, string $path, array $query, int $perPage, array $body): ?PromiseInterface
    {
        $v2 = $this->version === '2';
        $include = explode(',', $query['include'] ?? '');

        return match (true) {
            $method === 'GET' && $path === 'tags' => $v2 ? $this->paginate(array_map(fn ($id) => $this->tag($id), array_keys($this->tags)), $perPage, $query) : null,
            $method === 'GET' && $path === 'nests' => $v2 ? null : $this->paginate(array_map(fn ($id, $name) => ['object' => 'nest', 'attributes' => ['id' => $id, 'name' => $name]], array_keys($this->nests), $this->nests), $perPage, $query),
            $method === 'GET' && (bool) preg_match('#^nests/(\d+)/eggs$#', $path, $m) => $v2 || ! isset($this->nests[$m[1]]) ? null : Http::response(['object' => 'list', 'data' => array_values(array_map(fn ($egg) => $this->egg($egg, $include), array_filter($this->eggs, fn ($egg) => $egg['nest'] == $m[1])))]),
            $method === 'GET' && (bool) preg_match('#^nests/(\d+)/eggs/(\d+)$#', $path, $m) => $v2 || ($this->eggs[$m[2]]['nest'] ?? null) != $m[1] ? null : Http::response($this->egg($this->eggs[$m[2]], $include)),
            $method === 'GET' && $path === 'eggs' => $v2 ? $this->paginate(array_values(array_map(fn ($egg) => $this->egg($egg, $include), $this->eggs)), $perPage, $query) : null,
            $method === 'GET' && (bool) preg_match('#^eggs/(\d+)$#', $path, $m) => $v2 && isset($this->eggs[$m[1]]) ? Http::response($this->egg($this->eggs[$m[1]], $include)) : null,
            $method === 'GET' && $path === 'locations' => $this->paginate([['object' => 'location', 'attributes' => ['id' => 1, 'short' => 'fr']]], $perPage, $query),
            $method === 'GET' && $path === 'users' => $this->paginate($this->filteredUsers($query), $perPage, $query),
            $method === 'GET' && (bool) preg_match('#^users/external/(.+)$#', $path, $m) => $this->userByExternalId(urldecode($m[1])),
            $method === 'GET' && (bool) preg_match('#^users/(\d+)$#', $path, $m) => isset($this->users[$m[1]]) ? Http::response(['object' => 'user', 'attributes' => $this->userAttributes((int) $m[1], $include)]) : null,
            $method === 'PATCH' && (bool) preg_match('#^users/(\d+)$#', $path, $m) => Http::response(['object' => 'user', 'attributes' => $this->users[$m[1]]]),
            $method === 'POST' && $path === 'users' => $this->createUser($body),
            $method === 'GET' && $path === 'servers' => $this->paginate(array_values(array_map(fn ($server) => ['object' => 'server', 'attributes' => $server], $this->servers)), $perPage, $query),
            $method === 'POST' && $path === 'servers' => $this->createServer($body),
            $method === 'GET' && (bool) preg_match('#^servers/external/(.+)$#', $path, $m) => $this->serverByExternalId($m[1]),
            $method === 'GET' && (bool) preg_match('#^servers/(\d+)$#', $path, $m) => isset($this->servers[$m[1]]) ? Http::response(['object' => 'server', 'attributes' => $this->servers[$m[1]]]) : null,
            $method === 'POST' && (bool) preg_match('#^servers/(\d+)/(suspend|unsuspend|reinstall)$#', $path, $m) => $this->serverAction((int) $m[1], $m[2]),
            $method === 'PATCH' && (bool) preg_match('#^servers/(\d+)/(startup|build|details)$#', $path, $m) => isset($this->servers[$m[1]]) ? Http::response(['object' => 'server', 'attributes' => $this->servers[$m[1]]]) : null,
            default => null,
        };
    }

    private function paginate(array $items, int $perPage, array $query): PromiseInterface
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $chunk = array_slice($items, ($page - 1) * $perPage, $perPage);

        return Http::response(['object' => 'list', 'data' => $chunk, 'meta' => ['pagination' => ['total' => count($items), 'count' => count($chunk), 'per_page' => $perPage, 'current_page' => $page, 'total_pages' => max(1, (int) ceil(count($items) / max(1, $perPage)))]]]);
    }

    private function filteredUsers(array $query): array
    {
        $needle = $query['filter']['email'] ?? null;
        $users = array_filter($this->users, fn ($user) => $needle === null || str_contains($user['email'], $needle));

        return array_values(array_map(fn ($user) => ['object' => 'user', 'attributes' => $user], $users));
    }

    private function egg(array $egg, array $include): array
    {
        $attributes = ['id' => $egg['id'], 'name' => $egg['name'], 'docker_image' => 'image:'.$egg['id'], 'startup' => 'start-'.$egg['id']];
        if ($this->version !== '2') {
            $attributes['nest'] = $egg['nest'];
        }
        if (in_array('variables', $include)) {
            $attributes['relationships']['variables'] = ['object' => 'list', 'data' => [['object' => 'egg_variable', 'attributes' => ['env_variable' => 'VAR_'.$egg['id'], 'default_value' => 'default']]]];
        }
        if ($this->version === '2' && in_array('tags', $include)) {
            $attributes['relationships']['tags'] = ['object' => 'list', 'data' => array_map(fn ($id) => $this->tag($id), $egg['tags'])];
        }

        return ['object' => 'egg', 'attributes' => $attributes];
    }

    private function tag(int $id): array
    {
        return ['object' => 'tag', 'attributes' => ['id' => $id] + $this->tags[$id]];
    }

    private function createUser(array $body): PromiseInterface
    {
        foreach ($this->users as $user) {
            if (strtolower($user['email']) === strtolower($body['email'])) {
                return $this->error(422, 'ValidationException', 'The email has already been taken.');
            }
        }
        $id = $this->addUser($body['email']);
        $this->users[$id]['external_id'] = $body['external_id'] ?? null;

        return Http::response(['object' => 'user', 'attributes' => $this->users[$id]], 201);
    }

    private function userByExternalId(string $externalId): ?PromiseInterface
    {
        foreach ($this->users as $user) {
            if ($user['external_id'] === $externalId) {
                return Http::response(['object' => 'user', 'attributes' => $user]);
            }
        }

        return null;
    }

    private function userAttributes(int $id, array $include): array
    {
        $attributes = $this->users[$id];
        if (in_array('servers', $include)) {
            $owned = array_values(array_filter($this->servers, fn ($server) => $server['user'] === $id));
            $attributes['relationships']['servers'] = $this->includeServersAuthorized
                ? ['object' => 'list', 'data' => array_map(fn ($server) => ['object' => 'server', 'attributes' => $server], $owned)]
                : ['object' => 'null_resource', 'attributes' => null];
        }

        return $attributes;
    }

    private function createServer(array $body): PromiseInterface
    {
        if (! isset($this->eggs[$body['egg'] ?? 0])) {
            return $this->error(422, 'ValidationException', 'The selected egg is invalid.');
        }
        $id = $this->addServer((int) $body['user'], $body['external_id'] ?? null, (int) $body['egg']);

        return Http::response(['object' => 'server', 'attributes' => $this->servers[$id]], 201);
    }

    private function serverByExternalId(string $externalId): ?PromiseInterface
    {
        foreach ($this->servers as $server) {
            if ($server['external_id'] === $externalId) {
                $server['relationships']['allocations'] = ['object' => 'list', 'data' => [['object' => 'allocation', 'attributes' => ['id' => 4, 'ip' => '192.0.2.10', 'alias' => null, 'port' => 25565]]]];

                return Http::response(['object' => 'server', 'attributes' => $server]);
            }
        }

        return null;
    }

    private function serverAction(int $id, string $action): ?PromiseInterface
    {
        if (! isset($this->servers[$id])) {
            return null;
        }
        if ($action !== 'reinstall') {
            $this->servers[$id]['suspended'] = $action === 'suspend';
        }

        return Http::response(null, 204);
    }

    private function notFound(string $path): PromiseInterface
    {
        return $this->error(404, 'NotFoundHttpException', "The route api/application/{$path} could not be found.");
    }

    private function error(int $status, string $code, string $detail): PromiseInterface
    {
        return Http::response(['errors' => [['code' => $code, 'status' => (string) $status, 'detail' => $detail]]], $status);
    }
}
