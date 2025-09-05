<?php

namespace ControleOnline\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface as Security;
use ControleOnline\Entity\People;
use ControleOnline\Entity\Card;
use ControleOnline\Entity\CardType;
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
                AES_DECRYPT(C.name,           :tenancy_secret) AS name,
                C.type,
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
        $card->setId((int) $row['id']);
        $card->setName($row['name']);
        $card->setType($row['type']);
        $card->setDocument($row['document']);
        $card->setNumberGroup1($row['number_group_1']);
        $card->setNumberGroup2($row['number_group_2']);
        $card->setNumberGroup3($row['number_group_3']);
        $card->setNumberGroup4($row['number_group_4']);
        $card->setExpirationDate($row['expiration_date']);
        $card->setCcv($row['ccv']);
        return $card;
    }

    public function findCardById(int $card_id): ?Card
    {
        $conn = $this->manager->getConnection();

        $sql = 'SELECT 
                id,
                AES_DECRYPT(name,            :tenancy_secret) AS name,
                type,
                AES_DECRYPT(document,        :tenancy_secret) AS document,
                AES_DECRYPT(number_group_1,  :tenancy_secret) AS number_group_1,
                AES_DECRYPT(number_group_2,  :tenancy_secret) AS number_group_2,
                AES_DECRYPT(number_group_3,  :tenancy_secret) AS number_group_3,
                AES_DECRYPT(number_group_4,  :tenancy_secret) AS number_group_4,
                AES_DECRYPT(expiration_date, :tenancy_secret) AS expiration_date,
                AES_DECRYPT(ccv,             :tenancy_secret) AS ccv                
            FROM card
            LEFT JOIN people_link PL ON PL.company_id = C.people_id AND PL.link_type IN ("employee","family")
            WHERE (C.people_id = :people_id OR PL.people_id = :people_id)
            AND id = :card_id
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
                        name            = AES_ENCRYPT(:name, :tenancy_secret),
                        type            = :type,
                        people_id       = :people_id,
                        document        = AES_ENCRYPT(:document, :tenancy_secret),
                        number_group_1  = AES_ENCRYPT(:g1, :tenancy_secret),
                        number_group_2  = AES_ENCRYPT(:g2, :tenancy_secret),
                        number_group_3  = AES_ENCRYPT(:g3, :tenancy_secret),
                        number_group_4  = AES_ENCRYPT(:g4, :tenancy_secret),
                        expiration_date = AES_ENCRYPT(:exp, :tenancy_secret),
                        ccv             = AES_ENCRYPT(:ccv, :tenancy_secret)
                    WHERE (C.people_id = :people_id OR PL.people_id = :people_id)
                    AND id = :id
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
                'exp' => $card->getExpirationDate(),
                'ccv' => $card->getCcv(),
            ]);
        } else {
            $sql = '
                INSERT INTO card (
                    people_id, name, type, document, number_group_1,
                    number_group_2, number_group_3, number_group_4,
                    expiration_date, ccv
                )
                SELECT 
                    :people_id,
                    AES_ENCRYPT(:name, :tenancy_secret),
                    :type,
                    AES_ENCRYPT(:document, :tenancy_secret),
                    AES_ENCRYPT(:g1, :tenancy_secret),
                    AES_ENCRYPT(:g2, :tenancy_secret),
                    AES_ENCRYPT(:g3, :tenancy_secret),
                    AES_ENCRYPT(:g4, :tenancy_secret),
                    AES_ENCRYPT(:exp, :tenancy_secret),
                    AES_ENCRYPT(:ccv, :tenancy_secret)
                FROM people_link PL
                WHERE (PL.company_id = :people_id OR PL.people_id = :people_id)
                AND PL.link_type IN ("employee","family")
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
                'exp' => $card->getExpirationDate(),
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
}
