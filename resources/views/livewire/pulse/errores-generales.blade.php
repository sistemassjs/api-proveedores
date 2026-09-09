<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="Errores ocurridos"
        x-bind:title="`Tiempo: {{ number_format($time) }}ms; Actualizado: ${formatDate('{{ $runAt }}')};`"
        details="past {{ $this->periodForHumans() }}">
        <x-slot:icon>
            <x-pulse::icons.bug-ant />
        </x-slot:icon>
        <x-slot:actions>
            <x-pulse::select
                wire:model.live="orderBy"
                id="select-errores-order-by"
                label="Ordenar por"
                :options="[
                    'latest' => 'mas reciente',
                    'count' => 'cantidad',
                ]"
                @change="loading = true" />
        </x-slot:actions>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand" wire:poll.10s="">
        <div class="mb-3 text-sm text-gray-600 dark:text-gray-300">
            Total de errores detectados en el periodo: <strong>{{ number_format($erroresTotales) }}</strong>
        </div>

        @if ($errores->isEmpty())
        <x-pulse::no-results />
        @else
        <x-pulse::table>
            <colgroup>
                <col width="55%" />
                <col width="25%" />
                <col width="20%" />
            </colgroup>
            <x-pulse::thead>
                <tr>
                    <x-pulse::th>Error / motivo general</x-pulse::th>
                    <x-pulse::th class="text-right">Ultimo</x-pulse::th>
                    <x-pulse::th class="text-right">Cantidad</x-pulse::th>
                </tr>
            </x-pulse::thead>
            <tbody>
                @foreach ($errores->take(30) as $error)
                <tr wire:key="error-general-{{ md5($error->clase.$error->ubicacion) }}-spacer" class="h-2 first:h-0"></tr>
                <tr wire:key="error-general-{{ md5($error->clase.$error->ubicacion) }}-row">
                    <x-pulse::td class="max-w-[1px]">
                        <code class="block text-xs text-gray-900 dark:text-gray-100 truncate" title="{{ $error->clase }}">
                            {{ $error->clase }}
                        </code>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 truncate" title="{{ $error->ubicacion }}">
                            {{ $error->ubicacion }}
                        </p>
                    </x-pulse::td>
                    <x-pulse::td numeric class="text-gray-700 dark:text-gray-300 font-bold">
                        {{ $error->ultimo->ago(syntax: Carbon\CarbonInterface::DIFF_ABSOLUTE, short: true) }}
                    </x-pulse::td>
                    <x-pulse::td numeric class="text-gray-700 dark:text-gray-300 font-bold">
                        {{ number_format($error->conteo) }}
                    </x-pulse::td>
                </tr>
                @endforeach
            </tbody>
        </x-pulse::table>

        @if ($errores->count() > 30)
        <div class="mt-2 text-xs text-gray-400 text-center">Mostrando 30 errores</div>
        @endif
        @endif
    </x-pulse::scroll>
</x-pulse::card>