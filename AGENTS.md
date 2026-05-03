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
