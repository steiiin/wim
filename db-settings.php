<?php

namespace WIM;

// files ######################################################################################

// MainClass ##################################################################################
class Settings
{

    private $connection;
    public function __construct()
    {

        $this->connection = $this->Connect();
        if ($this->connection === false) { return; }

        // create table
        $sql = "CREATE TABLE IF NOT EXISTS `CONFIG` (
                `ID` int(11) AUTO_INCREMENT PRIMARY KEY,
                `KEY` text NOT NULL,
                `VAL` text NOT NULL,
                `IsModule` tinyint(1) NOT NULL DEFAULT 0);";
        $this->connection->query($sql);

    }

    public function __destruct()
    {
        $this->Close();
    }

    // Keys #######################################################################################
    const UiResolution = 'WIM_UI_RESOLUTION';
    const UiStationName = 'WIM_UI_STATION';
    const UiLocation = 'WIM_UI_LOCATION';
    const UiVehicleTiming = 'WIM_UI_VEHICLETIMING';
    const AdmHiddenEntries = 'WIM_ADM_HIDDENENTRIES';

    public function GetUiResolution() { return $this->Get(self::UiResolution) ?? 'default'; }
    public function GetStationName() { return $this->Get(self::UiStationName); }
    public function GetLocation() { return $this->Get(self::UiLocation); }
    public function GetVehicleTiming() { return $this->Get(self::UiVehicleTiming); }
    public function GetHiddenEntries() { $hidden = $this->Get(self::AdmHiddenEntries); return $hidden === false ? [] : \json_decode($hidden, true); }

    // Settings ################################################################################### 

    public function Get($key)
    {
        if ($this->connection === false) { return false; }
        try 
        {
            $statement = $this->connection->prepare("SELECT `KEY`, `VAL` FROM `CONFIG` WHERE `KEY`=?");
            $statement->bind_param("s", $key);
            $statement->execute();
            $statement->bind_result($key, $value);
            $statement->fetch();
            $statement->close();
            $value = $value ?? false;
            return $value;
        }
        catch (\Throwable $e) { return false; }
    }

    public function Set($key, $value, $isModule = false)
    {
        if ($this->connection === false) { return false; }
        try 
        {

            // Check if the key exists
            $statement = $this->connection->prepare("SELECT COUNT(*) FROM `CONFIG` WHERE `KEY`=?");
            $statement->bind_param("s", $key);
            $statement->execute();
            $statement->bind_result($count);
            $statement->fetch();
            $statement->close();

            $isModule = $isModule ? 1 : 0;

            // Update existing one
            if ($count > 0)
            {

                $statement = $this->connection->prepare("UPDATE `CONFIG` SET `VAL`=?, `IsModule`=? WHERE `KEY`=?");
                $statement->bind_param("sis", $value, $isModule, $key);
                $result = $statement->execute();
                $statement->close();
                return $result !== false;

            }

            // Insert new one
            else
            {

                $statement = $this->connection->prepare("INSERT INTO `CONFIG` (`KEY`, `VAL`, `IsModule`) VALUES (?, ?, ?)");
                $statement->bind_param("ssi", $key, $value, $isModule);
                $result = $statement->execute();
                $statement->close();
                return $result !== false;

            }

        }
        catch (\Throwable $e) { return false; }
    }

    public function Delete($key)
    {
        if ($this->connection === false) { return false; }
        try 
        {
            $statement = $this->connection->prepare("DELETE FROM `CONFIG` WHERE `KEY`=?");
            $statement->bind_param("s", $key);
            $result = $statement->execute();
            $statement->close();
            return $result !== false;
        }
        catch (\Throwable $e) { return false; }
    }

    // Import #####################################################################################
	
    public function LoadExport()
    {
        $settings = [];
        try 
        {
            $sql = "SELECT * FROM `CONFIG` WHERE `IsModule`=0;";
            $statement = $this->connection->query($sql);
            while ($row = $statement->fetch_object())
            {
                $settings[] = $row;
            }
        }
        catch (\Throwable $e) { }
        return $settings;
    }

    public function LoadImport($data)
    {

        if (!\is_array($data)) { return false; }

        $result = true;
        foreach ($data as $entry)
        {
            if (!\is_array($entry)) { continue; }
            $key = $entry['KEY'] ?? '';
            $value = $entry['VAL'] ?? '';
            if ($key == '' || $value == '') { continue; }
            
            $result = $this->Set($key, $value) ? $result : false;
        }
        return $result;

    }

    // Database ###################################################################################
	
    private function Connect()
    {

        try 
        {

            // Connect
            $config = include('wim-config.php');
            $conn = new \mysqli($config['DB_SERVER'], $config['DB_USER'], $config['DB_PASS']);
            if ($conn->connect_error) { return false; }

            // Create table, if not existing
            $sql = "CREATE DATABASE IF NOT EXISTS wim";
            if (!$conn->query($sql)) { return false; }
            $conn->select_db('wim');

            return $conn;

        }
        catch (\Throwable $e) { return false; }
    }

    private function Close()
    {
        if ($this->connection !== false) { $this->connection->close(); }
    }

}