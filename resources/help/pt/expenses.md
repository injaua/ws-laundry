## Registar uma despesa

1. **Despesas → Nova despesa**.
2. Preencha título, descrição, **categoria**, valor **bruto** (IVA incluído) e data.
3. Indique o **IVA cobrado na compra** em percentagem (ex.: 16). A categoria sugere a taxa da loja; use 0 se a factura não tiver IVA.
4. Nas categorias **fixas** (renda, salários…), escolha se a despesa é **mensal** ou pontual.
5. Guarde. O lançamento contabilístico é criado sozinho: gasto da categoria pelo valor líquido + **IVA dedutível (4.4.3.2)** contra **Caixa**. Na **Apuração do IVA**, o IVA dedutível é abatido ao IVA liquidado para obter o **IVA a pagar** (4.4.3.7).

> Não é possível registar nem alterar despesas com data num **período fechado**.

## Despesa de salários

Na categoria de salários, o valor pode ser preenchido a partir do **total processado** no mês (ver Salários).

## Categorias

**Despesas → Categorias**: nome, cor, se é fixa, valor por omissão, se tem **IVA** e a **conta de gasto** do plano de contas.

- Uma categoria **sem conta** não gera lançamento. Depois de lhe atribuir a conta, peça ao administrador que relance as despesas (`accounting:sync-expenses`).
