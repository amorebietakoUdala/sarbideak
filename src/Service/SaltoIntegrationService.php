<?php

namespace App\Service;

use DateTime;
use DateTimeZone;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;

/** 
* @IsGranted("ROLE_SARBIDEAK")
*/
class SaltoIntegrationService
{

   private $accessToken = null;

   public function __construct(
      private readonly string $saltoTokenUrl, 
      private readonly string $saltoApiBase, 
      private readonly string $saltoApiUsername, 
      private readonly string $saltoApiPassword, 
      private readonly string $saltoClientId, 
      private readonly string $saltoClientSecret, 
      private readonly HttpClientInterface $client, 
      private readonly LoggerInterface $logger, 
      private readonly RequestStack $requestStack
      ) {
      $this->logger->debug('->SaltoIntegrationService construct start');
      // Use reconnect true to force asking another token even if it's not expired.
      //$this->reconnect(true);
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
         $contentType = $response->getHeaders()['content-type'][0];
         $content = $response->getContent();
         $this->accessToken = $response->toArray();
         $this->accessToken['date'] = new \DateTime();
         return $this->accessToken;
      } catch (\Exception) {
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
         } catch (\Exception) {
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
      return $this->executeCommand(Request::METHOD_GET, 'me');
   }

   public function getActivatedIQs($siteId) {
      return $this->executeCommand(Request::METHOD_GET,'me/'.$siteId.'/activated_iqs');
   }

   public function restoreIQ($siteId, $iqId) {
      return $this->executeCommand(Request::METHOD_POST, 'sites/'.$siteId.'/iqs/'.$iqId.'/restore');
   }

   public function getSites() {
      return $this->executeCommand(Request::METHOD_GET, 'sites');
   }

   public function getIQsFromSite($siteId) {
      return $this->executeCommand(Request::METHOD_GET, 'sites/'.$siteId.'/iqs?orderby=id asc');
   }

   public function getIQFromSite($siteId, $iqId) {
      return $this->executeCommand(Request::METHOD_GET, 'sites/'.$siteId.'/iqs/'.$iqId);
   }

   public function sendPinBySMSFromIqFromSite($siteId, $iqId) {
      return $this->executeCommand(Request::METHOD_GET, 'sites/'.$siteId.'/iqs/'.$iqId.'/pin');
   }

   public function getSecretFromIqFromSite($siteId, $iqId, $iqSecret = null, $pin = null) {
      if ( $pin !== null && $iqSecret !== null ) {
         $otp = $this->calculateOTP($iqSecret, $pin);
         return $this->executeCommand(Request::METHOD_GET, 'sites/'.$siteId.'/iqs/'.$iqId.'/secret?otp='.$otp);
      } else {
         return $this->executeCommand(Request::METHOD_GET, 'sites/'.$siteId.'/iqs/'.$iqId.'/secret');
      }
   }

   public function getLocksFromSite($siteId) {
      $allResults = [];
      $skip = 0;
      do {
         $results = $this->executeCommand(Request::METHOD_GET, 'sites/'.$siteId.'/locks?skip='.$skip);
          $allResults = array_merge($allResults, $results['items']); 
         $skip += 20;
      } while (isset($results["next_page_link"]) && $results["next_page_link"] !== null);
      $results['items'] = $allResults;
      return $results;
   }

   public function getLockById($siteId, $lockId) {
      return $this->executeCommand(Request::METHOD_GET, 'sites/'.$siteId.'/locks/'.$lockId);
   }

   public function getLockSettingsById($siteId, $lockId) {
      return $this->executeCommand(Request::METHOD_GET, 'sites/'.$siteId.'/locks/'.$lockId.'/settings');
   }

   public function setNewPIN($siteId, $iqId, $iqSecret, $oldPin, $newPin) {
      if ($iqSecret !== null) {
         $otp = $this->calculateOTP($iqSecret, $newPin, $oldPin);
         $delta = $this->calculateDelta($newPin, $oldPin);
         $this->logger->debug("OTP:\"$otp\" delta:\"$delta\"");
         return $this->executeCommand(Request::METHOD_PUT, 'sites/'.$siteId.'/iqs/'.$iqId.'/pin', [
            'otp' => "$otp",
            'delta' => "$delta",
         ]);         
      }
   }

   public function unlock($siteId, $lockId, $iqSecret, $pin) {
      $otp = $this->calculateOTP($iqSecret, $pin);
      //dump($otp);
      return $this->executeCommand(Request::METHOD_PATCH, 'sites/'.$siteId.'/locks/'.$lockId.'/locking', [
         'otp' => "$otp",
         'locked_state' => 'unlocked',
      ]);
   }

   public function activateOfficeMode($siteId, $lockId, $iqSecret, $pin) {
      $otp = $this->calculateOTP($iqSecret, $pin);
      return $this->executeCommand(Request::METHOD_PATCH, 'sites/'.$siteId.'/locks/'.$lockId.'/locking', [
         'otp' => "$otp",
         'locked_state' => 'office_mode',
      ]);
   }

   public function deactivateOfficeMode($siteId, $lockId, $iqSecret, $pin) {
      $otp = $this->calculateOTP($iqSecret, $pin);
      return $this->executeCommand(Request::METHOD_PATCH, 'sites/'.$siteId.'/locks/'.$lockId.'/locking', [
         'otp' => "$otp",
         'locked_state' => 'locked',
      ]);
   }

   private function executeCommand($type, $command, array $parameters = null): ?array {
      $body = null;
      if ($parameters !== null) {
         $body = json_encode($parameters);
         $this->logger->debug("Body:$body");
      }
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
         switch ($statusCode) {
            case Response::HTTP_OK:
               $this->logger->debug($response->getContent());
               $reponseArray = $response->toArray();
               $reponseArray['status'] = 'success';
               return $reponseArray; 
            case Response::HTTP_NO_CONTENT:
               $reponseArray = [];
               $reponseArray['status'] = 'success';
               return $reponseArray;
            default:
               $body = $response->getContent(false);
               if ( $body !== null ) {
                  //{"ErrorCode":"3102","Message":"Request execution failed with code: pin_not_changed"}
                  $body = json_decode($body, true);
                  return [
                     'status' => 'error',
                     'errorCode' => $body['ErrorCode'],
                     'message' => $body['Message'],
                  ];
               } else {
                  return [
                     'status' => 'error',
                     'message' => 'HTTP Status: '.$statusCode,
                  ];
               }
         }
         return null;
         // throw new Exception('HTTP Status NOT Ok');
      } catch (Exception $e) {
         $this->logger->error($e->getMessage());
         if ($statusCode === Response::HTTP_FORBIDDEN) {
            $this->logger->error($response->getContent(false));
            $body = $response->getContent(false);
            if ( $body !== null ) {
               $body = json_decode($body, true);
               return [
                  'status' => 'error',
                  'errorCode' => $body['ErrorCode'],
                  'message' => $body['Message'],
               ];
            }
         }
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

   public function calculateOTP($iqSecret, $pin, $oldPin = null ) {
      if ($oldPin === null) {
         $this->logger->debug('->Calculate OTP: PIN:'.$pin.' Secret:'.$iqSecret);
      } else {
         $this->logger->debug('->Calculate OTP with old PIN: OldPIN:'.$oldPin.' Secret:'.$iqSecret);
      }
      $pin = str_pad((string) $pin,4,0,STR_PAD_LEFT);
      if ($oldPin) {
         $pin = str_pad((string) $oldPin,4,0,STR_PAD_LEFT);
      }
      $date = new DateTime('now', new DateTimeZone('UTC'));
      $otp = $date->format('YmdHis').$iqSecret.$pin;
      //dump($otp);
      $md5 = md5($otp);
      //dump($md5);
      $this->logger->debug('<-Calculate OTP: END Original String:"'. $otp.'" MD5:"'.$md5.'" Final Result:'.substr($md5,0,5));
      return substr($md5,0,5);
   }

   public function calculateDelta(int $newPin, int $oldPin) {
      $this->logger->debug('->Calculate Delta New Pin:'.$newPin.' OldPin:'.$oldPin);
      $delta = ((10000 + ($newPin - $oldPin) % 10000) % 10000);
      return str_pad($delta,4,0);
   }

   // public function getIqPin(): ?string
   // {
   //    return $this->iqPin;
   // }

   // public function setIqPin($iqPin): self
   // {
   //    $this->iqPin = $iqPin;

   //    return $this;
   // }

   // public function getIqSecret(): ?string
   // {
   //    return $this->iqSecret;
   // }

   // public function setIqSecret($iqSecret): self
   // {
   //    $this->iqSecret = $iqSecret;

   //    return $this;
   // }
}
