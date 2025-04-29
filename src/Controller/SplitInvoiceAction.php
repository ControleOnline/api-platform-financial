<?php

namespace ControleOnline\Controller;

use ControleOnline\Entity\Invoice;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use ControleOnline\Service\BraspagService;
use Braspag\Split\Exception\BraspagSplitException;
use ControleOnline\Service\LoggerService;


class SplitInvoiceAction
{
    protected static $logger;

    public function __construct(
        private LoggerService $loggerService,
        private EntityManagerInterface $manager,
        private BraspagService $braspag,
    ) {
        self::$logger = $loggerService->getLogger('braspag');
    }

    /**
     * @param Invoice $data
     * @param Request $request
     * @param string|null $operation
     * @return BinaryFileResponse|JsonResponse
     */
    public function __invoke(Invoice $data, Request $request)
    {

        try {
            $ret = $this->braspag->split($data);
            return new JsonResponse($ret, 200);
        } catch (\Exception $e) {
            $this->logger->error('Failed', ['exception' => $e]);
            return new JsonResponse([
                'response' => [
                    'data'    => [],
                    'count'   => 0,
                    'error'   => $e->getMessage(),
                    'success' => false,
                ],
            ], 500);
        } catch (BraspagSplitException $e) {
            $this->logger->error('Failed', ['exception' => $e]);
            return new JsonResponse([
                'response' => [
                    'data'    => [],
                    'count'   => 0,
                    'error'   => $e->getResponse(),
                    'success' => false,
                ],
            ], $e->getStatusCode());
        }
    }
}
