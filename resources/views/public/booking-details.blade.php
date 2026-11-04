<dl class="bh-public-details">
    <div><dt>Servicio</dt><dd>{{ $summary['service'] }}</dd></div>
    <div><dt>Profesional</dt><dd>{{ $summary['barber'] }}</dd></div>
    <div><dt>Fecha y hora</dt><dd><time datetime="{{ $summary['starts_at'] }}">{{ str_replace('T', ' · ', $summary['starts_at']) }}</time> ({{ $summary['timezone'] }})</dd></div>
    <div><dt>Duración</dt><dd>{{ $summary['duration'] }} minutos</dd></div>
    <div><dt>Precio</dt><dd>{{ $summary['price'] }}</dd></div>
    <div><dt>Nombre</dt><dd>{{ $summary['name'] }}</dd></div>
    <div><dt>Correo</dt><dd>{{ $summary['email'] }}</dd></div>
    @if ($summary['phone'])<div><dt>Teléfono</dt><dd>{{ $summary['phone'] }}</dd></div>@endif
</dl>
<x-barbershop-location :barbershop="$barbershop" />
