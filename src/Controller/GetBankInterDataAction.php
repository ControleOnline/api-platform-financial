<?php

namespace ControleOnline\Controller;

use App\Entity\Config;
use ControleOnline\Entity\ReceiveInvoice;
use ctodobom\APInterPHP\BancoInterException;
use Exception;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\Invoice;
use App\Library\Inter\InterClient;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\KernelInterface;

class GetBankInterDataAction
{
    /**
     * Entity Manager
     *
     * @var EntityManagerInterface
     */
    private $manager = null;
    /**
     * @var KernelInterface
     */
    private $appKernel;

    public function __construct(EntityManagerInterface $entityManager, KernelInterface $appKernel)
    {
        $this->manager = $entityManager;
        $this->appKernel = $appKernel;
    }

    /**
     * @param Invoice $data
     * @param Request $request
     * @param string|null $operation
     * @return BinaryFileResponse|JsonResponse
     */
    public function __invoke(Invoice $data, Request $request, string $operation = null)
    {
        try {

            $ret = array();
            $apiItemOperationName = $request->get('_api_item_operation_name', null);

            if ($apiItemOperationName === 'view_pdf_billet') { // Quando a rota é "/finance/{id}/download" echo no conteúdo do boleto
                $idInvoice = $request->get('id', null);
                return $this->echoBilletPdfContent($idInvoice);
            }

            $handler = sprintf('get%s', ucfirst($operation));

            $ret['response']['data'] = $this->$handler($data);
            $ret['response']['count'] = 1;
            $ret['response']['error'] = '';
            $ret['response']['success'] = true;
        } catch (BancoInterException $e) {
            //$error = json_decode($e->reply->body);
            //$error = (is_array($error) ? $error : (is_object($error) ? (is_array($error->message) ? json_encode($error->message) : $error->message) : $error['message']));
            //$error = $error ?: $e->reply->body;
            $error = $e;
            $ret['response']['data'] = [];
            $ret['response']['count'] = 0;
            $ret['response']['error'] = 'A API do Banco Inter Informa:<br><strong>' . $error . '</strong>';
            $ret['response']['success'] = false;
        } catch (Exception $e) {

            $ret['response']['data'] = [];
            $ret['response']['count'] = 0;
            $ret['response']['error'] = $e->getMessage();
            $ret['response']['success'] = false;
        }

        return new JsonResponse($ret, 200);
    }

    /**
     * Echo no conteúdo do arquivo do PDF do boleto
     * @param $idInvoice
     * @return BinaryFileResponse
     * @throws Exception
     */
    private function echoBilletPdfContent($idInvoice): BinaryFileResponse
    {
        $invEtt = $this->manager->getRepository(ReceiveInvoice::class)->find($idInvoice);
        if (empty($invEtt)) {
            throw new Exception("Não foi possível localizar a fatura com o ID: " . $idInvoice);
        }
        $billetDueDate = $invEtt->getDueDate()->format('Y-m-d'); // Para usar na data de vencimento do boleto
        $dirPath = 'data/invoices/inter'; // Pasta dos arquivos de boleto Inter
        $pathRoot = $this->appKernel->getProjectDir();
        $billetFullPath = $pathRoot . '/' . $dirPath . '/boleto-' . $idInvoice . '-' . $billetDueDate . '.pdf'; // Arquivo do PDF completo com o nome do invoiceId + invoiceDate
        $response = new BinaryFileResponse($billetFullPath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT);
        return $response;
    }

    /**
     * Retrieve INTER billing information
     *
     * @param ReceiveInvoice $invoice
     * @return void
     * @throws Exception
     */
    private function getPayment(Invoice $invoice): array
    {
        $pathRoot = $this->appKernel->getProjectDir();
        $configs = array();
        // ---------- Pega dados do pagador e beneficiário pelo invoiceId
        $invoiceId = $invoice->getId();
        $payer = new GetProviderDataPerInvoiceId($this->manager);
        $provAndPayerData = $payer->repoSql($invoiceId);
        if (empty($provAndPayerData)) {
            throw new Exception("O beneficiário não possui uma 'config_key' com um 'payment_type' definido como 'inter' ou 'itau'");
        }
        $payerId = $provAndPayerData['payer_people_id'];
        $providerId = $provAndPayerData['provider_id'];

        // ----------------------------------------------------------------------

        // -------- Pega o Caminho dos 2 Certificados e Pega Conta Inter na Tabela 'config' no BD pelo peopleId atrelado ao providerId do invoice
        /**
         * @var Config $provEtt
         */
        $provEtt = $this->manager->getRepository(Config::class)->findBy(
            ['people' => $providerId, 'config_key' => ['crt_inter', 'key_inter', 'conta_inter']]
        );
        $msgErrExp = "Não localizou em 'config.config_key' um dos dados 'conta_inter' ou 'crt_inter' ou 'key_inter' para o Beneficiário ID: $providerId";
        if (empty($provEtt)) {
            throw new Exception($msgErrExp);
        }
        $count = 0;
        foreach ($provEtt as $val) {
            $configs[$val->getConfigKey()] = $val->getConfigValue();
            $count++;
        }
        if ($count !== 3) {
            throw new Exception($msgErrExp);
        }

        // -------- Verifica se os arquivos dos certificados realmente existem na pasta do servidor, referenciada em config Ex: 'data/keys/MG.key'
        $pathCertCRT = $pathRoot . DIRECTORY_SEPARATOR . $configs['crt_inter'];
        $pathCertKEY = $pathRoot . DIRECTORY_SEPARATOR . $configs['key_inter'];
        if (!file_exists($pathCertCRT)) {
            throw new Exception("O Aquivo '.crt' referenciado em 'config.config_value' não existe em '$pathCertCRT' para o people_id: $providerId");
        }
        if (!file_exists($pathCertKEY)) {
            throw new Exception("O Aquivo '.key' referenciado em 'config.config_value' não existe em '$pathCertKEY' para o people_id: $providerId");
        }
        // ----------------------------------------

        // throw new Exception('Debug: ' . $pathCertKEY);

        // As 3 linhas abaixo são inclusas no array '$configs' automaticamente pelo "foreach ($provEtt as $val)" acima
        // $configs['conta_inter'] = '149377932'
        // $configs['crt_inter'] = "data/keys/MG.crt"
        // $configs['key_inter'] = "data/keys/MG.key"
        $configs['payerId'] = $payerId;
        $configs['providerId'] = $providerId;
        $configs['certificado'] = $pathCertCRT;
        $configs['chavePrivada'] = $pathCertKEY;        

        return (new InterClient($invoice, $configs, $this->appKernel, $this->manager))->getPayment();
    }
}
