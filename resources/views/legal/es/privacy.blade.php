@php $box = 'bg-white p-5 sm:p-6 rounded-2xl border border-zinc-200 space-y-3'; $h2 = 'text-base font-bold text-zinc-900'; @endphp

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">1. Responsable del tratamiento</h2>
    <ul class="space-y-1">
        <li><strong>Titular:</strong> {{ $identity['company'] }}</li>
        <li><strong>NIF/CIF:</strong> {{ $identity['cif'] ?? $pending }}</li>
        <li><strong>Domicilio:</strong> {{ $identity['address'] ?? $pending }}</li>
        <li><strong>Contacto de privacidad:</strong> <a href="mailto:{{ $identity['email'] }}" class="text-orange-700 font-semibold underline">{{ $identity['email'] }}</a></li>
    </ul>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">2. Qué datos tratamos</h2>
    <ul class="list-disc pl-5 space-y-1">
        <li><strong>Identificación y contacto:</strong> nombre, empresa, e-mail y teléfono que nos facilitas (formularios, portal o WhatsApp).</li>
        <li><strong>Facturación:</strong> razón social, NIF/CIF y dirección para emitir la factura. Se guardan cifrados.</li>
        <li><strong>Conversaciones:</strong> mensajes que envías a la Sofi en la web o por WhatsApp, y las respuestas.</li>
        <li><strong>Proyecto:</strong> información de tu marca (web pública, redes que indiques), guiones, vídeos y aprobaciones.</li>
        <li><strong>Redes sociales que conectes:</strong> identificador de la cuenta y credenciales de acceso de la red (cifradas), solo para publicar los vídeos que apruebes.</li>
        <li><strong>Pagos:</strong> estado y referencia del pago. Los datos de la tarjeta los trata directamente la pasarela; nosotros no los vemos ni los guardamos.</li>
        <li><strong>Datos técnicos:</strong> registros de seguridad y de uso necesarios para el funcionamiento del servicio.</li>
    </ul>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">3. Para qué y con qué base legal</h2>
    <ul class="list-disc pl-5 space-y-1">
        <li><strong>Atenderte, preparar presupuestos y prestar el servicio contratado</strong> (producción, portal, publicación): ejecución de un contrato o medidas precontractuales a tu solicitud (art. 6.1.b RGPD).</li>
        <li><strong>Facturar y cumplir obligaciones fiscales y contables:</strong> obligación legal (art. 6.1.c RGPD).</li>
        <li><strong>Seguimiento comercial de tu solicitud</strong> (CRM) y seguridad de la plataforma: interés legítimo (art. 6.1.f RGPD). Puedes oponerte en cualquier momento.</li>
        <li><strong>Contenido externo</strong> (reproductores de YouTube y Vimeo): tu consentimiento, que puedes retirar en <a href="{{ route('legal.cookies') }}" class="text-orange-700 font-semibold underline">Política de cookies</a>.</li>
    </ul>
    <p>No enviamos comunicaciones comerciales por e-mail sin tu consentimiento previo.</p>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">4. Inteligencia artificial</h2>
    <p>La <strong>Sofi es una asistente de inteligencia artificial</strong>, no una persona. Responde en la web y en WhatsApp, recomienda paquetes del catálogo y prepara propuestas con los precios configurados por la productora. No toma decisiones con efectos jurídicos sobre ti: la contratación y el pago siempre los confirmas tú, y puedes pedir hablar con una persona del equipo en cualquier momento.</p>
    <p>Para generar las respuestas, el texto de la conversación se envía al proveedor del modelo de IA (Google, modelos Gemini).</p>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">5. Quién recibe los datos (encargados del tratamiento)</h2>
    <ul class="list-disc pl-5 space-y-1">
        <li><strong>Google:</strong> modelo de IA (Gemini) y envío de e-mails del sistema.</li>
        <li><strong>Meta Platforms:</strong> WhatsApp Business (mensajes) y, si los conectas, Instagram y Facebook.</li>
        <li><strong>Pasarelas de pago:</strong> Redsys (tarjeta y Bizum) y, cuando esté activo, Stripe.</li>
        <li><strong>Redes sociales que tú conectes</strong> para publicar tus vídeos (YouTube, TikTok, LinkedIn, Pinterest, X, Threads).</li>
        <li><strong>Proveedor de alojamiento</strong> del servidor de la plataforma.</li>
        <li>Administraciones públicas cuando la ley lo exija (por ejemplo, la Agencia Tributaria).</li>
    </ul>
    <p><strong>Transferencias internacionales:</strong> Google, Meta y Stripe pueden tratar datos fuera del Espacio Económico Europeo (EE. UU.), con las garantías del Marco de Privacidad de Datos UE-EE. UU. o de las cláusulas contractuales tipo de la Comisión Europea.</p>
</section>

<section id="redes-sociais" class="{{ $box }}">
    <h2 class="{{ $h2 }}">5 bis. Cuentas de redes sociales conectadas (YouTube, Instagram, Facebook, TikTok y otras)</h2>
    <p>Cuando conectas una red social en el portal, la autorización se hace en la página oficial de la propia red. Nunca recibimos ni guardamos tu contraseña.</p>
    <ul class="list-disc pl-5 space-y-1">
        <li><strong>Datos que recibimos:</strong> identificador, nombre, @ y foto de perfil del canal o de la cuenta conectada, y las claves de acceso (tokens) que entrega la red.</li>
        <li><strong>Para qué los usamos:</strong> exclusivamente para publicar en tu cuenta, a petición tuya, los vídeos producidos para ti, y para mostrar en el portal qué cuenta está conectada.</li>
        <li><strong>Lo que no hacemos:</strong> no leemos mensajes ni tus otros vídeos o publicaciones, no vendemos ni compartimos estos datos y no los usamos para publicidad ni para entrenar modelos de IA.</li>
        <li><strong>Cómo los guardamos:</strong> los tokens se guardan cifrados en nuestro servidor y solo se usan al publicar o al renovar el acceso.</li>
    </ul>
    <p><strong>YouTube y Google.</strong> Usamos los YouTube API Services. Al conectar YouTube aceptas también los
        <a href="https://www.youtube.com/t/terms" class="text-orange-700 font-semibold underline" target="_blank" rel="noopener noreferrer">Términos del servicio de YouTube</a> y la
        <a href="https://policies.google.com/privacy" class="text-orange-700 font-semibold underline" target="_blank" rel="noopener noreferrer">Política de privacidad de Google</a>.
        Puedes revocar el acceso en cualquier momento en
        <a href="https://myaccount.google.com/permissions" class="text-orange-700 font-semibold underline" target="_blank" rel="noopener noreferrer">myaccount.google.com/permissions</a>.</p>
    <p class="text-xs text-zinc-600">
        {{ $identity['company'] }}'s use and transfer to any other app of information received from Google APIs will adhere to the
        <a href="https://developers.google.com/terms/api-services-user-data-policy" class="text-orange-700 font-semibold underline" target="_blank" rel="noopener noreferrer">Google API Services User Data Policy</a>,
        including the Limited Use requirements.
    </p>
    <p><strong>Cómo desconectar y borrar:</strong> en el portal, en Redes sociales, usa el botón <em>Desconectar</em>: el acceso se revoca en la red y los tokens se borran al momento. Para borrar también el historial de publicaciones y demás datos, consulta <a href="{{ route('legal.data-deletion') }}" class="text-orange-700 font-semibold underline">eliminación de datos</a>.</p>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">6. Cuánto tiempo los guardamos</h2>
    <ul class="list-disc pl-5 space-y-1">
        <li><strong>Clientes:</strong> mientras dure la relación y, después, el tiempo exigido por la ley (facturación y contabilidad: hasta 6 años, Código de Comercio).</li>
        <li><strong>Solicitudes que no se convierten en contrato:</strong> se anonimizan automáticamente a los {{ (int) config('crm.anonymize_lost_after_months', 24) }} meses.</li>
        <li><strong>Conversaciones:</strong> mientras sean necesarias para atenderte o hasta que solicites su supresión.</li>
    </ul>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">7. Tus derechos</h2>
    <p>Puedes ejercer tus derechos de <strong>acceso, rectificación, supresión, oposición, limitación del tratamiento y portabilidad</strong>, y retirar tu consentimiento cuando quieras, escribiendo a <a href="mailto:{{ $identity['email'] }}" class="text-orange-700 font-semibold underline">{{ $identity['email'] }}</a>. Responderemos en el plazo de un mes.</p>
    <p>Si consideras que no hemos atendido bien tu solicitud, puedes presentar una reclamación ante la <strong>Agencia Española de Protección de Datos</strong> (<a href="https://www.aepd.es" target="_blank" rel="noopener noreferrer" class="text-orange-700 font-semibold underline">www.aepd.es</a>).</p>
    <p>Para solicitar la eliminación de los datos de una red social conectada, consulta también <a href="{{ route('legal.data-deletion') }}" class="text-orange-700 font-semibold underline">eliminación de datos</a>.</p>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">8. Seguridad</h2>
    <p>Usamos conexión cifrada (HTTPS), cifrado de los datos de facturación y de las credenciales de redes sociales, control de acceso por empresa y registros de seguridad sin datos sensibles. Nuestros servicios se dirigen a empresas y profesionales mayores de edad.</p>
</section>
