@php $box = 'bg-white p-5 sm:p-6 rounded-2xl border border-zinc-200 space-y-3'; $h2 = 'text-base font-bold text-zinc-900'; $th = 'text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 py-2 pr-3'; $td = 'py-2 pr-3 align-top'; @endphp

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">1. Qué son y cómo las usamos</h2>
    <p>Las cookies son pequeños archivos que el navegador guarda al visitar una web. {{ $identity['company'] }} <strong>no usa cookies de análisis, publicidad ni seguimiento</strong>. Solo usamos las cookies necesarias para que la plataforma funcione y, si lo aceptas, el contenido externo de los reproductores de vídeo.</p>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">2. Cookies necesarias (no requieren consentimiento)</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr><th class="{{ $th }}">Cookie</th><th class="{{ $th }}">Finalidad</th><th class="{{ $th }}">Duración</th></tr></thead>
            <tbody class="divide-y divide-zinc-100">
                <tr><td class="{{ $td }} font-mono">{{ $sessionCookie }}</td><td class="{{ $td }}">Mantiene tu sesión (inicio de sesión, idioma, conversación con la Sofi).</td><td class="{{ $td }}">{{ $sessionMinutes }} minutos de inactividad</td></tr>
                <tr><td class="{{ $td }} font-mono">XSRF-TOKEN</td><td class="{{ $td }}">Protege los formularios contra envíos falsificados (seguridad).</td><td class="{{ $td }}">Sesión</td></tr>
                <tr><td class="{{ $td }} font-mono">remember_*</td><td class="{{ $td }}">Mantiene tu acceso si marcas "recordarme" al entrar en el portal.</td><td class="{{ $td }}">Hasta que cierres la sesión</td></tr>
                <tr><td class="{{ $td }} font-mono">nume_cookie_consent</td><td class="{{ $td }}">Guarda tu elección sobre el contenido externo.</td><td class="{{ $td }}">12 meses</td></tr>
            </tbody>
        </table>
    </div>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">3. Contenido externo (solo con tu consentimiento)</h2>
    <p>Los vídeos del portafolio y de tus producciones pueden estar alojados en <strong>YouTube</strong> (Google, modo de privacidad mejorada youtube-nocookie.com) o <strong>Vimeo</strong>. Al cargar el reproductor, estos servicios pueden guardar cookies o datos en tu navegador y conocer tu dirección IP, bajo sus propias políticas.</p>
    <p>Si no lo aceptas, el reproductor no se carga y verás un enlace para ver el vídeo directamente en la plataforma de origen.</p>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">4. Cómo cambiar tu elección</h2>
    <p>Puedes cambiar tu elección cuando quieras:</p>
    <button type="button" data-open-cookie-settings class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-bold text-white bg-zinc-900 hover:bg-zinc-700 transition">Configurar cookies</button>
    <p>También puedes borrar o bloquear las cookies desde la configuración de tu navegador. Si bloqueas las cookies necesarias, el portal no funcionará correctamente.</p>
</section>
