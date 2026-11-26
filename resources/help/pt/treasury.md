## Cheques

**Tesouraria → Cheques** lista os cheques recebidos:

| Estado | Ação |
|--------|------|
| Em carteira | Inclua-o num depósito. |
| Depositado | Quando o banco o compensar, marque **Compensado** (um ou vários de uma vez). |
| Compensado | Concluído. |
| Devolvido | Use **Devolvido** e indique o motivo: a dívida do cliente volta a existir. |

## Depósitos bancários

1. **Tesouraria → Depósitos → Novo depósito**.
2. Indique o valor em **numerário** e escolha os **cheques** a depositar.
3. Escreva o número do talão e confirme. O sistema faz o lançamento (Depósitos à ordem ← Caixa / Cheques a depositar).

## Pagamentos POS (cartão)

Os pagamentos por cartão no terminal POS ficam na conta **1.2.5 Pagamentos POS (cartões)** até o banco liquidar o lote. Quando o valor entrar no extrato, registe o lançamento que o passa para **Depósitos à ordem** (Débito 1.2.1, Crédito 1.2.5), descontando a comissão do banco, se houver.

## Reconciliação de carteiras móveis

1. Exporte o extrato da carteira (M-Pesa, e-Mola) no portal do operador.
2. **Reconciliação de carteiras → Importar**. Na primeira vez, indique que coluna corresponde a cada campo — o mapeamento fica guardado.
3. O sistema associa cada movimento ao pagamento com a mesma **referência**.
4. Analise as diferenças:
   - **linhas sem pagamento**: dinheiro na carteira que ninguém registou;
   - **pagamentos sem linha**: registados no sistema mas que não chegaram à carteira.
5. **Ignore** as linhas que não são vendas (por exemplo, transferências internas).
