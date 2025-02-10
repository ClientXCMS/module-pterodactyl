<?php
/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
        @include("admin/shared/input", ['name' => 'memory', 'label' => __('provisioning.memory'), 'value' => $config->memory, 'help' => __('provisioning.in_gb'), 'type' => 'number','step' => '0.1', 'min' => 0])
    </div>
    <div>
        @include("admin/shared/input", ['name' => 'disk', 'label' => __('provisioning.disk'), 'value' => $config->disk, 'help' => __('provisioning.in_gb'), 'type' => 'number', 'step' => '0.1', 'min' => 0])
    </div>
    <div>
        @include("admin/shared/input", ['name' => 'io', 'label' => __('provisioning.io'), 'value' => $config->io, 'type' => 'number'])
    </div>
    <div class="col-span-2">
        @include("admin/shared/search-select-multiple", ['name' => 'eggs[]', 'label' => __('provisioning.eggs'), 'options' => $eggs, 'value' => $currenteggs, 'multiple' => true])

        @if ($errors->has('eggs'))
            <p class="text-red-500 text-xs italic mt-2">
                {{ $errors->first('eggs') }}
            </p>
        @endif
    </div>
    <div>
        @include('admin/shared/select', ['name' => 'location_id', 'label' => __('provisioning.location'), 'options' => $locations, 'value' => $config->location_id])
    </div>
    <div>
        @include('admin/shared/select', ['name' => 'server_id', 'label' => __('provisioning.server'), 'options' => $servers, 'value' => $config->server_id])
    </div>
    <div>
        @include("admin/shared/input", ['name' => 'cpu', 'label' => __('provisioning.cpu'), 'value' => $config->cpu, 'type' => 'number'])
    </div>
    <div>
        @include("admin/shared/input", ['name' => 'swap', 'label' => __('provisioning.swap'), 'value' => $config->swap, 'type' => 'number'])
    </div>
    <div>
        @include("admin/shared/input", ['name' => 'databases', 'label' => __('provisioning.db'), 'value' => $config->databases, 'type' => 'number'])
    </div>
    <div>
        @include("admin/shared/input", ['name' => 'allocations', 'label' => __('provisioning.allocations'), 'value' => $config->allocations, 'type' => 'number'])
    </div>
    <div>
        @include("admin/shared/input", ['name' => 'backups', 'label' => __('provisioning.backups'), 'value' => $config->backups, 'type' => 'number'])
    </div>
    <div>
        @include("admin/shared/input", ['name' => 'port_range', 'label' => __('provisioning.port_range'), 'value' => $config->port_range, 'help' => __('provisioning.port_range_help'), 'type' => 'text', 'optional' => true])
    </div>
    <div class="col-span-2">
        @include("admin/shared/input", ['name' => 'server_name', 'label' => __('provisioning.server_name'), 'help' => __('provisioning.server_variables'), 'value' => $config->server_name, 'type' => 'text', 'optional' => true])
    </div>

    <div>
        @include("admin/shared/textarea", ['name' => 'server_description', 'label' => __('provisioning.server_description'), 'value' => $config->server_description, 'optional' => true])
    </div>
    <div class="col-span-2">
        @include("admin/shared/input", ['name' => 'startup', 'label' => __('provisioning.startup'), 'value' => $config->startup, 'type' => 'text', 'optional' => true])
        @include("admin/shared/input", ['name' => 'image', 'label' => __('provisioning.image'), 'value' => $config->image, 'type' => 'text', 'optional' => true])
    </div>
    <div>
        @include("admin/shared/checkbox", ['name' => 'dedicated_ip', 'label' => __('provisioning.dedicated_ip'), 'value' => $config->dedicated_ip])
    </div>
    <div>
        @include("admin/shared/checkbox", ['name' => 'oom_kill', 'label' => __('provisioning.oom_kill'), 'checked' => $config->oom_kill])
    </div>
</div>
