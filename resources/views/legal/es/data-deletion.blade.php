@php $box = 'bg-white p-5 sm:p-6 rounded-2xl border border-zinc-200 space-y-3'; $h2 = 'text-base font-bold text-zinc-900'; @endphp

<p class="text-xs text-zinc-500">Válido para la cuenta del portal y para las redes sociales conectadas (YouTube, Instagram, Facebook, TikTok y otras).</p>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">1. Solo desconectar una red social</h2>
    <ol class="list-decimal pl-5 space-y-1">
        <li>Entra en el portal y abre <strong>Redes sociales</strong>.</li>
        <li>En la tarjeta de la red, pulsa <strong>Desconectar</strong>.</li>
    </ol>
    <p>Los tokens de acceso se borran de nuestro servidor al momento (en YouTube/Google, TikTok y X el acceso también se revoca en la propia red). Los envíos pendientes para esa cuenta se cancelan.</p>
    <p>También puedes revocarlo desde la propia red, sin pasar por nosotros: en Google en
        <a href="https://myaccount.google.com/permissions" class="text-orange-700 font-semibold underline" target="_blank" rel="noopener noreferrer">myaccount.google.com/permissions</a>;
        en Facebook e Instagram, en Configuración &gt; Aplicaciones y sitios web; en Threads, en la configuración de aplicaciones y sitios web de la cuenta; en LinkedIn, en Configuración &gt; Privacidad de datos &gt; Servicios permitidos; en Pinterest, en la configuración de seguridad, en aplicaciones con acceso a la cuenta; en TikTok, en Configuración &gt; Seguridad y permisos &gt; Aplicaciones y servicios; en X, en Configuración &gt; Seguridad y acceso a la cuenta &gt; Aplicaciones y sesiones.</p>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">2. Eliminar tu cuenta y todos los datos</h2>
    <p>Envía un e-mail a <a href="mailto:{{ $identity['email'] }}?subject=Eliminaci%C3%B3n%20de%20datos" class="text-orange-700 font-semibold underline">{{ $identity['email'] }}</a> con el asunto <strong>Eliminación de datos</strong>, desde el e-mail registrado en el portal.</p>
    <ul class="list-disc pl-5 space-y-1">
        <li><strong>Plazo:</strong> respondemos en un máximo de 15 días hábiles, dentro del plazo del RGPD y la LOPDGDD.</li>
        <li><strong>Lo que borramos:</strong> tokens y datos de las redes conectadas, historial de publicaciones, vídeos enviados y datos de perfil.</li>
        <li><strong>Lo que podemos tener que conservar:</strong> facturas y registros exigidos por la normativa fiscal, durante el plazo legal.</li>
    </ul>
</section>

<p class="text-xs text-zinc-500">Más detalles sobre los datos que tratamos en nuestra <a href="{{ route('legal.privacy') }}#redes-sociais" class="text-orange-700 font-semibold underline">Política de privacidad</a>.</p>
