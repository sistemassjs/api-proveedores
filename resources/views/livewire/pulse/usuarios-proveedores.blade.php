<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="Usuarios y proveedores"
        x-bind:title="`Tiempo: {{ number_format($time) }}ms; Actualizado: ${formatDate('{{ $runAt }}')};`"
        details="ultimos 14 dias">
        <x-slot:icon>
            <x-pulse::icons.circle-stack />
        </x-slot:icon>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand" wire:poll.30s="">
        <div class="grid grid-cols-2 gap-3 mb-4">
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                <p class="text-xs text-gray-500 dark:text-gray-400">Usuarios totales</p>
                <p class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($usuariosTotal) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                <p class="text-xs text-gray-500 dark:text-gray-400">Proveedores totales</p>
                <p class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($proveedoresTotal) }}</p>
            </div>
        </div>

        <x-pulse::table>
            <colgroup>
                <col width="60%" />
                <col width="40%" />
            </colgroup>
            <x-pulse::thead>
                <tr>
                    <x-pulse::th>Fecha</x-pulse::th>
                    <x-pulse::th class="text-right">Usuarios registrados</x-pulse::th>
                </tr>
            </x-pulse::thead>
            <tbody>
                @foreach ($usuariosPorDia as $fila)
                <tr wire:key="usuarios-dia-{{ $fila->fecha }}">
                    <x-pulse::td>
                        {{ \Carbon\Carbon::parse($fila->fecha)->format('d/m/Y') }}
                    </x-pulse::td>
                    <x-pulse::td numeric class="text-gray-700 dark:text-gray-300 font-bold">
                        {{ number_format($fila->total) }}
                    </x-pulse::td>
                </tr>
                @endforeach
            </tbody>
        </x-pulse::table>
    </x-pulse::scroll>
</x-pulse::card>