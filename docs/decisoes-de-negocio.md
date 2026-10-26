# Decisões de negócio — Nume App

Registro das decisões do dono do produto. Atualizar quando mudar.

| Data | Tema | Decisão | Consequência técnica |
| :--- | :--- | :--- | :--- |
| 23/10/2026 | Hospedagem | VPS Hostinger KVM 2 (Alemanha), CloudPanel, PHP 8.4 | PHP ≥ 8.3 obrigatório (laravel/ai) |
| 23/10/2026 | Domínio | Domínio `.es` em nome do dono do negócio (CIF ou DNI/NIE) | `APP_URL` e URLs de Stripe, Redsys e Google OAuth no deploy |
| 23/10/2026 | IA de teste | Gemini (nível gratuito) para testes reais; Anthropic só depois | `SOFI_AI_PROVIDER=gemini`; dados fictícios nos testes |
| 23/10/2026 | Impostos | IVA/IRPF **por produtora**; global vira só padrão | `tenant_settings` + `TenantFiscalSettings` |
| 23/10/2026 | Emissor da fatura | **A produtora** emite (CIF/NIF, série e numeração próprias) | Numeração atômica por tenant e série; `invoice_snapshot` |
| 23/10/2026 | Moeda | Padrão **EUR**; Redsys/Bizum/transferência só em EUR; BRL só via Stripe | `packs.currency` default `eur` |
| 23/10/2026 | Transferência bancária | Nova forma de pagamento: solicitud de pago com IBAN; factura só na confirmação manual do staff | `bank_transfer`, `PackPaymentConfirmer::confirmBankTransfer` |
| 23/10/2026 | Redsys | **TPV por produtora**; plataforma não recebe e repassa (risco PSD2) | Credenciais Redsys em `tenant_settings`; webhook resolve o tenant antes de validar |
| 23/10/2026 | Assinaturas de pacote | **Fora do lançamento**; voltam numa fase fiscal com fatura por ciclo | Checkout recusa pacote recorrente |
| 23/10/2026 | Comissão fora da Stripe | **Item de uso (metered) na assinatura SaaS** da produtora | Registrar comissão devida por compra agora; cobrança em fase própria |
| 24/10/2026 | Redes sociais | **App próprio da NUME** nas APIs oficiais (sem engenharia reversa); Upload-Post só como alternativa por rede | Nume Social Engine: 8 redes nativas, `social.routing` |
| 26/10/2026 | Modelo de clientes | Hoje a NUME é a única produtora e usa a própria plataforma; os clientes são **empresas** que compram vídeo | Tenant `nume`; recursos de produtora valem para a NUME |
| 26/10/2026 | Proteção de dados | **Só Espanha por enquanto: RGPD + LOPDGDD** (sem LGPD, não vende para o Brasil) | Textos legais citam RGPD/LOPDGDD e AEPD; e-mail comercial segue a LSSI-CE (validar com jurídico) |
| 26/10/2026 | Vídeo após postar | O arquivo final **é apagado da hospedagem depois de postado** | Apagar só quando não houver publicação pendente/agendada/em andamento; cópia de entrega fica no link (Drive) |
| 26/10/2026 | Limite de postagens | **Mensal, por plano**; valores a definir. Conta **envios** (várias redes no mesmo envio = 1) | Campo no plano/pacote/orçamento; sem valor = sem limite |
| 26/10/2026 | Redes liberadas | O admin da NUME libera **quantas redes** cada cliente pode usar (o cliente escolhe quais conectar), conforme o plano, pacote ou orçamento contratado | Campo "redes liberadas" no plano/pacote/orçamento, copiado para o cliente na compra; ajuste manual na ficha do cliente prevalece; o portal bloqueia a conexão além do limite |
| 26/10/2026 | CRM | **Da NUME e das produtoras** (módulo por tenant); inclui o **suporte** (gestão de demandas) | Funil + origem do lead + histórico; demandas ligadas ao cliente |
| 26/10/2026 | Tipos de vídeo | **Cadastrados pelo admin da NUME**; cada tipo define seus blocos e duração | Catálogo por tenant; roteiro guarda o tipo; Sofi monta pelo tipo |
| 26/10/2026 | Anúncios | **Serviço**: a NUME cria a mídia; o cliente paga a verba direto à plataforma de anúncios; orçamento do anúncio → aprovação → fatura | Sem API de anúncios por ora; item de anúncio no orçamento |
| 26/10/2026 | Calculadora de orçamento | **A calculadora recebe a solicitação do cliente** (quantidade de vídeos, tipo de vídeo, plano) para calcular o orçamento e seguir o fluxo | Fase D: a solicitação do portal vira a entrada da `QuoteCalculatorService`; o tipo de vídeo já existe como entrada |
| 26/10/2026 | Chat do portal | Vira **suporte** (porta de entrada das demandas do CRM), sem sugerir roteiros | Chat de vendas da vitrine continua como está |

## Pendente com o analista
- Quem sobe o arquivo aprovado no Drive para a plataforma? (equipe manualmente ou integração com o Drive)
- O cliente recebe o arquivo final antes ou depois de quitar o saldo? (hoje: download e publicação só após quitação)
- Padrões adotados em 26/10 até o analista dizer o contrário: fatura do anúncio só com o serviço da NUME (verba paga direto à plataforma); a equipe sobe o arquivo final (o Drive é só link); arquivo final liberado depois de quitar o saldo; o cliente não cria nem edita roteiro (aprova ou pede ajuste); o arquivo só é apagado se houver link de entrega; Notion fora por agora.

## Pendências técnicas conhecidas
- Telas internas da equipe (calculadora de orçamento, pacotes, planos, vídeo, Batch Planner) ainda estão só em português; o portal do cliente e as mensagens já têm espanhol.

## Pendente com a gestoria
- A retenção de IRPF se aplica a clientes particulares? (hoje: nunca retém para particular)
- IRPF em todos os ciclos de assinatura (quando as assinaturas voltarem).
- Emissão de factura no pedido vs. na confirmação (hoje: na confirmação).

## Regra de modelo para economizar tokens
- Opus 5.5: planejar, pagamentos, webhooks, segurança, migrations, fiscal.
- Sonnet 5.5: implementar plano aprovado com testes.
- Haiku 5.5: docs, rodar testes/commits, ajustes pequenos.
