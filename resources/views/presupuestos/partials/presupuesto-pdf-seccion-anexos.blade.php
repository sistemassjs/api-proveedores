@php
    $anexosLista = $anexosLista ?? [];
    $variant = (string) ($variant ?? 'tailwind');
    $tituloAnexos = trim((string) ($tituloAnexos ?? ''));
    if ($tituloAnexos === '') {
        $tituloAnexos = 'Anexos';
    }
@endphp
@if (count($anexosLista) > 0)
    {{-- Salto explícito: evita que anexos compartan hoja con Atentamente (page_script). --}}
    <div class="{{ $variant === 'tailwind' ? 'tw-page-break' : 'page-break' }}"></div>
    <div class="pdf-seccion pdf-seccion--anexos">
        @foreach (collect($anexosLista)->chunk(4) as $pageIndex => $anexosPagina)
            @if ($pageIndex > 0)
                <div class="{{ $variant === 'tailwind' ? 'tw-page-break' : 'page-break' }}"></div>
            @endif
            <div class="{{ $variant === 'tailwind' ? 'tw-anexos-page' : 'anexos-page' }}">
                @if ($variant === 'tailwind')
                    <div class="tw-anexos-header">
                        <div class="tw-anexos-title">{{ $tituloAnexos }}</div>
                    </div>
                    <div class="tw-anexos-list">
                        @foreach ($anexosPagina as $index => $anexo)
                            @php $numeroAnexo = (($pageIndex * 4) + $index + 1); @endphp
                            <div class="tw-anexo-simple">
                                <table class="tw-anexo-simple-table">
                                    <tr>
                                        @if (!empty($anexo['archivo_base64']))
                                            <td class="tw-anexo-simple-media">
                                                <div class="tw-anexo-simple-image-wrap">
                                                    <img src="{{ $anexo['archivo_base64'] }}" alt="{{ $anexo['titulo'] ?? ('Anexo ' . (($anexo['orden'] ?? 0) ?: $numeroAnexo)) }}" class="tw-anexo-simple-image" />
                                                </div>
                                            </td>
                                        @endif
                                        <td class="tw-anexo-simple-text">
                                            <div class="tw-anexo-simple-heading">{{ $anexo['titulo'] ?? '' }}</div>
                                            @if (!empty($anexo['descripcion']))
                                                <div class="tw-anexo-simple-desc">{{ $anexo['descripcion'] }}</div>
                                            @endif
                                            @if (array_key_exists('precio', $anexo) && $anexo['precio'] !== null)
                                                <div class="tw-anexo-simple-price">${{ number_format((float) $anexo['precio'], 2, '.', ',') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="anexos-preview-header">
                        <div class="anexos-preview-title">{{ $tituloAnexos }}</div>
                    </div>
                    @foreach ($anexosPagina as $index => $anexo)
                        @php $numeroAnexo = (($pageIndex * 4) + $index + 1); @endphp
                        <div class="anexo-simple">
                            <table class="anexo-simple-table">
                                <tr>
                                    @if (!empty($anexo['archivo_base64']))
                                        <td class="anexo-simple-media">
                                            <div class="anexo-simple-image-wrap">
                                                <img src="{{ $anexo['archivo_base64'] }}" alt="{{ $anexo['titulo'] ?? ('Anexo ' . (($anexo['orden'] ?? 0) ?: $numeroAnexo)) }}" class="anexo-simple-image" />
                                            </div>
                                        </td>
                                    @endif
                                    <td class="anexo-simple-text">
                                        <div class="anexo-simple-heading">{{ $anexo['titulo'] ?? '' }}</div>
                                        @if (!empty($anexo['descripcion']))
                                            <div class="anexo-simple-desc">{{ $anexo['descripcion'] }}</div>
                                        @endif
                                        @if (array_key_exists('precio', $anexo) && $anexo['precio'] !== null)
                                            <div class="anexo-simple-price">${{ number_format((float) $anexo['precio'], 2, '.', ',') }}</div>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                    @endforeach
                @endif
            </div>
        @endforeach
    </div>
@endif
