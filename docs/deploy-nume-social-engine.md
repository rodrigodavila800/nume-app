# Deploy do Nume Social Engine (Fase 0 + Fase 1)

Checklist para subir o vídeo hospedado e o YouTube direto na VPS (CloudPanel). Siga na ordem. Nada aqui exige mexer no Upload-Post: as redes que não estão conectadas direto continuam saindo por ele.

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

## 5. Variáveis de ambiente (você edita o `.env`)

| Variável | Valor | Observação |
| :--- | :--- | :--- |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | do cliente OAuth | já usadas no login com Google |
| `GOOGLE_YOUTUBE_REDIRECT_URI` | vazio | só preencha se a URI cadastrada for diferente da padrão |
| `SOCIAL_ROUTE_YOUTUBE` | `native` | `upload_post` desliga o YouTube direto sem deploy |
| `SOCIAL_YOUTUBE_PRIVACY` | `private` | troque para `public` só depois da auditoria |
| `SOCIAL_YOUTUBE_UPLOAD_TIMEOUT` | `3600` | segundos por envio |
| `SOCIAL_UPLOADS_RETRY_AFTER` | `4000` | sempre maior que o timeout + 120 |

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

## 8. Voltar atrás

- Desligar só o YouTube direto: `SOCIAL_ROUTE_YOUTUBE=upload_post` + `config:cache`. Os canais conectados continuam salvos; novos envios saem pelo Upload-Post.
- As migrations têm `down()`, mas `migrate:rollback` apaga `social_connections` (todos os canais conectados). Prefira a chave acima.
