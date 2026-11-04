@php $box = 'bg-white p-5 sm:p-6 rounded-2xl border border-zinc-200 space-y-3'; $h2 = 'text-base font-bold text-zinc-900'; @endphp

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">1. El servicio</h2>
    <p>{{ $identity['company'] }} ofrece una plataforma para productoras audiovisuales y sus clientes: briefing, guiones, aprobación, entrega de vídeos y publicación en las redes sociales del cliente.</p>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">2. Cuenta y responsabilidades</h2>
    <ul class="list-disc pl-5 space-y-1">
        <li>Eres responsable de tus datos de acceso y de las acciones realizadas con tu cuenta.</li>
        <li>Declaras tener los derechos sobre el material que envías y sobre las cuentas de redes sociales que conectas.</li>
        <li>El contenido publicado debe respetar la ley y las normas de cada red. Podemos rechazar o suspender el envío de contenido que las incumpla.</li>
    </ul>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">3. Publicación en redes sociales</h2>
    <ul class="list-disc pl-5 space-y-1">
        <li>Solo publicamos en tu cuenta cuando lo pides (o lo programas) y únicamente los vídeos producidos para ti.</li>
        <li>Puedes desconectar una red en cualquier momento desde el portal o desde la configuración de la propia red.</li>
        <li>Al conectar YouTube aceptas también los <a href="https://www.youtube.com/t/terms" class="text-orange-700 font-semibold underline" target="_blank" rel="noopener noreferrer">Términos del servicio de YouTube</a>.</li>
        <li>Las redes pueden limitar, aplazar o rechazar publicaciones (cuotas, revisión de contenido, cambios de política). No controlamos esas decisiones ni garantizamos alcance o resultados.</li>
    </ul>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">4. Propuestas, pagos y entrega</h2>
    <p>Los importes, la forma de pago y las condiciones de entrega y descarga de los archivos (incluido el pago con señal y resto a la entrega) son los de la propuesta aprobada. Los precios que muestran la web o la Sofi, asistente de inteligencia artificial, son orientativos hasta la emisión de la propuesta.</p>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">5. Datos personales</h2>
    <p>El tratamiento de datos sigue nuestra <a href="{{ route('legal.privacy') }}" class="text-orange-700 font-semibold underline">Política de privacidad</a>. Para eliminar datos, consulta <a href="{{ route('legal.data-deletion') }}" class="text-orange-700 font-semibold underline">Eliminación de datos</a>.</p>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">6. Contacto</h2>
    <p>Dudas sobre estos términos: <a href="mailto:{{ $identity['email'] }}" class="text-orange-700 font-semibold underline">{{ $identity['email'] }}</a>.</p>
</section>
