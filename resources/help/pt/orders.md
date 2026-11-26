## Criar um pedido

1. **Pedidos → Novo pedido**.
2. Escolha o **cliente** (ou crie-o no momento). Se tiver crédito, o sistema mostra o limite e o saldo disponível.
3. Adicione as linhas: serviços **ao quilo** (peso), **à peça** (quantidade) ou **pacotes**. O preço vem sempre do catálogo.
4. Opcional: marque **Expresso**, indique **cuidados especiais** (delicado, separar cores, sem amaciador…) e notas de recepção.
5. Confirme a **data de entrega** proposta (calculada pela capacidade da lavandaria).
6. Para clientes com crédito, escolha se é **venda a crédito**.
7. Guarde. O pedido fica no estado **Recebido** com um número único, no formato **ORD** + código da loja + data (ddmmaaaa) + sequência do dia — por exemplo **ORDLJ0125112026004** é o 4.º pedido de 25/11/2026 da loja LJ01.

### Como o preço é calculado

- O preço de cada linha é o **mais baixo** entre o preço do cliente (negociado ou com desconto) e o catálogo com a melhor promoção do dia. Os descontos não se acumulam.
- Somam-se a sobretaxa expresso, a taxa de entrega e o **IVA**.

## Estados do pedido

Recebido → Em lavagem → Em secagem → Em engomadoria → Em dobragem → **Pronto** → Em entrega → **Concluído**.

- O estado só avança. Para voltar atrás é preciso um **retrabalho** (ver Produção).
- Um pedido **Pronto** totalmente pago passa sozinho a **Em entrega**.
- Um pedido **Concluído** fica bloqueado.

<!-- roles:cashier -->
> Depois de criado, o operador de caixa só pode **mudar o estado** do pedido. Se as linhas ou os preços estiverem errados, peça a correção a um gestor.
<!-- /roles -->

## Ficha do pedido

Abra o pedido para ver linhas, totais, histórico de estados, produção, peças, pagamentos, logística e pós-venda, e para imprimir **fatura**, **talão** ou **etiquetas**.

## Vendas a crédito

- Só para clientes com **crédito ativo**.
- Se o cliente tiver faturas **vencidas** ou passar o **limite**, o pedido a crédito é bloqueado.
<!-- roles:admin,owner -->
- Como gestor, pode autorizar a exceção escrevendo uma **justificação** (fica registada).
<!-- /roles -->

<!-- roles:admin,owner -->
## Editar, apagar e restaurar

- **Editar** permite corrigir linhas e preços enquanto o pedido não estiver concluído.
- **Apagar** envia o pedido para a **Reciclagem**; de lá pode ser **restaurado**.
<!-- /roles -->
