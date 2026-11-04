@php $box = 'bg-white p-5 sm:p-6 rounded-2xl border border-zinc-200 space-y-3'; $h2 = 'text-base font-bold text-zinc-900'; @endphp

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">1. Titular del sitio web</h2>
    <p>En cumplimiento del artículo 10 de la Ley 34/2002, de Servicios de la Sociedad de la Información y de Comercio Electrónico (LSSI-CE):</p>
    <ul class="space-y-1">
        <li><strong>Titular:</strong> {{ $identity['company'] }}</li>
        <li><strong>NIF/CIF:</strong> {{ $identity['cif'] ?? $pending }}</li>
        <li><strong>Domicilio:</strong> {{ $identity['address'] ?? $pending }}</li>
        <li><strong>E-mail:</strong> <a href="mailto:{{ $identity['email'] }}" class="text-orange-700 font-semibold underline">{{ $identity['email'] }}</a></li>
        <li><strong>Actividad:</strong> producción audiovisual, estrategia de contenido en vídeo y plataforma de gestión para productoras.</li>
    </ul>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">2. Condiciones de uso</h2>
    <p>El acceso a este sitio web es gratuito y supone la aceptación de este aviso legal. Te comprometes a usar el sitio y el portal de forma lícita, sin dañar su funcionamiento ni acceder a cuentas o datos de terceros. Las condiciones de cada contratación se detallan en la propuesta y en los <a href="{{ route('legal.terms') }}" class="text-orange-700 font-semibold underline">términos del servicio</a>.</p>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">3. Propiedad intelectual</h2>
    <p>Los textos, diseños, marcas y vídeos de este sitio pertenecen a su titular o a sus clientes, que han autorizado su exhibición. No se permite su reproducción ni distribución sin autorización. Los derechos sobre los vídeos producidos para cada cliente se rigen por lo pactado en su contratación.</p>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">4. Responsabilidad</h2>
    <p>Procuramos que la información sea correcta y esté actualizada. Los precios que muestran la web y la Sofi son orientativos hasta la emisión de la propuesta, que es el documento que vale para la contratación. No respondemos del contenido de sitios de terceros enlazados.</p>
    <p>La Sofi es una asistente de inteligencia artificial: sus respuestas pueden contener errores y no sustituyen la propuesta formal ni la atención de una persona del equipo.</p>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">5. Protección de datos y cookies</h2>
    <p>El tratamiento de tus datos se explica en la <a href="{{ route('legal.privacy') }}" class="text-orange-700 font-semibold underline">política de privacidad</a> y el uso de cookies en la <a href="{{ route('legal.cookies') }}" class="text-orange-700 font-semibold underline">política de cookies</a>.</p>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">6. Legislación aplicable</h2>
    <p>Este aviso legal se rige por la legislación española. Si eres consumidor, podrás acudir a los tribunales de tu domicilio.</p>
</section>
