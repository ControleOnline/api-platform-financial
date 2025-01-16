<?php

namespace ControleOnline\Controller;

use ControleOnline\Service\DomainService;
use ControleOnline\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;


class PaylistController extends AbstractController
{
    public function __construct(
        protected EntityManagerInterface $manager,
        protected UserService $userService,
        private DomainService $domainService
    ) {


    }
    /**
     * @Route("/invoice/paylist", name="invoice_paylist", methods={"GET"})
     */
    public function __invoke(Request $request): JsonResponse
    {
        
    }
}
