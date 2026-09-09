@if (($variant ?? 'default') === 'tailwind')
    <tr>
        <th scope="col" style="width:5%;{{ $thCellStyle ?? '' }}">#</th>
        <th scope="col" style="width:36%;{{ $thCellStyle ?? '' }}">Descripción</th>
        <th scope="col" style="width:10%;{{ $thCellStyle ?? '' }}">Cantidad</th>
        <th scope="col" style="width:10%;{{ $thCellStyle ?? '' }}">Unidad</th>
        <th scope="col" style="width:19%;{{ $thCellStyle ?? '' }}">Precio unitario</th>
        <th scope="col" style="width:20%;{{ $thCellStyle ?? '' }}">Importe</th>
    </tr>
@else
    <tr>
        <th scope="col">#</th>
        <th scope="col">Descripción</th>
        <th scope="col">Cantidad</th>
        <th scope="col">Unidad</th>
        <th scope="col">Precio Unitario</th>
        <th scope="col">Importe</th>
    </tr>
@endif
