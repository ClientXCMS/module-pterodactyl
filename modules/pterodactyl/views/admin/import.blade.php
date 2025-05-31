<?php
/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */
?>

<h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
    {{ __('provisioning.admin.services.create.choose') }}
</h2>
@include('admin/shared/select', ['name' => 'pterodactylserver_id', 'label' => __('global.server'), 'options' => $servers, 'value' => old('pterodactylserver_id')])
