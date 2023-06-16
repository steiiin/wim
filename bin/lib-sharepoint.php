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

            // Check Username
            if (!$this->CheckUsername()) { return false; }

            // Retrieve BinaryToken
            $bToken = $this->GetBinaryToken();
            if ($bToken === false) { return false; }

            // Retrieve SharepointCookie
            $sCookie = $this->GetSharepointCookie($bToken);
            if ($sCookie === false) { return false; }

            // Retrieve BearerToken
            $bearer = $this->GetSharepointBearerToken();
            if ($bearer === false) { return false; }
            
            $this->AUTHTOKEN = $bearer;
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

        private function CheckUsername(): bool
        {

            $checkUrl = 'https://login.microsoftonline.com/GetUserRealm.srf';
            $headers = array('Accept' => 'application/json');
            $body = array('login' => $this->username, 'json' => 1);

            try
            {

                $response = Unirest\Request::post($checkUrl, $headers, $body);
                if ($response->code == 200) 
                {

                    $data = json_decode($response->raw_body);
                    return ($data->FederationBrandName == 'MalteserCloud' &&
                            $data->NameSpaceType == 'Managed');

                }
                return false;

            }
            catch (Throwable $e) { return false; }

        }

        private function GetBinaryToken()
        {

            $rstUrl = 'https://login.microsoftonline.com/rst2.srf';
            $headers = array('Accept' => 'application/soap+xml; charset=utf-8', 'Content-Type' => 'application/soap+xml; charset=utf-8');
            $body = "<?xml version='1.0' encoding='UTF-8'?>
            <s:Envelope xmlns:s='http://www.w3.org/2003/05/soap-envelope' xmlns:a='http://www.w3.org/2005/08/addressing' xmlns:u='http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd'>
                <s:Header>
                    <a:Action s:mustUnderstand='1'>http://schemas.xmlsoap.org/ws/2005/02/trust/RST/Issue</a:Action>
                    <a:ReplyTo>
                        <a:Address>http://www.w3.org/2005/08/addressing/anonymous</a:Address>
                    </a:ReplyTo>
                    <a:To s:mustUnderstand='1'>https://login.microsoftonline.com/extSTS.srf</a:To>
                    <o:Security s:mustUnderstand='1' xmlns:o='http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd'>
                        <o:UsernameToken>
                            <o:Username>$this->username</o:Username>
                            <o:Password>$this->password</o:Password>
                        </o:UsernameToken>
                    </o:Security>
                </s:Header>
                <s:Body>
                    <t:RequestSecurityToken xmlns:t='http://schemas.xmlsoap.org/ws/2005/02/trust'>
                        <wsp:AppliesTo xmlns:wsp='http://schemas.xmlsoap.org/ws/2004/09/policy'>
                            <a:EndpointReference>
                                <a:Address>maltesercloud.sharepoint.com</a:Address>
                            </a:EndpointReference>
                        </wsp:AppliesTo>
                        <t:KeyType>http://schemas.xmlsoap.org/ws/2005/05/identity/NoProofKey</t:KeyType>
                        <t:RequestType>http://schemas.xmlsoap.org/ws/2005/02/trust/Issue</t:RequestType>
                        <t:TokenType>urn:oasis:names:tc:SAML:1.0:assertion</t:TokenType>
                    </t:RequestSecurityToken>
                </s:Body>
            </s:Envelope>";

            try 
            {
             
                $response = Unirest\Request::post($rstUrl, $headers, $body);
                if ($response->code == 200) {

                    // load the token
                    $doc = new \DOMDocument();
                    $doc->loadXML($response->raw_body);
                    $xpath = new \DOMXPath($doc);
                    $xpath->registerNamespace('wsse', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd');
                    $tokenNode = $xpath->query('//wsse:BinarySecurityToken')->item(0);
                    $tokenValue = $tokenNode->nodeValue;

                    // extract token from nodeValue
                    preg_match('/t=([^&]+)/', $tokenValue, $matches);
                    $result = $matches[0];
                    if ($result == null) { return false; }
                    return $result;

                }
                return false; 
                
            }
            catch (Throwable $e) { return false; }

        }

        private function GetSharepointCookie($binaryToken)
        {

            $tenantUrl = 'https://maltesercloud.sharepoint.com/_vti_bin/idcrl.svc/';
            $headers = array('Authorization' => "BPOSIDCRL $binaryToken",
                             'X-IDCRL_ACCEPTED' => 't');

            try 
            {
                
                $response = Unirest\Request::get($tenantUrl, $headers);
                if ($response->code == 200) 
                {

                    $SPOIDCRL = '';
                    if (array_key_exists('set-cookie', $response->headers)) {
                        $SPOIDCRL = substr($response->headers['set-cookie'], stripos($response->headers['set-cookie'], 'SPOIDCRL='));
                    } 
                    if (array_key_exists('Set-Cookie', $response->headers)) {
                        $SPOIDCRL = substr($response->headers['Set-Cookie'], stripos($response->headers['Set-Cookie'], 'SPOIDCRL='));
                    }
                    $SPOIDCRL = substr($SPOIDCRL, 0, stripos($SPOIDCRL, ';'));
                    if ($SPOIDCRL == '') { return false; }
                    return $SPOIDCRL;

                }
                return false;

            }
            catch (Throwable $e) { return false; }

        }

        private function GetSharepointBearerToken()
        {

            $tenantUrl = 'https://maltesercloud.sharepoint.com/_vti_bin/sites.asmx';
            $headers = array('Accept' => '*/*', 'Content-Type' => 'text/xml;',
                             'X-RequestForceAuthentication' => 'true',
                             'X-FORMS_BASED_AUTH_ACCEPTED' => 'f',
                             'Accept-Encoding' => 'gzip, deflate',
                             'SOAPAction' => 'http://schemas.microsoft.com/sharepoint/soap/GetUpdatedFormDigestInformation'
            );
            $body = "<?xml version='1.0' encoding='utf-8'?>
                    <soap:Envelope
                        xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance'
                        xmlns:xsd='http://www.w3.org/2001/XMLSchema'
                        xmlns:soap='http://schemas.xmlsoap.org/soap/envelope/'>
                        <soap:Body>
                            <GetUpdatedFormDigestInformation xmlns='http://schemas.microsoft.com/sharepoint/soap/' />
                        </soap:Body>
                    </soap:Envelope>";

            try
            {

                $response = Unirest\Request::post($tenantUrl, $headers, $body);
                if ($response->code == 200) {
                    
                    $DIGEST = substr($response->raw_body, stripos($response->raw_body, '<DigestValue>') + 13);
                    $DIGEST = substr($DIGEST, 0, stripos($DIGEST, '</DigestValue>'));
                    if ($DIGEST != '') { return $DIGEST; }
    
                }
                return false;

            }
            catch (Throwable $e) { return false; }

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

    }

////// Old Federated Login ########################################################################

    // private function GetLoginSts() {

    //     $return['Success'] = false;

    //     $headers = array('Accept' => 'text/xml');
    //     $query = array('login' => $this->username, 'xml' => 1);
    //     $response = Unirest\Request::post('https://login.microsoftonline.com/GetUserRealm.srf', $headers, $query);
    //     if ($response->code == 200) {

    //         $xml = simplexml_load_string($response->raw_body);

    //         if ($xml->NameSpaceType == 'Federated') {
    //             if ($xml->FederationBrandName == 'MalteserCloud') {

    //                 $return['StsAuthUrl'] = (string)$xml->STSAuthURL;
    //                 $return['StsAuthCert'] = (string)$xml->Certificate;

    //                 $return['Success'] = true;

    //             }
    //         }
            
    //     }

    //     return $return;

    // }

    // private function GetLoginAssertion($stsUrl) {

    //     $return["Success"] = false;

    //     $adfsGuid = strtolower(sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535))); 
    //     $adfsMsgCreated = gmdate("Y-m-d\TH:i:s.0000000\Z", time()); 
    //     $adfsMsgExpires = gmdate("Y-m-d\TH:i:s.0000000\Z", strtotime('+10 minutes')); 

    //     $soap = "<?xml version='1.0' encoding='UTF-8'>
    //                 <s:Envelope
    //                     xmlns:s='http://www.w3.org/2003/05/soap-envelope'
    //                     xmlns:wsse='http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd'
    //                     xmlns:saml='urn:oasis:names:tc:SAML:1.0:assertion'
    //                     xmlns:wsp='http://schemas.xmlsoap.org/ws/2004/09/policy'
    //                     xmlns:wsu='http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd'
    //                     xmlns:wsa='http://www.w3.org/2005/08/addressing'
    //                     xmlns:wssc='http://schemas.xmlsoap.org/ws/2005/02/sc'
    //                     xmlns:wst='http://schemas.xmlsoap.org/ws/2005/02/trust'>
    //                     <s:Header>
    //                         <wsa:Action s:mustUnderstand='1'>http://schemas.xmlsoap.org/ws/2005/02/trust/RST/Issue</wsa:Action>
    //                         <wsa:To s:mustUnderstand='1'>$stsUrl</wsa:To>
    //                         <wsa:MessageID>$adfsGuid</wsa:MessageID>
    //                         <ps:AuthInfo
    //                             xmlns:ps='http://schemas.microsoft.com/Passport/SoapServices/PPCRL' Id='PPAuthInfo'>
    //                             <ps:HostingApp>Managed IDCRL</ps:HostingApp>
    //                             <ps:BinaryVersion>6</ps:BinaryVersion>
    //                             <ps:UIVersion>1</ps:UIVersion>
    //                             <ps:Cookies></ps:Cookies>
    //                             <ps:RequestParams>AQAAAAIAAABsYwQAAAAxMDMz</ps:RequestParams>
    //                         </ps:AuthInfo>
    //                         <wsse:Security>
    //                             <wsse:UsernameToken wsu:Id='user'>
    //                                 <wsse:Username>$this->username</wsse:Username>
    //                                 <wsse:Password>$this->password</wsse:Password>
    //                             </wsse:UsernameToken>
    //                             <wsu:Timestamp Id='Timestamp'>
    //                                 <wsu:Created>$adfsMsgCreated</wsu:Created>
    //                                 <wsu:Expires>$adfsMsgExpires</wsu:Expires>
    //                             </wsu:Timestamp>
    //                         </wsse:Security>
    //                     </s:Header>
    //                     <s:Body>
    //                         <wst:RequestSecurityToken Id='RST0'>
    //                             <wst:RequestType>http://schemas.xmlsoap.org/ws/2005/02/trust/Issue</wst:RequestType>
    //                             <wsp:AppliesTo>
    //                                 <wsa:EndpointReference>
    //                                     <wsa:Address>urn:federation:MicrosoftOnline</wsa:Address>
    //                                 </wsa:EndpointReference>
    //                             </wsp:AppliesTo>
    //                             <wst:KeyType>http://schemas.xmlsoap.org/ws/2005/05/identity/NoProofKey</wst:KeyType>
    //                         </wst:RequestSecurityToken>
    //                     </s:Body>
    //                 </s:Envelope>";

    //     $headers = array('Accept' => 'application/soap+xml; charset=utf-8', 'Content-Type' => 'application/soap+xml; charset=utf-8');
    //     $response = Unirest\Request::post($stsUrl, $headers, $soap);
    //     if ($response->code == 200) {


    //         $assertionString = substr($response->raw_body, stripos($response->raw_body, 'RequestedSecurityToken>') + 23);
    //         $assertionString = substr($assertionString, 0, stripos($assertionString, 'assertion>') + 10);
            
    //         $return['assertion'] = $assertionString;
    //         $return['Success'] = true;

    //     }

    //     return $return;

    // }

    // private function GetLoginSharepointToken($assertionString) {

    //     $return["Success"] = false;

    //     $soap = "<S:Envelope
    //                 xmlns:S='http://www.w3.org/2003/05/soap-envelope'
    //                 xmlns:wsse='http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd'
    //                 xmlns:wsp='http://schemas.xmlsoap.org/ws/2004/09/policy'
    //                 xmlns:wsu='http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd'
    //                 xmlns:wsa='http://www.w3.org/2005/08/addressing'
    //                 xmlns:wst='http://schemas.xmlsoap.org/ws/2005/02/trust'>
    //                 <S:Header>
    //                     <wsa:Action S:mustUnderstand='1'>http://schemas.xmlsoap.org/ws/2005/02/trust/RST/Issue</wsa:Action>
    //                     <wsa:To S:mustUnderstand='1'>https://login.microsoftonline.com/rst2.srf</wsa:To>
    //                     <ps:AuthInfo
    //                         xmlns:ps='http://schemas.microsoft.com/LiveID/SoapServices/v1' Id='PPAuthInfo'>
    //                         <ps:BinaryVersion>5</ps:BinaryVersion>
    //                         <ps:HostingApp>Managed IDCRL</ps:HostingApp>
    //                     </ps:AuthInfo>
    //                     <wsse:Security>$assertionString</wsse:Security>
    //                 </S:Header>
    //                 <S:Body>
    //                     <wst:RequestSecurityToken
    //                         xmlns:wst='http://schemas.xmlsoap.org/ws/2005/02/trust' Id='RST0'>
    //                         <wst:RequestType>http://schemas.xmlsoap.org/ws/2005/02/trust/Issue</wst:RequestType>
    //                         <wsp:AppliesTo>
    //                             <wsa:EndpointReference>
    //                                 <wsa:Address>maltesercloud.sharepoint.com</wsa:Address>
    //                             </wsa:EndpointReference>
    //                         </wsp:AppliesTo>
    //                         <wsp:PolicyReference URI='MBI'/>
    //                     </wst:RequestSecurityToken>
    //                 </S:Body>
    //             </S:Envelope>";
        
    //     $headers = array('Accept' => 'application/soap+xml; charset=utf-8', 'Content-Type' => 'application/soap+xml; charset=utf-8');
    //     $response = Unirest\Request::post('https://login.microsoftonline.com/rst2.srf', $headers, $soap);
    //     if ($response->code == 200) {

    //         $searchPos = stripos($response->raw_body, 'binarysecuritytoken ');
    //         $searchPos = stripos($response->raw_body, '>', $searchPos);

    //         $binaryToken = substr($response->raw_body, $searchPos + 1);
    //         $binaryToken = substr($binaryToken, 0, stripos($binaryToken, '</'));
            
    //         $return['binaryToken'] = html_entity_decode($binaryToken);
    //         $return['Success'] = true;

    //     }

    //     return $return;

    // }

    // private function GetLoginDigest($binaryToken) {

    //     $return['Success'] = false;

    //     // Schritt1: SharepointCookie abrufen
    //     $headers = array('Authorization' => "BPOSIDCRL $binaryToken",
    //                      'X-IDCRL_ACCEPTED' => 't');

    //     $response = Unirest\Request::get('https://maltesercloud.sharepoint.com/_vti_bin/idcrl.svc/', $headers);
    //     if ($response->code == 200) {

    //         $SPOIDCRL = '';
    //         if (array_key_exists('set-cookie', $response->headers)) {
    //             $SPOIDCRL = substr($response->headers['set-cookie'], stripos($response->headers['set-cookie'], 'SPOIDCRL='));
    //         } 
    //         if (array_key_exists('Set-Cookie', $response->headers)) {
    //             $SPOIDCRL = substr($response->headers['Set-Cookie'], stripos($response->headers['Set-Cookie'], 'SPOIDCRL='));
    //         }
    //         $SPOIDCRL = substr($SPOIDCRL, 0, stripos($SPOIDCRL, ';'));
    //         $return['SPOIDCRL'] = $SPOIDCRL;

    //         // Schritt2: SharepointDigest abrufen
    //         $headers = array('Accept' => '*/*', 'Content-Type' => 'text/xml;',
    //                          'X-RequestForceAuthentication' => 'true',
    //                          'X-FORMS_BASED_AUTH_ACCEPTED' => 'f',
    //                          'Accept-Encoding' => 'gzip, deflate',
    //                          'SOAPAction' => 'http://schemas.microsoft.com/sharepoint/soap/GetUpdatedFormDigestInformation');
    //         $soap = "<?xml version='1.0' encoding='utf-8'>
    //                 <soap:Envelope
    //                     xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance'
    //                     xmlns:xsd='http://www.w3.org/2001/XMLSchema'
    //                     xmlns:soap='http://schemas.xmlsoap.org/soap/envelope/'>
    //                     <soap:Body>
    //                         <GetUpdatedFormDigestInformation
    //                             xmlns='http://schemas.microsoft.com/sharepoint/soap/' />
    //                     </soap:Body>
    //                 </soap:Envelope>";

    //         $response = Unirest\Request::post('https://maltesercloud.sharepoint.com/_vti_bin/sites.asmx', $headers, $soap);
    //         if ($response->code == 200) {
                
    //             $DIGEST = substr($response->raw_body, stripos($response->raw_body, '<DigestValue>') + 13);
    //             $DIGEST = substr($DIGEST, 0, stripos($DIGEST, '</DigestValue>'));

    //             $return['DIGEST'] = $DIGEST;
    //             $return['Success'] = true;

    //         }
            
            
    //     }

    //     return $return;

    // }
