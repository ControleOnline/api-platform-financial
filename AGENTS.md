## Escopo
- Modulo financeiro central da API.
- Cobre `Invoice`, `Wallet`, `PaymentType`, `WalletPaymentType`, `Card`, caixa e operacoes financeiras compartilhadas.

## Quando usar
- Prompts sobre faturas, carteiras, meios de pagamento, cartoes, cash register, splits e consultas financeiras.

## Limites
- A integracao com gateways, webhooks e provedores externos deve ficar em `integration`.
- O fluxo operacional de pedido continua pertencendo a `orders`, mesmo quando gera invoice.
- `financial` e o dono do dominio financeiro compartilhado.
- Quando `Invoice` precisar ser expandida dentro de outro recurso leve, como `OrderInvoice`, use um group especifico e enxuto para esse embed. Nao acople colecoes operacionais ao group amplo `invoice:read`.
- `Invoice.paymentType` descreve o meio de pagamento real da cobranca, como `Credito`, `Debito`, `Pix` ou `Dinheiro`. Descricao operacional, taxa, desconto e motivo contabil ficam em `description`/metadata, nunca no meio de pagamento.
- `Invoice.invoiceType` classifica a natureza financeira em ingles. Os tipos canonicos atuais sao `invoice`, `payment`, `discount` e `tax`, com default `invoice`.
- Totais financeiros de collections de `Invoice` devem sair do `summary` do backend. Para aberto/pago, use resolver de `CollectionSummary`; nao deixe o frontend calcular esses valores pela pagina carregada.
- As listagens de `Invoice` consumidas por `DefaultTable` React precisam expor `search`, `order` e filtros no backend com `CustomOrFilter`, `OrderFilter` e `DateFilter` alinhados ao store.
- O financeiro de marketplace de `Food99` deve ser montado a partir do snapshot do pedido e nao por recalculo em outro service, para que o backfill reproduza exatamente o mesmo contrato.
- Em `Food99`, `receiver = 99 Food` continua obrigatorio nas invoices de repasse e cobranca da plataforma, mas a `wallet` da loja no repasse semanal vem apenas de `store_settlement_wallet_id` configurado na integracao; nomes `iFood` nao podem entrar no dominio financeiro da empresa.
- Abertura e fechamento de caixa devem gerar alerta humano do `MANAGER` como `PushNotification` na fila de integracao, nao como websocket. `cash.open` deve informar operador e horario da abertura.
