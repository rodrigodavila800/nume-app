<x-mail::message>
# A Sofi precisa de uma pessoa

Uma conversa no WhatsApp de **{{ $tenantName }}** passou para a equipe.

**Contato:** {{ $contactLabel }}<br>
**Telefone:** +{{ $contactPhone }}<br>
**Motivo:** {{ $reasonLabel }}

**Resumo da Sofi:** {{ $summary }}

<x-mail::button :url="$panelUrl">
Abrir a conversa
</x-mail::button>

A Sofi avisou o cliente que a equipe retorna por lá. A conversa fica marcada como "Precisa de você" no painel até alguém responder.
</x-mail::message>
