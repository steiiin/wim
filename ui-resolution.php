<?php

    namespace WIM;

    class UiResolution
    {

        private static function getResolutions(): array 
        {
            return [

                "L" => "
    
                    :root {
            
                        --spec-header-size: 60px;
                        --spec-header-padd: 30px;
                        --spec-today-group-size: 30px;
                        --spec-today-group-padd: 10px;
                        --spec-today-content-size: 20px;
                        --spec-today-inactive-size: 15px;
                        --spec-today-group-list-padd: 30px;
                        --spec-events-size-head: 18px;
                        --spec-events-size-content: 16px;
                        --spec-icon-size: 24px;
            
                    }
    
                ",
                "M" => "
            
                    :root {
            
                        --spec-header-size: 50px;
                        --spec-header-padd: 20px;
                        --spec-today-group-size: 25px;
                        --spec-today-group-padd: 10px;
                        --spec-today-content-size: 18px;
                        --spec-today-inactive-size: 14px;
                        --spec-today-group-list-padd: 25px;
                        --spec-events-size-head: 14px;
                        --spec-events-size-content: 12px;
                        --spec-icon-size: 24px;
            
                    }
            
                ",
                "S" => "
            
                    :root {
            
                        --spec-header-size: 40px;
                        --spec-header-padd: 20px;
                        --spec-today-group-size: 14px;
                        --spec-today-group-padd: 10px;
                        --spec-today-content-size: 16px;
                        --spec-today-inactive-size: 14px;
                        --spec-today-group-list-padd: 20px;
                        --spec-events-size-head: 14px;
                        --spec-events-size-content: 11px;
                        --spec-icon-size: 20px;
            
                    }
            
                "
            ];
        } 

        public static function Get($key = 'default'): string
        {
            
            // get resoultions-array
            $resolutions = self::getResolutions();

            // replace key with first key, if default or not existing
            if ($key == 'default' || !array_key_exists($key, $resolutions))
            {
                $key = array_key_first($resolutions);
            }

            // return
            return $resolutions[$key];

        }

        public static function GetAvailable(): array
        {
            return array_keys(self::getResolutions());
        }

        public static function KeyExists($key): bool
        {
            return array_key_exists($key, self::getResolutions());
        }


    }

    if (isset($_GET['res'])) 
    {
        header('Content-Type: text/css');
        die(UiResolution::Get($_GET['res']));
    }