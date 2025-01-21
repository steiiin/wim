<?php

    namespace WIM\SharepointApi;

    // files ##########################################################################################
    require_once dirname(__FILE__) . '/Unirest/Exception.php';
    require_once dirname(__FILE__) . '/Unirest/Method.php';
    require_once dirname(__FILE__) . '/Unirest/Response.php';
    require_once dirname(__FILE__) . '/Unirest/Request.php';
    require_once dirname(__FILE__) . '/Unirest/Request/Body.php';

    // Exceptions #################################################################################
    class ApiError extends \Exception {};
    class AuthenticationError extends \Exception {};

    // Entities ###################################################################################
    class EventItem
    {

        private string $id;
        private string $title;
        private string $description;
        private string $category;
        private string $location;

        private bool $eventAllday;
        private \DateTime $eventStart;
        private \DateTime $eventEnd;

        public function __construct(
            ?string $id,
            ?string $title,
            ?string $description,
            ?string $category,
            ?string $location,
            bool $isAllday,
            ?string $eventStart,
            ?string $eventEnd
        ) {
            $this->id = $id;
            $this->title = $title;

            $this->description = \strip_tags($description) ?? '';
            $this->location = $location ?? '';
            $this->category = $category ?? '';
            if (strpos($title, 'MDR') !== false && !$category && !$description) 
            { 
                $this->category = 'Monatsdesinfektion'; 
            }

            $this->eventAllday = $isAllday;
            $this->eventStart = \DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $eventStart);
            $this->eventEnd = \DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $eventEnd);
        }

        public function getId(): string { return $this->id ?? ""; }
        public function getTitle(): string { return $this->title ?? ""; }
        public function getDescription(): string { return $this->description ?? ""; }
        public function getLocation(): string { return $this->location ?? ""; }
        public function getCategory(): string { return $this->category ?? ""; }
        public function getIsAllDay(): bool { return $this->eventAllday ?? true; }
        public function getEventStart(): \DateTime { return $this->eventStart ?? new \DateTime(); }
        public function getEventEnd(): \DateTime { return $this->eventEnd ?? new \DateTime(); }

    }

    // MainClass ##################################################################################
    class Client 
    {

        private string $username = '';
        private string $password = '';

        private string $AUTHTOKEN = '';

        public function __construct($username, 
                                    $password
        ) {

            $this->username = $username;
            $this->password = $password;

            // Delete Cookiecache
            $cookiePath = dirname(__FILE__) .  '/Unirest/cookieJar.txt';
            if(!$this->clearFile($cookiePath)) { die('ERROR: Could not clear CookieCache.'); }

            // Configure Unirest
            Unirest\Request::defaultHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4482.0 Safari/537.36 Edg/92.0.874.0');
            Unirest\Request::defaultHeader('Accept-Language', 'de-DE');
            Unirest\Request::cookieFile($cookiePath);

        }

        // CLIENT-ENDPOINTS #######################################################################

        public function Login()
        {

	    // Open authenticate
	    $open = $this->OpenAuthentication();
	    if ($open['auth'] == 'authenticated')
	    {
	        echo 'Login(): Already authenticated.';
	        return true;
	    }
	    else if ($open['auth'] == 'opened')
	    {
	        // get authentication parameters
	        $paramsState = $this->GetAuthParams($open);
	        if ($paramsState['ok'] === false)
	        {
	            echo 'Login()::GetAuthParams()// Fehler: ' . $paramsState['msg'];
	            return false;
	        }

	        // authenticate
	        $authState = $this->Authenticate($open);
	        if ($paramsState['ok'] === false)
                {
                    echo 'Login()::Authenticate()// Fehler: ' . $authState['msg'];
                    return false;
                }

                // get access
                $authState = $this->GetAccess($open);
                if ($paramsState['ok'] === false)
                {
                    echo 'Login()::GetAccess()// Fehler: ' . $authState['msg'];
                    return false;
                }

	        return true;

	    }
	    else if ($open['auth'] == 'failure')
	    {
	        echo 'Login()::OpenAuthentication()// Fehler: ' . $open['msg'];
	        return false;
	    }

            return true;

        }

        public function GetEvents($endpoint)
        {

            $filterDateStart = gmdate("Y-m-d\TH:i:s\Z", strtotime('-1 days')); 
            $filterDateEnd = gmdate("Y-m-d\TH:i:s\Z", strtotime('+30 days'));

            $headers = [
                'X-RequestDigest' => $this->AUTHTOKEN,
                'Accept' => 'application/json; odata=verbose'
            ];
            $apiUrl = $endpoint . '/items?$select=ID,GUID,Title,Description,EventDate,EndDate,fAllDayEvent,Category,Location&$filter=(EndDate ge datetime\''.$filterDateStart.'\') and (EventDate le datetime\''.$filterDateEnd.'\')&$orderby=EventDate%20asc';

            try 
            {

                $response = Unirest\Request::get($apiUrl, $headers);
                if ($response->code == 200)
                {

                    $json = \json_decode($response->raw_body);
                    $data = $json->d->results;

                    $events = [];
                    foreach ($data as $item)
                    {

                        // fetch description
                        $events[] = new EventItem(
                            $item->GUID,
                            $item->Title,
                            $item->Description,
                            $item->Category,
                            $item->Location,
                            $item->fAllDayEvent,
                            $item->EventDate,
                            $item->EndDate
                        );

                    }
                    if (count($events) > 0) { return $events; }

                }
                return false; 

            }
            catch (Throwable $e) { return false; }

        }

        // AUTHENTICATION #########################################################################

	private function OpenAuthentication(): Array
	{

	    $checkUrl = 'https://maltesercloud.sharepoint.com/';
            $headers = array('Accept' => "text/html,application/xhtml+xml,application/xml;q=0.9,*/\*;q=0.8");

	    $error = '';

	    try
	    {

	        $response = Unirest\Request::get($checkUrl, $headers);
	        if ($response->code == 200)
                {

	            $finalUrl = array_slice($response->headers['location'], -1)[0];
	            if (stripos($finalUrl, 'login.microsoftonline.com') !== false)
	            {
	                // has to authenticate
	                $msRequestId = $response->headers['x-ms-request-id'];
	                $oauthId = $this->extractValue($finalUrl, '.com:443/', '/');

	                $flowToken = $this->extractValue($response->raw_body, '"sFT":"', '"');
	                if ($flowToken === false)
	                {
	                    $error = 'no flowToken (sFT) found.';
			}
			else
			{

		           $orgRequest = $this->extractValue($response->raw_body, 'fctx%3d', '\u0026');
                           if ($orgRequest === false)
                           {
                               $error = 'no originalRequest (fctx) found.';
                           }
                           else
                           {

                              $hpgid = $this->extractValue($response->raw_body, '"hpgid":', ',');
                              if ($hpgid === false)
                              {
                                  $error = 'no hpgid found.';
                              }
                              else
                              {

                                  $hpgact = $this->extractValue($response->raw_body, '"hpgact":', ',');
                                  if ($hpgact === false)
                                  {
                                      $error = 'no hpgact found.';
                                  }
                                  else
                                  {

                                      $paramsCanary = $this->extractValue($response->raw_body, '"apiCanary":"', '"');
                                      if ($paramsCanary === false)
                                      {
                                          $error = 'no paramsCanary (apiCanary) found.';
                                      }
                                      else
                                      {

	                                  $loginCanary = $this->extractValue($response->raw_body, '"canary":"', '"');
	                                  if ($loginCanary === false)
                                          {
                                              $error = 'no loginCanary (canary) found.';
                                          }
                                          else
                                          {

                                              $clientId = $this->extractValue($response->raw_body, 'client-request-id=', '\u0026');
                                              if ($clientId === false)
                                              {
                                                  $error = 'no clientId (client-request-id) found.';
                                              }
                                              else
                                              {

	                                          $state = $this->extractValue($response->raw_body, '"state":"', '"');
	                                          if ($state === false)
	                                          {
	                                              $error = 'no state found.';
	                                          }
	                                          else
	                                          {

   			    	                      return [
	                                                  'auth' => 'opened',
                                                          'flowToken' => $flowToken,
                                                          'orgRequest' => $orgRequest,
                                                          'msRequestId' => $msRequestId,
	                                                  'hpgid' => $hpgid,
	                                                  'hpgact' => $hpgact,
	                                                  'paramsCanary' => $paramsCanary,
	                                                  'loginCanary' => $loginCanary,
	                                                  'clientId' => $clientId,
	                                                  'oauthId' => $oauthId,
	                                                  'state' => $state,
                                                      ];

	                                          }

	                                     }

                                          }

                                      }

                                  }

                              }

                           }

			}

                    }
                    else if (stripos($finalUrl, 'login.microsoftonline.com') !== false)
                    {
                        // already logged in
                        return [ 'auth' => 'authenticated' ];
	            }

                }

	    }
	    catch (Throwable $e)
	    {
	        // nothing to do
	        $error = $e->message;
	    }

	    return [ 'auth' => 'failure', 'msg' => $error ];

	}

	private function GetAuthParams(&$open): Array
	{

	    $hpgid = $open['hpgid'];
	    $hpgact = $open['hpgact'];
	    $paramsCanary = $open['paramsCanary'];
	    $msRequestId = $open['msRequestId'];
	    $clientId = $open['clientId'];
	    $flowToken = $open['flowToken'];
	    $orgRequest = $open['orgRequest'];

	    $checkUrl = 'https://login.microsoftonline.com/common/GetCredentialType?mkt=de';
            $headers = [
	        'Accept' => 'application/json',
	        'canary' => $paramsCanary,
	        'client-request-id' => $clientId,
	        'hpgact' => $hpgact,
	        'hpgid' => $hpgid,
	        'hprequestid' => $msRequestId,
	        'Priority' => 'u=0'
	    ];
	    $body = Unirest\Request\Body::Json([
	        "username" => $this->username,
	        "isOtherIdpSupported" => true,
	        "checkPhones" => false,
	        "isRemoteNGCSupported" => true,
	        "isCookieBannerShown" => false,
	        "isFidoSupported" => true,
	        "originalRequest" => $orgRequest,
	        "country" => "DE",
	        "forceotclogin" => false,
	        "isExternalFederationDisallowed" => false,
	        "isRemoteConnectSupported" => false,
	        "federationFlags" => 0,
	        "isSignup" => false,
	        "flowToken" => $flowToken,
	        "isAccessPassSupported" => true,
	        "isQrCodePinSupported" => true
	    ]);

	    $error = '';

	    try
	    {

	        $response = Unirest\Request::post($checkUrl, $headers, $body);
                if ($response->code == 200)
	        {

	            return [ 'ok' => true ];

	        }

	        $error = 'GetAuthParams(): status code not ok (' . $response->code . ')';

	    }
            catch (Throwable $e)
            {
                // nothing to do
                $error = $e->message;
            }

            return [ 'ok' => false, 'msg' => $error ];

	}

	private function Authenticate(&$open): Array
	{

	    $oauthId = $open['oauthId'];
	    $loginCanary = $open['loginCanary'];
	    $msRequestId = $open['msRequestId'];
	    $flowToken = $open['flowToken'];
	    $orgRequest = $open['orgRequest'];

	    $checkUrl = 'https://login.microsoftonline.com/' . $oauthId . '/login';
	    $body = Unirest\Request\Body::Form([
	        'i13' => 0,
                'login' => $this->username,
                'loginfmt' => $this->username,
                'type' => 11,
                'LoginOptions' => 3,
                'passwd' => $this->password,
                'ps' => 2,
                'canary' => $loginCanary,
                'ctx' => $orgRequest,
                'hpgrequestid' => $msRequestId,
                'flowToken' => $flowToken,
                'NewUser' => 1,
                'fspost' => 0,
                'i21' => 0,
                'CookieDisclosure' => 0,
                'IsFidoSupported' => 1,
                'isSignupPost' => 0,
                'i19' => 45842
	    ]);

	    $error = '';

            try
            {

                $response = Unirest\Request::post($checkUrl, [], $body);
                if ($response->code == 200 && stripos($response->raw_body, 'https://maltesercloud.sharepoint.com/_forms/default.aspx') !== false)
                {

	            $open['code'] = $this->extractValue($response->raw_body, 'name="code" value="', '"');
                    if ($open['code'] === false)
                    {
                        $error = 'no code found.';
                    }
                    else
                    {

	                $open['msRequestId'] = $response->headers['x-ms-request-id'];

	                $open['idToken'] = $this->extractValue($response->raw_body, 'name="id_token" value="', '"');
                        if ($open['idToken'] === false)
                        {
                            $error = 'no idToken (id_token) found.';
                        }
                        else
                        {

	                    $open['sessionState'] = $this->extractValue($response->raw_body, 'name="session_state" value="', '"');
                            if ($open['sessionState'] === false)
                            {
                                $error = 'no sessionState (session_state) found.';
                            }
                            else
                            {

	                        $open['correlation'] = $this->extractValue($response->raw_body, 'name="correlation_id" value="', '"');
                                if ($open['correlation'] === false)
                                {
                                    $error = 'no correlation (correlation_id) found.';
                                }
                                else
                                {
                                    return [ 'ok' => true ];
	                    
	                        }
	                    }

        	        }

	            }
                }

                $error = 'status not ok (' . $response->code . ') or failure (' . $response->raw_body . ')';

            }
            catch (Throwable $e)
            {
                // nothing to do
                $error = $e->message;
            }

            return [ 'ok' => false, 'msg' => $error ];


	}

	private function GetAccess(&$open): Array
	{

	    $code = $open['code'];
            $idToken = $open['idToken'];
            $state = $open['state'];
            $sessionState = $open['sessionState'];
            $correlation = $open['correlation'];

	    $checkUrl = 'https://maltesercloud.sharepoint.com/_forms/default.aspx';
            $body = Unirest\Request\Body::Form([
                'code' => $code,
                'id_token' => $idToken,
                'state' => $state,
                'session_state' => $sessionState,
                'correlation_id' => $correlation,
            ]);

            $error = '';

	    try
            {

                $response = Unirest\Request::post($checkUrl, [], $body);
                if ($response->code == 200)
                {

                    return [ 'ok' => true ];

                }

                $error = 'status code not ok (' . $response->code . ')';

            }
            catch (Throwable $e)
            {
                // nothing to do
                $error = $e->message;
            }

            return [ 'ok' => false, 'msg' => $error ];

	}

        // HELPER #################################################################################

        private function clearFile($filename): bool {
            if (is_file($filename)) {
                // Try to open the file in write mode
                $file = fopen($filename, 'w');
                if ($file === false) {
                    // Failed to open the file
                    return false;
                }

                // Try to truncate the file to clear its contents
                $result = ftruncate($file, 0);
                if ($result === false) {
                    // Failed to truncate the file
                    fclose($file);
                    return false;
                }

                // Close the file
                fclose($file);
            } else {
                // Try to create a new file
                $result = file_put_contents($filename, '');
                if ($result === false) {
                    // Failed to create the file
                    return false;
                }
            }

            // Clearing the file was successful
            return true;
        }

	private function extractValue($body, $key, $endSep)
	{

	    $needlePosition = strpos($body, $key);
            if ($needlePosition !== false)
            {
	        $needlePosition = $needlePosition + strlen($key);
	        $needleEnd = strpos($body, $endSep, $needlePosition);
                return substr($body, $needlePosition, $needleEnd - $needlePosition);
	    }
	    return false;

	}

    }
