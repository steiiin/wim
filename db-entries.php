<?php

namespace WIM;

// files ##########################################################################################
require_once dirname(__FILE__) . '/cron-auto-abfall.php';
require_once dirname(__FILE__) . '/cron-auto-maltesercloud.php';
require_once dirname(__FILE__) . '/cron-auto-nina.php';

// imports ########################################################################################

// global tags ####################################################################################
class TypeTag
{

    const INFO = 'INFO';
    const WARN = 'WARN';
    const EVENT = 'EVENT';
    const TASK = 'TASK';
    const RECURRING = 'RECURRING';

    public static function IsValidType($tag)
    {
        return $tag == self::INFO ||
               $tag == self::WARN ||
               $tag == self::EVENT ||
               $tag == self::TASK ||
               $tag == self::RECURRING;
    }

}

class RequestType    
{

    const INFO = 'INFO-AREA';
    const TASK = 'TASK-AREA';
    const EVENT = 'EVENT-AREA';
    const WARN = 'WARN-AREA';

    const ADMIN_INFO = 'ADMIN_INFO';
    const ADMIN_TASK = 'ADMIN_TASK';
    const ADMIN_RECURRING = 'ADMIN_RECURRING';
    const ADMIN_RECURRING_SEARCH = 'ADMIN_RECURRING_SEARCH';
    const ADMIN_EVENT = 'ADMIN_EVENT';

    public static function IsAdminViewType($tag)
    {
        return $tag == self::ADMIN_INFO ||
               $tag == self::ADMIN_TASK ||
               $tag == self::ADMIN_RECURRING ||
               $tag == self::ADMIN_RECURRING_SEARCH ||
               $tag == self::ADMIN_EVENT;
    }

    public static function IsValidType($tag)
    {
        return $tag == self::INFO ||
               $tag == self::WARN ||
               $tag == self::TASK ||
               $tag == self::EVENT ||
               $tag == self::ADMIN_INFO ||
               $tag == self::ADMIN_TASK ||
               $tag == self::ADMIN_RECURRING ||
               $tag == self::ADMIN_RECURRING_SEARCH ||
               $tag == self::ADMIN_EVENT;
    }

}

// interfaces #####################################################################################
interface ModuleWim {

    public function getName();
    public function getAutoTag();
    public function getAdminEntry();
    public function getAdminSettingsLink();
    public function run($cli = true);

}

// MainClasses ####################################################################################
class Entries
{

    private $connection;
    public function __construct()
    {

        $this->connection = $this->Connect();
        if ($this->connection === false) { return; }

        // create table
        $sql = "CREATE TABLE IF NOT EXISTS `ENTRYDB` (
                `ID` int(11) AUTO_INCREMENT PRIMARY KEY,
                `TIMETAG` datetime ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `USERTAG` text NOT NULL,
                `TYPETAG` text NOT NULL,
                `AUTOTAG` text,
                `PAYLOAD` text NOT NULL,
                `DATESTART` datetime,
                `DATEEND` datetime,
                `DATEHASTIME` tinyint(1) DEFAULT 0,
                `DATESHOWTIME` tinyint(1) DEFAULT 1,
                `TASK_SHOWUPCOMING` tinyint(1) DEFAULT 0,
                `CYCL_TYPE` tinyint(4),     /* 0: Everyday, 1: DayOfWeek, 2: DayOfMonth, 3: LastDayOfMonth, 4: LastWeekdayOfMonth */
                `CYCL_WEEKDAY` tinyint(4),
                `CYCL_DOM` tinyint(4));";
        $this->connection->query($sql);

        // clean ENTRYDB
        $requestedNow = date("Y-m-d H:i:s");
        $requested0000 = date("Y-m-d")." 00:00:00";
        $sql = "DELETE FROM `ENTRYDB` WHERE 
                    (
                        `TYPETAG` = '".TypeTag::INFO."' AND `DATEEND` < '$requestedNow'
                    ) 
                    OR
                    (
                        `TYPETAG` = '".TypeTag::WARN."' AND `DATEEND` < '$requestedNow'
                    ) 
                    OR
                    (
                        `TYPETAG` = '".TypeTag::TASK."' AND `DATEEND` < '$requestedNow'
                    )
                    OR
                    (
                        `TYPETAG` = '".TypeTag::EVENT."' AND
                        (
                            `DATEEND` IS NULL AND
                            (
                                (`DATEHASTIME`=1 AND `DATESTART` < '$requestedNow') OR
                                (`DATEHASTIME`=0 AND `DATESTART` < '$requested0000')
                            )
                        )
                        OR
                        (
                            `DATEEND` IS NOT NULL AND
                            (
                                (`DATEHASTIME`=1 AND `DATEEND` < '$requestedNow') OR
                                (`DATEHASTIME`=0 AND `DATEEND` < '$requested0000')
                            )
                        )
                    )
        ";
        $result = $this->connection->query($sql);

    }

    public function __destruct()
    {
        $this->Close();
    }

    // Creation ###################################################################################
	
    public function EditInfo($id, string $payload, bool $withDate, ?string $dateStart, ?string $dateEnd, string $autotag = '')
    {
        if ($this->connection === false) { return false; }

        // prepare tags
        $typetag = TypeTag::INFO;
        $usertag = $autotag != '' ? 'wim-automatic' : $_SESSION['User'];

        // prepare datetime
        if ($dateStart !== null) { $dateStart = UserInterface::GenerateDatetime($dateStart, '00:00'); }
        if ($dateEnd !== null) { $dateEnd = UserInterface::GenerateDatetime($dateEnd, '23:59'); }

        // prepare meta
        $hasTime = $hasTime ? 1 : 0;
        $showTime = $showTime ? 1 : 0;

        try 
        {

            // create new
            if ($id === false)
            {
                $sql = "INSERT INTO `ENTRYDB` (
                    `USERTAG`, 
                    `TYPETAG`, 
                    `AUTOTAG`, 
                    `PAYLOAD`, 
                    `DATESTART`, 
                    `DATEEND`, 
                    `DATEHASTIME`,
                    `DATESHOWTIME`,
                    `TASK_SHOWUPCOMING`) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)
                ";
                $statement = $this->connection->prepare($sql);
                $statement->bind_param('ssssssii',
                    $usertag,
                    $typetag,
                    $autotag,
                    $payload,
                    $dateStart,
                    $dateEnd, 
                    $showTime,
                    $showUpcoming);
            }

            // update existing entry
            else
            {
                $sql = "UPDATE `ENTRYDB` SET
                    `USERTAG`=?,
                    `TYPETAG`=?, 
                    `AUTOTAG`=?, 
                    `PAYLOAD`=?, 
                    `DATESTART`=?, 
                    `DATEEND`=?, 
                    `DATEHASTIME`=1,
                    `DATESHOWTIME`=?,
                    `TASK_SHOWUPCOMING`=? 
                    WHERE `ID`=?
                ";
                $statement = $this->connection->prepare($sql);
                $statement->bind_param('ssssssiii',
                    $usertag,
                    $typetag,
                    $autotag,
                    $payload,
                    $dateStart,
                    $dateEnd,
                    $showTime,
                    $showUpcoming,
                    $id);
            }

            $result = $statement->execute();
            $statement->close();
            return $result !== false;
            
        }
        catch (\Throwable $e) { return false; }
    }

    public function EditWarn($id, string $payload, ?string $dateStart, ?string $dateEnd, ?string $timeStart, ?string $timeEnd, string $autotag = '')
    {
        if ($this->connection === false) { return false; }

        $typetag = TypeTag::WARN;
        $usertag = $autotag != '' ? 'wim-automatic' : $_SESSION['User'];

        // prepare datetime
        $dateStart = UserInterface::GenerateDatetime($dateStart, $timeStart);
        $dateEnd = UserInterface::GenerateDatetime($dateEnd, $timeEnd);

        try 
        {

            // create new
            if ($id === false)
            {
                $sql = "INSERT INTO `ENTRYDB` (
                    `USERTAG`, 
                    `TYPETAG`, 
                    `AUTOTAG`, 
                    `PAYLOAD`, 
                    `DATESTART`, 
                    `DATEEND`, 
                    `DATEHASTIME`,
                    `DATESHOWTIME`) VALUES (?, ?, ?, ?, ?, ?, 1, 1)
                ";
                $statement = $this->connection->prepare($sql);
                $statement->bind_param('ssssss',
                    $usertag,
                    $typetag,
                    $autotag,
                    $payload,
                    $dateStart,
                    $dateEnd);
            }

            // update existing entry
            else
            {
                $sql = "UPDATE `ENTRYDB` SET
                    `USERTAG`=?,
                    `TYPETAG`=?, 
                    `AUTOTAG`=?, 
                    `PAYLOAD`=?, 
                    `DATESTART`=?, 
                    `DATEEND`=?, 
                    `DATEHASTIME`=1,
                    `DATESHOWTIME`=1
                    WHERE `ID`=?
                ";
                $statement = $this->connection->prepare($sql);
                $statement->bind_param('ssssssi',
                    $usertag,
                    $typetag,
                    $autotag,
                    $payload,
                    $dateStart,
                    $dateEnd,
                    $id);
            }

            $result = $statement->execute();
            $statement->close();
            return $result !== false;
            
        }
        catch (\Throwable $e) { return false; }
    }

    public function EditTask($id, string $payload, ?string $dateStart, ?string $dateEnd, ?string $timeStart, ?string $timeEnd, bool $showUpcoming, string $autotag = '')
    {
        if ($this->connection === false) { return false; }

        $typetag = TypeTag::TASK;
        $usertag = $autotag != '' ? 'wim-automatic' : $_SESSION['User'];

        // prepare datetime
        $dateEnd = UserInterface::GenerateDatetime($dateEnd, $timeEnd);
        if ($dateStart !== null)
        {
            $dateStart = UserInterface::GenerateDatetime($dateStart, $timeStart);
        }

        // prepare meta
        $showUpcoming = $showUpcoming ? 1 : 0;

        try 
        {

            // create new
            if ($id === false)
            {
                $sql = "INSERT INTO `ENTRYDB` (
                    `USERTAG`, 
                    `TYPETAG`, 
                    `AUTOTAG`, 
                    `PAYLOAD`, 
                    `DATESTART`, 
                    `DATEEND`, 
                    `DATEHASTIME`,
                    `DATESHOWTIME`,
                    `TASK_SHOWUPCOMING`) VALUES (?, ?, ?, ?, ?, ?, 1, 1, ?)
                ";
                $statement = $this->connection->prepare($sql);
                $statement->bind_param('ssssssi',
                    $usertag,
                    $typetag,
                    $autotag,
                    $payload,
                    $dateStart,
                    $dateEnd, 
                    $showUpcoming);
            }

            // update existing entry
            else
            {
                $sql = "UPDATE `ENTRYDB` SET
                    `USERTAG`=?,
                    `TYPETAG`=?, 
                    `AUTOTAG`=?, 
                    `PAYLOAD`=?, 
                    `DATESTART`=?, 
                    `DATEEND`=?, 
                    `DATEHASTIME`=1,
                    `DATESHOWTIME`=1,
                    `TASK_SHOWUPCOMING`=? 
                    WHERE `ID`=?
                ";
                $statement = $this->connection->prepare($sql);
                $statement->bind_param('ssssssii',
                    $usertag,
                    $typetag,
                    $autotag,
                    $payload,
                    $dateStart,
                    $dateEnd,
                    $showUpcoming,
                    $id);
            }

            $result = $statement->execute();
            $statement->close();
            return $result !== false;
            
        }
        catch (\Throwable $e) { return false; }
    }

    public function EditRecurring($id, string $payload, ?string $timeStart, ?string $timeEnd, int $cycleMode, ?int $weekday, ?int $dom, string $autotag = '')
    {
        if ($this->connection === false) { return false; }

        $typetag = TypeTag::RECURRING;
        $usertag = $autotag != '' ? 'wim-automatic' : $_SESSION['User'];

        // prepare datetime
        $dateStart = UserInterface::GenerateDatetime('3000-01-01', $timeStart);
        $dateEnd = UserInterface::GenerateDatetime('3000-01-01', $timeEnd);
        
        try 
        {

            // create new
            if ($id === false)
            {
                $sql = "INSERT INTO `ENTRYDB` (
                    `USERTAG`, 
                    `TYPETAG`, 
                    `AUTOTAG`, 
                    `PAYLOAD`, 
                    `DATESTART`, 
                    `DATEEND`, 
                    `DATEHASTIME`,
                    `CYCL_TYPE`,
                    `CYCL_WEEKDAY`,
                    `CYCL_DOM`) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?)
                ";
                $statement = $this->connection->prepare($sql);
                $statement->bind_param('ssssssiii',
                    $usertag,
                    $typetag,
                    $autotag,
                    $payload,
                    $dateStart,
                    $dateEnd, 
                    $cycleMode,
                    $weekday,
                    $dom);
            }

            // update existing entry
            else
            {
                $sql = "UPDATE `ENTRYDB` SET
                    `USERTAG`=?,
                    `TYPETAG`=?, 
                    `AUTOTAG`=?, 
                    `PAYLOAD`=?, 
                    `DATESTART`=?, 
                    `DATEEND`=?, 
                    `DATEHASTIME`=1,
                    `CYCL_TYPE`=?,
                    `CYCL_WEEKDAY`=?,
                    `CYCL_DOM`=?
                    WHERE `ID`=?
                ";
                $statement = $this->connection->prepare($sql);
                $statement->bind_param('ssssssiiii',
                    $usertag,
                    $typetag,
                    $autotag,
                    $payload,
                    $dateStart,
                    $dateEnd,
                    $cycleMode,
                    $weekday,
                    $dom,
                    $id);
            }

            $result = $statement->execute();
            $statement->close();
            return $result !== false;
            
        }
        catch (\Throwable $e) { return false; }
    }

    public function EditEvent($id, string $payload, ?string $dateStart, ?string $dateEnd, ?string $timeStart, ?string $timeEnd, bool $hasTime, bool $showTime, string $autotag = '')
    {
        if ($this->connection === false) { return false; }

        $typetag = TypeTag::EVENT;
        $usertag = $autotag != '' ? 'wim-automatic' : $_SESSION['User'];

        // prepare datetime
        if ($timeStart !== null) { $dateStart = UserInterface::GenerateDatetime($dateStart, $timeStart); }
        else { $dateStart = UserInterface::GenerateDatetime($dateStart); }
        if ($dateEnd !== null)
        {
            if ($timeEnd !== null) { $dateEnd = UserInterface::GenerateDatetime($dateEnd, $timeEnd); }
            else { $dateEnd = UserInterface::GenerateDatetime($dateEnd); }
        }
        else
        {
            $dateEnd = null;
        }

        // prepare meta
        $hasTime = $hasTime ? 1 : 0;
        $showTime = $showTime ? 1 : 0;

        try 
        {

            // create new
            if ($id === false)
            {
                $sql = "INSERT INTO `ENTRYDB` (
                    `USERTAG`, 
                    `TYPETAG`, 
                    `AUTOTAG`, 
                    `PAYLOAD`, 
                    `DATESTART`, 
                    `DATEEND`, 
                    `DATEHASTIME`,
                    `DATESHOWTIME`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ";
                $statement = $this->connection->prepare($sql);
                $statement->bind_param('ssssssii',
                    $usertag,
                    $typetag,
                    $autotag,
                    $payload,
                    $dateStart,
                    $dateEnd,
                    $hasTime, 
                    $showTime);
            }

            // update existing entry
            else
            {
                $sql = "UPDATE `ENTRYDB` SET
                    `USERTAG`=?,
                    `TYPETAG`=?, 
                    `AUTOTAG`=?, 
                    `PAYLOAD`=?, 
                    `DATESTART`=?, 
                    `DATEEND`=?, 
                    `DATEHASTIME`=?,
                    `DATESHOWTIME`=? 
                    WHERE `ID`=?
                ";
                $statement = $this->connection->prepare($sql);
                $statement->bind_param('ssssssiii',
                    $usertag,
                    $typetag,
                    $autotag,
                    $payload,
                    $dateStart,
                    $dateEnd,
                    $hasTime, 
                    $showTime,
                    $id);
            }

            $result = $statement->execute();
            $statement->close();
            return $result !== false;
            
        }
        catch (\Throwable $e) { return false; }
    }

    // Sourcing ###################################################################################
	
    public function LoadEntries($requestType)
    {
        if ($this->connection === false) { return false; }

        $entries = [];
        try 
        {
            $sql = $this->getSelect($requestType);
            $statement = $this->connection->query($sql);
            while ($row = $statement->fetch_object())
            {
                $entries[] = $row;
            }
        }
        catch (\Throwable $e) { return false; }
        return $entries;
    }

    public function LoadCustom($where)
    {
        if ($this->connection === false) { return false; }
        $entries = [];
        try 
        {
            $sql = "SELECT * FROM `ENTRYDB` WHERE $where;";
            $statement = $this->connection->query($sql);
            while ($row = $statement->fetch_object())
            {
                $entries[] = $row;
            }
        }
        catch (\Throwable $e) { return false; }
        return $entries;
    }

    public function LoadAutoInfo($autotag, $column, $sort)
    {
        $statement = $this->LoadCustom("`AUTOTAG`='".$autotag."' ORDER BY `$column` $sort LIMIT 1");
        if ($statement === false) { return false; }
        if (count($statement) != 1) { return false; }
        $result = $statement[0]->{$column};
        return $result == null ? false : $result;
    }

    // Import #####################################################################################

    public function LoadExport()
    {

        $result = [];
        $entries = $this->LoadCustom("`AUTOTAG`=''");
        if ($entries === false) { return $result; }
        foreach ($entries as $entry)
        {
            $result[] = $entry;
        }
        return $result;

    }
    public function LoadImport($data)
    {

        if (!\is_array($data)) { return false; }

        $rows = [];
        foreach ($data as $entry)
        {
            if (!\is_array($entry)) { continue; }
            $usertag = $entry['USERTAG'] ?? 'admin';
            $typetag = $entry['TYPETAG'] ?? '';
            if ($typetag == '' || !TypeTag::IsValidType($typetag)) { continue; }
            $payload = $entry['PAYLOAD'] ?? '';
            if ($payload == '' || !Validation::IsPayloadValid($payload)) { continue; }
            $dateStart = $entry['DATESTART'];
            if ($dateStart != null && !Validation::IsSqlDateValid($dateStart)) { continue; }
            $dateEnd = $entry['DATEEND'];
            if ($dateEnd != null && !Validation::IsSqlDateValid($dateEnd)) { continue; }
            $hasTime = (int)($entry['DATEHASTIME'] ?? 0);
            $showTime = (int)($entry['DATESHOWTIME'] ?? $hasTime == 1);

            $taskUpcoming = (int)$entry['DATEHASTIME'];
            $cyclType = $entry['CYCL_TYPE'] !== null ? (int)$entry['CYCL_TYPE'] : null;
            $cyclWeekday = $entry['CYCL_WEEKDAY'] !== null ? (int)$entry['CYCL_WEEKDAY'] : null;
            $cyclDom = $entry['CYCL_DOM'] !== null ? (int)$entry['CYCL_DOM'] : null;
            
            $rows[] = [
                'USERTAG' => $usertag,
                'TYPETAG' => $typetag,
                'AUTOTAG' => '',
                'PAYLOAD' => $payload,
                'DATESTART' => $dateStart,
                'DATEEND' => $dateEnd,
                'DATEHASTIME' => $hasTime,
                'DATESHOWTIME' => $showTime,
                'TASK_SHOWUPCOMING' => $taskUpcoming,
                'CYCL_TYPE' => $cyclType,
                'CYCL_WEEKDAY' => $cyclWeekday,
                'CYCL_DOM' => $cyclDom
            ];
        }
        if (count($rows) == 0) { return false; }

        // purge all entries
        if (!$this->DeleteAll()) { return false; }

        // insert all in $rows
        // Step 1: Prepare the SQL statement
        $sql = "INSERT INTO `ENTRYDB` (
                    `USERTAG`, 
                    `TYPETAG`, 
                    `AUTOTAG`, 
                    `PAYLOAD`, 
                    `DATESTART`, 
                    `DATEEND`, 
                    `DATEHASTIME`,
                    `DATESHOWTIME`,
                    `TASK_SHOWUPCOMING`,
                    `CYCL_TYPE`,
                    `CYCL_WEEKDAY`,
                    `CYCL_DOM`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmt = $this->connection->prepare($sql);
        $result = true;

        // Step 2: Bind parameters and execute for each row
        foreach ($rows as $row) {
            $usertag = $row['USERTAG'];
            $typetag = $row['TYPETAG'];
            $autotag = $row['AUTOTAG'];
            $payload = $row['PAYLOAD'];
            $dateStart = $row['DATESTART'];
            $dateEnd = $row['DATEEND'];
            $hasTime = $row['DATEHASTIME'];
            $showTime = $row['DATESHOWTIME'];
            $taskShowUpcoming = $row['TASK_SHOWUPCOMING'];
            $cyclType = $row['CYCL_TYPE'];
            $cyclWeekday = $row['CYCL_WEEKDAY'];
            $cyclDom = $row['CYCL_DOM'];

            // Bind parameters
            $stmt->bind_param('ssssssiiiiii', $usertag, $typetag, $autotag, 
                $payload, $dateStart, $dateEnd, $hasTime, $showTime, 
                $taskShowUpcoming, $cyclType, $cyclWeekday, $cyclDom);

            // Execute the statement
            $result = $stmt->execute() ? $result : false;
        }

        $stmt->close();
        return $result;

    }

    // Select #####################################################################################

    private function getSelect($requestType, $autotag = '')
    {
        return $this->getSelectForDate($requestType, date("Y-m-d"), date("H:i:s"), $autotag);
    }
    private function getSelectForDate($requestType, $requestDate, $requestTime, $autotag = '')
    {

        $requested0000 = (new \DateTime($requestDate . " 00:00:00"))->format("Y-m-d H:i:s"); 
        $requested2359 = (new \DateTime($requestDate . " 23:59:59"))->format("Y-m-d H:i:s"); 
        $requestedNow = $requestDate . " " . $requestTime;

        $time0000 = "3000-01-01 00:00:00";
        $time2359 = "3000-01-01 23:59:59";
        $timeNow = "3000-01-01 ".$requestTime;

        $filterUser = " AND `AUTOTAG` = '$autotag'".(isset($_SESSION['ident']) && isset($_SESSION['IsAdmin']) && $_SESSION['IsAdmin'] ? '' : " AND `USERTAG` = '".$_SESSION['User']."'");

        switch ($requestType)
        {
            case RequestType::INFO:
                return "SELECT * FROM `ENTRYDB` WHERE 
                    (
                        `TYPETAG` = '".TypeTag::INFO."' AND 
                        (
                            (`DATESTART` IS NULL) OR 
                            (`DATESTART` <= '$requested0000' AND `DATEEND` >= '$requestedNow')
                        )
                    )
                    OR
                    (
                        `TYPETAG` = '".TypeTag::EVENT."' AND 
                        (
                            (`DATEHASTIME` = 0 AND `DATESTART` <= '$requested0000') OR 
                            (`DATEHASTIME` = 1 AND `DATESTART` <= '$requested2359' AND `DATEEND` IS NULL) OR 
                            (`DATEHASTIME` = 1 AND `DATESTART` <= '$requested2359' AND `DATEEND` >= '$requestedNow') 
                        )
                    ) 
                    ORDER BY `PAYLOAD` ASC";
                
            case RequestType::TASK:

                $requestedWeekday = (new \DateTime($requestDate))->format("w");
                $requestedDoM = (new \DateTime($requestDate))->format("j");
                $requestedLWeekOM = (new \DateTime('last day of this month'))->modify('-6 days')->format("Y-m-d 00:00:00");
                $isRequestedLastDoM = ((new \DateTime($requestDate))->format("t") == $requestedDoM) ? 1 : 0;

                return "SELECT * FROM `ENTRYDB` WHERE 
                    (
                        `TYPETAG` = '".TypeTag::TASK."' AND '$requestedNow' < `DATEEND` AND 
                        (
                            (`DATESTART` < '$requestedNow' ) OR 
                            (`DATESTART` IS NULL AND `DATEEND` < '$requested2359' )
                        )
                    )
                    OR
                    (
                        `TYPETAG` = '".TypeTag::RECURRING."' AND ('$timeNow' BETWEEN `DATESTART` AND `DATEEND`) AND 
                        (
                            (`CYCL_TYPE` = 0) OR 
                            (`CYCL_TYPE` = 1 AND `CYCL_WEEKDAY` = $requestedWeekday) OR 
                            (`CYCL_TYPE` = 2 AND `CYCL_DOM` = $requestedDoM) OR 
                            (`CYCL_TYPE` = 3 AND 1 = $isRequestedLastDoM) OR 
                            (`CYCL_TYPE` = 4 AND '$requested0000' >= '$requestedLWeekOM' AND `CYCL_WEEKDAY` = $requestedWeekday)
                        )
                    ) 
                    ORDER BY `DATEEND` ASC, `PAYLOAD` ASC";

            case RequestType::EVENT: 
                return "SELECT * FROM `ENTRYDB` WHERE 
                    (`TYPETAG` = '".TypeTag::EVENT."' AND `DATESTART` > '$requested2359') OR 
                    (`TYPETAG` = '".TypeTag::TASK."' AND `TASK_SHOWUPCOMING` = 1 AND `DATESTART` > '$requestedNow') 
                    ORDER BY `DATESTART` ASC, `DATEEND` ASC";
        
            case RequestType::WARN:
                return "SELECT * FROM `ENTRYDB` WHERE `TYPETAG` = '".TypeTag::WARN."' ORDER BY `PAYLOAD` ASC";
                
            case RequestType::ADMIN_INFO:
                return "SELECT * FROM `ENTRYDB` WHERE `TYPETAG` = '".TypeTag::INFO."'$filterUser ORDER BY `DATESTART` ASC, `PAYLOAD` ASC";
            case RequestType::ADMIN_TASK:
                return "SELECT * FROM `ENTRYDB` WHERE `TYPETAG` = '".TypeTag::TASK."'$filterUser ORDER BY `DATEEND` ASC, `DATESTART` ASC";
            case RequestType::ADMIN_RECURRING:
                return "SELECT * FROM `ENTRYDB` WHERE `TYPETAG` = '".TypeTag::RECURRING."'$filterUser ORDER BY `CYCL_TYPE` ASC, `CYCL_WEEKDAY` ASC, `CYCL_DOM` ASC, `PAYLOAD` ASC";
            case RequestType::ADMIN_EVENT:
                return "SELECT * FROM `ENTRYDB` WHERE `TYPETAG` = '".TypeTag::EVENT."'$filterUser ORDER BY `DATEEND` ASC, `DATESTART` ASC";
        }

    }

    // Deletion ###################################################################################
	
    public function DeleteByAutotag($autotag)
    {
        if ($this->connection === false) { return false; }
        try 
        {
            $statement = $this->connection->prepare("DELETE FROM `ENTRYDB` WHERE `AUTOTAG`=?");
            $statement->bind_param("s", $autotag);
            $result = $statement->execute();
            $statement->close();
            return $result !== false;
        }
        catch (\Throwable $e) { return false; }
    }

    public function DeleteById($itemId)
    {
        if ($this->connection === false) { return false; }
        try 
        {
            $statement = $this->connection->prepare("DELETE FROM `ENTRYDB` WHERE `ID`=?");
            $statement->bind_param("i", $itemId);
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
            $statement = $this->connection->prepare("DELETE FROM `ENTRYDB`;");
            $result = $statement->execute();
            $statement->close();
            return $result !== false;
        }
        catch (\Throwable $e) { return false; }
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

class UserInterface
{

    // Payload-Converter ##########################################################################

    public static function GetPayloadFromMalteserCloudEvent(SharepointApi\EventItem $event)
    {
        $data = [
            'key' => $event->getCategory().'#'.$event->getLocation().'#'.$event->getTitle(),
            'title' => $event->getTitle(),
            'description' => $event->getDescription(),
            'category' => $event->getCategory(),
            'location' => $event->getLocation()
        ];
        return \json_encode($data);
    }

    public static function GetPayloadFromWarningInfo(NinaApi\WarningItem $item)
    {

        $id = $item->ID;
        $title = $item->Title;
        $provider = strtolower($item->Provider);

        // set type & severity
        $severity = strtolower($item->Severity);
        $type = strtolower($item->Type);
        if ($type == "cancel")
        {
            $severity = "";
            $type = "Entwarnung";
        }
        else
        {
            $type = $severity == "minor" ? "Information" : "Warnung";
            if ($severity == "moderate") { $severity = $provider == "dwd" ? "Warnstufe: Markantes Wetter" : "Warnstufe: Moderat"; }
            else if ($severity == "severe") { $severity = $provider == "dwd" ? "Warnstufe: Unwetter" : "Warnstufe: Gefahr"; }
            else if ($severity == "extreme") { $severity = $provider == "dwd" ? "Warnstufe: Extremes Unwetter" : "Warnstufe: Extreme Gefahr"; }
            else { $severity = ""; }
        }
        $isWeather = $provider == "dwd";

        // set provider description
        if ($provider == "dwd") { $provider = "Deutscher Wetterdienst"; }
        else if ($provider == "police") { $provider = "Polizeimeldungen"; }
        else if ($provider == "lhp") { $provider = "Hochwassermeldungen"; }
        else { $provider = "Katastrophenschutz"; }
        
        // create payload
        $data = [
            'key' => $item->ID,
            'warntype' => $isWeather ? "weather" : "warn",
            'title' => $item->Title,
            'category' => $provider
        ];
        if ($type !== "") { $data['vehicle'] = $type; }
        if ($severity !== "") { $data['location'] = $severity; }

        return \json_encode($data);
    }

    public static function GetPayloadFromAbfallEvent(ICal\iCal_Event $event)
    {
        $title = '';
        if (strpos($event->summary, "Restabfall") !== false) { $title = 'Restabfalltonne (Schwarz)'; }
        else if (strpos($event->summary, "Gelbe Tonne") !== false) { $title = 'Gelbe Tonne'; }
        else if (strpos($event->summary, "Pappe") !== false) { $title = 'Papiertonne (Blau)'; }
        $title = $title == '' ? $event->summary : $title . ' an die Straße stellen';

        $data = [
            'key' => $title,
            'title' => $title,
            'category' => 'Abfallkalender'
        ];
        return \json_encode($data);
    }

    public static function GetPayloadObj(string $payload)
    {
        return \json_decode($payload);
    }

    public static function GetFriendlyDate($dt, $showTime): string
    {
        if ($dt === false) { return ''; }

        $now = new \DateTime();
        if ($dt < $now) { return ''; }
        
        $isToday = ($now->format('Y-m-d') == $dt->format('Y-m-d'));
        $isSameYear = ($now->format('Y') == $dt->format('Y'));

        if ($isToday) { return $showTime ? $dt->format('H:i') : ''; }
        else if ($isSameYear) { return $dt->format('d.m.'.($showTime ? ' H:i' : '')); }
        else { return $dt->format('d.m.y'.($showTime ? ' H:i' : '')); }

    }

    public static function GetFrienlyRecurringDescription($cycleMode, $weekday, $dom, $short = false): string
    {
        $cycleText = "";
        if ($cycleMode == 0) 
        {
            $cycleText = $short ? 'Täglich' : 'Täglich. Jeden einzelnen Tag.';
        }
        else if ($cycleMode == 2)
        {
            $cycleText = $short ? "Monatlich (Zum $dom.)" : "Jeden Monat zum $dom.";
        }
        else if ($cycleMode == 3)
        {
            $cycleText = $short ? "Monatlich (Letzter Tag)" : "Zum Letzten Tag jedes Monats.";
        }
        else 
        {
            $formatter = new \IntlDateFormatter('de-DE', \IntlDateFormatter::FULL, \IntlDateFormatter::NONE);
            $formatter->setPattern('EEEE');

            if ($cycleMode == 1) 
            {
                $weekday = $formatter->format(strtotime("sun +{$weekday} days"));
                $cycleText = $short ? "Wöchentlich ($weekday)" : "Jede Woche am $weekday.";
            }
            else if ($cycleMode == 4)
            {
                $weekday = $formatter->format(strtotime("sun +{$weekday} days"));
                $cycleText = $short ? "Monatlich (Letzter $weekday)" : "Jeden letzten $weekday des Monats.";
            }
        }
        return $cycleText;
    }

    // DateTime-Converter ##########################################################################
    public static function GenerateDatetime($date, $time = '00:00')
    {
        return "$date $time:00";
    }

    // HTML-Generator #############################################################################

    public static function GenerateHtmlOfEntriesList($entries, $requestType, $filter = [])
    {

        // variables
        $adminView = RequestType::IsAdminViewType($requestType);
        $recurringGroup = '';

        $html = '';
        $isFirst = true;
        
        foreach($entries as $entry)
        {

            // filter hidden entries
            if (count($filter) > 0 && $requestType !== RequestType::ADMIN_RECURRING_SEARCH)
            {
                if (in_array($entry->ID, $filter)) { continue; }
            }

            $payload = self::GetPayloadObj($entry->PAYLOAD);
            $otherUser = $entry->USERTAG != $_SESSION['User'];

            $dtNow = new \DateTime();
            $dtStart = ($entry->DATESTART != null ? \DateTime::createFromFormat("Y-m-d H:i:s", $entry->DATESTART) : false);
            $dtEnd = ($entry->DATEEND != null ? \DateTime::createFromFormat("Y-m-d H:i:s", $entry->DATEEND) : false);

            $encodedPayload = \htmlentities($entry->PAYLOAD);
            $encodedDateStart = $dtStart === false ? 'null' : "'".$dtStart->format('Y-m-d')."'";
            $encodedTimeStart = $dtStart === false ? 'null' : "'".$dtStart->format('H:i')."'";
            $encodedDateEnd = $dtEnd === false ? 'null' : "'".$dtEnd->format('Y-m-d')."'";
            $encodedTimeEnd = $dtEnd === false ? 'null' : "'".$dtEnd->format('H:i')."'";
            
            switch ($entry->TYPETAG)
            {
                case TypeTag::INFO:

                    // opening listitem ------------------------------------------------------
                    $html .= $adminView ? "<li class='editable'>" : "<li>";
                    $html .= $adminView ? "<button onclick=\"WIM.EDITOR.infoEditor.edit($entry->ID, $encodedPayload, $encodedDateStart, $encodedDateEnd);\">&nbsp;</button>" : '';

                    // payload ------------------------------------------------------------------
                    $html .= self::GenerateHtmlOfPayload($payload);

                    // time-info -----------------------------------------------------------------
                    if ($adminView && $dtStart !== false && $dtEnd !== false)
                    {
                        $dateStart = $dtStart->format('d.m.y');
                        $dateEnd = $dtEnd->format('d.m.y');
                        $html .= "<div class='timeinfo'>";
                        $html .= $dtStart < $dtNow ? '' : "Sichtbar ab: $dateStart, ";
                        $html .= "Wird nach dem $dateEnd gelöscht.";
                        $html .= '</div>';
                    }
                    break;

                case TypeTag::EVENT:

                    $hasTime = $entry->DATEHASTIME == '1' ? true : false;
                    $showTime = $entry->DATESHOWTIME == '1' ? true : false;

                    $calcStartEndIsSame = ($dtStart !== false && $dtEnd !== false) ? ($dtStart->format('Y-m-d') == $dtEnd->format('Y-m-d')) : false;
                    $calcStartIsToday = ($dtStart !== false) ? ($dtNow->format('Y-m-d') == $dtStart->format('Y-m-d')) : true;
                    $calcEndIsToday = ($dtEnd !== false) ? ($dtNow->format('Y-m-d') == $dtEnd->format('Y-m-d')) : $calcStartIsToday;
                    
                    $timeInfo = '';
                    if ($requestType == RequestType::INFO)
                    {

                        // show only short listitem, if longer lasting event
                        if (!$calcStartIsToday && !$showTime)
                        {
                            $timeInfo = self::GetFriendlyDate($dtEnd, false);
                            $timeInfo = $timeInfo == '' ? '' : "- $timeInfo";

                            $html .= '<li>';
                            $html .= "<div class='title'>$payload->title $timeInfo</div>";
                            break;
                        }

                        $timeInfo = self::GetFriendlyDate($dtStart, $showTime);

                        $until = self::GetFriendlyDate($dtEnd, $showTime);
                        if ($until) { $timeInfo = $timeInfo == "" ? "BIS $until" : "$timeInfo - $until"; }

                    }
                    else
                    {

                        // event-area & admin
                        $timeInfo .= self::GetFriendlyDate($dtStart, $showTime);
                        if ($calcStartEndIsSame) 
                        {
                            if ($showTime) 
                            {
                                $timeInfo .= $timeInfo ? ' - ' : 'BIS ';
                                $timeInfo .= $dtEnd->format('H:i');
                            }
                        }
                        else
                        {
                            $endInfo = self::GetFriendlyDate($dtEnd, $showTime);
                            $timeInfo .= $endInfo ? ' - ' : '';
                            $timeInfo .= $endInfo;
                        }
                        if ($timeInfo == '') 
                        {
                            // norange - sameday - admin
                            $timeInfo = 'Heute';
                        }

                    }

                    if (!$hasTime) { $encodedTimeStart = 'null'; $encodedTimeEnd = 'null'; }

                    // opening listitem ------------------------------------------------------
                    $html .= $adminView ? "<li class='editable'>" : "<li>";
                    $html .= $adminView ? "<button onclick=\"WIM.EDITOR.eventEditor.edit($entry->ID, $encodedPayload, $encodedDateStart, $encodedTimeStart, $encodedDateEnd, $encodedTimeEnd);\">&nbsp;</button>" : '';
                    
                    // payload ------------------------------------------------------------------
                    $html .= self::GenerateHtmlOfPayload($payload);

                    // time-info ----------------------------------------------------------------------------
                    $html .= "<div class='timeinfo'>$timeInfo</div>";
                    break;

                case TypeTag::TASK:

                    $showUpcoming = $entry->TASK_SHOWUPCOMING == '1' ? true : false;

                    $timeInfo = '';
                    if ($requestType == RequestType::EVENT)
                    {
                        $timeInfo = self::GetFriendlyDate($dtEnd, true);
                    }
                    else
                    {
                        $timeInfo .= $adminView && $dtStart !== false && $dtStart > $dtNow ? 'Sichtbar ab: '.self::GetFriendlyDate($dtStart, true).($showUpcoming ? ' (Vorher Terminliste)' : '').', ' : '';
                        $timeInfo .= 'Zu Erledigen bis: '.self::GetFriendlyDate($dtEnd, true);
                    }

                    // opening listitem ------------------------------------------------------
                    $html .= $adminView ? "<li class='editable'>" : ($requestType == RequestType::EVENT ? '<li>' : "<li class='check'>");
                    $html .= $adminView ? "<button onclick=\"WIM.EDITOR.taskEditor.edit($entry->ID, $encodedPayload, $encodedDateStart, $encodedTimeStart, $encodedDateEnd, $encodedTimeEnd, $showUpcoming);\">&nbsp;</button>" : '';
                    
                    // payload ------------------------------------------------------------------
                    $html .= self::GenerateHtmlOfPayload($payload);

                    // time-info ----------------------------------------------------------------------------
                    $html .= $timeInfo ? "<div class='timeinfo'>$timeInfo</div>" : '';
                    break;
                
                case TypeTag::RECURRING:

                    $group = self::GetFrienlyRecurringDescription($entry->CYCL_TYPE, $entry->CYCL_WEEKDAY, $entry->CYCL_DOM, true);
                    if ($requestType == RequestType::ADMIN_RECURRING && $recurringGroup != $group)
                    {
                        $recurringKey = $entry->CYCL_TYPE.'#'.($entry->CYCL_WEEKDAY ? $entry->CYCL_WEEKDAY : '').'#'.($entry->CYCL_DOM ? $entry->CYCL_DOM : '');
                        $recurringGroup = $group;
                        $html .= $isFirst ? '' : '</ul></li>'; 
                        $html .= "<li><script type='text/javascript'>window.addEventListener('load', function() { WIM.EDITOR.adminGroups.expandInit(\"subgroup-{$recurringKey}\");} );</script>";
                        $html .= "<h2 class='subgroup' id='subgroup-{$recurringKey}' onclick='WIM.EDITOR.adminGroups.expandToggle(this);' expanded='false'><span class='arrow'>&nbsp;</span>{$recurringGroup}</h2>";
                        $html .= "<ul id='subgroup-{$recurringKey}-list's>";
                    }

                    $encodedWeekday = $entry->CYCL_WEEKDAY ? "$entry->CYCL_WEEKDAY" : 'null';
                    $encodedDom = $entry->CYCL_DOM ? "'".$entry->CYCL_DOM."'" : 'null';
                    $isHidden = in_array($entry->ID, $filter);

                    // opening listitem ----------------------------recurringGroup--------------------------
                    $html .= $adminView ? "<li class='editable'>" : "<li class='check'>";
                    if ($requestType == RequestType::ADMIN_RECURRING_SEARCH)
                    { 
                        $html .= "<button class='select' type='button' onclick=\"WIM.EDITOR.hidetaskEditor.invokeSelect(this, $entry->ID, ".($isHidden ? 'true' : 'false').");return false;\">&nbsp;</button>";
                    }
                    else
                    { $html .= $adminView ? "<button onclick=\"WIM.EDITOR.recurringEditor.edit($entry->ID, $encodedPayload, $encodedTimeStart, $encodedTimeEnd, $entry->CYCL_TYPE, $encodedWeekday, $encodedDom);\">&nbsp;</button>" : ''; }
                    

                    // payload ------------------------------------------------------------------
                    $html .= self::GenerateHtmlOfPayload($payload);

                    // time-info ----------------------------------------------------------------------------
                    $timeInfo = $adminView ? self::GetFrienlyRecurringDescription($entry->CYCL_TYPE, $entry->CYCL_WEEKDAY, $entry->CYCL_DOM) : '';
                    $timeInfo .= $adminView ? " ({$dtStart->format('H:i')}-{$dtEnd->format('H:i')})" : "";
                    $html .= $timeInfo ? "<div class='timeinfo'>$timeInfo</div>" : '';
                    if ($isHidden) { $html .= "<div class='timeinfo'>(Heute Ausgeblendet)</div>"; }
                    break;

                case TypeTag::WARN:

                    $isWeather = isset($payload->warntype) && $payload->warntype == "weather";
                    $html .= "<li class='".($isWeather ? "weather" : "warn")."'>";

                    // payload ------------------------------------------------------------------
                    $html .= self::GenerateHtmlOfPayload($payload);
                    break;

            }

            // closing listitem -----------------------------------------------------------
            $html .= $adminView && $otherUser ? "<div class='usertag'>von: @$entry->USERTAG</div>" : '';
            $html .= '<hr>';
            $html .= '</li>';
            $isFirst = false;

        }

        return $html;

    }

    private static function GenerateHtmlOfPayload($payload): string
    {

        $html = "";
        $anyMeta = (isset($payload->category) && is_string($payload->category) && trim($payload->category) !== '') || 
                   (isset($payload->location) && is_string($payload->location) && trim($payload->location) !== '');
        $duoMeta = (isset($payload->category) && is_string($payload->category) && trim($payload->category) !== '') && 
                   (isset($payload->location) && is_string($payload->location) && trim($payload->location) !== '');
        $hasVehicle = isset($payload->vehicle) && is_string($payload->vehicle) && trim($payload->vehicle) !== '';

        // title-text
        $html .= "<div class='title".($hasVehicle ? " vehicle'>" : "'>");
        $html .= $hasVehicle ? "<span>$payload->vehicle</span>" : ''; 
        $html .= "$payload->title</div>";

        // meta-text
        if ($anyMeta)
        {
            $html .= "<div class='subtext meta'>";
            $html .= "{$payload->category}";
            $html .= $duoMeta ? " ({$payload->location})" : "{$payload->location}";
            $html .= "</div>";
        }
        
        if ($payload->description) { $html .= "<div class='subtext description'>$payload->description</div>"; }

        return $html;
    }

    public static function GenerateHtmlOfUsersList($users)
    {

        // variables
        $html = '';
        $isFirst = true;
        
        foreach($users as $user)
        {

            $html .= "<li class='editable'>";

            $html .= "<button onclick=\"WIM.EDITOR.userEditor.edit($user->ID, '$user->User', ";
            $html .= $user->IsAdmin == '1' ? "true": "false";
            $html .= ")\">&nbsp;</button>";

            $html .= "<div class=\"title\">$user->User</div>";

            $html .= "<div class=\"subtext\">@$user->User";
            $html .= $user->IsAdmin == '1' ? ' (Administrator)' : '';
            $html .= $user->IsFirstAccess == '1' ? ' - [Passwort noch nicht geändert]' : '';
            $html .= "</div>";


            $html .= "<hr>";
            $html .= "</li>";

        }

        return $html;
        
    }

    public static function GenerateHtmlOfGroup($typeTag, $groupTitle, $isEmpty, $listHtml, array $tools)
    {

        $html = '';
        $typeTag = \strtolower($typeTag);

        // group container
        $html .= "<div class='group'>";

        // header, with or without toggle ability
        $html .= "  <h2 id='entries-$typeTag-anchor'";
        $html .= $isEmpty ? " class='nopointer'" : '';
        $html .= $isEmpty ? '>' : " onclick='WIM.EDITOR.adminGroups.expandToggle(this)'><span class='arrow'>&nbsp;</span>";
        $html .= "$groupTitle</h2>";
            
        // add tool-options
        if (count($tools) > 0)
        {
            $html .= "  <div class='tools'>";
            foreach ($tools as &$tool) {
                if ($tool['title'] && $tool['onclick']) 
                {
                    // default icon
                    if (!isset($tool['icon'])) { $tool['icon'] = 'ic_action_add.svg'; }

                    $html .= "    <button onclick=\"{$tool['onclick']}\">";
                    $html .= "      <img src='res/{$tool['icon']}'>";
                    $html .= "      <span>{$tool['title']}</span>";
                    $html .= "    </button>";

                }
            }
            $html .= "  </div>";
        }
            
        $html .= "  <ul id='entries-$typeTag-list' style='display:none'>{$listHtml}</ul>";
        $html .= "</div>";

        return $html;

    }

}