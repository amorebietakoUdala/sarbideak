<?php

namespace App\Service;

use DateTime;
use DateTimeZone;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;

/** 
* @IsGranted("ROLE_SALTO")
*/
class SaltoIntegrationService
{

   private HttpClientInterface $client;
   private $saltoTokenUrl = null;
   private $saltoApiBase = null;
   private $saltoApiUsername = null;
   private $saltoApiPassword = null;
   private $saltoClientId = null;
   private $saltoClientSecret = null;
   private $accessToken = null;
   private LoggerInterface $logger;
   private RequestStack $requestStack;
   private $iqPin;
   private $iqSecret;
   private $command = [
      'me' => '/me'
   ];

   public function __construct(string $saltoTokenUrl, string $saltoApiBase, string $saltoApiUsername, string $saltoApiPassword, string $saltoClientId, string $saltoClientSecret, string $iqSecret, $iqPin, HttpClientInterface $client, LoggerInterface $logger, RequestStack $requestStack) {
      $this->logger = $logger;
      $this->logger->debug('->SaltoIntegrationService construct start');
      $this->client = $client;
      $this->requestStack = $requestStack;
      $this->saltoTokenUrl = $saltoTokenUrl;
      $this->saltoApiBase = $saltoApiBase;
      $this->saltoApiUsername = $saltoApiUsername;
      $this->saltoApiPassword= $saltoApiPassword;
      $this->saltoClientId = $saltoClientId;
      $this->saltoClientSecret= $saltoClientSecret;
      $this->iqSecret = $iqSecret;
      $this->iqPin = $iqPin;
      $this->reconnect();
      $this->logger->debug('<-SaltoIntegrationService construct end');
   }

   private function loadToken() {
      $session = $this->requestStack->getCurrentRequest()->getSession();
      $this->accessToken = $session->get('accessToken');
      if ( $this->accessToken === null || $this->isExpiredToken()) {
         $this->accessToken = $this->getToken();
         $session->set('accessToken', $this->accessToken);
         $this->logger->debug('Token renewed');
      }
      return $this->accessToken;
   }

   private function getToken(): ?array {
      try {
         $this->accessToken = null;
         $response = $this->client->request('POST',$this->saltoTokenUrl,[
            'headers' => [
               'content-type' => 'application/x-www-form-urlencoded',
               'authorization' => 'Basic '.base64_encode($this->saltoClientId.':'.$this->saltoClientSecret),
            ],
            'body' => $this->build_query([
               'grant_type' => 'password',
               'scope' => 'user_api.full_access',
               'username' => $this->saltoApiUsername,
               'password' => $this->saltoApiPassword,
            ])
         ]);
         $statusCode = $response->getStatusCode();
         // $statusCode = 200
         $contentType = $response->getHeaders()['content-type'][0];
         // $contentType = 'application/json'
         $content = $response->getContent();
         // $content = '{"id":521583, "name":"symfony-docs", ...}'
         $this->accessToken = $response->toArray();
         $this->accessToken['date'] = new \DateTime();
         // $content = ['id' => 521583, 'name' => 'symfony-docs', ...]
         return $this->accessToken;
      } catch (\Exception $e) {
         return null;
      }
   }

   private function build_query(array $data, $separator = '&'): string {
      $pairs = [];

      foreach ($data as $key => $value) {
         $pairs[] = "{$key}={$value}";
      }

      return implode($separator, $pairs);
   }

   public function isExpiredToken() {
      if ($this->accessToken === null) {
         return true;
      }
      $date = $this->accessToken['date'];
      $expiresIn = $this->accessToken['expires_in'];
      $expirationDate = clone $date;
      $expirationDate->modify("+$expiresIn seconds");
      if ( $expirationDate < new \DateTime() ) {
         $this->logger->debug('Expired token');
         return true;
      }
      $this->logger->debug('NOT Expired token');
      return false;
   }

   public function reconnect(bool $force = false): bool {
      if ($force) {
         $session = $this->requestStack->getCurrentRequest()->getSession();
         $session->set('accessToken', null);
         $this->accessToken = $this->loadToken();
      }
      if ( $this->isExpiredToken() ) {
         try {
            if ($this->loadToken() !== null) {
               return true;
            }
            return false;
         } catch (\Exception $e) {
            return false;
         }
      }
      return true;
   }

   public function getAccessToken()
   {
      return $this->accessToken;
   }

   public function setAccessToken($accessToken)
   {
      $this->accessToken = $accessToken;

      return $this;
   }

   public function getMe() {
      return $this->executeGetCommand('me');
   }

   public function getSites() {
      return $this->executeGetCommand('sites');
   }

   public function getIQsFromSite($siteId) {
      return $this->executeGetCommand('sites/'.$siteId.'/iqs');
   }

   public function sendPinBySMSFromIqFromSite($siteId, $iqId) {
      return $this->executeGetCommand('sites/'.$siteId.'/iqs/'.$iqId.'/pin');
   }

   public function getSecretFromIqFromSite($siteId, $iqId) {
      $otp = $this->calculateOTP($this->iqSecret,$this->iqPin);
      return $this->executeGetCommand('sites/'.$siteId.'/iqs/'.$iqId.'/secret?otp='.$otp);
   }

   public function getLocksFromSite($siteId) {
      return $this->executeGetCommand('sites/'.$siteId.'/locks');
   }

   public function getLockById($siteId, $lockId) {
      return $this->executeGetCommand('sites/'.$siteId.'/locks/'.$lockId);
   }

   public function activateIq($siteId, $iqId, $newPin) {
      if ($this->iqSecret !== null) {
         $otp = $this->calculateOTP();
         $delta = $this->calculateDelta($newPin, $this->iqPin);
         $this->logger->debug("OTP:\"$otp\" delta:\"$delta\"");
         return $this->executePutCommand('sites/'.$siteId.'/iqs/'.$iqId.'/pin', [
            'otp' => "$otp",
            'delta' => "$delta",
         ]);
      }
   }

   public function unlock($siteId, $lockId) {
      $otp = $this->calculateOTP();
      //dump($otp);
      return $this->executePatchCommand('sites/'.$siteId.'/locks/'.$lockId.'/locking', [
         'otp' => "$otp",
         'locked_state' => 'unlocked',
      ]);
   }

   private function executeGetCommand($command) {
      return $this->executeCommand(Request::METHOD_GET, $command);
   }

   private function executePatchCommand($command, $params) {
      $body = json_encode($params);
      return $this->executeCommand(Request::METHOD_PATCH, $command, $body);
   }

   private function executePutCommand($command, $params) {
      $body = json_encode($params);
      //dump($body);
      $this->logger->debug("Body:$body");
      return $this->executeCommand(Request::METHOD_PUT, $command, $body);
   }

   private function executeCommand($type, $command, $body = null): ?array {
      try {
         if (!$this->reconnect()) {
            throw new Exception('Could not connect!!');
         }
         if ( $this->getAccessToken() === null ) {
            throw new Exception('No Token!!');
         }
         $params = $this->createParams($body);
         //dump($params);
         $response = $this->client->request($type,$this->saltoApiBase.'/'. $command, $params);
         $statusCode = $response->getStatusCode();
         $this->logger->debug('Status Code:'.$statusCode);
         if ($statusCode === Response::HTTP_OK || Response::HTTP_NO_CONTENT) {
            $this->logger->debug($response->getContent());
            $reponseArray = $response->toArray();
            $reponseArray['status'] = 'success';
            return $reponseArray; 
         }
         return null;
         // throw new Exception('HTTP Status NOT Ok');
      } catch (Exception $e) {
         $this->logger->error($e->getMessage());
         return [
            'status' => 'error',
            'message' => $e->getMessage(),
         ];
      }
      return null;
   }

   private function createParams($body) {
      $params = [ 
         'headers' => [
            'Content-Type' => 'application/json',
            'authorization' => $this->getAccessToken()['token_type'].' '.$this->getAccessToken()['access_token'],
         ]
      ];
      if ($body !== null ) {
         $params['body'] = $body;
      }
      return $params;
   }

   public function calculateOTP() {
      $this->logger->debug('->Calculate OTP: PIN:'.$this->iqPin.' Secret:'.$this->iqSecret);
      $date = new DateTime('now', new DateTimeZone('UTC'));
      $otp = $date->format('YmdHis').$this->iqSecret.$this->iqPin;
      //dump($otp);
      $md5 = md5($otp);
      //dump($md5);
      $this->logger->debug('<-Calculate OTP: END Original String:"'. $otp.'" MD5:"'.$md5.'" Final Result:'.substr($md5,0,5));
      return substr($md5,0,5);
   }

   public function calculateDelta(int $newPin, int $oldPin) {
      $this->logger->debug('->Calculate Delta New Pin:'.$newPin.' OldPin:'.$oldPin);
      $delta = ((10000 + ($newPin - $oldPin) % 10000) % 10000);
      return $delta;
   }

   public function getIqPin(): ?string
   {
      return $this->iqPin;
   }

   public function setIqPin($iqPin): self
   {
      $this->iqPin = $iqPin;

      return $this;
   }

   public function getIqSecret(): ?string
   {
      return $this->iqSecret;
   }

   public function setIqSecret($iqSecret): self
   {
      $this->iqSecret = $iqSecret;

      return $this;
   }
}
