## Contas a receber

**Contas a receber** mostra o saldo em dívida por cliente, com a **antiguidade** (a vencer, 1–30, 31–60, 61–90 e mais de 90 dias). Abra um cliente para ver os pedidos em aberto ou **exporte** para Excel.

## Lista de faturas por pagar

**Faturas** abre por omissão em **Não pagas**: numa só lista, ordenada pelo vencimento (o que vence primeiro aparece primeiro), com o total em dívida no topo:

- a **fatura da ordem** de cada pedido com saldo por pagar (exceto os que já estão numa fatura consolidada emitida);
- as **faturas consolidadas (FT)** emitidas com saldo.

Em cada linha pode abrir, imprimir ou **pagar**. Filtre por cliente, por tipo (fatura da ordem ou consolidada) e por estado:

| Filtro | Mostra |
|--------|--------|
| Não pagas | Faturas das ordens e FT com saldo. |
| Vencidas | As não pagas com vencimento passado. |
| Rascunhos (FT) · Pagas (FT) · Anuladas (FT) · Todas as consolidadas (FT) | Só faturas consolidadas. As faturas das ordens já pagas estão em cada ordem. |

## Faturas consolidadas (clientes mensais)

Para empresas com ciclo de faturação **mensal**:

1. No dia 1 de cada mês, às 07:00, o sistema cria **rascunhos** com os pedidos a crédito do mês anterior. Também pode criar uma fatura em **Faturas → Nova fatura** (só aparecem os **clientes a crédito** da loja; para faturar um cliente, edite-o em **Clientes** e marque «Cliente a crédito (paga depois)»).
2. Reveja o rascunho e retire pedidos que não devam constar.
3. **Emitir** numera a fatura e fixa a mesma **data de vencimento** em todos os seus pedidos.
4. Imprima ou envie a fatura.
5. Se necessário, **anule-a** (os pedidos ficam de novo disponíveis).

| Estado | Significado |
|--------|-------------|
| Rascunho | Editável, sem efeito fiscal. |
| Emitida | Numerada, com vencimento, não editável. |
| Anulada | Sem efeito. |

## Pagamento agrupado

Quando uma empresa paga vários pedidos de uma vez (normalmente por transferência):

1. Na ficha do cliente (ou da fatura), **Registar pagamento**.
2. Indique o valor, o método e a referência.
3. O sistema distribui o valor **do pedido mais antigo para o mais recente** e emite um **recibo de grupo**.

## Extrato de conta

Na ficha do cliente, **Extrato**: todos os movimentos por data com saldo corrente — venda (+), nota de crédito (−), reembolso (+), pagamento (−).
