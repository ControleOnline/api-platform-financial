## Escopo
- Modulo financeiro central da API.
- Cobre `Invoice`, `Wallet`, `PaymentType`, `WalletPaymentType`, `Card`, caixa e operacoes financeiras compartilhadas.

## Quando usar
- Prompts sobre faturas, carteiras, meios de pagamento, cartoes, cash register, splits e consultas financeiras.

## Limites
- A integracao com gateways, webhooks e provedores externos deve ficar em `integration`.
- O fluxo operacional de pedido continua pertencendo a `orders`, mesmo quando gera invoice.
- `financial` e o dono do dominio financeiro compartilhado.
