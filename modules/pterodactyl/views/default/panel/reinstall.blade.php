<div class="card">
    <div>
        <h2 class="text-lg font-bold mb-4">{{ __('pterodactyl::panel.reinstall_server') }}</h2>
        <div class="alert text-red-700 bg-red-100 mb-4" role="alert">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
            </svg>
            {!! __('pterodactyl::panel.reinstall_warning') !!}
        </div>

        <form method="POST" action="{{ route('pterodactyl.changeEgg', $service) }}" id="changeEggForm">
            @csrf
            @php
                $currentEggId = $server->attributes->egg ?? ($server->attributes->container->egg_id ?? null);
                $availableEggs = [];
                if (isset($eggs) && is_array($eggs) && count($eggs) > 0) {
                    foreach ($eggs as $id => $egg) {
                        if ($currentEggId == $id) {
                            $availableEggs[$id] = $egg . ' (' . __('pterodactyl::panel.current_egg') . ')';
                        } else {
                            $availableEggs[$id] = $egg;
                        }
                    }
                } else {
                    $availableEggs[''] = __('pterodactyl::panel.no_egg_available');
                }
            @endphp
            @include('shared.select', [
                'name' => 'egg_id',
                'label' => __('pterodactyl::panel.change_egg'),
                'options' => $availableEggs,
                'value' => $currentEggId,
            ])
            <div class="flex gap-2 mt-4">
                <button type="submit" class="btn-primary w-full">{{ __('pterodactyl::panel.reinstall') }}</button>
            </div>
        </form>
    </div>
</div>
