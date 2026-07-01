<?php

/*
 * Contract imported from AGENTS.md
 * ## Escopo
 * - Modulo financeiro central da API.
 * - Cobre `Invoice`, `Wallet`, `PaymentType`, `WalletPaymentType`, `Card`, caixa e operacoes financeiras compartilhadas.
 *
 * ## Quando usar
 * - Prompts sobre faturas, carteiras, meios de pagamento, cartoes, cash register, splits e consultas financeiras.
 *
 * ## Limites
 * - A integracao com gateways, webhooks e provedores externos deve ficar em `integration`.
 * - O fluxo operacional de pedido continua pertencendo a `orders`, mesmo quando gera invoice.
 * - `financial` e o dono do dominio financeiro compartilhado.
 * - `extra_data` e `extra_fields` nao podem guardar snapshot financeiro rico, repasse, liquidez, split ou qualquer outro estado que ja tenha destino canonico em `Invoice`, `Wallet`, `PaymentType`, `WalletPaymentType` ou `OrderInvoice`. Nesta camada, so IDs e codigos remotos sem coluna materializada equivalente podem continuar em `extra_data`.
 * - Quando `Invoice` precisar ser expandida dentro de outro recurso leve, como `OrderInvoice`, use um group especifico e enxuto para esse embed. Nao acople colecoes operacionais ao group amplo `invoice:read`.
 * - `Invoice.paymentType` descreve o meio de pagamento real da cobranca, como `Credito`, `Debito`, `Pix` ou `Dinheiro`. Descricao operacional, taxa, desconto e motivo contabil ficam em `description`/metadata, nunca no meio de pagamento.
 * - `Invoice.invoiceType` classifica a natureza financeira em ingles. Os tipos canonicos atuais sao `invoice`, `payment`, `discount` e `tax`, com default `invoice`.
 * - Totais financeiros de collections de `Invoice` devem sair do `summary` do backend. Para aberto/pago, use resolver de `CollectionSummary`; nao deixe o frontend calcular esses valores pela pagina carregada.
 * - As listagens de `Invoice` consumidas por `DefaultTable` React precisam expor `search`, `order` e filtros no backend com `CustomOrFilter`, `OrderFilter` e `DateFilter` alinhados ao store.
 * - O financeiro de marketplace de `Food99` deve ser montado a partir do snapshot do pedido e nao por recalculo em outro service, para que o backfill reproduza exatamente o mesmo contrato.
 * - Em `Food99`, `receiver = 99 Food` continua obrigatorio nas invoices de repasse e cobranca da plataforma, mas a `wallet` da loja no repasse semanal vem apenas de `store_settlement_wallet_id` configurado na integracao; nomes `iFood` nao podem entrar no dominio financeiro da empresa.
 * - Abertura e fechamento de caixa devem gerar alerta humano do `MANAGER` como `PushNotification` na fila de integracao, nao como websocket. `cash.open` deve informar operador e horario da abertura.
 */


namespace ControleOnline\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface as Security;
use ControleOnline\Entity\Card;
use Doctrine\DBAL\Types\Types;

class CardService
{
    private $currentPeople = null;
    public function __construct(
        private EntityManagerInterface $manager,
        private Security $security,
        private PeopleRoleService $peopleRoleService
    ) {
        $this->currentPeople = $this->security->getToken()->getUser()->getPeople();
    }

    public function findCardResumeByPeople(): array
    {
        $conn = $this->manager->getConnection();

        $sql = 'SELECT 
                C.id,
                C.type,
                AES_DECRYPT(C.name,           :tenancy_secret) AS name,                
                AES_DECRYPT(C.number_group_1, :tenancy_secret) AS number_group_1,
                AES_DECRYPT(C.number_group_4, :tenancy_secret) AS number_group_4
            FROM card C
            LEFT JOIN people_link PL ON PL.company_id = C.people_id AND PL.link_type IN ("employee","family")
            WHERE (C.people_id = :people_id OR PL.people_id = :people_id)
        ';

        $rows = $conn->executeQuery(
            $sql,
            [
                'tenancy_secret' => $_ENV['TENANCY_SECRET'],
                'people_id' => $this->currentPeople->getId(),
            ],
            [
                'tenancy_secret' => Types::STRING,
                'people_id' => Types::INTEGER,
            ]
        )->fetchAllAssociative();

        $cards = [];
        foreach ($rows as $row)
            $cards[] = $this->toObject($row);

        return $cards;
    }

    public function toObject($row): Card
    {
        $card = new Card();
        if (isset($row['id'])) {
            $card->setId((int) $row['id']);
        }

        if (isset($row['people_id'])) {
            $card->setPeopleId((int) $row['people_id']);
        }

        if (array_key_exists('name', $row)) {
            $card->setName($row['name']);
        }

        if (array_key_exists('type', $row)) {
            $card->setType($row['type']);
        }

        if (array_key_exists('document', $row)) {
            $card->setDocument($row['document']);
        }

        if (array_key_exists('number_group_1', $row)) {
            $card->setNumberGroup1($row['number_group_1']);
        }

        if (array_key_exists('number_group_2', $row)) {
            $card->setNumberGroup2($row['number_group_2']);
        }

        if (array_key_exists('number_group_3', $row)) {
            $card->setNumberGroup3($row['number_group_3']);
        }

        if (array_key_exists('number_group_4', $row)) {
            $card->setNumberGroup4($row['number_group_4']);
        }

        if (array_key_exists('expiration_month', $row)) {
            $card->setExpirationMonth($row['expiration_month']);
        }

        if (array_key_exists('expiration_year', $row)) {
            $card->setExpirationYear($row['expiration_year']);
        }

        if (array_key_exists('ccv', $row)) {
            $card->setCcv($row['ccv']);
        }

        return $card;
    }

    public function createCardFromContent(?string $content): Card
    {
        return $this->saveCard(
            $this->hydrateCard(new Card(), $this->decodePayload($content))
        );
    }

    public function updateCardFromContent(Card $card, ?string $content): Card
    {
        return $this->saveCard(
            $this->hydrateCard($card, $this->decodePayload($content))
        );
    }

    public function findCardById(int $card_id): ?Card
    {
        $conn = $this->manager->getConnection();

        $sql = 'SELECT 
                C.id,
                C.type,
                AES_DECRYPT(C.name,            :tenancy_secret) AS name,                
                AES_DECRYPT(C.document,        :tenancy_secret) AS document,
                AES_DECRYPT(C.number_group_1,  :tenancy_secret) AS number_group_1,
                AES_DECRYPT(C.number_group_2,  :tenancy_secret) AS number_group_2,
                AES_DECRYPT(C.number_group_3,  :tenancy_secret) AS number_group_3,
                AES_DECRYPT(C.number_group_4,  :tenancy_secret) AS number_group_4,
                AES_DECRYPT(C.expiration_month, :tenancy_secret) AS expiration_month,
                AES_DECRYPT(C.expiration_year, :tenancy_secret) AS expiration_year,
                AES_DECRYPT(C.ccv,             :tenancy_secret) AS ccv                
            FROM card C
            LEFT JOIN people_link PL ON PL.company_id = C.people_id AND PL.link_type IN ("employee","family")
            WHERE (C.people_id = :people_id OR PL.people_id = :people_id)
            AND C.id = :card_id
        ';

        $row = $conn->executeQuery(
            $sql,
            [
                'tenancy_secret' => $_ENV['TENANCY_SECRET'],
                'card_id' => $card_id,
                'people_id' => $this->currentPeople->getId(),
            ],
            [
                'tenancy_secret' => Types::STRING,
                'card_id' => Types::INTEGER,
                'people_id' => Types::INTEGER,
            ]
        )->fetchAssociative();

        return $row ? $this->toObject($row) : null;
    }

    public function saveCard(Card $card): Card
    {
        $conn = $this->manager->getConnection();

        if ($card->getId()) {
            $sql = 'UPDATE card C
                    LEFT JOIN people_link PL ON PL.company_id = C.people_id AND PL.link_type IN ("employee","family")
                    SET
                        C.name            = AES_ENCRYPT(:name, :tenancy_secret),
                        C.type            = :type,
                        C.people_id       = :people_id,
                        C.document        = AES_ENCRYPT(:document, :tenancy_secret),
                        C.number_group_1  = AES_ENCRYPT(:g1, :tenancy_secret),
                        C.number_group_2  = AES_ENCRYPT(:g2, :tenancy_secret),
                        C.number_group_3  = AES_ENCRYPT(:g3, :tenancy_secret),
                        C.number_group_4  = AES_ENCRYPT(:g4, :tenancy_secret),
                        C.expiration_month = AES_ENCRYPT(:expm, :tenancy_secret),
                        C.expiration_year = AES_ENCRYPT(:expy, :tenancy_secret),
                        C.ccv             = AES_ENCRYPT(:ccv, :tenancy_secret)
                    WHERE (C.people_id = :people_id OR PL.people_id = :people_id)
                    AND C.id = :id
            ';
            $conn->executeStatement($sql, [
                'tenancy_secret' => $_ENV['TENANCY_SECRET'],
                'id' => $card->getId(),
                'name' => $card->getName(),
                'type' => $card->getType(),
                'people_id' => $card->getPeopleId(),
                'document' => $card->getDocument(),
                'g1' => $card->getNumberGroup1(),
                'g2' => $card->getNumberGroup2(),
                'g3' => $card->getNumberGroup3(),
                'g4' => $card->getNumberGroup4(),
                'expm' => $card->getExpirationMonth(),
                'expy' => $card->getExpirationYear(),
                'ccv' => $card->getCcv(),
            ]);
        } else {
            $this->guardCanManagePeopleCard($card->getPeopleId());

            $sql = '
                INSERT INTO card (
                    people_id, name, type, document, number_group_1,
                    number_group_2, number_group_3, number_group_4,
                    expiration_month,expiration_year, ccv
                )
                VALUES (
                    :people_id,
                    AES_ENCRYPT(:name, :tenancy_secret),
                    :type,
                    AES_ENCRYPT(:document, :tenancy_secret),
                    AES_ENCRYPT(:g1, :tenancy_secret),
                    AES_ENCRYPT(:g2, :tenancy_secret),
                    AES_ENCRYPT(:g3, :tenancy_secret),
                    AES_ENCRYPT(:g4, :tenancy_secret),
                    AES_ENCRYPT(:expm, :tenancy_secret),
                    AES_ENCRYPT(:expy, :tenancy_secret),
                    AES_ENCRYPT(:ccv, :tenancy_secret)
                )
            ';
            $conn->executeStatement($sql, [
                'tenancy_secret' => $_ENV['TENANCY_SECRET'],
                'people_id' => $card->getPeopleId(),
                'name' => $card->getName(),
                'type' => $card->getType(),
                'document' => $card->getDocument(),
                'g1' => $card->getNumberGroup1(),
                'g2' => $card->getNumberGroup2(),
                'g3' => $card->getNumberGroup3(),
                'g4' => $card->getNumberGroup4(),
                'expm' => $card->getExpirationMonth(),
                'expy' => $card->getExpirationYear(),
                'ccv' => $card->getCcv(),
            ]);

            $card->setId((int) $conn->lastInsertId());
        }

        return $card;
    }

    public function deleteCard(int $card_id): bool
    {
        $conn = $this->manager->getConnection();

        $sql = 'DELETE C FROM card C
                LEFT JOIN people_link PL ON PL.company_id = C.people_id AND PL.link_type IN ("employee","family")
                WHERE (C.people_id = :people_id OR PL.people_id = :people_id)
                AND C.id = :card_id
        ';

        $result = $conn->executeStatement($sql, [
            'card_id' => $card_id,
            'people_id' => $this->currentPeople->getId(),
        ], [
            'card_id' => Types::INTEGER,
            'people_id' => Types::INTEGER,
        ]);

        return $result > 0;
    }

    private function hydrateCard(Card $card, array $data): Card
    {
        if (isset($data['people_id'])) {
            $card->setPeopleId((int) $data['people_id']);
        }

        if (isset($data['type'])) {
            $card->setType($data['type']);
        }

        if (isset($data['name'])) {
            $card->setName($data['name']);
        }

        if (isset($data['document'])) {
            $card->setDocument($data['document']);
        }

        if (isset($data['number_group_1'])) {
            $card->setNumberGroup1($data['number_group_1']);
        }

        if (isset($data['number_group_2'])) {
            $card->setNumberGroup2($data['number_group_2']);
        }

        if (isset($data['number_group_3'])) {
            $card->setNumberGroup3($data['number_group_3']);
        }

        if (isset($data['number_group_4'])) {
            $card->setNumberGroup4($data['number_group_4']);
        }

        if (isset($data['ccv'])) {
            $card->setCcv($data['ccv']);
        }

        if (isset($data['expiration_month'])) {
            $card->setExpirationMonth($data['expiration_month']);
        }

        if (isset($data['expiration_year'])) {
            $card->setExpirationYear($data['expiration_year']);
        }

        return $card;
    }

    private function decodePayload(?string $content): array
    {
        if (!is_string($content) || trim($content) === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function guardCanManagePeopleCard(int $peopleId): void
    {
        if ($peopleId === $this->currentPeople->getId()) {
            return;
        }

        $allowed = (int) $this->manager->getConnection()->executeQuery(
            'SELECT COUNT(1)
               FROM people_link
              WHERE company_id = :people_id
                AND people_id = :current_people_id
                AND link_type IN ("employee", "family")',
            [
                'people_id' => $peopleId,
                'current_people_id' => $this->currentPeople->getId(),
            ],
            [
                'people_id' => Types::INTEGER,
                'current_people_id' => Types::INTEGER,
            ]
        )->fetchOne();

        if ($allowed <= 0) {
            throw new \InvalidArgumentException('Card owner not allowed');
        }
    }
}
