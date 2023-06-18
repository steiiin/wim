<?php

    namespace WIM\NinaApi;

    // files ##########################################################################################

    // Exceptions #################################################################################
    class ApiError extends \Exception {};
    class AuthenticationError extends \Exception {};

    // Entities ###################################################################################
    class WarningItem
    {

        public string $ID;
        public string $Title;
        public string $Provider;
        public string $Type;
        public string $Severity;
        public \DateTime $DateSent;
        public \DateTime $DateExpires;

        public function __construct(
            string $id, 
            string $title,
            string $provider,
            ?string $type,
            ?string $severity,
            string $dateSent,
            string $dateExpires
        ) {

            $this->ID = $id;
            $this->Title = $title;
            $this->Provider = $provider;

            $this->Type = $type ?? '';
            $this->Severity = $severity ?? '';

            $tmpStart = \DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $dateStart);
            $this->DateSent = $tmpStart === false ? new \DateTime() : $tmpStart;
            $tmpExpir = \DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $dateExpires);
            $this->DateExpires = $tmpExpir === false ? (new \DateTime())->add(new \DateInterval('PT2H')) : $tmpExpir;

        }

        public function getId(): string { return $this->id; }
        public function getType(): string { return $this->type; }
        public function getTitle(): string { return $this->title; }
        public function getProvider(): string { return $this->provider; }
        public function getSeverity(): string { return $this->severity; }
        public function getDateStart(): \DateTime { return $this->dateStart; }
        public function getDateExpires(): \DateTime { return $this->dateExpires; }

    }

    // MainClass ##################################################################################
    class Client 
    {

        private string $ARS = '';

        public function __construct($ARS) 
        {
            $this->ARS = $ARS;
        }

        // CLIENT-ENDPOINTS #######################################################################

        public function GetWarnings()
        {

            if (strlen($this->ARS) != 12) { return false; }

            try 
            {
                
                // create request
                $endpoint = "https://nina.api.proxy.bund.dev/api31/dashboard/{$this->ARS}.json";
                $acceptHeader = 'application/json'; // Set your desired Accept header value
                $options = [
                    'http' => [
                        'header' => "Accept: $acceptHeader\r\n"
                    ]
                ];
                $context = stream_context_create($options);
                $response = file_get_contents($endpoint, false, $context);

                // parse json & create array
                $warnings = [];
                $unique = [];

                $json = \json_decode($response, true);
                foreach($json as $warning)
                {

                    $id = $warning['id'];
                    if ($id == null) { continue; }

                    $provider = $warning['payload']['data']['provider'];
                    if ($provider == null) { continue; }

                    $title = $warning['i18nTitle']['de'];
                    if ($title == null) { continue; }

                    $uniqueKey = "$title|$provider";
                    if (\in_array($uniqueKey, $unique)) { continue; }
                    $unique[] = $uniqueKey;

                    $severity = $warning['payload']['data']['severity'];
                    $type = $warning['payload']['data']['msgType'];

                    $sent = $warning['sent']; if ($sent === null || !\is_string($sent)) { $sent = ''; }
                    $expires = $warning['expires']; if ($expires === null || !\is_string($expires)) { $expires = ''; }

                    $item = new WarningItem($id, $title, $provider, $type, $severity, $sent, $expires);
                    $warnings[] = $item;

                }

                return $warnings;

            }
            catch (\Throwable $e) { }   
            return false;

        }

        // HELPER #################################################################################


    }
