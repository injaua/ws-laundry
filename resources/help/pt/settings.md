## Definições do negócio

| Definição | Efeito |
|-----------|--------|
| Taxa de IVA | Aplicada aos pedidos novos. |
| Serviço expresso | Ativo / inativo, sobretaxa (percentagem ou valor) e prazo. |
| Capacidade diária | Kg e peças por dia — datas de entrega realistas. |
| Controlo de qualidade obrigatório | Exige aprovação antes de Pronto. |
| Política de cheques | Ninguém, só empresas ou todos. |
| Limite de numerário em caixa | A partir daqui o sistema pede uma sangria. |

## Definições do sistema

- **Geral**: nome comercial, NUIT, endereço, contactos, logótipo e cores (faturas, talões). Valem para a **loja em uso**; o que a loja não definir vem da empresa.
<!-- roles:admin -->
- **Localização**: idioma por omissão, fuso horário, formatos de data e número (iguais em todas as lojas).
- **Idiomas**: crie idiomas e traduza os textos do sistema no ecrã; exporte / importe as traduções.
- **Moedas**: lista de moedas e moeda por omissão.
<!-- /roles -->

As definições do negócio, as notificações automáticas e os modelos de mensagem são **de cada loja**.

## WhatsApp

<!-- roles:admin -->
- No topo, escolha o que está a configurar: **Empresa** (vale para todas as lojas sem configuração própria) ou **Loja** (a loja em uso). Ao gravar na loja, ela passa a ter **configuração própria**, a partir de uma cópia da configuração da empresa. O botão **Voltar a usar a configuração da empresa** apaga a configuração própria.
- Escolha o canal das **conversas**: teste, **API oficial** ou **código QR** (número normal). O número da empresa serve as lojas sem configuração própria: cada mensagem recebida vai para a loja que falou por último com esse cliente. Uma loja com configuração própria tem **o seu número**, e as mensagens para ele vão sempre para essa loja.
- **API oficial**: indique o *Phone number ID*, o *access token*, o *app secret* e o *verify token* do número. Na empresa, os campos em branco usam os valores do `.env`. Todas as apps da Meta usam o mesmo URL de webhook.
- Com **código QR**, o serviço arranca sozinho; leia o código no telemóvel em **WhatsApp → Dispositivos associados**. Cada loja com configuração própria lê o seu código, com o seu telemóvel.
<!-- /roles -->
- **Notificações automáticas** (só pela API oficial): ative cada evento — encomenda recebida, pronta, pagamento recebido, lembrete de pagamento, fatura vencida, peças por levantar, recolha agendada, estafeta a caminho, encomenda atrasada — e ligue-o a um **modelo**.
- **Modelos**: use as variáveis `{customer}`, `{order}`, `{amount}`, `{due_date}`, `{delivery_date}`, `{days}`.

<!-- roles:admin -->
## Pagamentos e carteiras móveis

Como no WhatsApp, escolha no topo **Empresa** ou **Loja**: uma loja pode ter a sua configuração de pagamentos.

- **Métodos de pagamento aceites**: só os métodos marcados aparecem nos pagamentos, na entrega e no faturamento. Os pagamentos já registados mantêm o seu método.
- **Pagamentos móveis**: credenciais de M-Pesa e e-Mola e modo — desligado, **simulação** (formação, sem dinheiro), sandbox (testes do operador) ou produção. Os pedidos de pagamento só funcionam se o método (M-Pesa ou e-Mola) também estiver aceite.
<!-- /roles -->

<!-- roles:admin -->
## Site público

Textos e imagens da página inicial, **galeria** e **testemunhos** de clientes (um site para toda a empresa). Com várias lojas, o visitante escolhe a loja no pedido de recolha.
<!-- /roles -->

## Registo de atividades

**Registo de atividades**: quem criou, alterou ou apagou o quê e quando, incluindo entradas no sistema e falhas de autenticação. Filtre por tipo de ação e tipo de registo. O **proprietário** vê apenas o que aconteceu na sua loja. O **administrador** vê tudo, com uma coluna «Loja»: todas as lojas e ainda as entradas **Geral (sem loja)** — as suas entradas no sistema, configurações gerais, cópias de segurança e alterações a outros administradores —, que nenhum proprietário vê. Pode filtrar por uma loja ou por «Geral (sem loja)».
