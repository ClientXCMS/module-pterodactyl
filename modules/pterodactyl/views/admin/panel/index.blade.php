<?php
/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */
?>

<div class="grid sm:grid-cols-{{ $server->attributes->limits->swap > 0 ? '2' : 3 }} lg:grid-cols-{{ $server->attributes->limits->swap > 0 ? '2' : 3 }} gap-2 sm:gap-6">
    <div class="flex flex-col bg-white border shadow-sm rounded-xl dark:bg-slate-900 dark:border-gray-800">
        <div class="p-4 md:p-5 flex justify-between gap-x-3">
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500">
                    {{ __('provisioning.memory') }}
                </p>
                <div class="mt-1 flex items-center gap-x-2">
                    <h3 class="text-xl sm:text-2xl font-medium text-gray-800 dark:text-gray-200">
                        {{ $server->attributes->limits->memory / 1024 }} GB
                    </h3>
                </div>
            </div>
            <div class="flex-shrink-0 flex justify-center items-center w-[46px] h-[46px] bg-indigo-600 text-white rounded-full dark:bg-indigo-900 dark:text-indigo-200">
                <i class="bi bi-memory"></i>
            </div>
        </div>
    </div>
    <div class="flex flex-col bg-white border shadow-sm rounded-xl dark:bg-slate-900 dark:border-gray-800">
        <div class="p-4 md:p-5 flex justify-between gap-x-3">
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500">
                    {{ __('provisioning.disk') }}
                </p>
                <div class="mt-1 flex items-center gap-x-2">
                    <h3 class="mt-1 text-xl font-medium text-gray-800 dark:text-gray-200">
                        {{ $server->attributes->limits->disk / 1024 }} GB
                    </h3>
                </div>
            </div>
            <div class="flex-shrink-0 flex justify-center items-center w-[46px] h-[46px] bg-indigo-600 text-white rounded-full dark:bg-indigo-900 dark:text-indigo-200">
                <i class="bi bi-hdd"></i>
            </div>
        </div>
    </div>
    <div class="flex flex-col bg-white border shadow-sm rounded-xl dark:bg-slate-900 dark:border-gray-800">
        <div class="p-4 md:p-5 flex justify-between gap-x-3">
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500">
                    {{ __('provisioning.cpu') }}
                </p>
                <div class="mt-1 flex items-center gap-x-2">
                    <h3 class="text-xl sm:text-2xl font-medium text-gray-800 dark:text-gray-200">
                        {{ $server->attributes->limits->cpu }}%
                    </h3>
                </div>
            </div>
            <div class="flex-shrink-0 flex justify-center items-center w-[46px] h-[46px] bg-indigo-600 text-white rounded-full dark:bg-indigo-900 dark:text-indigo-200">
                <i class="bi bi-cpu"></i>

            </div>
        </div>
    </div>
    @if ($server->attributes->limits->swap > 0)
        <div class="flex flex-col bg-white border shadow-sm rounded-xl dark:bg-slate-900 dark:border-gray-800">
            <div class="p-4 md:p-5 flex justify-between gap-x-3">
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-500">
                        {{ __('provisioning.swap') }}
                    </p>
                    <div class="mt-1 flex items-center gap-x-2">
                        <h3 class="mt-1 text-xl font-medium text-gray-800 dark:text-gray-200">
                            {{ $server->attributes->limits->swap / 1024 }} GB
                        </h3>
                    </div>
                </div>
                <div class="flex-shrink-0 flex justify-center items-center w-[46px] h-[46px] bg-indigo-600 text-white rounded-full dark:bg-indigo-900 dark:text-indigo-200">
                    <i class="bi bi-hdd-rack"></i>

                </div>
            </div>
        </div>
    @endif
</div>
<div class="mt-2">
    <div class="flex mt-2">
        @if ($server->isOffline($utilization))
            <form method="POST" action="{{ route($uuid. '.power', ['service' => $service, 'power' => 'start']) }}" class="w-full">
                @csrf
                <button class="w-full mr-2 py-2 px-4 mt-4 btn-primary text-center py-2 px-4">
                    {{ __('provisioning.start') }}
                </button>
            </form>
        @else
            <form method="POST" action="{{ route($uuid. '.power', ['service' => $service, 'power' => 'stop']) }}" class="w-full">
                @csrf
                <button class="w-full mr-2 btn-warning text-center py-2 px-4">
                    {{ __('provisioning.stop') }}
                </button>
            </form>
            <form method="POST" action="{{ route($uuid. '.power', ['service' => $service, 'power' => 'restart']) }}" class="w-full">
                @csrf
                <button class="w-full ml-2 btn-danger text-center py-2 px-4">
                    {{ __('provisioning.restart') }}
                </button>
            </form>
        @endif
    </div>
</div>

