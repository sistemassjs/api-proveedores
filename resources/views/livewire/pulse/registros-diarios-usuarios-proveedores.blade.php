<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="Registros por día"
        x-bind:title="`Tiempo: {{ number_format($time) }}ms; Actualizado: ${formatDate('{{ $runAt }}')};`"
        details="ultimos 14 dias">
        <x-slot:icon>
            <x-pulse::icons.arrow-trending-up />
        </x-slot:icon>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand" wire:poll.30s="">
        <div class="flex items-center gap-4 px-2 pb-3">
            <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                <span class="inline-block w-3 h-3 rounded bg-blue-500"></span> Usuarios
            </div>
            <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                <span class="inline-block w-3 h-3 rounded bg-emerald-500"></span> Proveedores
            </div>
        </div>

        <div
            wire:ignore
            class="h-[calc(100vh-20rem)] min-h-[80vh] px-2 pb-2"
            x-data="registrosUsuariosProveedoresChart({
                labels: @js(collect($series)->pluck('fecha')->map(fn ($fecha) => \Carbon\Carbon::parse($fecha)->format('d/m'))->values()),
                usuarios: @js(collect($series)->pluck('usuarios')->values()),
                proveedores: @js(collect($series)->pluck('proveedores')->values()),
            })">
            <canvas x-ref="canvas" class="ring-1 ring-gray-900/5 dark:ring-gray-100/10 bg-gray-50 dark:bg-gray-800 rounded-md shadow-sm"></canvas>
        </div>
    </x-pulse::scroll>
</x-pulse::card>

@script
<script>
    Alpine.data('registrosUsuariosProveedoresChart', (config) => ({
        init() {
            new Chart(this.$refs.canvas, {
                type: 'line',
                data: {
                    labels: config.labels,
                    datasets: [{
                            label: 'Usuarios',
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59,130,246,0.20)',
                            data: config.usuarios,
                            tension: 0.25,
                            fill: true,
                        },
                        {
                            label: 'Proveedores',
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16,185,129,0.20)',
                            data: config.proveedores,
                            tension: 0.25,
                            fill: true,
                        },
                    ],
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            ticks: {
                                color: '#6b7280',
                            },
                            grid: {
                                color: 'rgba(107,114,128,0.1)',
                            },
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                                color: '#6b7280',
                            },
                            grid: {
                                color: 'rgba(107,114,128,0.1)',
                            },
                        },
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                boxWidth: 10,
                                usePointStyle: true,
                            },
                        },
                    },
                },
            });
        },
    }))
</script>
@endscript