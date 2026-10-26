<?php

return [
    // RGPD: leads perdidos são anonimizados depois deste prazo sem mudança de etapa.
    'anonymize_lost_after_months' => (int) env('CRM_ANONYMIZE_LOST_AFTER_MONTHS', 24),
];
