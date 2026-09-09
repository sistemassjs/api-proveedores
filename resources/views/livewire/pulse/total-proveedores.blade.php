<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="Proveedores registrados"
        x-bind:title="`Tiempo: {{ number_format($time) }}ms; Actualizado: ${formatDate('{{ $runAt }}')};`"
        details="total de empresas proveedoras"
    >
        <x-slot:icon>
            <x-pulse::icons.circle-stack />
        </x-slot:icon>
    </x-pulse::card-header>

    <div class="px-6 pb-6 pt-2">
        <p class="text-5xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">
            {{ number_format($totalProveedores) }}
        </p>
    </div>
</x-pulse::card>
