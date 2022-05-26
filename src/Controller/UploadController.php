<?php

namespace App\Controller;

use App\Entity\Audit;
use App\Form\UploadType;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class UploadController extends AbstractController
{
 
    private MailerInterface $mailer;
    private TranslatorInterface $translator;
    private EntityManagerInterface $em;

    public function __construct(MailerInterface $mailer, TranslatorInterface $translator, LoggerInterface $auditLogger, EntityManagerInterface $em) {
        $this->mailer = $mailer;
        $this->translator = $translator;
        $this->em = $em;
    }

    /**
     * @Route("/{_locale}/erregistro", name="app_erregistro")
     */
    public function register(Request $request): Response
    {
        return $this->forward('App\Controller\UploadController::upload',[
            'request' => $request,
            'register' => true,
        ]);
    }

    /**
     * @Route("/{_locale}/igo", name="app_igo")
     */
    public function upload(Request $request, $register = false ): Response
    {
        $routeName = 'app_igo';
        if ($register) {
            $routeName = 'app_erregistro';
        }
        if ( $request->getSession()->get('giltzaUser') === null ) {
            return $this->redirectToRoute('app_giltza', [
                'destination' => $routeName,
            ]);
        }
        $form = $this->createForm(UploadType::class,null,[
            'maxFileSize' => $this->getParameter('maxFileSize'),
            'register' => $register,
            'receptionEmail' => $this->getParameter('receptionEmail'),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Audit $data */
            $data = $form->getData();
            if ( strpos($data->getReceiverEmail(), $this->getParameter('receiverDomain')) === false ) {
                $message = $this->translator->trans('message.domainNotAllowed', [
                    'receiverDomain' => $this->getParameter('receiverDomain'),
                ]);
                $this->addFlash('error', $message);
                return $this->render('kutxa/upload.html.twig',[
                    'form' => $form->createView(),
                    'maxFileSize' => $this->getParameter('maxFileSize'),
                ]);                
            }
            /** @var UploadedFile $file */
            $file = $form->get('file')->getData();
            $data->setFileData($file);
            $error = $this->moveUploadedFile($file);
            if (!$error) {
                $giltzaUser = $request->getSession()->get('giltzaUser');
                $data->fill($giltzaUser);
                $data->setCreatedAt(new \DateTime());
                $this->sendEmails($data);
                $this->em->persist($data);
                $this->em->flush();
                $message = $this->translator->trans('message.fileSaved');
                $this->addFlash('success', $message);
                return $this->redirectToRoute($routeName);
            }
        }

        return $this->render('kutxa/upload.html.twig',[
            'form' => $form->createView(),
            'maxFileSize' => $this->getParameter('maxFileSize'),
            'register' => $register,
        ]);
    }

    private function moveUploadedFile(UploadedFile $file) {
        $error = false;
        if ($file) {
            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $newFilename = $originalFilename.'.'.$file->getClientOriginalExtension();

            try {
                $sha1 = sha1_file($file);
                $finalDir = $this->getParameter('uploadDir').'/'.$sha1;
                file_exists($finalDir) ? $this->deleteDirectory($finalDir) : mkdir($finalDir);
                $file->move($finalDir,$newFilename);
            } catch (FileException $e) {
                $error = true;
                $this->addFlash('error', $e->getMessage());
            }
        }
        return $error;
    }

    private function sendEmails(Audit $data) {
        $context = [
            'data' => $data,
        ];
        if ($this->getParameter('sendMessagesReceiver')) {
            $template = 'kutxa/fileReceptionEmailReceiver.html.twig';
            $subject = $this->translator->trans('message.emailSubjectReceiver');
//            $html = $this->renderView('kutxa/fileReceptionEmailReceiver.html.twig', $context);
            $this->sendEmail($data->getReceiverEmail(), $subject, $template, $context);
        }
        if ($this->getParameter('sendMessagesSender')) {
            $template = 'kutxa/fileReceptionEmailSender.html.twig';
            $subject = $this->translator->trans('message.emailSubjectSender');
//            $html = $this->renderView('kutxa/fileReceptionEmailSender.html.twig', $context);
            $this->sendEmail($data->getSenderEmail(), $subject, $template, $context);
        }
    }

    private function sendEmail($to, $subject, $template, $context) {
        $email = (new TemplatedEmail())
            ->from($this->getParameter('mailerFrom'))
            ->to($to)
            ->subject($subject)
            ->htmlTemplate($template)
            ->context($context);
        if ( $this->getParameter('sendBCC') ) {
            $addresses = [$this->getParameter('mailerBCC')];
            foreach ($addresses as $address) {
                $email->addBcc($address);
            }
        }
        $this->mailer->send($email);
    }

    private function deleteDirectory($dir) {
        if (!file_exists($dir)) {
            return true;
        }
        if (!is_dir($dir)) {
            return unlink($dir);
        }
    
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            if (!$this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }
    
        return rmdir($dir);
    }



}
