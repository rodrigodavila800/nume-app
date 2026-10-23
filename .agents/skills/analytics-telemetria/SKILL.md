---
name: analytics-telemetria
description: Conhecimento do módulo Analytics, Telemetria & Sofi Feed — Rastreamento de métricas de vídeos (TelemetryService: visualizações, retenção, engajamento), dashboards de produtividade e Sofi Intelligence Data Feed (insights gerados por IA para tomada de decisão). Carregar sempre que a tarefa envolver métricas, telemetria, relatórios, analytics ou feed da Sofi.
---

# Módulo Analytics, Telemetria & Sofi Feed

**Responsabilidade:** Coletar, consolidar e analisar métricas de engajamento dos vídeos produzidos, além de alimentar o *Sofi Intelligence Data Feed* — um feed em tempo real com recomendações estratégicas e insights automatizados por IA para clientes e produtoras.

---

## 1. O Serviço de Telemetria: `TelemetryService`

Localizado em `app/Services/TelemetryService.php`, o serviço é responsável por:
- Consolidar dados de receita bruta, base imponible e IVA repercutido com **isolamento estrito por `tenant_id`**.
- Medir o mix de canais de pagamento: **Bizum (BBVA)**, **Tarjeta de Crédito (Redsys)** e **Stripe**.
- Medir a eficiência do funil de aprovação (taxa percentual de aprovação de roteiros).
- Avaliar a tração individual de pacotes de vídeo e ranquear os top clientes em volume de produção.

---

## 2. Sofi Intelligence Data Feed

Acessível via `GET /analytics/sofi-data-feed` (`AnalyticsController@sofiDataFeed`), o feed analisa os dados reais do tenant e gera cartões de inteligência com:
- **Taxa de Retenção de Vídeos:** Comparação de performance entre diferentes frameworks (ex: *Pattern Interrupt* vs. *Direct Offer*).
- **Alertas de Oportunidade:** Identificação de clientes com pacotes prestes a expirar ou com baixa frequência de postagem.
- **Sugestões de Pauta:** Tópicos em alta no nicho do cliente para novos roteiros.
- **Detalhamento de Pagamentos:** Objeto `gateways_breakdown` com volume e contagem de vendas por pasarela.

---

## 3. Rotas & Visualização

- `GET /analytics` (`AnalyticsController@index`): Dashboard visual com KPIs (Receita Bruta, Base Imponible, Ticket Médio, Eficiência de Roteiros, Base Ativa), card de canais de liquidação e diagnósticos da Sofi Copilot.
- Protegido pelo middleware `auth` com isolamento por `tenant_id`.
