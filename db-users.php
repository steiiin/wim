<?php

namespace WIM;

// files ##########################################################################################

// MainClass ######################################################################################
class Users
{

    private $connection;
    public function __construct()
    {

        $this->connection = $this->Connect();
        if ($this->connection === false) { return; }

        // create table
        $sql = "CREATE TABLE IF NOT EXISTS `USERDB` (
                `ID` int(11) AUTO_INCREMENT PRIMARY KEY,
                `User` text NOT NULL,
                `Pass` text NOT NULL,
                `IsAdmin` tinyint(1) NOT NULL DEFAULT 0,
                `IsFirstAccess` tinyint(1) NOT NULL DEFAULT 1,
                UNIQUE `UNIQUE` (`User`));";
        $this->connection->query($sql);

    }

    public function __destruct()
    {
        $this->Close();
    }

    // User #######################################################################################

    public function LoginUser($username, $password)
    {

        if ($this->connection === false) { return false; }
        if (!$password) { return false; }
        $username = strtolower($username);
        $password = trim($password);

        // catch SUPERUSER
        $config = include('wim-config.php');
        if ($username == '' && $password == $config['CD_SUPERPASS'])
        {
            $_SESSION['UserID'] = -1;
            $_SESSION['User'] = "admin";
            $_SESSION['IsAdmin'] = true;
            $_SESSION['IsFirstAccess'] = false;
            $_SESSION['AllowChanges'] = false;
            return true;
        }

        try 
        {
            $sql = "SELECT `ID`, `User`, `Pass`, `IsAdmin`, `IsFirstAccess` FROM `USERDB` WHERE `User`=?";
            $statement = $this->connection->prepare($sql);
            $statement->bind_param("s", $username);
            $statement->execute();
            $statement->bind_result($userId, $username, $passHash, $isAdmin, $isFirstAccess);
            $statement->fetch();
            $statement->close();
            if (password_verify($password, $passHash))
            {
                $_SESSION['UserID'] = $userId;
                $_SESSION['User'] = $username;
                $_SESSION['IsAdmin'] = $isAdmin == 1;
                $_SESSION['IsFirstAccess'] = $isFirstAccess == 1;
                $_SESSION['AllowChanges'] = true;
                return true; 
            }
            return false;
        }
        catch (\Throwable $e) { return false; }
    }

    // User #######################################################################################

    public function AddUser($userName, $isAdmin)
    {
        if ($this->connection === false) { return false; }

        try 
        {
            
            $isAdmin = $isAdmin ? 1 : 0;
            $userName = \strtolower($userName);

            $sql = "INSERT INTO `USERDB` (
                `User`, 
                `Pass`, 
                `IsAdmin`, 
                `IsFirstAccess`) VALUES (?, '', ?, 1)
            ";
            $statement = $this->connection->prepare($sql);
            $statement->bind_param('si',
                $userName,
                $isAdmin);
              
            $exec = $statement->execute();
            if ($exec === false) { return false; }

            $inserted = $this->connection->insert_id;
            $statement->close();

            return $inserted;
            
        }
        catch (\Throwable $e) { return false; }
    }

    public function DeleteUser($userId)
    {
        if ($this->connection === false) { return false; }

        try 
        {
            
            $isAdmin = $isAdmin ? 1 : 0;

            $sql = "DELETE FROM `USERDB` WHERE `ID`=?";
            $statement = $this->connection->prepare($sql);
            $statement->bind_param('i', $userId);
              
            $result = $statement->execute();
            $statement->close();

            return $result !== false;
            
        }
        catch (\Throwable $e) { return false; }
    }

    public function DeleteAll()
    {
        if ($this->connection === false) { return false; }

        try 
        {
            
            $isAdmin = $isAdmin ? 1 : 0;

            $sql = "DELETE FROM `USERDB`;";
            $statement = $this->connection->prepare($sql);              
            $result = $statement->execute();
            $statement->close();

            return $result !== false;
            
        }
        catch (\Throwable $e) { return false; }
    }

    public function ChangeName($newName, $editId = -1): bool
    {
        if ($this->connection === false) { return false; }
        if ($editID < 0) { $editID = $this->SelectSelfId(); }
        if ($editID < 0) { return false; }

        $newName = \strtolower($newName);

        try
        {

            $sql = "UPDATE `USERDB` SET `User`=? WHERE `ID`=?";
            $statement = $this->connection->prepare($sql);
            $statement->bind_param("si", $newName, $editId);
            $result = $statement->execute();
            $statement->close();

            return $result !== false;

        }
        catch (\Throwable $e) { return false; }
    }

    public function ChangeIsAdmin($isAdmin, $editId = -1): bool
    {
        if ($this->connection === false) { return false; }
        if ($editID < 0) { $editID = $this->SelectSelfId(); }
        if ($editID < 0) { return false; }

        $isAdmin = (int)($isAdmin ? 1 : 0);

        try
        {

            $sql = "UPDATE `USERDB` SET `IsAdmin`=? WHERE `ID`=?";
            $statement = $this->connection->prepare($sql);
            $statement->bind_param("ii", $isAdmin, $editId);
            $result = $statement->execute();
            $statement->close();

            return $result !== false;

        }
        catch (\Throwable $e) { return false; }
    }

    public function ChangePass($newPassword, $editId = -1): bool
    {
        if ($this->connection === false) { return false; }
        if ($editId < 0) { $editId = $this->SelectSelfId(); }
        if ($editId < 0) { return false; }

        $hashedPass = \password_hash(trim($newPassword),  PASSWORD_DEFAULT);

        try
        {

            $sql = "UPDATE `USERDB` SET `Pass`=?, `IsFirstAccess`=0 WHERE `ID`=?";
            $statement = $this->connection->prepare($sql);
            $statement->bind_param("si", $hashedPass, $editId);
            $result = $statement->execute();
            $statement->close();

            return $result !== false;

        }
        catch (\Throwable $e) { return false; }

    }

    public function ResetPass($editId = -1)
    {
        if ($this->connection === false) { return false; }
        if ($editID < 0) { $editID = $this->SelectSelfId(); }
        if ($editID < 0) { return false; }

        // generate new password
        $characters = '2345679ABCDEFGHJKLMNPQRSTUVWXYZ'; // Excluding ambiguous characters: 0, 1, I, O
        $password = '';
        $length = strlen($characters);
        for ($i = 0; $i < 4; $i++) {
            $index = random_int(0, $length - 1);
            $password .= $characters[$index]; }
           
        // hash new created password
        $hashedPass = \password_hash(trim($password),  PASSWORD_DEFAULT);

        try
        {

            $sql = "UPDATE `USERDB` SET `Pass`=?, `IsFirstAccess`=1 WHERE `ID`=?";
            $statement = $this->connection->prepare($sql);
            $statement->bind_param("si", $hashedPass, $editId);
            $result = $statement->execute();
            $statement->close();

            return $result !== false ? $password : false;

        }
        catch (\Throwable $e) { return false; }

    }

    private function SelectSelfId(): int
    {
        if ($this->connection === false) { return -1; }
        if (!isset($_SESSION['UserID'])) { return -1; }
        return $_SESSION['UserID'];
    }

    // GetUsers ###################################################################################

    public function LoadUsers()
    {
        if ($this->connection === false) { return false; }
        $selfId = $this->SelectSelfId();

        $users = [];
        try 
        {
            $sql = "SELECT * FROM `USERDB` WHERE `ID`<>$selfId;";
            $statement = $this->connection->query($sql);
            while ($row = $statement->fetch_object())
            {
                $users[] = $row;
            }
        }
        catch (\Throwable $e) { return false; }
        return $users;
    }

    // Import #####################################################################################

    public function LoadExport()
    {
        $users = [];
        try 
        {
            $sql = "SELECT * FROM `USERDB`;";
            $statement = $this->connection->query($sql);
            while ($row = $statement->fetch_object())
            {
                $users[] = $row;
            }
        }
        catch (\Throwable $e) {  }
        return $users;
    }

    public function LoadImport($data)
    {

        if (!\is_array($data)) { return false; }

        $rows = [];
        foreach ($data as $entry)
        {

            $user = $entry['User'] ?? '';
            $pass = $entry['Pass'] ?? '';
            $isAdmin = (int)$entry['IsAdmin'];
            $isFirst = (int)$entry['IsFirstAccess'];
            if ($user == '' || $pass == '') { continue; }

            $rows[] = [
                'User' => $user,
                'Pass' => $pass,
                'IsAdmin' => $isAdmin,
                'IsFirstAccess' => $isFirst
            ];

        }
        if (count($rows) == 0) { return false; }

        // purge all entries
        if (!$this->DeleteAll()) { return false; }

        // insert all in $rows
        // Step 1: Prepare the SQL statement
        $sql = "INSERT INTO `USERDB` (
            `User`, 
            `Pass`, 
            `IsAdmin`, 
            `IsFirstAccess`) VALUES (?,?,?,?);";
        $stmt = $this->connection->prepare($sql);
        $result = true;

        // Step 2: Bind parameters and execute for each row
        foreach ($rows as $row) {
            $user = $row['User'];
            $pass = $row['Pass'];
            $isAdmin = $row['IsAdmin'];
            $isFirst = $row['IsFirstAccess'];

            // Bind parameters
            $stmt->bind_param('ssii', $user, $pass, 
                $isAdmin, $isFirst);

            // Execute the statement
            $result = $stmt->execute() ? $result : false;
        }

        $stmt->close();
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