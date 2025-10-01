<?php
/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */
?>
@if ($subdomains->isNotEmpty())
    <div>
        <label for="domain_subdomain" class="block text-sm font-medium leading-6 text-gray-900 dark:text-gray-400 mt-2">{{ __('provisioning.admin.subdomains_hosts.use_subdomain', ['app_name' => config('app.name')]) }}</label>
        <div class="sm:flex rounded-lg shadow-sm">
            <input type="text" class="py-3 px-4 pe-11 input-text" name="domain_subdomain" value="{{ $data['domain_subdomain'] ?? '' }}">
            <select type="text" class="py-3 px-4 pe-11 input-text" name="subdomain">
                @foreach($subdomains as $subdomain)
                    <option value="{{ $subdomain->domain }}"{{ $data['subdomain'] ?? '' == $subdomain->domain ? ' selected' : '' }}>{{ $subdomain->domain }}</option>
                @endforeach
            </select>
        </div>
        @if ($errors->has('domain_subdomain'))
            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $errors->first('domain_subdomain') }}</p>
        @endif
    </div>
@endif


@include("shared.select", ["name" => "eggname", "label" => "Eggs", "options" => $eggnames, "value" => $eggname])
