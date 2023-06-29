<?php

namespace App\Controller;

use App\Entity\Audit;
use App\Entity\Iq;
use App\Form\PinType;
use App\Repository\IqRepository;
use App\Service\SaltoIntegrationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/** 
* @IsGranted("ROLE_ADMIN")
* @Route("/{_locale}")
*/
class IQController extends BaseController
{
    private SaltoIntegrationService $salto;
    private string $siteId;
    private EntityManagerInterface $em;
    private TranslatorInterface $translator;
    private IqRepository $repo;

    public function __construct(SaltoIntegrationService $salto, EntityManagerInterface $em, string $siteId, TranslatorInterface $translator, IqRepository $repo) {
        $this->salto = $salto;
        $this->siteId = $siteId;
        $this->em = $em;
        $this->translator = $translator;
        $this->repo = $repo;
    }

    /**
     * Shows IQ index page
     * 
     * @Route("/iq", name="iq_index")
     */
    public function index(Request $request): Response
    {
        $this->loadQueryParameters($request);
        // When IQ has been reset if has an attribute restore_required = true. And needs to be restored in order to work.
        $iqs = $this->salto->getIQsFromSite($this->siteId);
        $activatedIqs = $this->salto->getActivatedIQs($this->siteId);
        $activatedIqIds = $this->getActivatedIQIds($activatedIqs);
        return $this->render('iq/index.html.twig', [
            'iqs' => $iqs['items'],
            'activatedIqIds' => $activatedIqIds,
        ]);
    }

    /**
     * Activates the selected IQ.
     * Only works on a reset device. Else it's gives a 403 Error.
     * 
     * @Route("/iq/{iqId}/activate", name="iq_activate")
    */
    public function activate(Request $request, $iqId) {
        $secret = $customerReference = null;
        $form = $this->createForm(PinType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $result = $this->salto->setNewPIN($this->siteId, $iqId, $data['secret'], $data['oldPin'], $data['newPin']);
            if ($result !== null && $result['status'] === 'success') {
                $audit = Audit::createAudit(new \DateTime(), $this->getUser(),$data['customerReference'], 'activate_iq','success');
                $this->addFlash('success', 'messages.iqSuccessfullyActivated');
                $iq = $this->repo->findOneBy([
                    'iqId' => $iqId,
                ]);
                if ($iq !== null) {
                    $iq->setPin($data['newPin']);
                    $iq->setSecret($data['secret']);
                } else {
                    $iq = new Iq();
                    $iq->setPin($data['newPin']);
                    $iq->setSecret($data['secret']);
                    $iq->setIqId($iqId);
                }
                $this->em->persist($iq);
            } else {
                $audit = Audit::createAudit(new \DateTime(), $this->getUser(), $data['customerReference'], 'activate_iq', 'error: '.($result !== null ? $result['message']: ''));
                $this->addFlash('error', $result['message']);
            }
            $this->em->persist($audit);
            $this->em->flush();
            return $this->redirectToRoute('iq_index');
        }
        if (!$form->isSubmitted()) {
             $result = $this->getSecretAndPin($iqId);
             $secret = $result['secret'];
             $customerReference = $result['customerReference'];
        }
        return $this->renderForm('iq/changePin.html.twig', [
            'form' => $form,
            'secret' => $secret,
            'customerReference' => $customerReference,
        ]);
    }

    /**
    * @Route("/iq/{iqId}/pin", name="get_pin")
    */
    // public function getPin(Request $request, $iqId) {
    //     $result = $this->salto->sendPinBySMSFromIqFromSite($this->siteId, $iqId);
    //     if ($result !== null && $result['status'] === 'success') {
    //         $audit = Audit::createAudit(new \DateTime(), $this->getUser(),$iqId, 'get_pin','success');
    //         $this->addFlash('success', $this->translator->trans('messages.pinSuccessfullySent'));           
    //     } else {
    //         $audit = Audit::createAudit(new \DateTime(), $this->getUser(),$iqId, 'get_pin', 'error: '. ($result !== null ? $result['message']: ''));
    //         $this->addFlash('error', $result['message']);
    //     }
    //     $this->em->persist($audit);
    //     $this->em->flush();
    //     $form = $this->createForm(PinType::class);
    //     return $this->redirectToRoute('iq_index');
    // }

    /**
    * Generates an OTP with for that IQ.
    * The IQ needs to be activated previously, otherwise it won't work.
    * Only needed to activate other devices like mobile phones.
    *
    * @Route("/iq/{iqId}/otp", name="iq_otp")
    */
    public function otp($iqId): Response
    {
        $iq = $this->repo->findOneBy(['iqId' => $iqId]);
        $result = $this->salto->getIQFromSite($this->siteId, $iqId);
        if ( $iq === null ) {
            $this->addFlash('error', 'messages.noSecretAndPinForThisIQ');
            $audit = Audit::createAudit(new \DateTime(), $this->getUser(),$result['customer_reference'], 'get_otp', $this->translator->trans('messages.noSecretAndPinForThisIQ'));
            $this->em->persist($audit);
            $this->em->flush();
            return $this->redirectToRoute('iq_index');
        }
        $otp = $this->salto->calculateOTP($iq->getSecret(),$iq->getPin());
        $audit = Audit::createAudit(new \DateTime(), $this->getUser(),$result['customer_reference'], 'get_otp', 'success');
        $this->em->persist($audit);
        $this->em->flush();
        $this->addFlash('success', $this->translator->trans('messages.otpSuccessfullyGenerated', [
            '{otp}' => $otp,
        ]));
        return $this->redirectToRoute('iq_index');
    }

    /**
    * @Route("/iq/activated", name="iq_activated")
    */
    // public function getActivatedIQs(Request $request) {
    //     $result = $this->salto->getActivatedIQs($this->siteId);
    //     dd($result);
    //     if ($result !== null && $result['status'] === 'success') {
    //         $audit = Audit::createAudit(new \DateTime(), $this->getUser(),$iqId, 'get_pin','success');
    //         $this->addFlash('success', $this->translator->trans('messages.pinSuccessfullySent'));           
    //     } else {
    //         $audit = Audit::createAudit(new \DateTime(), $this->getUser(),$iqId, 'get_pin', 'error: '. $result !== null ? $result['message']: '');
    //         $this->addFlash('error', $result['message']);
    //     }
    //     $this->em->persist($audit);
    //     $this->em->flush();
    //     $form = $this->createForm(PinType::class);
    //     return $this->redirectToRoute('iq_index');
    // }

    /**
    * It ask the IQ for the secret and sends SMS with IQ PIN.
    * The IQ needs to be reset, otherwise you won't get the secret.
    * 
    */
    private function getSecretAndPin($iqId): array {
        $secret = $customerReference = null;
        $iqData = $this->salto->getIQFromSite($this->siteId, $iqId);
        $result = $this->salto->getSecretFromIqFromSite($this->siteId, $iqId);
        if ($result !== null && $result['status'] === 'success') {
            $audit = Audit::createAudit(new \DateTime(), $this->getUser(), ( $result !== null ? $iqData['customer_reference'] : $iqId ), 'get_secret','success');
            $this->addFlash('success', $this->translator->trans('messages.getSecretSuccess', [
                '{secret}' => $result['secret'],
            ]));
            $secret = $result['secret'];
            $customerReference = $iqData['customer_reference'];
            $result = $this->salto->sendPinBySMSFromIqFromSite($this->siteId, $iqId);
        } else {
            $audit = Audit::createAudit(new \DateTime(), $this->getUser(), ( $result !== null ? $iqData['customer_reference'] : $iqId ), 'get_secret', 'error: '. ( $result !== null ? $result['message']: '' ));
            $this->addFlash('error', $result['message']);
        }
        $this->em->persist($audit);
        $this->em->flush();
        return [ 
            'secret' => $secret, 
            'customerReference' => $customerReference,
        ];
    }

    /**
    * Get the Ids othe activated IQs. Needed to show/hide buttons the IQs are activated or deactivated.
    * 
    */
    private function getActivatedIQIds($activatedIqs): array
    {
        $activatedIqIds = [];
        foreach ($activatedIqs['items'] as $activated) {
            foreach ( $activated as $key => $value) {
                if ( $key === 'iq_id' ) {
                    $activatedIqIds[] = $activated['iq_id'];
                }
            }
        }
        return $activatedIqIds;
    }
}
