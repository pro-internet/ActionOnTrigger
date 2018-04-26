<?
    // Klassendefinition
    class ActionOnTrigger extends IPSModule {
 
        public $prefix = "PIAOT";

        public function __construct($InstanceID) {

            // Diese Zeile nicht löschen
            parent::__construct($InstanceID);

        }
 
        // Überschreibt die interne IPS_Create($id) Funktion

        public function Create() {

            // Diese Zeile nicht löschen.

            parent::Create();

            $this->registAllProperties();

            $this->checkScript("SetValue", "<?php SetValue(\$IPS_VARIABLE, \$IPS_VALUE); ?>", false);

            $this->checkProfile("Switch", 0, 0, 1, 5, 1, "", "", "");
 
            $this->checkProfile("LOM.Tl", 1, 0, 3600, 5, 1, "", " s", "Clock");

            $this->checkProfile("LOM.Wert.100", 1, 0, 100, 1, 0, "", " %", "Electricity");

            $this->checkProfile("Sun", 1, 0, 120000, 1000, 0, "", " lx", "Intensity");

            $this->checkProfile("Temperature_C", 1, 0, 120, 1, 0, "", " °C", "Temperature");

            $this->checkProfile("Temperature_F", 1, 0, 3600, 30, 0, "", " °F", "Temperature");

            $this->checkProfile("Wattage", 1, 0, 5000, 1, 0, "", " W", "Electricity");

            $sperre = $this->checkVar("Sperre", 0, true, "", 1);
            $automatik = $this->checkVar("Automatik", 0, true, "", 0);
            $status = $this->checkVar("Status", 0, true, "", 2);
            $timerLength = $this->checkVar("Nachlauf", 1, true, "", 4, 30); 

            $this->checkFolder("Targets"); 
            $this->checkFolder("Sensoren");

            $this->checkScript("SensorActivated", $this->prefix . "_onSensorActivated");

            $this->checkScript("AutomaticChange", $this->prefix . "_onAutomaticChange");

            $this->checkScript("SubTimer", $this->prefix . "_subTimer");

            $this->mergeEvents();

            $this->setOnAutomaticChangeEvent();
 
        }
 
        // Überschreibt die intere IPS_ApplyChanges($id) Funktion

        public function ApplyChanges() {

            // Diese Zeile nicht löschen
            
            parent::ApplyChanges();

            if ($this->doesExist($this->searchObjectByName(""))) {

                $this->mergeLinksInFolder("SchwellwertSensoren", "Lichtsensor");

            }

            
        }

// ------------------------------------------------------------------------------------------------------------


        //                                  //
        //  Einmalige Funktionen / Parts   //
        //                                //


        // Prüft ob Schwellwert-Funktion aktiviert ist 

        protected function checkTreshold () {

            if ($this->ReadPropertyBoolean("BenutzeSchwellwert") == true) {

                if (!$this->doesExist($this->searchObjectByName("BenutzeSchwellwert"))) {

                    $this->checkFolder("SchwellwertSensoren");
                    $schwellwert = $this->checkVar("Schwellwert", 1, false, "", 4, 5000);

                    if ($this->ReadPropertyInteger("ProfileType") == 0) {

                        $this->addThresholdSun($schwellwert);

                    } else if ($this->ReadPropertyInteger("ProfileType") == 1) {

                        $this->addThresholdTemperature_C($schwellwert);

                    } else if ($this->ReadPropertyInteger("ProfileType") == 2) {

                        $this->addThresholdTemperature_F($schwellwert);

                    } else if ($this->ReadPropertyInteger("ProfileType") == 3) {

                        $this->addThresholdWattage($schwellwert);

                    }

                }
 
            } else {

                if ($this->doesExist($this->searchObjectByName("Schwellwert"))) {

                    $this->deleteInstance($this->searchObjectByName("SchwellwertSensoren"));
                    IPS_DeleteVariable($this->searchObjectByName("Schwellwert"));

                }

            }

        }

        // Prüft erst ob EIB Dim im Targets ordner ist, wenn ja wird die Variable für den DIM Wert erstellt

        protected function checkDimVar () {

            if($this->checkEIBDim() != false) {

                $dimIntensity = $this->checkVar("Wert", 1, false, "", 3, 50);
                IPS_SetVariableCustomProfile($this->searchObjectByName("Wert"), "LOM.Wert.100");
                IPS_SetVariableCustomAction($this->searchObjectByName("Wert"), $this->searchObjectByName("SetValue"));

            } else {

                if ($this->doesExist($this->searchObjectByName("Wert"))) {

                    IPS_DeleteVariable($this->searchObjectByName("Wert"));

                }

            }

        }

        // Prüft ob EIB Dim im Targets Ordner vorhanden ist, returnt true wenn ja

        protected function checkEIBDim () {

            $targets = IPS_GetObject($this->searchObjectByName("Targets"));

            foreach ($targets['ChildrenIDs'] as $child) {

                $lnk = IPS_GetLink($child);

                $obj = IPS_GetObject($lnk['TargetID']);

                $parent = IPS_GetObject($obj['ParentID']);

                if ($parent['ObjectType'] == 1) {

                    $parentInstance = IPS_GetInstance($parent['ObjectID']);

                    if ($parentInstance['ModuleInfo']['ModuleName'] == "EIB Group") {

                        $getVar = IPS_GetVariable($lnk['TargetID']);

                        if ($getVar['VariableType'] == 1) {

                            return true;

                        }

                    }

                }

            }

            return false;

        } 

        protected function linkTresholdSensors () {

            /*if ($this->ReadPropertyBoolean("BenutzeSchwellwert") == true) {

                if (count($this->getPropertyList("TresholdSensors")) > 0) {

                    $allSensors = $this->getPropertyList("TresholdSensors");

                    $sensorsFolder = IPS_GetObject($this->searchObjectByName("SchwellwertSensoren"));

                    print_r($sensorsFolder);

                    $actualSensors = $sensorsFolder['ChildrenIDs'];

                    $sensorVars = [];

                    if (count($actualSensors) > 0) {

                        foreach ($actualSensors as $sensor) {

                            $lnk = IPS_GetLink($sensor);

                            $sensorVars[] = $lnk['TargetID'];

                        }

                    }

                    foreach ($allSensors as $sensor) {

                        if (!in_array($sensor, $sensorVars)) {

                            $sensorObj = IPS_GetObject($sensor);

                            $this->linkVar($sensor, $this->searchObjectByName("SchwellwertSensoren", $sensorObj['ObjectName']));

                        }

                    }

                }

            } */

        }

        // Registriert alle Properties (8 Lampen und 6 Senoren) 
 
        protected function registAllProperties () {

            $this->RegisterPropertyBoolean("BenutzeSchwellwert", false);
            $this->RegisterPropertyInteger("Lichtsensor", 0);
            $this->RegisterPropertyInteger("ProfileType", 0);

        }

        // [Parameter ('Sensoren' oder 'Targets')] Gibt array mit ID's aller angeforderten Properties zurück 

        public function getPropertyList ($from) {

            $units = array();

            if ($from == "Lichtsensor") {

                if ($this->ReadPropertyInteger("LichtSensor") != null) {

                    $units[] = $this->ReadPropertyInteger("Lichtsensor");

                }

            }

            return $units;

        }

        // Setzt alle onChange Events

        public function setOnChangeEvents ($moduleId) {

            $moduleChilds = IPS_GetChildrenIDs($moduleId);

            foreach ($moduleChilds as $child) {

                if (IPS_LinkExists($child)) {

                    $getObjectId = IPS_GetLink($child)['TargetID'];

                    $this->easyCreateFunctionEvent($getObjectId, $this->prefix . "_onSensorActivated($this->InstanceID);");

                }

            }
        }

        // Gibt Timer zurück

        public function getTimer ($scriptId) {

            return $this->searchObjectByName("ScriptTimer", $scriptId);

        }

        // Updated alle onChange Events

        public function updateMe () {

            // $this->mergeLinksInFolder("Sensoren", "Sensoren");

            // $this->mergeLinksInFolder("Targets", "Targets");

            $this->checkDimVar();

            $this->mergeEvents();

            $this->linkTresholdSensors();

            $this->checkTreshold(); 

            if ($this->doesExist($this->searchObjectByName(""))) {

                $this->mergeLinksInFolder("SchwellwertSensoren", "Lichtsensor");

            }

        }

        // Setzt onAutomaticChange Event

        protected function setOnAutomaticChangeEvent () {

            $automatikVar = IPS_GetObject($this->searchObjectByName("Automatik"));

            if (count($automatikVar['ChildrenIDs']) == 0 && !$this->doesExist($this->searchEventByTargetId($automatikVar['ObjectID'], null))) {
                
                $this->easyCreateFunctionEvent($automatikVar['ObjectID'], "IPS_RunScript(" . $this->searchObjectByName("AutomaticChange") . ");");

            } else { 



            }

        }

        public function mergeEvents () {

            $childs = IPS_GetChildrenIDs($this->InstanceID);

            $allEventTargets = [];

            $moduleChilds = IPS_GetChildrenIDs($this->searchObjectByName("Sensoren"));

            $allTargets = [];

            foreach ($moduleChilds as $moduleLink) {

                $targetLink = IPS_GetLink($moduleLink)['TargetID'];
                $allTargets[] = $targetLink;

            }

            foreach ($childs as $child) {

                $childObj = IPS_GetObject($child);

                if ($childObj['ObjectType'] == 4) {

                    $event = IPS_GetEvent($child);

                    $allEventTargets[] = $event['TriggerVariableID'];

                    if (!in_array($event['TriggerVariableID'], $allTargets)) {

                        IPS_DeleteEvent($child);

                    }

                }
 
            }

            foreach ($moduleChilds as $child) {

                if (IPS_LinkExists($child)) {

                    $getObjectId = IPS_GetLink($child)['TargetID'];

                    if (!in_array($getObjectId, $allEventTargets)) {

                        $this->easyCreateFunctionEvent($getObjectId, "IPS_RunScriptEx(" . $this->searchObjectByName("SensorActivated") . ", array('CustomSender' => \$_IPS['SENDER']));");

                    }

                }

            }            

        }



// ------------------------------------------------------------------------------------------------------------

        //                                          //
        //                                         //
        //         Globale Funktionen             //    ==> Funktionen die öfter verwendet werden können (-> zB searchObjectByName etc.)
        //                                       //
        //                                      //

        // Setzt alle Lampen in einem Dummy Modul auf einen angegebenen Wert
        // [$folder --> (int) id, $wert --> (boolean) An oder Aus]

        public function setAllLamps ($folder, $wert) {

            $folder = IPS_GetObject($folder);

            if ($folder['HasChildren'] > 0) {

                foreach ($folder['ChildrenIDs'] as $lamp) {

                    echo $lamp;

                    if (IPS_LinkExists($lamp)) {

                        $link = IPS_GetLink($lamp);

                        $this->setLamp($link['TargetID'], $wert);

                    }

                }

            }

        }

        // Parameter: $listId --> Id des Moduls / des Ordners aus dem die Links abgeglichen werden 
        // [$listId --> (int) id, $onlyThisLinks --> ({int}array) IDs die beibehalten werden sollen]

        public function deleteUnusedLinks ($listId, $onlyThisLinks) {

            $allLinks = IPS_GetObject($listId);

            foreach ($allLinks['ChildrenIDs'] as $child) {

                $obj = IPS_GetObject($child);

                $getLink = IPS_GetLink($obj['ObjectID']);

                if (!in_array($getLink['TargetID'], $onlyThisLinks)) {

                    IPS_DeleteLink($getLink['LinkID']);

                } else {


                }

            }

        }

        public function deleteLink ($id) {

            IPS_DeleteLink($id);

        }

        // Setzt eine einzelne Lampe auf einen angegebenen Wert
        // [$lampId --> (int) Id der Lampe, $wert --> (boolean) An oder Aus]
        // Aktuell supportet: EIB-DIM, EIB-Switch, normale Variablen

        public function setLamp ($lampId, $wert) {

            $device = IPS_GetObject($lampId);

            // Wenn Variable

            if ($device['ObjectType'] == 2) {

                $parent = IPS_GetObject($device['ParentID']);

                $getVar = IPS_GetVariable($device['ObjectID']);

                if ($parent['ObjectType'] == 1) {

                    $parentInstance = IPS_GetInstance($parent['ObjectID']);

                    // EIB Support (Dim und Switch)

                    if ($parentInstance['ModuleInfo']['ModuleName'] == "EIB Group") {

                        // Wenn EIB-Switch

                        if ($getVar['VariableType'] == 0) {

                            EIB_Switch($parent['ObjectID'], $wert);

                        }

                        // Wenn EIB-Dim

                        if ($getVar['VariableType'] == 1) {

                            if ($this->doesExist($this->searchObjectByName("Wert"))) {

                                $intensity = GetValue($this->searchObjectByName("Wert"));

                                if ($wert == false) {

                                    $intensity = 0;

                                }

                                EIB_DimValue($parent['ObjectID'], $intensity);

                            }

                        }

                    }

                    // Homematic Support (Aktuell: Switch)

                    if ($parentInstance['ModuleInfo']['ModuleName'] == "HomeMatic Device") {

                        // Wenn switch

                        if ($getVar['VariableType'] == 0) {

                            HM_WriteValueBoolean($parent['ObjectID'], "STATE", $wert);

                        }

                    }

                } else {

                    SetValue($device['ObjectID'], $wert);

                }

            }

            // Wenn Instanz ausgewählt

            if ($device['ObjectType'] == 1) {

                $instance = IPS_GetInstance($device['ObjectID']);

                // Wenn EIB-Switch

                print_r($instance);

                if ($instance['ModuleInfo']['ModuleName'] == "EIB Group") {

                    EIB_Switch($instance['ObjectID'], $wert);

                }

            }

        }

        // Überprüft ob Profil vorhanden ist und erstellt dieses wenn nicht
        // [$profileName --> (String) Name des Profils, $profileType --> (int) ProfilTyp, $min --> (int) minimaler Wert, $max --> (int) maximaler Wert, $steps --> (int) Schritte {1er, 2er, 5er, ...}, $prefix --> (String) Vorzeichen {DMX1,  ABC1, ...}, $suffix --> (String) Suffix {1s, 2s, 3s, 5s, ...}, $icon --> (String) Icon]

        protected function checkProfile ($profileName, $profileType, $min, $max, $steps, $digits = 0, $prefix = "DMX", $suffix = "", $icon = "") {

            if (!IPS_VariableProfileExists($profileName)) {

                $this->createProfile($profileName, $profileType, $min, $max, $steps, $digits, $prefix, $suffix, $icon);

            }

        }

        // Funktion zum einfachen erstellen von Profilen
        // [Für Parametererklärung siehe checkProfile]

        protected function createProfile($profile, $type, $min, $max, $steps, $digits = 0, $prefix = "DMX", $suffix = "", $icon = ""){
        
            IPS_CreateVariableProfile($profile, $type);
            IPS_SetVariableProfileValues($profile, $min, $max, $steps);
            IPS_SetVariableProfileText($profile, $prefix, $suffix);
            IPS_SetVariableProfileDigits($profile, $digits);
            IPS_SetVariableProfileIcon($profile, $icon);

            if ($type == 0) {

                IPS_SetVariableProfileAssociation($profile,0,"Aus","",-1);
                IPS_SetVariableProfileAssociation($profile,1,"An","", 0x8000FF);
                IPS_SetVariableProfileIcon($profile,"Power");

            }
        
        }

        // Fügt Switchprofil zu Variable hinzu, aber nur wenn das Profil vorhanden ist
        // [$vid --> (int) Id]

        public function addSwitch ($vid) {

            if(IPS_VariableProfileExists("Switch"))
            {

                IPS_SetVariableCustomProfile($vid,"Switch");
                IPS_SetVariableCustomAction($vid, $this->searchObjectByName("SetValue"));
            
            }

        }

        // Fügt Zeitprofil zu Variable hinzu
        // [$vid --> (int) Id]

        public function addTime ($vid) {

            if (IPS_VariableProfileExists("LOM.Tl")) {

                IPS_SetVariableCustomProfile($vid, "LOM.Tl");
                IPS_SetVariableCustomAction($vid, $this->searchObjectByName("SetValue"));

            }

        }

        // Fügt Schwellwertprofil hinzu
        // [$vid --> (int) id]

        public function addThresholdSun ($vid) {

            if (IPS_VariableProfileExists("Sun")) {

                IPS_SetVariableCustomProfile($vid, "Sun");
                IPS_SetVariableCustomAction($vid, $this->searchObjectByName("SetValue"));

            }

        }

        public function addThresholdTemperature_F ($vid) {

            if (IPS_VariableProfileExists("Temperature_F")) {

                IPS_SetVariableCustomProfile($vid, "Temperature_F");
                IPS_SetVariableCustomAction($vid, $this->searchObjectByName("SetValue"));

            }

        }

        public function addThresholdTemperature_C ($vid) {

            if (IPS_VariableProfileExists("Temperature_C")) {

                IPS_SetVariableCustomProfile($vid, "Temperature_C");
                IPS_SetVariableCustomAction($vid, $this->searchObjectByName("SetValue"));

            }

        }

        public function addThresholdWattage ($vid) {

            if (IPS_VariableProfileExists("Wattage")) {

                IPS_SetVariableCustomProfile($vid, "Wattage");
                IPS_SetVariableCustomAction($vid, $this->searchObjectByName("SetValue"));

            }

        }

        // Erstellt mit einfachen Mitteln eine Variable
        // [$type]

        public function easyCreateVariable ($type = 1, $name = "Variable", $position = "", $index = 0, $defaultValue = null) {

            if ($position == "") {

                $position = $this->InstanceID;

            } 

            $newVariable = IPS_CreateVariable($type);
            IPS_SetName($newVariable, $name);
            IPS_SetParent($newVariable, $position);
            IPS_SetPosition($newVariable, $index);

            if ($defaultValue != null) {

                SetValue($newVariable, $defaultValue);

            }

            return $newVariable;

        }

        // Erstellt mit einfachen Mitteln eine Scriptdatei

        public function easyCreateScript ($name, $script, $function = true ,$parent = "") {

            if ($parent == "") {

                $parent = $this->InstanceID;

            }

            $newScript = IPS_CreateScript(0);
            IPS_SetName($newScript, $name);
            
            if ($function == true) {

                IPS_SetScriptContent($newScript, "<?php " . $script . "(" . $this->InstanceID . ");" . " ?>");

            } else {

                IPS_SetScriptContent($newScript, $script);

            }
            IPS_SetParent($newScript, $parent);
            return $newScript;

        }

        // Sucht nach Objekt im angegebenem Ordner (wenn kein Ordner angegeben wird, wird das Modul selbst verwendet)

        public function searchObjectByName ($name, $searchIn = null) {

            if ($searchIn == null) {

                $searchIn = $this->InstanceID;

            }

            $childs = IPS_GetChildrenIDs($searchIn);

            $returnId = 0;

            foreach ($childs as $child) {

                $childObject = IPS_GetObject($child);

                if ($childObject['ObjectName'] == $name) {

                    $returnId = $childObject['ObjectID'];

                }

            }

            return $returnId;

        }

        // Sucht nach Event in angegebenem Ordner (hierbei wird die TargetID angegeben, nicht der Name)

        public function searchEventByTargetId ($targetId, $searchIn) {

            if ($searchIn == null) {

                $searchIn = $this->InstanceID;

            }

            $toReturn = null;

            $obj = IPS_GetObject($searchIn);

            $children = $obj['ChildrenIDs'];

            foreach ($children as $child) {

                $ch = IPS_GetObject($child);

                if ($ch['ObjectType'] == 4) {

                    $event = IPS_GetEvent($child);

                    if ($event['TriggerVariableID'] == $targetId) {

                        $toReturn = $child;
                        
                    }

                } 

            }

            return $toReturn;

        }

        // Erstellt mit einfachen Mitteln ein "VariableOnChange" Event

        public function easyCreateFunctionEvent ($target, $func, $parent = "") {

            if ($parent == "") {

                $parent = $this->InstanceID;

            }

            $eid = IPS_CreateEvent(0);
            IPS_setEventTrigger($eid, 0, $target);
            IPS_SetParent($eid, $parent);
            IPS_SetEventScript($eid, $func);
            IPS_SetEventActive($eid, true); 

        } 

        // Gibt GUID eines Moduls zurück

        public function getModuleGuidByName ($name = "Dummy Module") {

            $allModules = IPS_GetModuleList();
            $GUID = ""; //init

            foreach ($allModules as $module) {

                if (IPS_GetModule($module)['ModuleName'] == $name) {

                    $GUID = $module;
                    break;

                }

            }

            return $GUID;

        } 


        // Prüft ob Variable bereits existiert und erstellt diese wenn nicht

        public function checkVar ($var, $type = 1, $profile = false , $position = "", $index = 0, $defaultValue = null) {

            if ($this->searchObjectByName($var) == 0) {

                $nVar = $this->easyCreateVariable($type, $var ,$position, $index, $defaultValue);

                if ($type == 0 && $profile == true) {

                    $this->addSwitch($nVar);

                }

                if ($type == 1 && $profile == true) {

                    $this->addTime($nVar);

                }

                return $nVar;

            } else {

                return $this->searchObjectByName($var);

            }

        }

        // Prüft ob Script vorhanden ist und erstellt dieses wenn nicht

        public function checkScript ($name, $script, $function = true) {

            if ($this->searchObjectByName($name) == 0) {

                
                $script = $this->easyCreateScript($name, $script, $function);
                $this->hide($script);

            }

        }

        // Versteckt Objekt

        public function hide ($id) {

            IPS_SetHidden($id, true);

        }

        //Zeigt Objekt

        public function show ($id) {

            IPS_SetHidden($id, false);

        }


        // Prüft ob Objekt existiert (Return: Wenn ja --> true; nein --> false)

        public function doesExist ($id) {

            if ($id != null) {

                $obj = IPS_GetObject($id);

                if ($obj != null) {

                    return true;

                } else {

                    return false;

                }

            } else {

                return false;

            }

        }

        // Verlinkt Variable in angegebenem Ordner / Objekt

        public function linkVar ($targetId, $parent = "", $name = "Unnamed Link") {

            if ($parent == "") {

                $parent = $this->InstanceID;

            }

            $LinkID = IPS_CreateLink();
            IPS_SetName($LinkID, $name);
            IPS_SetParent($LinkID, $parent);
            IPS_SetLinkTargetID($LinkID, $targetId);

        }

        // Gleicht Links von Parameter1 den Links von Parameter2 an
        // UNUSED

        public function mergeLinksInFolder ($name, $pname) {

            $obj = IPS_GetObject($this->searchObjectByName($name));

            $dontLink = array();

            foreach ($obj['ChildrenIDs'] as $child) {
            
                $childObj = IPS_GetLink($child);

                if (in_array($childObj['TargetID'], $this->getPropertyList($pname))) {

                    $dontLink[] = $childObj['TargetID'];

                }

            }

            foreach ($this->getPropertyList($pname) as $link) {

                if (!in_array($link, $dontLink)) {

                    $oj = IPS_GetObject($link);

                    $this->linkVar($link, $this->searchObjectByName($name), $oj['ObjectName']);

                }

            }

            $this->deleteUnusedLinks($this->searchObjectByName($name, $this->InstanceID), $this->getPropertyList($pname));

        }

        // Prüft ob Dummy Modul (Vereinfacht: Ordner) existiert und erstellt diesen 

        public function checkFolder ($name) {

            if ($this->searchObjectByName($name) == 0) {

                $targets = $this->createFolder($name);
                $this->hide($targets);

            }

        }

        // Erstellt Dummy Modul (Vereinfacht: Ordner)

        public function createFolder ($name) {

            $units = IPS_CreateInstance($this->getModuleGuidByName());
            IPS_SetName($units, $name);
            IPS_SetParent($units, $this->InstanceID);

            return $units;

        }

        // Löscht Dummy Modul 

        public function deleteFolder ($name) {

            $ob = IPS_GetObject($this->searchObjectByName($name));
            IPS_DeleteInstance($ob['ObjectID']);

        }

// ------------------------------------------------------------------------------------------------------------

        //                  //
        //  Set Funktionen  //     // ==> Funktionen die gesetzt werden / bei bestimmten Events ausgeführt werden
        //                  //

        // Standard SetValue Script

        public function setValue () {

            SetValue($IPS_VARIABLE, $IPS_VALUE);

        }

        public function deleteInstance ($id) {

            if ($this->doesExist($id)) {

                $obj = IPS_GetObject($id);

                if ($obj['HasChildren'] == true) {

                    $children = $obj['ChildrenIDs'];

                    foreach ($children as $child) {

                        $lnk = IPS_GetObject($child);

                        if($lnk['ObjectType'] == 6) {

                            IPS_DeleteLink($lnk['ObjectID']);

                        }

                    }

                }

                IPS_DeleteInstance($id);

            }

        }
 
        // Wenn einer der Sensoren aktiviert wurde

        public function onSensorActivated () {

            $automatik = GetValue($this->searchObjectByName("Automatik"));
            $sperre = GetValue($this->searchObjectByName("Sperre"));
            $status = GetValue($this->searchObjectByName("Status"));
            $schwellwertOk = $this->checkTresholdOk();

            //if ($automatik == true) {

                if ($_IPS['SENDER'] == "TimerEvent" && $sperre == false) {

                    // Wenn Event von Timer ausgelöst wurde ( -> Lampen alle aus)

                    echo "Licht aus";

                    $this->setAllLamps($this->searchObjectByName("Targets"), false);
                    
                    SetValue($this->searchObjectByName("Status"), false);

                    IPS_SetEventActive($this->getTimer($this->searchObjectByName("SensorActivated")), false);

                    if ($this->doesExist($this->searchObjectByName("Timer"))) {

                        $this->deleteLink($this->searchObjectByName("Timer"));

                    }

                } else if ($this->isOneTrue() && $sperre == false && $automatik == true) {


                    if ($schwellwertOk == true) {

                        echo "Licht an";

                        SetValue($this->searchObjectByName("Status"), true);

                        $this->setAllLamps($this->searchObjectByName("Targets"), true);

                        $timerLength = GetValue($this->searchObjectByName("Nachlauf"));

                        $timerLength = $timerLength;

                        IPS_SetScriptTimer($this->searchObjectByName("SensorActivated"), $timerLength);
                        IPS_SetScriptTimer($this->searchObjectByName("SubTimer"), 10);

                        IPS_SetEventActive($this->getTimer($this->searchObjectByName("SensorActivated")), true);

                        if (!$this->doesExist($this->searchObjectByName("Timer"))) {

                            $timerLink = IPS_CreateLink();
                            IPS_SetName($timerLink, "Timer"); 
                            IPS_SetParent($timerLink, $this->InstanceID); 
                            IPS_SetLinkTargetID($timerLink, $this->searchObjectByName("ScriptTimer" ,$this->searchObjectByName("SensorActivated")));   
                            IPS_SetPosition($timerLink, 99);

                        } else {

                            $this->show($this->searchObjectByName("Timer"));

                        }

                    }


                } else {

                    echo "Sperre aktiv";

                }

            /*} else { 

                echo "Automatik deaktiviert";

            } */
        }

        public function isOneTrue () {

            $folder = IPS_GetObject($this->searchObjectByName("Sensoren"));

            $isTrue = false;

            foreach ($folder['ChildrenIDs'] as $lamp) {

                $link = IPS_GetLink($lamp);

                $val = GetValue($link['TargetID']);

                if ($val == true) {

                    $isTrue = true;

                }

            }

            return $isTrue;

        }

        // Wenn etwas an der Automatikvariable verändert wurde

        public function onAutomaticChange () {

            // $automatik = GetValue($this->searchObjectByName("Automatik"));
            // $sperre = GetValue($this->searchObjectByName("Sperre"));

            // if ($automatik == false && $sperre == false) {
   


            //     //if ($this->doesExist($this->searchObjectByName("Status"))) {

            //         SetValue($this->searchObjectByName("Status"), false);

            //     //}

            //     $this->setAllLamps($this->searchObjectByName("Targets"), false);

            //     $timerLength = GetValue($this->searchObjectByName("Nachlauf"));

            //     IPS_SetScriptTimer($this->searchObjectByName("SensorActivated"), $timerLength);
            //     IPS_SetEventActive($this->getTimer($this->searchObjectByName("SensorActivated")), false);


            //     $this->deleteLink($this->searchObjectByName("Timer"));

            // } 

        }

        public function subTimer () {

            $schwellwertOk = $this->checkTresholdOk();
            //$actualTimer = IPS_GetScriptTimer($this->getTimer($this->searchObjectByName("SensorActivated")));
            $timerLength = GetValue($this->searchObjectByName("Nachlauf"));

            if ($_IPS['SENDER'] == "TimerEvent") {

                if ($this->isOneTrue() && $sperre == false) {

                    IPS_SetScriptTimer($this->searchObjectByName("SensorActivated"), $timerLength);

                } else {

                    IPS_SetScriptTimer($this->searchObjectByName("SubTimer"), 0);

                }

            }

        }

        public function checkTresholdOk () {

            if ($this->doesExist($this->searchObjectByName("Schwellwert"))) {

                $treshold = GetValue($this->searchObjectByName("Schwellwert"));

                $lightSensors = IPS_GetObject($this->searchObjectByName("SchwellwertSensoren"));

                $childs = $lightSensors['ChildrenIDs'];

                $isOk = false;

                foreach ($childs as $childId) {

                    $child = IPS_GetLink($childId);

                    $childVal = GetValue($child['TargetID']);


                    if ($childVal <= $treshold) {

                        $isOk = true;

                    }

                }

                return $isOk;

            } else {

                return true;

            }

        } 

        public function resetProfiles () {

            $this->deleteProfile("LOM.Tl");
            $this->deleteProfile("LOM.Wert.100");
            $this->deleteProfile("Sun");
            $this->deleteProfile("Temperature_C");
            $this->deleteProfile("Temperature_F");

        }

        public function deleteProfile ($name) {

            if (IPS_VariableProfileExists($name)) {

                IPS_DeleteVariableProfile($name);

            }

        }


    }
?>