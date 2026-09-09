<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="Usuarios registrados"
        x-bind:title="`Tiempo: {{ number_format($time) }}ms; Actualizado: ${formatDate('{{ $runAt }}')};`"
        details="total en plataforma"
    >
        <x-slot:icon>
            <x-pulse::icons.sparkles />
        </x-slot:icon>
    </x-pulse::card-header>

    <div class="px-6 pb-6 pt-2">
        <p class="text-5xl font-bold tracking-tight text-blue-600 dark:text-blue-400">
            {{ number_format($totalUsuarios) }}
        </p>
    </div>
</x-pulse::card>
