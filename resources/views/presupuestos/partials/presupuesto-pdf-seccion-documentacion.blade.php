@php
    $documentacionLista = $documentacionLista ?? [];
    $variant = (string) ($variant ?? 'tailwind');
@endphp
@if (count($documentacionLista) > 0)
    <div class="{{ $variant === 'tailwind' ? 'tw-page-break' : 'page-break' }}"></div>
    <div class="pdf-seccion pdf-seccion--documentacion">
        @foreach ($documentacionLista as $docIndex => $documento)
            @if ($docIndex > 0)
                <div class="{{ $variant === 'tailwind' ? 'tw-page-break' : 'page-break' }}"></div>
            @endif
            <div class="pdf-seccion-documentacion__pagina">
                @if ($variant === 'tailwind')
                    <div class="tw-anexos-header">
                        <div class="tw-anexos-title">Documentación</div>
                    </div>
                @else
                    <div class="anexos-preview-header">
                        <div class="anexos-preview-title">Documentación</div>
                    </div>
                @endif
                <div class="{{ $variant === 'tailwind' ? 'tw-anexo-simple-heading' : 'anexo-simple-heading' }}">
                    {{ $documento['titulo'] ?? 'Documento' }}
                </div>
                @if (!empty($documento['descripcion']))
                    <div class="{{ $variant === 'tailwind' ? 'tw-anexo-simple-desc' : 'anexo-simple-desc' }}">
                        {{ $documento['descripcion'] }}
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif
