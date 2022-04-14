<?php

    require_once('lib-unirest.php');

    class SharepointApi 
    {

        private string $username = '';
        private string $password = '';

        private string $AUTH_bearer = '';
        private string $AUTH_spoidcrl = '';

        public function __construct($spUsername, $spPassword)
        {

            $this->username = $spUsername;
            $this->password = $spPassword;

            $cookiePath = join(DIRECTORY_SEPARATOR, array(__DIR__, 'Unirest', 'cookieJar.txt'));

            // Cookiespeicher löschen
            $f = fopen($cookiePath, 'w');
            if ($f !== false) {
                ftruncate($f, 0);
            } else {
                fwrite($f, '');
            }
            fclose($f);

            // Unirest konfigurieren
            Unirest\Request::defaultHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4482.0 Safari/537.36 Edg/92.0.874.0');
            Unirest\Request::defaultHeader('Accept-Language', 'de-DE');
            Unirest\Request::cookieFile($cookiePath);

        }

        // ########################################################################################

        public function Login() {

            // Federate abrufen
            $infoSts = $this->GetLoginSts();
            if ($infoSts['Success'] == false) { return false; }
            
            // Assertion mit Zugangsdaten aufrufen
            $infoAssertion = $this->GetLoginAssertion($infoSts['StsAuthUrl']);
            if ($infoAssertion['Success'] == false) { return false; }

            // SharepointToken abrufen
            $infoSp = $this->GetLoginSharepointToken($infoAssertion['assertion']);
            if ($infoSp['Success'] == false) { return false; }

            // MalteserDigest abrufen
            $infoDigest = $this->GetLoginDigest($infoSp['binaryToken']);
            if ($infoDigest['Success'] == false) { return false; }

            $this->AUTH_bearer = $infoDigest['DIGEST'];
            $this->AUTH_spoidcrl = $infoDigest['SPOIDCRL'];

            return true;

        }

        public function GetEvents() {

            // Kalenderdaten abrufen
            $headers = array('X-RequestDigest' => $this->AUTH_bearer,
                             'Accept' => 'application/json; odata=verbose'); //application/json; odata=verbose');

            $filterDateStart = gmdate("Y-m-d\TH:i:s\Z", strtotime('-1 days')); 
            $filterDateEnd = gmdate("Y-m-d\TH:i:s\Z", strtotime('+30 days'));

            $url = 'https://maltesercloud.sharepoint.com/sites/hilfsdienst/2601/Fkt_Bereich_Mei/_api/lists(guid\'8e0ce127-04ab-4104-b85e-9efc97866402\')/items?$select=ID,GUID,Title,Description,EventDate,EndDate,fAllDayEvent,Category,Location&$filter=(EndDate ge datetime\''.$filterDateStart.'\') and (EventDate le datetime\''.$filterDateEnd.'\')&$orderby=EventDate%20asc';

            $response = Unirest\Request::get($url, $headers);
            if ($response->code == 200) {

                foreach ($response->body->d->results as $value) {

                    $event['id'] = $value->GUID;
                    $event['title'] = $value->Title;

                    $event['subtitle'] = '';
                    if (strpos($event['title'],'MDR') !== false) { $event['subtitle'] = 'Monatsdesinfektion'; }
                    if ($value->Category === 'Werkstatt') { $event['subtitle'] = 'Werkstatt'; }
                    if ($value->Description != null) { $event['subtitle'] = strip_tags($value->Description); }
                    if ($value->Location != null) { 
                        if (strlen($event['subtitle']) > 0) { $event['subtitle'] .= ' - '.$value->Location; }
                        else { $event['subtitle'] = $value->Location; }}

                        if($value->fAllDayEvent) {
                            $vv = 0;
                        }
                    $event['dateAllDay'] = $value->fAllDayEvent;
                    $event['dateStart'] = DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $value->EventDate);
                    $event['dateEnd'] = DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $value->EndDate);

                    $eventList[] = $event;

                }

                return $eventList;

            }

            return false;

        }

        // ########################################################################################

        private function GetLoginSts() {

            $return['Success'] = false;

            $headers = array('Accept' => 'text/xml');
            $query = array('login' => $this->username, 'xml' => 1);
            $response = Unirest\Request::post('https://login.microsoftonline.com/GetUserRealm.srf', $headers, $query);
            if ($response->code == 200) {

                $xml = simplexml_load_string($response->raw_body);

                if ($xml->NameSpaceType == 'Federated') {
                    if ($xml->FederationBrandName == 'MalteserCloud') {

                        $return['StsAuthUrl'] = (string)$xml->STSAuthURL;
                        $return['StsAuthCert'] = (string)$xml->Certificate;

                        $return['Success'] = true;

                    }
                }
                
            }

            return $return;

        }

        private function GetLoginAssertion($stsUrl) {

            $return["Success"] = false;

            $adfsGuid = strtolower(sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535))); 
            $adfsMsgCreated = gmdate("Y-m-d\TH:i:s.0000000\Z", time()); 
            $adfsMsgExpires = gmdate("Y-m-d\TH:i:s.0000000\Z", strtotime('+10 minutes')); 

            $soap = "<?xml version='1.0' encoding='UTF-8'?>
                        <s:Envelope
                            xmlns:s='http://www.w3.org/2003/05/soap-envelope'
                            xmlns:wsse='http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd'
                            xmlns:saml='urn:oasis:names:tc:SAML:1.0:assertion'
                            xmlns:wsp='http://schemas.xmlsoap.org/ws/2004/09/policy'
                            xmlns:wsu='http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd'
                            xmlns:wsa='http://www.w3.org/2005/08/addressing'
                            xmlns:wssc='http://schemas.xmlsoap.org/ws/2005/02/sc'
                            xmlns:wst='http://schemas.xmlsoap.org/ws/2005/02/trust'>
                            <s:Header>
                                <wsa:Action s:mustUnderstand='1'>http://schemas.xmlsoap.org/ws/2005/02/trust/RST/Issue</wsa:Action>
                                <wsa:To s:mustUnderstand='1'>$stsUrl</wsa:To>
                                <wsa:MessageID>$adfsGuid</wsa:MessageID>
                                <ps:AuthInfo
                                    xmlns:ps='http://schemas.microsoft.com/Passport/SoapServices/PPCRL' Id='PPAuthInfo'>
                                    <ps:HostingApp>Managed IDCRL</ps:HostingApp>
                                    <ps:BinaryVersion>6</ps:BinaryVersion>
                                    <ps:UIVersion>1</ps:UIVersion>
                                    <ps:Cookies></ps:Cookies>
                                    <ps:RequestParams>AQAAAAIAAABsYwQAAAAxMDMz</ps:RequestParams>
                                </ps:AuthInfo>
                                <wsse:Security>
                                    <wsse:UsernameToken wsu:Id='user'>
                                        <wsse:Username>$this->username</wsse:Username>
                                        <wsse:Password>$this->password</wsse:Password>
                                    </wsse:UsernameToken>
                                    <wsu:Timestamp Id='Timestamp'>
                                        <wsu:Created>$adfsMsgCreated</wsu:Created>
                                        <wsu:Expires>$adfsMsgExpires</wsu:Expires>
                                    </wsu:Timestamp>
                                </wsse:Security>
                            </s:Header>
                            <s:Body>
                                <wst:RequestSecurityToken Id='RST0'>
                                    <wst:RequestType>http://schemas.xmlsoap.org/ws/2005/02/trust/Issue</wst:RequestType>
                                    <wsp:AppliesTo>
                                        <wsa:EndpointReference>
                                            <wsa:Address>urn:federation:MicrosoftOnline</wsa:Address>
                                        </wsa:EndpointReference>
                                    </wsp:AppliesTo>
                                    <wst:KeyType>http://schemas.xmlsoap.org/ws/2005/05/identity/NoProofKey</wst:KeyType>
                                </wst:RequestSecurityToken>
                            </s:Body>
                        </s:Envelope>";

            $headers = array('Accept' => 'application/soap+xml; charset=utf-8', 'Content-Type' => 'application/soap+xml; charset=utf-8');
            $response = Unirest\Request::post($stsUrl, $headers, $soap);
            if ($response->code == 200) {


                $assertionString = substr($response->raw_body, stripos($response->raw_body, 'RequestedSecurityToken>') + 23);
                $assertionString = substr($assertionString, 0, stripos($assertionString, 'assertion>') + 10);
                
                $return['assertion'] = $assertionString;
                $return['Success'] = true;

            }

            return $return;

        }

        private function GetLoginSharepointToken($assertionString) {

            $return["Success"] = false;

            $soap = "<S:Envelope
                        xmlns:S='http://www.w3.org/2003/05/soap-envelope'
                        xmlns:wsse='http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd'
                        xmlns:wsp='http://schemas.xmlsoap.org/ws/2004/09/policy'
                        xmlns:wsu='http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd'
                        xmlns:wsa='http://www.w3.org/2005/08/addressing'
                        xmlns:wst='http://schemas.xmlsoap.org/ws/2005/02/trust'>
                        <S:Header>
                            <wsa:Action S:mustUnderstand='1'>http://schemas.xmlsoap.org/ws/2005/02/trust/RST/Issue</wsa:Action>
                            <wsa:To S:mustUnderstand='1'>https://login.microsoftonline.com/rst2.srf</wsa:To>
                            <ps:AuthInfo
                                xmlns:ps='http://schemas.microsoft.com/LiveID/SoapServices/v1' Id='PPAuthInfo'>
                                <ps:BinaryVersion>5</ps:BinaryVersion>
                                <ps:HostingApp>Managed IDCRL</ps:HostingApp>
                            </ps:AuthInfo>
                            <wsse:Security>$assertionString</wsse:Security>
                        </S:Header>
                        <S:Body>
                            <wst:RequestSecurityToken
                                xmlns:wst='http://schemas.xmlsoap.org/ws/2005/02/trust' Id='RST0'>
                                <wst:RequestType>http://schemas.xmlsoap.org/ws/2005/02/trust/Issue</wst:RequestType>
                                <wsp:AppliesTo>
                                    <wsa:EndpointReference>
                                        <wsa:Address>maltesercloud.sharepoint.com</wsa:Address>
                                    </wsa:EndpointReference>
                                </wsp:AppliesTo>
                                <wsp:PolicyReference URI='MBI'/>
                            </wst:RequestSecurityToken>
                        </S:Body>
                    </S:Envelope>";
            
            $headers = array('Accept' => 'application/soap+xml; charset=utf-8', 'Content-Type' => 'application/soap+xml; charset=utf-8');
            $response = Unirest\Request::post('https://login.microsoftonline.com/rst2.srf', $headers, $soap);
            if ($response->code == 200) {

                $searchPos = stripos($response->raw_body, 'binarysecuritytoken ');
                $searchPos = stripos($response->raw_body, '>', $searchPos);

                $binaryToken = substr($response->raw_body, $searchPos + 1);
                $binaryToken = substr($binaryToken, 0, stripos($binaryToken, '</'));
                
                $return['binaryToken'] = html_entity_decode($binaryToken);
                $return['Success'] = true;

            }

            return $return;

        }

        private function GetLoginDigest($binaryToken) {

            $return['Success'] = false;

            // Schritt1: SharepointCookie abrufen
            $headers = array('Authorization' => "BPOSIDCRL $binaryToken",
                             'X-IDCRL_ACCEPTED' => 't');

            $response = Unirest\Request::get('https://maltesercloud.sharepoint.com/_vti_bin/idcrl.svc/', $headers);
            if ($response->code == 200) {

                $SPOIDCRL = '';
                if (array_key_exists('set-cookie', $response->headers)) {
                    $SPOIDCRL = substr($response->headers['set-cookie'], stripos($response->headers['set-cookie'], 'SPOIDCRL='));
                } 
                if (array_key_exists('Set-Cookie', $response->headers)) {
                    $SPOIDCRL = substr($response->headers['Set-Cookie'], stripos($response->headers['Set-Cookie'], 'SPOIDCRL='));
                }
                $SPOIDCRL = substr($SPOIDCRL, 0, stripos($SPOIDCRL, ';'));
                $return['SPOIDCRL'] = $SPOIDCRL;

                // Schritt2: SharepointDigest abrufen
                $headers = array('Accept' => '*/*', 'Content-Type' => 'text/xml;',
                                 'X-RequestForceAuthentication' => 'true',
                                 'X-FORMS_BASED_AUTH_ACCEPTED' => 'f',
                                 'Accept-Encoding' => 'gzip, deflate',
                                 'SOAPAction' => 'http://schemas.microsoft.com/sharepoint/soap/GetUpdatedFormDigestInformation');
                $soap = "<?xml version='1.0' encoding='utf-8'?>
                        <soap:Envelope
                            xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance'
                            xmlns:xsd='http://www.w3.org/2001/XMLSchema'
                            xmlns:soap='http://schemas.xmlsoap.org/soap/envelope/'>
                            <soap:Body>
                                <GetUpdatedFormDigestInformation
                                    xmlns='http://schemas.microsoft.com/sharepoint/soap/' />
                            </soap:Body>
                        </soap:Envelope>";

                $response = Unirest\Request::post('https://maltesercloud.sharepoint.com/_vti_bin/sites.asmx', $headers, $soap);
                if ($response->code == 200) {
                    
                    $DIGEST = substr($response->raw_body, stripos($response->raw_body, '<DigestValue>') + 13);
                    $DIGEST = substr($DIGEST, 0, stripos($DIGEST, '</DigestValue>'));

                    $return['DIGEST'] = $DIGEST;
                    $return['Success'] = true;

                }
                
                
            }

            return $return;

        }

    }
