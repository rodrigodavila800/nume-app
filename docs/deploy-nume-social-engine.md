# Deploy do Nume Social Engine (Fases 0 a 3)

Checklist para subir o vídeo hospedado e as redes diretas (YouTube, Instagram, Facebook, Threads e TikTok) na VPS (CloudPanel). Siga na ordem. Nada aqui exige mexer no Upload-Post: as redes que não estão conectadas direto continuam saindo por ele.

## 1. Código e banco

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force        # videos.source_*, social_connections, campos nativos em video_publications
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## 2. Servidor (vídeo hospedado)

- PHP do site (CloudPanel > PHP Settings): `upload_max_filesize` e `post_max_size` com pelo menos **512M** (ou o valor de `VIDEO_MAX_UPLOAD_MB`).
- Nginx do site (Vhost): `client_max_body_size 512M;`
- Pasta `storage/app/private/videos` gravável pelo usuário do site.
- Opcional: `apt install ffmpeg` (o `ffprobe` lê largura, altura e duração; sem ele o upload funciona igual).

## 3. Fila de envios (obrigatório para o YouTube direto)

O envio vai por uma conexão de fila própria, `social-uploads`, com `retry_after` maior que o upload. **Sem este worker, os envios ficam parados em "pendente".**

Supervisor (ou o gerenciador de processos que você já usa para a fila padrão):

```ini
[program:nume-social-uploads]
command=php /home/USUARIO/htdocs/SITE/artisan queue:work social-uploads --queue=social-uploads --timeout=3800 --sleep=3 --max-time=86400
user=USUARIO
autostart=true
autorestart=true
stopwaitsecs=3900
stdout_logfile=/home/USUARIO/htdocs/SITE/storage/logs/social-uploads.log
```

- `stopwaitsecs` maior que o timeout: um deploy não pode matar um upload no meio.
- Depois de cada deploy: `php artisan queue:restart`.
- O cron do scheduler (`* * * * * php artisan schedule:run`) precisa estar ativo: ele dispara os agendados (`videos:publish-scheduled`) e a checagem diária dos tokens (`social:refresh-tokens`, 04:15).

## 4. Google Cloud (uma vez)

No mesmo projeto do login com Google:

1. **APIs e serviços > Biblioteca:** ativar **YouTube Data API v3**.
2. **Tela de consentimento OAuth:** nome do app "NUME Creative", e-mail de suporte, domínio `numestudio.es`, links da política de privacidade e dos termos (precisam estar no ar). Escopos: `youtube.upload` e `youtube.readonly`.
3. Enquanto estiver em **Teste**: adicionar os e-mails das contas Google que vão conectar canais (até 100). Nesse modo o Google invalida o refresh token em **7 dias** (o cliente precisa reconectar) e os vídeos enviados pela API ficam **privados**.
4. **Credenciais > cliente OAuth (Aplicativo da Web):** em "URIs de redirecionamento autorizados", acrescentar
   `https://SEU-DOMINIO/portal/redes-sociais/oauth/youtube/callback`
   (ou o valor de `GOOGLE_YOUTUBE_REDIRECT_URI`, se definido). Tem que bater exatamente, com https.
5. Para sair do modo Teste: verificar o domínio no Search Console, pedir a verificação do app (escopos sensíveis) e, para vídeos públicos, a **auditoria de conformidade da YouTube API**. A cota padrão é de 10.000 unidades/dia por projeto, somando todos os clientes, e o upload é a operação mais cara (historicamente 1.600 unidades, ou seja, uns 6 vídeos por dia; confira o valor atual na tabela de custos da YouTube Data API). Peça aumento de cota junto com a auditoria.

## 4b. App da Meta: Instagram, Facebook e Threads (uma vez)

Em [developers.facebook.com](https://developers.facebook.com), com a conta de administrador da NUME:

1. **Criar app** do tipo Empresa, ligado ao portfólio empresarial da NUME. Em paralelo, iniciar a **Verificação da Empresa** (Configurações do Negócio > Central de Segurança). Ela é exigida para o acesso avançado e costuma levar semanas.
2. **Casos de uso:** adicionar "Gerenciar tudo na sua Página" (Facebook Login for Business), "Instagram API (com Facebook Login)" e "Acessar a API do Threads".
3. **Facebook Login for Business > Configurações:** URI de redirecionamento válida
   `https://SEU-DOMINIO/portal/redes-sociais/oauth/meta/callback`.
   Opcional: criar uma *configuração* com as permissões abaixo e pôr o ID em `META_LOGIN_CONFIG_ID`.
4. **Threads > Configurações:** URI de redirecionamento
   `https://SEU-DOMINIO/portal/redes-sociais/oauth/threads/callback`. O Threads tem **ID e segredo próprios** (vão em `THREADS_APP_ID` e `THREADS_APP_SECRET`).
5. **Configurações básicas do app:** URL da Política de Privacidade (`/privacidade`), dos Termos (`/termos`) e de **instruções de exclusão de dados** (`/exclusao-de-dados`), ícone e categoria.
6. **Permissões que o app usa** (são as que vão para a Análise do App):
   - Página: `pages_show_list`, `pages_read_engagement`, `pages_manage_posts`
   - Instagram: `instagram_basic`, `instagram_content_publish`
   - Threads: `threads_basic`, `threads_content_publish`
7. **Enquanto o app não passa na Análise:** só funciona com quem tem papel no app (Funções do app > adicionar Administradores/Testadores). Use as contas da NUME para testar tudo de ponta a ponta.
8. **Análise do App:** para cada permissão, um vídeo de tela mostrando o fluxo: login no portal > Redes Sociais > Conectar > tela da Meta > escolha da Página > publicar um vídeo > post aparecendo na rede. Explique em inglês que o app publica vídeos produzidos pela produtora na conta do próprio cliente, a pedido dele.

Requisitos que valem para os clientes:
- **Instagram** precisa ser conta profissional (Empresa ou Criador) **ligada a uma Página**; a Página é conectada junto.
- **Reels do Facebook:** vertical 9:16, mínimo 540x960, de 3 a 90 segundos. Vídeo fora disso é recusado antes do envio, com explicação no histórico.
- **Limites da Meta (24 h):** 100 posts por conta de Instagram, 30 Reels por Página, 250 posts por perfil do Threads.
- **Threads** só aceita buscar o vídeo por URL pública: o servidor precisa estar acessível pela internet (não funciona em `localhost`).

## 4c. App do TikTok (uma vez)

Em [developers.tiktok.com](https://developers.tiktok.com), com a conta da NUME:

1. **Criar app** (Web) e preencher ícone, nome "NUME Creative", categoria, e as URLs de Termos (`/termos`) e Privacidade (`/privacidade`).
2. **Produtos:** adicionar **Login Kit** e **Content Posting API**, com **Direct Post** ligado.
3. **Login Kit > Redirect URI:** `https://SEU-DOMINIO/portal/redes-sociais/oauth/tiktok/callback` (https, sem parâmetros).
4. **Escopos:** `user.info.basic` e `video.publish`.
5. **Sandbox / sem auditoria:** só contas adicionadas como usuários de teste conseguem conectar. Os posts saem **privados (Somente eu)**, a conta precisa estar privada na hora do post e o limite é de 5 usuários publicando por dia. É o modo para testarmos.
6. **Auditoria (para publicar público):** enviar o app para análise com vídeo de tela do fluxo completo: Redes Sociais > Conectar TikTok > tela do TikTok > página da produção > "Publicar no TikTok" > escolhas (privacidade, interações, divulgação comercial) > publicar > post no perfil. A tela "Publicar no TikTok" já segue as regras de UX obrigatórias do Direct Post (dados do criador a cada abertura, privacidade sem padrão, interações desmarcadas, divulgação comercial, declaração de uso de música, prévia).

Regras que valem para os clientes:
- O TikTok **não sai pelo modal "Publicar nas Redes"**. Quem conectou direto usa o botão **Publicar no TikTok** em cada vídeo, porque o TikTok exige que o dono da conta escolha as opções a cada post. Pelo painel da produtora, o TikTok direto não é publicado: aparece um aviso.
- Duração máxima, privacidades possíveis e interações dependem da conta (vêm do TikTok na hora).
- Conteúdo de marca não pode ser "Somente eu".

## 5. Variáveis de ambiente (você edita o `.env`)

| Variável | Valor | Observação |
| :--- | :--- | :--- |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | do cliente OAuth | já usadas no login com Google |
| `GOOGLE_YOUTUBE_REDIRECT_URI` | vazio | só preencha se a URI cadastrada for diferente da padrão |
| `SOCIAL_ROUTE_YOUTUBE` | `native` | `upload_post` desliga o YouTube direto sem deploy |
| `SOCIAL_YOUTUBE_PRIVACY` | `private` | troque para `public` só depois da auditoria |
| `SOCIAL_YOUTUBE_UPLOAD_TIMEOUT` | `3600` | segundos por envio |
| `SOCIAL_UPLOADS_RETRY_AFTER` | `4000` | sempre maior que o timeout + 120 |
| `META_APP_ID` / `META_APP_SECRET` | do app da Meta | Configurações > Básico |
| `META_LOGIN_CONFIG_ID` | vazio ou ID da configuração | opcional (Facebook Login for Business) |
| `META_GRAPH_VERSION` | `v26.0` | atualize de propósito quando a Meta avisar que a versão vai expirar |
| `THREADS_APP_ID` / `THREADS_APP_SECRET` | do caso de uso Threads | diferentes dos da Meta |
| `SOCIAL_ROUTE_INSTAGRAM` / `_FACEBOOK` / `_THREADS` / `_TIKTOK` | `native` | `upload_post` desliga a rede direta sem deploy |
| `TIKTOK_CLIENT_KEY` / `TIKTOK_CLIENT_SECRET` | do app do TikTok | Client key e Client secret do painel |

Depois de editar: `php artisan config:cache` e `php artisan queue:restart`.

**Não troque a `APP_KEY`** sem pôr a antiga em `APP_PREVIOUS_KEYS`: os tokens dos canais são criptografados com ela e se perdem (todos os clientes teriam que reconectar).

## 6. Verificação

1. Portal do cliente > Redes Sociais: o card do YouTube mostra **Conectar YouTube**. Se não mostrar, falta `GOOGLE_CLIENT_ID`/`SECRET` ou `SOCIAL_ROUTE_YOUTUBE` não está em `native`.
2. Conectar com uma conta de teste: volta com "Canal do YouTube @... conectado".
3. Publicar um vídeo **hospedado** marcando YouTube: aparece no histórico como "Envio direto pela NUME", passa de pendente para publicado e o link "Ver no YouTube" abre o vídeo (privado enquanto o app estiver em teste).
4. `php artisan social:refresh-tokens` mostra "Renovadas: N".
5. Logs (`storage/logs`): eventos `social.youtube.connected`, `social.publish.succeeded` ou `social.publish.failed` com `reason`. Nenhum token nem URL de sessão aparece.

## 7. Problemas comuns

| Sintoma | Causa provável |
| :--- | :--- |
| `redirect_uri_mismatch` na tela do Google | URI do passo 4.4 diferente da usada (http x https, barra final, domínio) |
| Envio parado em "pendente" | worker `social-uploads` não está rodando |
| `reconnect_required` no histórico | o cliente revogou o acesso ou passaram os 7 dias do modo Teste: reconectar |
| `quotaExceeded` | cota diária da YouTube API esgotada; zera à meia-noite (horário do Pacífico) |
| Vídeo publicado como privado | app ainda sem auditoria, ou `SOCIAL_YOUTUBE_PRIVACY=private` |
| "URL bloqueada" ou "Can't load URL" na tela do Facebook | URI de redirecionamento do passo 4b.3/4b.4 não cadastrada |
| Instagram não aparece ao conectar | conta não é profissional, não está ligada à Página escolhida ou a permissão do Instagram foi desmarcada |
| Ninguém de fora consegue conectar a Meta | app ainda sem Análise aprovada: só pessoas com função no app |
| `publish_limit_reached` | limite diário da Meta atingido para aquela conta |
| Threads falha em `media_processing_failed` em ambiente local | o Threads não alcança `localhost`; teste no servidor |
| `unaudited_client_can_only_post_to_private_accounts` | app do TikTok sem auditoria: deixe a conta do TikTok privada e publique como "Somente eu" |
| "O TikTok limitou novas contas no app hoje" | sem auditoria, o TikTok aceita só 5 usuários publicando por dia |
| Botão "Publicar no TikTok" não aparece | conta do TikTok não conectada direto, `SOCIAL_ROUTE_TIKTOK` não é `native` ou o vídeo não é arquivo hospedado |

## 8. Voltar atrás

- Desligar uma rede direta: `SOCIAL_ROUTE_<REDE>=upload_post` (YOUTUBE, INSTAGRAM, FACEBOOK, THREADS, TIKTOK) + `config:cache`. Os canais conectados continuam salvos; novos envios saem pelo Upload-Post.
- As migrations têm `down()`, mas `migrate:rollback` apaga `social_connections` (todos os canais conectados). Prefira a chave acima.
