<?php

namespace ControleOnline\Repository;

use ControleOnline\Entity\PeoplePayment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PeoplePayment>
 *
 * @method PeoplePayment|null find($id, $lockMode = null, $lockVersion = null)
 * @method PeoplePayment|null findOneBy(array $criteria, array $orderBy = null)
 * @method PeoplePayment[]    findAll()
 * @method PeoplePayment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PeoplePaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PeoplePayment::class);
    }
}
