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
     * @Route("/oauth/google/return", name="google_return", methods={"GET","POST"})
     */
    public function __invoke(Request $request): JsonResponse
    {
        
    }
}
