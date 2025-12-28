<?php
/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */

namespace App\Modules\Pterodactyl\DTO;

use App\Models\Provisioning\Server;
use App\Models\Provisioning\Service;
use App\Modules\Pterodactyl\Http;
use App\Modules\Pterodactyl\Models\PterodactylConfig;

class NodeAllocationDiagnosticDTO
{
    public string $nodeName;
    public int $nodeId;

    public int $totalMemory;
    public int $totalDisk;
    public int $memoryOverallocate;
    public int $diskOverallocate;
    public int $allocatedMemory;
    public int $allocatedDisk;

    public array $allAllocations = [];
    public array $availableAllocations = [];
    public array $assignedAllocations = [];

    public int $requiredMemory;
    public int $requiredDisk;
    public ?string $portRange;
    public bool $dedicatedIp;

    public array $errors = [];
    public array $warnings = [];
    public bool $hasError = false;

    public static function analyze(Server $server, PterodactylConfig $config, Service $service, ?int $locationId = null): self
    {
        $dto = new self();

        $dto->requiredMemory = (int) (($config->memory + $service->getOptionValue('additional_memory', 0)) * 1024);
        $dto->requiredDisk = (int) (($config->disk + $service->getOptionValue('additional_disk', 0)) * 1024);
        $dto->portRange = $config->port_range;
        $dto->dedicatedIp = (bool) $service->getOptionValue($service->type . '_dedicated_ip', $config->dedicated_ip);

        $targetLocationId = $service->getOptionValue($service->type . '_location_id', $config->location_id);

        $nodesResponse = Http::callApi($server, 'nodes?include=allocations');
        if (!$nodesResponse->successful(true)) {
            $dto->hasError = true;
            $dto->errors[] = 'Unable to retrieve node list: ' . $nodesResponse->formattedErrors();
            return $dto;
        }

        $nodes = $nodesResponse->toJson()->data ?? [];
        $eligibleNodes = [];

        foreach ($nodes as $nodeData) {
            $node = $nodeData->attributes;

            if ($node->location_id != $targetLocationId) {
                continue;
            }

            if ($node->maintenance_mode) {
                continue;
            }

            $eligibleNodes[] = $node;
        }

        if (empty($eligibleNodes)) {
            $dto->hasError = true;
            $dto->errors[] = "No available node in location {$targetLocationId}";
            return $dto;
        }

        $allNodeDiagnostics = [];
        foreach ($eligibleNodes as $node) {
            $nodeDiagnostic = self::analyzeNode($dto, $node, $server);
            $allNodeDiagnostics[] = $nodeDiagnostic;
        }

        $hasValidNode = false;
        foreach ($allNodeDiagnostics as $diagnostic) {
            if (empty($diagnostic['errors'])) {
                $hasValidNode = true;
                break;
            }
        }

        if (!$hasValidNode) {
            $dto->hasError = true;
            foreach ($allNodeDiagnostics as $diagnostic) {
                foreach ($diagnostic['errors'] as $error) {
                    $dto->errors[] = "[{$diagnostic['nodeName']}] {$error}";
                }
            }
        }

        foreach ($allNodeDiagnostics as $diagnostic) {
            foreach ($diagnostic['warnings'] as $warning) {
                $dto->warnings[] = "[{$diagnostic['nodeName']}] {$warning}";
            }
        }

        return $dto;
    }

    public static function analyzeNodeById(Server $server, int $nodeId, PterodactylConfig $config, Service $service): self
    {
        $dto = new self();

        $dto->requiredMemory = (int) (($config->memory + $service->getOptionValue('additional_memory', 0)) * 1024);
        $dto->requiredDisk = (int) (($config->disk + $service->getOptionValue('additional_disk', 0)) * 1024);
        $dto->portRange = $config->port_range;
        $dto->dedicatedIp = (bool) $service->getOptionValue($service->type . '_dedicated_ip', $config->dedicated_ip);

        $nodeResponse = Http::callApi($server, "nodes/{$nodeId}?include=allocations");
        if (!$nodeResponse->successful(true)) {
            $dto->hasError = true;
            $dto->errors[] = "Unable to retrieve node {$nodeId}: " . $nodeResponse->formattedErrors();
            return $dto;
        }

        $node = $nodeResponse->toJson()->attributes;
        $diagnostic = self::analyzeNode($dto, $node, $server);

        $dto->nodeName = $diagnostic['nodeName'];
        $dto->nodeId = $diagnostic['nodeId'];
        $dto->errors = $diagnostic['errors'];
        $dto->warnings = $diagnostic['warnings'];
        $dto->hasError = !empty($diagnostic['errors']);

        return $dto;
    }

    private static function analyzeNode(self $mainDto, object $node, Server $server): array
    {
        $errors = [];
        $warnings = [];

        $nodeName = $node->name;
        $nodeId = $node->id;

        $totalMemory = $node->memory;
        $totalDisk = $node->disk;
        $memoryOverallocate = $node->memory_overallocate;
        $diskOverallocate = $node->disk_overallocate;
        $allocatedMemory = $node->allocated_resources->memory ?? 0;
        $allocatedDisk = $node->allocated_resources->disk ?? 0;

        $mainDto->nodeName = $nodeName;
        $mainDto->nodeId = $nodeId;
        $mainDto->totalMemory = $totalMemory;
        $mainDto->totalDisk = $totalDisk;
        $mainDto->memoryOverallocate = $memoryOverallocate;
        $mainDto->diskOverallocate = $diskOverallocate;
        $mainDto->allocatedMemory = $allocatedMemory;
        $mainDto->allocatedDisk = $allocatedDisk;

        $maxMemory = $totalMemory + ($totalMemory * $memoryOverallocate / 100);
        $maxDisk = $totalDisk + ($totalDisk * $diskOverallocate / 100);
        $availableMemory = $maxMemory - $allocatedMemory;
        $availableDisk = $maxDisk - $allocatedDisk;

        if ($availableMemory < $mainDto->requiredMemory) {
            $missingMemory = $mainDto->requiredMemory - $availableMemory;
            $errors[] = sprintf(
                "Insufficient RAM: %d MB required, only %d MB available (missing %d MB). " .
                    "Used: %d MB / %d MB max (with %d%% overallocation)",
                $mainDto->requiredMemory,
                (int) $availableMemory,
                (int) $missingMemory,
                $allocatedMemory,
                (int) $maxMemory,
                $memoryOverallocate
            );
        } elseif ($availableMemory < $mainDto->requiredMemory * 1.2) {
            $warnings[] = sprintf(
                "RAM almost full: %d MB available for %d MB required",
                (int) $availableMemory,
                $mainDto->requiredMemory
            );
        }

        if ($availableDisk < $mainDto->requiredDisk) {
            $missingDisk = $mainDto->requiredDisk - $availableDisk;
            $errors[] = sprintf(
                "Insufficient disk: %d MB required, only %d MB available (missing %d MB). " .
                    "Used: %d MB / %d MB max (with %d%% overallocation)",
                $mainDto->requiredDisk,
                (int) $availableDisk,
                (int) $missingDisk,
                $allocatedDisk,
                (int) $maxDisk,
                $diskOverallocate
            );
        } elseif ($availableDisk < $mainDto->requiredDisk * 1.2) {
            $warnings[] = sprintf(
                "Disk almost full: %d MB available for %d MB required",
                (int) $availableDisk,
                $mainDto->requiredDisk
            );
        }

        $allocations = $node->relationships->allocations->data ?? [];
        $availableAllocations = [];
        $assignedAllocations = [];
        $allAllocations = [];

        foreach ($allocations as $allocationData) {
            $allocation = $allocationData->attributes;
            $allAllocations[] = [
                'id' => $allocation->id,
                'ip' => $allocation->ip,
                'port' => $allocation->port,
                'alias' => $allocation->alias,
                'assigned' => $allocation->assigned,
            ];

            if ($allocation->assigned) {
                $assignedAllocations[] = $allocation->port;
            } else {
                $availableAllocations[] = $allocation->port;
            }
        }

        $mainDto->allAllocations = $allAllocations;
        $mainDto->availableAllocations = $availableAllocations;
        $mainDto->assignedAllocations = $assignedAllocations;

        if (empty($availableAllocations)) {
            $errors[] = sprintf(
                "No allocation available on this node. Total: %d allocations, all assigned.",
                count($allAllocations)
            );
        }

        if (!empty($mainDto->portRange)) {
            $portRanges = self::parsePortRange($mainDto->portRange);
            $portsInRange = [];
            $availablePortsInRange = [];

            foreach ($availableAllocations as $port) {
                if (self::isPortInRanges($port, $portRanges)) {
                    $availablePortsInRange[] = $port;
                }
            }

            foreach ($allAllocations as $allocation) {
                if (self::isPortInRanges($allocation['port'], $portRanges)) {
                    $portsInRange[] = $allocation['port'];
                }
            }

            if (empty($portsInRange)) {
                $errors[] = sprintf(
                    "No allocation in configured port range (%s). " .
                        "You must create allocations with ports within this range.",
                    $mainDto->portRange
                );
            } elseif (empty($availablePortsInRange)) {
                $errors[] = sprintf(
                    "All allocations in port range (%s) are already assigned. " .
                        "%d allocations found in range, all used. " .
                        "Ports in range: %s",
                    $mainDto->portRange,
                    count($portsInRange),
                    implode(', ', $portsInRange)
                );
            } else {
                if (count($availablePortsInRange) <= 3) {
                    $warnings[] = sprintf(
                        "Only %d port(s) available in range %s: %s",
                        count($availablePortsInRange),
                        $mainDto->portRange,
                        implode(', ', $availablePortsInRange)
                    );
                }
            }
        }

        if ($mainDto->dedicatedIp) {
            $dedicatedIps = [];
            foreach ($allAllocations as $allocation) {
                if (!$allocation['assigned']) {
                    $dedicatedIps[$allocation['ip']] = ($dedicatedIps[$allocation['ip']] ?? 0) + 1;
                }
            }

            $hasExclusiveIp = false;
            foreach ($dedicatedIps as $ip => $count) {
                $ipAssigned = false;
                foreach ($allAllocations as $allocation) {
                    if ($allocation['ip'] === $ip && $allocation['assigned']) {
                        $ipAssigned = true;
                        break;
                    }
                }
                if (!$ipAssigned && $count > 0) {
                    $hasExclusiveIp = true;
                    break;
                }
            }

            if (!$hasExclusiveIp && !empty($dedicatedIps)) {
                $errors[] = "Dedicated IP required but no exclusive IP available. " .
                    "All IPs already have servers assigned.";
            }
        }

        return [
            'nodeName' => $nodeName,
            'nodeId' => $nodeId,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    private static function parsePortRange(string $portRange): array
    {
        $ranges = [];
        $parts = explode(',', $portRange);

        foreach ($parts as $part) {
            $part = trim($part);
            if (str_contains($part, '-')) {
                [$min, $max] = explode('-', $part, 2);
                $ranges[] = [(int) trim($min), (int) trim($max)];
            } else {
                $port = (int) $part;
                $ranges[] = [$port, $port];
            }
        }

        return $ranges;
    }

    private static function isPortInRanges(int $port, array $ranges): bool
    {
        foreach ($ranges as [$min, $max]) {
            if ($port >= $min && $port <= $max) {
                return true;
            }
        }
        return false;
    }

    public function getSummary(): string
    {
        $lines = [];

        if ($this->hasError) {
            $lines[] = "DIAGNOSTIC:";
            foreach ($this->errors as $error) {
                $lines[] = "  - " . $error;
            }
        } else {
            $lines[] = "No issues detected";
        }

        if (!empty($this->warnings)) {
            $lines[] = "";
            $lines[] = "Warnings:";
            foreach ($this->warnings as $warning) {
                $lines[] = "  - " . $warning;
            }
        }

        return implode("\n", $lines);
    }

    public function toArray(): array
    {
        return [
            'has_error' => $this->hasError,
            'node' => [
                'name' => $this->nodeName ?? null,
                'id' => $this->nodeId ?? null,
            ],
            'resources' => [
                'memory' => [
                    'total' => $this->totalMemory ?? 0,
                    'allocated' => $this->allocatedMemory ?? 0,
                    'required' => $this->requiredMemory ?? 0,
                    'overallocate' => $this->memoryOverallocate ?? 0,
                ],
                'disk' => [
                    'total' => $this->totalDisk ?? 0,
                    'allocated' => $this->allocatedDisk ?? 0,
                    'required' => $this->requiredDisk ?? 0,
                    'overallocate' => $this->diskOverallocate ?? 0,
                ],
            ],
            'allocations' => [
                'total' => count($this->allAllocations),
                'available' => count($this->availableAllocations),
                'assigned' => count($this->assignedAllocations),
                'port_range' => $this->portRange,
            ],
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'summary' => $this->getSummary(),
        ];
    }
}
