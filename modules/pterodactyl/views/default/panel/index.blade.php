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
                <button class="w-full mr-2 py-2 px-4 mt-4 btn-primary text-center">
                    {{ __('provisioning.start') }}
                </button>
            </form>
            <button type="button" class="w-full ml-2 py-2 px-4 mt-4 btn-primary text-center" onclick="document.getElementById('eggOverlay').style.display='block'">Réinstaller</button>

            <!-- Overlay -->
            <div id="eggOverlay" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(17,24,39,0.85); z-index:50;">
                <div style="max-width:400px; margin:10vh auto; background:#1e293b; border-radius:8px; padding:32px; color:#fff; box-shadow:0 0 20px #000;">
                    <h2 class="text-lg font-bold mb-4">Réinstaller le serveur</h2>
                    <div class="mb-4 p-3 rounded bg-red-600 text-white text-sm">
                        <strong>Attention :</strong> La réinstallation va <u>supprimer tous les fichiers</u> présents sur le serveur.
                    </div>

                    <form method="POST" action="{{ route('pterodactyl.changeEgg', $service) }}" id="changeEggForm" onsubmit="return validateEggSelection();">
                        @csrf
                        <label for="egg_id" class="block text-sm font-medium mb-2">Changer l'egg (optionnel)</label>
                        @php
                            $currentEggId = $server->attributes->egg ?? ($server->attributes->container->egg_id ?? null);
                        @endphp
                        <select name="egg_id" id="egg_id" class="block w-full rounded-md border-gray-300 shadow-sm mb-4" style="background-color: #111827; color: #fff;" onchange="eggSelectionChanged()">
                            <option value="">-- Ne pas changer l'egg --</option>
                            @if(isset($eggs) && is_array($eggs) && count($eggs) > 0)
                                @foreach($eggs as $id => $egg)
                                    @if($currentEggId != $id)
                                        <option value="{{ $id }}">{{ $egg }}</option>
                                    @endif
                                @endforeach
                            @else
                                <option disabled>{{ __('Aucun egg disponible') }}</option>
                            @endif
                        </select>
                        @if($currentEggId && isset($eggs[$currentEggId]))
                            <div class="mb-4 text-xs text-gray-400">{{ __('Egg actuel :') }} {{ $eggs[$currentEggId] }}</div>
                        @endif
                        <div class="flex gap-2 mt-4">
                            <button type="button" id="confirmEggBtn" style="display:none" class="px-4 py-2 bg-indigo-600 text-white rounded w-full" onclick="showReinstallBtn()">Confirmer l'egg sélectionné</button>
                            <button type="submit" id="reinstallEggBtn" style="display:none" class="px-4 py-2 bg-indigo-600 text-white rounded w-full">Changer l'egg et réinstaller</button>
                        </div>
                    </form>

                    <!-- Réinstaller sans changer + Annuler côte à côte -->
                    <div class="flex gap-2 mt-4">
                        <form method="POST" action="{{ route('pterodactyl.changeEgg', $service) }}" class="w-full">
                            @csrf
                            <input type="hidden" name="egg_id" value="{{ $currentEggId }}">
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded w-full">Réinstaller sans changer l'egg</button>
                        </form>
                        <button type="button" class="px-4 py-2 bg-gray-700 text-white rounded w-full" onclick="document.getElementById('eggOverlay').style.display='none'">Annuler</button>
                    </div>
                </div>
            </div>
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

<script>
function eggSelectionChanged() {
    var select = document.getElementById('egg_id');
    var confirmBtn = document.getElementById('confirmEggBtn');
    var reinstallBtn = document.getElementById('reinstallEggBtn');
    if (select.value && select.value !== '{{ $currentEggId }}') {
        confirmBtn.style.display = 'block';
        reinstallBtn.style.display = 'none';
    } else {
        confirmBtn.style.display = 'none';
        reinstallBtn.style.display = 'none';
    }
}
function showReinstallBtn() {
    document.getElementById('confirmEggBtn').style.display = 'none';
    document.getElementById('reinstallEggBtn').style.display = 'block';
}
function validateEggSelection() {
    var select = document.getElementById('egg_id');
    if (document.getElementById('reinstallEggBtn').style.display === 'block' && !select.value) {
        alert('Veuillez sélectionner un egg avant de réinstaller avec changement d\'egg.');
        return false;
    }
    return true;
}
</script>
