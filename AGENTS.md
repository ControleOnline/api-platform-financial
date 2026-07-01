## Financial
- `payOrder` precisa promover `cart` para `sale` antes de resolver o estado final do pedido.
- O fechamento depois do pagamento deve escolher `closed` quando nao houver entrega ou fila pendente e `preparando` quando ainda houver trabalho a executar.
- `order.created` e os pushes de criacao pertencem ao momento em que o pedido vira `sale`; `cart` nao deve disparar esse evento.
