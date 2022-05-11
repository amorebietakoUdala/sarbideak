<?php

namespace App\Controller;

use App\DTO\Audit;
use App\Form\UploadType;
use DateTime;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class UploadController extends AbstractController
{
 
    private $mailer;
    private $translator;
    private $logger;

    public function __construct(MailerInterface $mailer, TranslatorInterface $translator, LoggerInterface $auditLogger) {
        $this->mailer = $mailer;
        $this->translator = $translator;
        $this->logger = $auditLogger;
    }

    /**
     * @Route("/{_locale}/upload", name="app_kutxa")
     */
    public function upload(Request $request, SluggerInterface $slugger): Response
    {
        if ( $request->getSession()->get('giltzaUser') === null ) {
            return $this->redirectToRoute('app_giltza');
        }
//        dd($request->getSession()->get('giltzaUser'));
        $form = $this->createForm(UploadType::class,null,[
            'maxFileSize' => $this->getParameter('maxFileSize'),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            if ( strpos($data['receiverEmail'], $this->getParameter('receiverDomain')) === false ) {
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
            $fileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME).'.'.$file->getClientOriginalExtension();
            $sha1 = sha1_file($file); 
            $size = $file->getSize(); 
            $data['sha1'] = $sha1;
            $data['size'] = $this->formatBytes($size);
            $data['fileName'] = $fileName;
            $error = $this->moveUploadedFile($file);
            if (!$error) {
                $giltzaUser = $request->getSession()->get('giltzaUser');
                $this->sendEmails($data, $giltzaUser);
                $audit = new Audit($giltzaUser['cif'],$giltzaUser['dni'],$fileName,$sha1,$size,$data['senderEmail'],$data['receiverEmail']);
                $this->logger->info($audit->__toString());
                $message = $this->translator->trans('message.fileSaved');
                $this->addFlash('success', $message);
                return $this->redirectToRoute('app_kutxa');
            }
        }

        return $this->render('kutxa/upload.html.twig',[
            'form' => $form->createView(),
            'maxFileSize' => $this->getParameter('maxFileSize'),
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
    }

    private function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KiB', 'MiB', 'GiB', 'TiB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    private function sendEmails($data, $giltzaUser) {
        $context = [
            'giltzaUser' => $giltzaUser,
            'data' => $data,
            'date' => (new \DateTime())->format('Y-m-d'),
            'hour' => (new \DateTime())->format('H:i:s'),
        ];
        if ($this->getParameter('sendMessagesReceiver')) {
            $template = 'kutxa/fileReceptionEmailReceiver.html.twig';
            $subject = $this->translator->trans('message.emailSubjectReceiver');
            $html = $this->renderView('kutxa/fileReceptionEmailReceiver.html.twig', $context);
            $this->sendEmail($data['receiverEmail'], $subject, $template, $context);
        }
        if ($this->getParameter('sendMessagesSender')) {
            $template = 'kutxa/fileReceptionEmailSender.html.twig';
            $subject = $this->translator->trans('message.emailSubjectSender');
            $this->sendEmail($data['senderEmail'], $subject, $template, $context);
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
