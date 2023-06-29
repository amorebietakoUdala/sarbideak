<?php

namespace App\Controller;

use App\Entity\Audit;
use App\Entity\Iq;
use App\Repository\IqRepository;
use App\Service\SaltoIntegrationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @IsGranted("ROLE_SALTO")
 * @Route("/api")
 */
class ApiController extends AbstractController
{

    const STATUS_LOCKED='locked';
    const STATUS_OFFICE_MODE='office_mode';
    const STATUS_UNLOCKED='unlocked';

    private SaltoIntegrationService $salto;
    private string $siteId;
    private EntityManagerInterface $em;
    private TranslatorInterface $translator;
    private IqRepository $iqRepo;

    public function __construct(SaltoIntegrationService $salto, EntityManagerInterface $em, TranslatorInterface $translator, IqRepository $iqRepo, string $siteId) {
        $this->salto = $salto;
        $this->siteId = $siteId;
        $this->em = $em;
        $this->translator = $translator;
        $this->iqRepo = $iqRepo;
    }

    /**
    * Unlock the selected lock. The look needs to be attached to an IQ. 
    * The IQ needs to be previously activated.
    * 
    * @Route("/lock/{lockId}/unlock", name="api_unlock")
    */
    public function unlock($lockId): JsonResponse {
        $lock = $this->salto->getLockById($this->siteId, $lockId);
        if ( !$this->checkIfLockIsAttachedToIQ($lock) ) {
            $audit = Audit::createAudit(new \DateTime(), $this->getUser(),$lock['customer_reference'], 'unlock', $this->translator->trans('messages.noIQAttachedToLock'));
            $responseData = $this->createResponseData('error','unlock', $this->translator->trans('messages.noIQAttachedToLock'));
            return $this->json($responseData,$responseData['status'] === 'success' ? 200 : 422);
        }
        $iq = $this->getIQFromLockId($lockId);
        if ($iq === null) {
            $audit = Audit::createAudit(new \DateTime(), $this->getUser(),$lock['customer_reference'], 'unlock', $this->translator->trans('messages.noSecretAndPinForThisIQ'));
            $responseData = $this->createResponseData('error','unlock', $this->translator->trans('messages.noSecretAndPinForThisIQ'));
            return $this->json($responseData,$responseData['status'] === 'success' ? 200 : 422);
        }
        $result = $this->salto->unlock($this->siteId,$lockId, $iq->getSecret(), $iq->getPin());
        if ($result !== null && $result['status'] === 'success') {
            $audit = Audit::createAudit(new \DateTime(), $this->getUser(),$lock['customer_reference'], 'unlock', 'success');
            $responseData = $this->createResponseData('success','unlock', $this->translator->trans('messages.successfullyUnlocked'));
        } else {
            $audit = Audit::createAudit(new \DateTime(), $this->getUser(),$lock['customer_reference'], 'unlock', $result !== null ? $result['message']: '');
            $responseData = $this->createResponseData('error','unlock', ( $result !== null ? $result['message']: '') );
        }
        $this->em->persist($audit);
        $this->em->flush();
        return $this->json($responseData,$responseData['status'] === 'success' ? 200 : 422);
    }

    /**
    * Activate office mode for a lock. 
    * @Route("/lock/{lockId}/activate-office-mode", name="api_activate_office_mode")
    */
    public function activateOfficeMode($lockId): JsonResponse {
        $lock = $this->salto->getLockById($this->siteId, $lockId);
        if ( !$this->checkIfLockIsAttachedToIQ($lock) ) {
            $audit = Audit::createAudit(new \DateTime(), $this->getUser(),$lock['customer_reference'], 'unlock', $this->translator->trans('messages.noIQAttachedToLock'));
            $responseData = $this->createResponseData('error','unlock', $this->translator->trans('messages.noIQAttachedToLock'));
            return $this->json($responseData,$responseData['status'] === 'success' ? 200 : 422);
        }
        $iq = $this->getIQFromLockId($lockId);
        if ($iq === null) {
            $audit = Audit::createAudit(new \DateTime(), $this->getUser(),$lock['customer_reference'], 'unlock', $this->translator->trans('messages.noSecretAndPinForThisIQ'));
            $responseData = $this->createResponseData('error','unlock', $this->translator->trans('messages.noSecretAndPinForThisIQ'));
            return $this->json($responseData,$responseData['status'] === 'success' ? 200 : 422);
        }
        $result = $this->salto->activateOfficeMode($this->siteId,$lockId, $iq->getSecret(), $iq->getPin());
        if ($result !== null && $result['status'] === 'success') {
            $audit = Audit::createAudit(new \DateTime(), $this->getUser(),$lock['customer_reference'], 'activate_office_mode','success');
            $responseData = $this->createResponseData('success','activateOfficeMode', $this->translator->trans('messages.officeModeEnabled'));
        } else {
            $audit = Audit::createAudit(new \DateTime(), $this->getUser(),$lock['customer_reference'], 'activate_office_mode', $result['message']);
            $responseData = $this->createResponseData('error','activateOfficeMode', ( $result !== null ? $result['message']: '') );
        }
        $this->em->persist($audit);
        $this->em->flush();
        return $this->json($responseData,$responseData['status'] === 'success' ? 200 : 422);
    }   

    /**
    * Deactivate office mode for a lock. 
    * @Route("/lock/{lockId}/deactivate-office-mode", name="api_deactivate_office_mode")
    */
    public function deactivateOfficeMode($lockId): JsonResponse {
        $lock = $this->salto->getLockById($this->siteId, $lockId);
        if ( !$this->checkIfLockIsAttachedToIQ($lock) ) {
            $audit = Audit::createAudit(new \DateTime(), $this->getUser(),$lock['customer_reference'], 'unlock', $this->translator->trans('messages.noIQAttachedToLock'));
            $responseData = $this->createResponseData('error','unlock', $this->translator->trans('messages.noIQAttachedToLock'));
            return $this->json($responseData,$responseData['status'] === 'success' ? 200 : 422);
        }
        $iq = $this->getIQFromLockId($lockId);
        if ($iq === null) {
            $audit = Audit::createAudit(new \DateTime(), $this->getUser(),$lock['customer_reference'], 'unlock', $this->translator->trans('messages.noSecretAndPinForThisIQ'));
            $responseData = $this->createResponseData('error','unlock', $this->translator->trans('messages.noSecretAndPinForThisIQ'));
            return $this->json($responseData,$responseData['status'] === 'success' ? 200 : 422);
        }
        $result = $this->salto->deactivateOfficeMode($this->siteId,$lockId, $iq->getSecret(), $iq->getPin());
        if ($result !== null && $result['status'] === 'success') {
            $audit = Audit::createAudit(new \DateTime(), $this->getUser(),$lock['customer_reference'], 'deactivate_office_mode', 'success');
            $responseData = $this->createResponseData('success','deactivateOfficeMode', $this->translator->trans('messages.officeModeDisabled'));
        } else {
            $audit = Audit::createAudit(new \DateTime(), $this->getUser(),$lock['customer_reference'], 'deactivate_office_mode', $result['message']);
            $responseData = $this->createResponseData('error','deactivateOfficeMode', ( $result !== null ? $result['message']: '') );
        }
        $this->em->persist($audit);
        $this->em->flush();
        return $this->json($responseData,$responseData['status'] === 'success' ? 200 : 422);
    }

    /**
    * Get IQ details. Status restore_required and so on.
    *
    * @Route("/iq/{iqId}", name="api_iq_details")
    */
    public function iqDetails($iqId): JsonResponse {
        $result = $this->salto->getIQFromSite($this->siteId, $iqId);
        if ($result !== null && $result['status'] === 'success') {
            $audit = Audit::createAudit(new \DateTime(), $this->getUser(),$result['customer_reference'], 'iq_details', 'success');
            $responseData = $this->createResponseData('success','iq_details', $result);
        } else {
            $audit = Audit::createAudit(new \DateTime(), $this->getUser(),$result['customer_reference'], 'iq_details', $result['message']);
            $responseData = $this->createResponseData('error','iq_details', ( $result !== null ? $result['message']: '' ));
        }
        $this->em->persist($audit);
        $this->em->flush();
        return $this->json($responseData,$responseData['status'] === 'success' ? 200 : 422);
    }

    /**
     * Needed after a the IQ has been reset. When restore_required is true you need to restore the IQ config in order to work.
     * If it's not done this way it gives a 403 Error when you try to unlock door.
     * 
     * @Route("/iq/{iqId}/restore", name="iq_restore")
    */
    public function restore($iqId): JsonResponse {
        $result = $this->salto->restoreIQ($this->siteId, $iqId);
        if ($result !== null && $result['status'] === 'success') {
            $audit = Audit::createAudit(new \DateTime(), $this->getUser(),( $result !== null ? $result['customer_reference']: $iqId ), 'restore_iq','success');
            $responseData = $this->createResponseData('success','restore_iq', $this->translator->trans('messages.iqSuccessfullyRestored'));
        } else {
            $audit = Audit::createAudit(new \DateTime(), $this->getUser(), ( $result !== null ? $result['customer_reference']: $iqId ) , 'restore_iq', 'error: '. ($result !== null ? $result['message']: ''));
            $responseData = $this->createResponseData('error','restore_iq', ( $result !== null ? $result['message']: '' ));
        }
        $this->em->persist($audit);
        $this->em->flush();
        return $this->json($responseData,$responseData['status'] === 'success' ? 200 : 422);
    }

    private function getIQFromLockId($lockId): ?Iq {
        $lock = $this->salto->getLockById($this->siteId, $lockId);
        $iqData = $lock['iq'] !== null ? $lock['iq'] : null;
        if ( $iqData !== null ) {
            $iq = $this->iqRepo->findOneBy(['iqId' => $iqData['id']]);
            return $iq;
        } 
        return null;
    }

    private function checkIfLockIsAttachedToIQ($lock): bool {
        $iq = $lock['iq'] !== null ? $lock['iq'] : null;
        if ( $iq !== null ) {
            return true;
        }
        return false;
    }

    private function createResponseData($status, $action, $message): array {
        return $responseData = [
            'status' => $status,
            'action' => $action,
            'message' => $message,
        ];
    }

}
