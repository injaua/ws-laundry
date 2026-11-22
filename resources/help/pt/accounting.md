A contabilidade segue o **PGC-NIRF**. A maior parte dos lançamentos é **automática**: pagamentos, vendas a crédito, despesas, notas de crédito, cheques, depósitos e fechos de caixa geram o lançamento no momento em que acontecem.

## Plano de contas

- **Contabilidade → Plano de contas**: código, nome e tipo (ativo, passivo, capital próprio, rendimento, gasto).
- Pode criar subcontas. Contas com movimentos ou subcontas não podem ser apagadas.

## Lançamentos

- **Lançamentos** mostra o diário com número, data, descrição, **origem** (manual, pagamento, despesa, venda a crédito, caixa) e linhas.
- **Novo lançamento** (manual): o total a **débito** tem de ser igual ao total a **crédito**.
- Os lançamentos automáticos corrigem-se na **origem** (anulando o pagamento, a despesa, etc.).

## Mapas

| Mapa | Conteúdo |
|------|----------|
| Balancete | Saldos de cada conta num período (débito = crédito). |
| Balanço | Ativo, passivo e capital próprio numa data. |
| Demonstração de resultados | Rendimentos menos gastos até ao resultado líquido. |

## Apuramento do IVA

1. **Apuramento do IVA**: escolha o mês e o ano.
2. Confira o **IVA liquidado** (4.4.3.3), o **IVA dedutível** (4.4.3.2 — inclui saldos antigos de 4.4.3.1 IVA suportado) e os movimentos.
3. **Apurar** transfere os saldos para **4.4.3.7 IVA a pagar** (ou a recuperar).

## Pagamentos ao Estado

**Contabilidade → Pagamentos ao Estado** mostra, para o mês escolhido:

- **IVA a pagar**: IVA liquidado nas vendas menos IVA dedutível nas despesas do mês, e o saldo já apurado em 4.4.3.7 ainda por pagar. Prazo: último dia do mês seguinte.
- **INSS a pagar**: calculado sobre os salários processados do mês (salário base + bónus − faltas), 3% do trabalhador + 4% da empresa, por funcionário. Prazo: dia 10 do mês seguinte.
- **Pagamentos efectuados**: lançamentos que pagam 4.4.3.7 ou 4.4.9 a partir da Caixa ou do Banco (ex.: Débito 4.4.3.7, Crédito 1.1 Caixa).

## Fecho de período

1. Confira o balancete e faça o apuramento do IVA.
2. **Fecho de período → Fechar** o mês. O balancete fica arquivado.
3. Depois de fechado, não é possível registar nem alterar operações com essa data.
4. Pode **exportar** o período fechado.

<!-- roles:accountant -->
> **Reabrir** um período fechado é exclusivo do administrador ou do proprietário. Se precisar de corrigir um mês fechado, peça-lhes a reabertura.
<!-- /roles -->
<!-- roles:admin,owner -->
> **Reabrir** um período fica registado (quem e quando). Use-o só para corrigir erros e volte a fechar logo a seguir.
<!-- /roles -->
