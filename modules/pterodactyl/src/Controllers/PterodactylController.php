<?php

/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */

namespace App\Modules\Pterodactyl\Controllers;

use App\Models\Provisioning\Service;
use App\Modules\Pterodactyl\DTO\PterodactylServerDTO;

class PterodactylController extends \App\Http\Controllers\Controller
{
    public function changeEgg(Service $service)
    {
        if (!auth('admin')->check()) {
            if (auth('web')->guest()) {
                abort(404);
            }
            if (!auth('web')->user()->hasServicePermission($service, 'pterodactyl.changeegg')) {
                abort(404);
            }
        }
        abort_if($service->type != 'pterodactyl', 404);

        $server = \App\Modules\Pterodactyl\DTO\PterodactylServerDTO::getServerFromExternalId($service);
        if ($server->suspended()) {
            \Session::flash('error', __('client.alerts.service_suspended'));
            return redirect()->route('front.services.show', $service);
        }
        if (!$server->installed()) {
            \Session::flash('error', __('client.alerts.servernotinstalled'));
            return redirect()->route('front.services.show', $service);
        }

        $eggId = request('egg_id');
        $currentEggId = $server->attributes->egg ?? ($server->attributes->container->egg_id ?? null);
        if (request()->has('egg_id') && $eggId == $currentEggId) {
            \Session::flash('error', "L'egg sélectionné est déjà installé sur le serveur.");
            return redirect()->back();
        }
        if (request()->has('egg_id') && empty($eggId)) {
            \Session::flash('error', 'Veuillez sélectionner un egg avant de réinstaller avec changement d\'egg.');
            return redirect()->back();
        }
        if (!$eggId) {
            $eggId = $server->attributes->egg ?? ($server->attributes->container->egg_id ?? null);
        }
        $config = \App\Modules\Pterodactyl\Models\PterodactylConfig::where('product_id', $service->product_id)->first();
        
        // Récupérer le nestId associé à l'egg sélectionné
        $nestId = null;
        if ($config && isset($config->eggs)) {
            $eggsArray = is_array($config->eggs) ? $config->eggs : json_decode($config->eggs, true);
            if (is_array($eggsArray)) {
                foreach ($eggsArray as $eggNest) {
                    [$egg, $nest] = explode(\App\Modules\Pterodactyl\Models\PterodactylConfig::DELIMITER, $eggNest);
                    if ($egg == $eggId) {
                        $nestId = $nest;
                        break;
                    }
                }
            }
        }
        
        // Récupérer toutes les infos du serveur via l'API application
        $serverApi = \App\Modules\Pterodactyl\Http::callApi($service->server, 'servers/' . $server->id, [], 'GET', true, 'application');
        $serverData = $serverApi->toJson()->attributes ?? null;
        
        if (!$serverData) {
            \Session::flash('error', 'Impossible de récupérer les données du serveur.');
            return redirect()->back();
        }

        // Récupérer l'allocation actuelle du serveur
        $currentAllocation = $this->getCurrentServerAllocation($service, $server, $serverData);
        if (!$currentAllocation) {
            \Session::flash('error', 'Impossible de récupérer l\'allocation du serveur.');
            return redirect()->back();
        }
        
        $limits = [
            'memory' => (int) (($config->memory + $service->getOptionValue('additional_memory', 0)) * 1024),
            'swap' => $config->swap + $service->getOptionValue('additional_swap', 0),
            'disk' => (int) (($config->disk + $service->getOptionValue('additional_disk', 0)) * 1024),
            'io' => $config->io + $service->getOptionValue('additional_io', 0),
            'cpu' => ($config->cpu + $service->getOptionValue('additional_cpu', 0)),
        ];
        $feature_limits = [
            'databases' => $config->databases + $service->getOptionValue('additional_databases', 0),
            'allocations' => $config->allocations + $service->getOptionValue('additional_allocations', 0),
            'backups' => $config->backups + $service->getOptionValue('additional_backups', 0),
        ];

        $startup = $serverData->container->startup ?? $config->startup;
        $image = $serverData->container->image ?? $config->image;
        $environment = $serverData->container->environment ?? [];

        // Si un nouvel egg est sélectionné, récupérer son image et son startup
        if ($eggId && $nestId) {
            $eggApi = \App\Modules\Pterodactyl\Http::callApi($service->server, "nests/$nestId/eggs/$eggId", [], 'GET', true, 'application');
            if ($eggApi->status() == 200 && isset($eggApi->toJson()->attributes)) {
                $image = $eggApi->toJson()->attributes->docker_image ?? $image;
                $startup = $eggApi->toJson()->attributes->startup ?? $startup;
            }
        }

        // Étape 1: Mettre à jour la configuration startup (avec skip_scripts)
        $startupPayload = [
            'startup' => $startup,
            'environment' => (array)$environment,
            'egg' => (int)$eggId,
            'image' => $image,
            'skip_scripts' => false, // REQUIS par l'API
        ];

        logger()->info('ChangeEgg - Mise à jour startup', [
            'server_id' => $server->id,
            'service_id' => $service->id,
            'payload' => $startupPayload
        ]);

        $updateStartupResult = \App\Modules\Pterodactyl\Http::callApi(
            $service->server,
            'servers/' . $server->id . '/startup',
            $startupPayload,
            'PATCH',
            true,
            'application'
        );

        if (!$updateStartupResult->successful()) {
            logger()->error('ChangeEgg - Erreur lors de la mise à jour startup', [
                'server_id' => $server->id,
                'error' => $updateStartupResult->formattedErrors(),
                'response' => $updateStartupResult->toJson()
            ]);
            \Session::flash('error', 'Erreur lors de la mise à jour startup: ' . $updateStartupResult->formattedErrors());
            return redirect()->back();
        }

        // Étape 2: Mettre à jour la configuration build (avec allocation)
        $buildPayload = [
            'allocation' => (int)$currentAllocation, // REQUIS par l'API
            'memory' => $limits['memory'],
            'swap' => $limits['swap'],
            'disk' => $limits['disk'],
            'io' => $limits['io'],
            'cpu' => $limits['cpu'],
            'feature_limits' => $feature_limits,
        ];

        logger()->info('ChangeEgg - Mise à jour build', [
            'server_id' => $server->id,
            'service_id' => $service->id,
            'payload' => $buildPayload
        ]);

        $updateBuildResult = \App\Modules\Pterodactyl\Http::callApi(
            $service->server,
            'servers/' . $server->id . '/build',
            $buildPayload,
            'PATCH',
            true,
            'application'
        );

        if (!$updateBuildResult->successful()) {
            logger()->error('ChangeEgg - Erreur lors de la mise à jour build', [
                'server_id' => $server->id,
                'error' => $updateBuildResult->formattedErrors(),
                'response' => $updateBuildResult->toJson()
            ]);
            \Session::flash('error', 'Erreur lors de la mise à jour build: ' . $updateBuildResult->formattedErrors());
            return redirect()->back();
        }

        // Étape 3: Réinstaller le serveur
        $reinstallResult = \App\Modules\Pterodactyl\Http::callApi(
            $service->server,
            'servers/' . $server->id . '/reinstall',
            [],
            'POST',
            true,
            'application'
        );

        if ($reinstallResult->successful()) {
            \Session::flash('success', 'Serveur réinstallé avec succès avec le nouvel egg.');
        } else {
            logger()->error('ChangeEgg - Erreur lors de la réinstallation', [
                'server_id' => $server->id,
                'error' => $reinstallResult->formattedErrors()
            ]);
            \Session::flash('error', 'Configuration modifiée mais erreur lors de la réinstallation: ' . $reinstallResult->formattedErrors());
        }

        return redirect()->back();
    }

    /**
     * Récupère l'allocation actuelle du serveur (version améliorée)
     */
    private function getCurrentServerAllocation(Service $service, $server, $serverData)
    {
        // Log pour déboguer la structure des données reçues
        logger()->info('Structure serverData reçue', [
            'server_id' => $server->id,
            'serverData_keys' => array_keys((array)$serverData),
            'has_allocation' => isset($serverData->allocation),
            'allocation_value' => $serverData->allocation ?? 'non défini',
            'has_relationships' => isset($serverData->relationships),
            'relationships_keys' => isset($serverData->relationships) ? array_keys((array)$serverData->relationships) : 'pas de relationships'
        ]);

        // Méthode 1: Allocation directe (valeur numérique)
        if (isset($serverData->allocation) && is_numeric($serverData->allocation)) {
            logger()->info('Allocation trouvée - allocation directe', [
                'server_id' => $server->id,
                'allocation' => $serverData->allocation
            ]);
            return (int)$serverData->allocation;
        }

        // Méthode 2: Allocation dans un objet
        if (isset($serverData->allocation->id)) {
            logger()->info('Allocation trouvée - objet allocation', [
                'server_id' => $server->id,
                'allocation' => $serverData->allocation->id
            ]);
            return (int)$serverData->allocation->id;
        }

        // Méthode 3: Via relationships - allocation unique
        if (isset($serverData->relationships->allocation->data->id)) {
            logger()->info('Allocation trouvée - relationships allocation', [
                'server_id' => $server->id,
                'allocation' => $serverData->relationships->allocation->data->id
            ]);
            return (int)$serverData->relationships->allocation->data->id;
        }

        // Méthode 4: Via relationships - allocation avec attributes
        if (isset($serverData->relationships->allocation->data->attributes->id)) {
            logger()->info('Allocation trouvée - relationships allocation attributes', [
                'server_id' => $server->id,
                'allocation' => $serverData->relationships->allocation->data->attributes->id
            ]);
            return (int)$serverData->relationships->allocation->data->attributes->id;
        }

        // Méthode 5: Via relationships - allocations (pluriel) - première de la liste
        if (isset($serverData->relationships->allocations->data) && 
            is_array($serverData->relationships->allocations->data) && 
            count($serverData->relationships->allocations->data) > 0) {
            
            $firstAlloc = $serverData->relationships->allocations->data[0];
            $allocId = null;
            
            if (isset($firstAlloc->id)) {
                $allocId = $firstAlloc->id;
            } elseif (isset($firstAlloc->attributes->id)) {
                $allocId = $firstAlloc->attributes->id;
            }
            
            if ($allocId) {
                logger()->info('Allocation trouvée - première des allocations', [
                    'server_id' => $server->id,
                    'allocation' => $allocId,
                    'total_allocations' => count($serverData->relationships->allocations->data)
                ]);
                return (int)$allocId;
            }
        }

        // Méthode 6: Fallback - appel API séparé avec include=allocations
        logger()->warning('Tentative fallback - appel API séparé pour les allocations', [
            'server_id' => $server->id
        ]);

        $allocationsApi = \App\Modules\Pterodactyl\Http::callApi(
            $service->server, 
            'servers/' . $server->id . '?include=allocations', 
            [], 
            'GET', 
            true, 
            'application'
        );
        
        if ($allocationsApi->successful()) {
            $data = $allocationsApi->toJson();
            
            // Essayer les mêmes méthodes sur la nouvelle réponse
            if (isset($data->attributes->allocation) && is_numeric($data->attributes->allocation)) {
                logger()->info('Allocation trouvée - fallback allocation directe', [
                    'server_id' => $server->id,
                    'allocation' => $data->attributes->allocation
                ]);
                return (int)$data->attributes->allocation;
            }
            
            if (isset($data->attributes->relationships->allocations->data) && 
                is_array($data->attributes->relationships->allocations->data) && 
                count($data->attributes->relationships->allocations->data) > 0) {
                
                $firstAlloc = $data->attributes->relationships->allocations->data[0];
                $allocId = isset($firstAlloc->attributes->id) ? $firstAlloc->attributes->id : ($firstAlloc->id ?? null);
                
                if ($allocId) {
                    logger()->info('Allocation trouvée - fallback première allocation', [
                        'server_id' => $server->id,
                        'allocation' => $allocId
                    ]);
                    return (int)$allocId;
                }
            }
            
            // Log de la structure complète en cas d'échec
            logger()->error('Structure complète de la réponse API fallback', [
                'server_id' => $server->id,
                'response' => json_encode($data, JSON_PRETTY_PRINT)
            ]);
        }

        logger()->error('IMPOSSIBLE de trouver une allocation pour le serveur', [
            'server_id' => $server->id,
            'service_id' => $service->id,
            'serverData_structure' => json_encode($serverData, JSON_PRETTY_PRINT)
        ]);

        return null;
    }

    /**
     * Utilitaire pour récupérer une valeur imbriquée
     */
    private function getNestedValue($object, $path)
    {
        $keys = explode('.', $path);
        $current = $object;
        
        foreach ($keys as $key) {
            if (is_object($current) && isset($current->$key)) {
                $current = $current->$key;
            } elseif (is_array($current) && isset($current[$key])) {
                $current = $current[$key];
            } else {
                return null;
            }
        }
        
        return $current;
    }

    public function power(Service $service, string $power)
    {
        if (! auth('admin')->check()) {
            if (auth('web')->guest()) {
                abort(404);
            }
            if (! auth('web')->user()->hasServicePermission($service, 'pterodactyl.power')) {
                abort(404);
            }
        }
        abort_if(! in_array($power, ['start', 'stop', 'restart']), 404);
        abort_if($service->type != 'pterodactyl', 404);
        
        $server = PterodactylServerDTO::getServerFromExternalId($service);
        if ($server->suspended()) {
            \Session::flash('error', __('client.alerts.service_suspended'));
            return redirect()->route('front.services.show', $service);
        }
        if (! $server->installed()) {
            \Session::flash('error', __('client.alerts.servernotinstalled'));
            return redirect()->route('front.services.show', $service);
        }
        
        $result = $server->power($service, $power);
        if ($result->status() == 204) {
            \Session::flash('success', __('client.alerts.power_success'));
        } else {
            logger()->error('Power action failed', [
                'server_id' => $server->id,
                'power' => $power,
                'error' => $result->toJson()->errors[0]->detail ?? 'Unknown error'
            ]);
            \Session::flash('error', __('client.alerts.power_error'));
        }

        return redirect()->back();
    }
}