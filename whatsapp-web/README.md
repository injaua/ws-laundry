# WhatsApp por código QR (gratuito)

Serviço que liga um número normal de WhatsApp à plataforma, como o WhatsApp Web, para os caixas conversarem com os clientes no painel **Mensagens → WhatsApp a clientes**. Usa a biblioteca não oficial [Baileys](https://github.com/WhiskeySockets/Baileys).

> **Atenção:** é uma ligação não oficial. O WhatsApp pode bloquear números que a usam, sobretudo quando enviam muitas mensagens seguidas ou escrevem a quem não guardou o número. Por isso a plataforma **só a usa para conversas**. As notificações automáticas saem pela API oficial da Meta (`WHATSAPP_NOTIFICATIONS_DRIVER=cloud`) ou ficam desligadas. De preferência, use um número dedicado à lavandaria.

## Configuração

Não é preciso editar o `.env`. Em **Definições → Definições WhatsApp → Ligação WhatsApp**:

1. Em «Conversas com clientes», escolha **Ligação por código QR** e guarde.
2. O sistema gera o segredo partilhado com o serviço em `storage/app/whatsapp-web.token`. O serviço lê esse ficheiro.
3. Em «Notificações automáticas», escolha a API oficial da Meta se a tiver configurada. Caso contrário, as notificações ficam desligadas.

Os valores do `.env` continuam a valer como predefinição, e o que for escolhido no ecrã sobrepõe-se a eles:

```env
WHATSAPP_DRIVER=web                 # conversas pela ligação QR (log, cloud ou web)
WHATSAPP_NOTIFICATIONS_DRIVER=cloud # notificações pela API oficial (vazio = desligadas)
WHATSAPP_WEB_URL=http://127.0.0.1:3010
WHATSAPP_WEB_TOKEN=                 # opcional: sem ele, usa o segredo gerado pelo sistema
# WHATSAPP_WEB_WEBHOOK=             # por omissão APP_URL/webhooks/whatsapp-web
```

O serviço lê o mesmo `.env`, na pasta acima. Escuta apenas em `127.0.0.1`, por isso não fica exposto à internet. Se arrancar antes de o QR ser escolhido, espera pelo segredo em vez de terminar.

## Arrancar

O sistema arranca o serviço sozinho quando escolhe **Ligação por código QR** e guarda. Na primeira vez corre `npm install`, o que pode demorar alguns minutos, e depois `node server.mjs`, em segundo plano e sem janela. O ecrã mostra «A arrancar…» e, a seguir, o código QR. Se o serviço não responder, aparecem o botão **Iniciar serviço** e as últimas linhas do registo (`storage/logs/whatsapp-web.log`).

- Ao escolher outro canal, o serviço é parado.
- Depois de um reinício do servidor, o agendador volta a arrancá-lo (`php artisan whatsapp:web-service`, a cada minuto). Para isso, o `php artisan schedule:run` tem de correr a cada minuto, como já acontece para as outras tarefas.
- Se o servidor web não encontrar o Node, indique o caminho em `WHATSAPP_WEB_NODE` (por exemplo `C:\Program Files\nodejs\node.exe`).
- Para gerir o serviço por conta própria (pm2, systemd, NSSM), defina `WHATSAPP_WEB_AUTOSTART=false`.

Arranque à mão:

```bash
cd whatsapp-web
npm install
npm start
```

Depois, em **Definições → Definições WhatsApp**, leia o código QR com o telemóvel: WhatsApp → Dispositivos ligados → Ligar um dispositivo. A sessão fica guardada em `whatsapp-web/auth/`. Não partilhe essa pasta: quem a tiver controla o número.

Com `WHATSAPP_WEB_AUTOSTART=false`, para o serviço continuar a correr depois de reiniciar o servidor:

- Linux: `pm2 start server.mjs --name whatsapp-web && pm2 save`, ou um serviço systemd.
- Windows: [NSSM](https://nssm.cc/) ou `pm2` com `pm2-windows-startup`.

O telemóvel não precisa de ficar ligado à internet o tempo todo, mas o WhatsApp desliga dispositivos que não abre há cerca de 14 dias.

## Limitações

- Não envia templates da Meta nem notificações automáticas.
- Recebe texto, legendas de fotos e documentos, localizações e respostas a botões. Os ficheiros em si não são descarregados.
- Grupos, estados e canais são ignorados.
- As respostas escritas diretamente no telemóvel (ou no WhatsApp Web) também ficam na conversa da plataforma, com a indicação «Enviada pelo telemóvel». As mensagens antigas, de antes de ligar o número, não são importadas.
