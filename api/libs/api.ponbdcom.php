<?php

class PONBdcom extends PONProto {

    /**
     * Receives, preprocess and stores all required data from BDCOM 36xx/33xx or Eltex OLT device
     * 
     * @param int $oltModelId
     * @param int $oltid
     * @param string $oltIp
     * @param string $oltCommunity
     * @param bool $oltNoFDBQ
     * 
     * @return void
     */
    public function collect($oltModelId, $oltid, $oltIp, $oltCommunity, $oltNoFDBQ) {
        $sigIndexOID = $this->snmpTemplates[$oltModelId]['signal']['SIGINDEX'];
        $sigIndex = $this->snmp->walk($oltIp . ':' . self::SNMPPORT, $oltCommunity, $sigIndexOID, self::SNMPCACHE);
        $sigIndex = str_replace($sigIndexOID . '.', '', $sigIndex);
        $sigIndex = str_replace($this->snmpTemplates[$oltModelId]['signal']['SIGVALUE'], '', $sigIndex);
        $sigIndex = explodeRows($sigIndex);
        $ifaceCustDescrIndex = array();
//ONU distance polling for bdcom devices
        if (isset($this->snmpTemplates[$oltModelId]['misc'])) {
            if (isset($this->snmpTemplates[$oltModelId]['misc']['DISTINDEX'])) {
                if (!empty($this->snmpTemplates[$oltModelId]['misc']['DISTINDEX'])) {
                    $distIndexOid = $this->snmpTemplates[$oltModelId]['misc']['DISTINDEX'];
                    $distIndex = $this->snmp->walk($oltIp . ':' . self::SNMPPORT, $oltCommunity, $distIndexOid, self::SNMPCACHE);
                    $distIndex = str_replace($distIndexOid . '.', '', $distIndex);
                    $distIndex = str_replace($this->snmpTemplates[$oltModelId]['misc']['DISTVALUE'], '', $distIndex);
                    $distIndex = explodeRows($distIndex);

                    $onuIndexOid = $this->snmpTemplates[$oltModelId]['misc']['ONUINDEX'];
                    $onuIndex = $this->snmp->walk($oltIp . ':' . self::SNMPPORT, $oltCommunity, $onuIndexOid, self::SNMPCACHE);
                    $onuIndex = str_replace($onuIndexOid . '.', '', $onuIndex);
                    $onuIndex = str_replace($this->snmpTemplates[$oltModelId]['misc']['ONUVALUE'], '', $onuIndex);
                    $onuIndex = explodeRows($onuIndex);

                    if (isset($this->snmpTemplates[$oltModelId]['misc']['DEREGREASON'])) {
                        $deregIndexOid = $this->snmpTemplates[$oltModelId]['misc']['DEREGREASON'];
                        $deregIndex = $this->snmp->walk($oltIp . ':' . self::SNMPPORT, $oltCommunity, $deregIndexOid, self::SNMPCACHE);
                        $deregIndex = str_replace($deregIndexOid . '.', '', $deregIndex);
                        $deregIndex = str_replace($this->snmpTemplates[$oltModelId]['misc']['DEREGVALUE'], '', $deregIndex);
                        $deregIndex = explodeRows($deregIndex);
                    }

                    $intIndexOid = $this->snmpTemplates[$oltModelId]['misc']['INTERFACEINDEX'];
                    $intIndex = $this->snmp->walk($oltIp . ':' . self::SNMPPORT, $oltCommunity, $intIndexOid, self::SNMPCACHE);
                    $intIndex = str_replace($intIndexOid . '.', '', $intIndex);
                    $intIndex = str_replace($this->snmpTemplates[$oltModelId]['misc']['INTERFACEVALUE'], '', $intIndex);
                    $intIndex = explodeRows($intIndex);

                    if (!$oltNoFDBQ) {
                        $FDBIndexOid = $this->snmpTemplates[$oltModelId]['misc']['FDBINDEX'];
                        $FDBIndex = $this->snmp->walk($oltIp . ':' . self::SNMPPORT, $oltCommunity, $FDBIndexOid, self::SNMPCACHE);
                        $FDBIndex = str_replace($FDBIndexOid . '.', '', $FDBIndex);
                        $FDBIndex = explodeRows($FDBIndex);
                    }
                }
            }

            if (isset($this->snmpTemplates[$oltModelId]['misc']['IFACECUSTOMDESCR'])) {
                $ifaceCustDescrIndexOID = $this->snmpTemplates[$oltModelId]['misc']['IFACECUSTOMDESCR'];
                $ifaceCustDescrIndex = $this->snmp->walk($oltIp . ':' . self::SNMPPORT, $oltCommunity, $ifaceCustDescrIndexOID, self::SNMPCACHE);
                $ifaceCustDescrIndex = str_replace($ifaceCustDescrIndexOID . '.', '', $ifaceCustDescrIndex);
                $ifaceCustDescrIndex = str_replace(array($this->snmpTemplates[$oltModelId]['misc']['INTERFACEVALUE'], '"'), '', $ifaceCustDescrIndex);
                $ifaceCustDescrIndex = explodeRows($ifaceCustDescrIndex);
            }
        }
//getting other system data from OLT
        if (isset($this->snmpTemplates[$oltModelId]['system'])) {
            //OLT uptime
            if (isset($this->snmpTemplates[$oltModelId]['system']['UPTIME'])) {
                $uptimeIndexOid = $this->snmpTemplates[$oltModelId]['system']['UPTIME'];
                $oltSystemUptimeRaw = $this->snmp->walk($oltIp . ':' . self::SNMPPORT, $oltCommunity, $uptimeIndexOid, self::SNMPCACHE);
                $this->uptimeParse($oltid, $oltSystemUptimeRaw);
            }

            //OLT temperature
            if (isset($this->snmpTemplates[$oltModelId]['system']['TEMPERATURE'])) {
                $temperatureIndexOid = $this->snmpTemplates[$oltModelId]['system']['TEMPERATURE'];
                $oltTemperatureRaw = $this->snmp->walk($oltIp . ':' . self::SNMPPORT, $oltCommunity, $temperatureIndexOid, self::SNMPCACHE);
                $this->temperatureParse($oltid, $oltTemperatureRaw);
            }
        }
//getting MAC index.
        $macIndexOID = $this->snmpTemplates[$oltModelId]['signal']['MACINDEX'];
        $macIndex = $this->snmp->walk($oltIp . ':' . self::SNMPPORT, $oltCommunity, $macIndexOID, self::SNMPCACHE);
        $macIndex = str_replace($macIndexOID . '.', '', $macIndex);
        $macIndex = str_replace($this->snmpTemplates[$oltModelId]['signal']['MACVALUE'], '', $macIndex);
        $macIndex = explodeRows($macIndex);
        $this->signalParse($oltid, $sigIndex, $macIndex, $this->snmpTemplates[$oltModelId]['signal']);
        /**
         * This is here because BDCOM is BDCOM and another SNMP queries cant be processed after MACINDEX query in some cases. 
         */
        if (isset($this->snmpTemplates[$oltModelId]['misc'])) {
            if (isset($this->snmpTemplates[$oltModelId]['misc']['DISTINDEX'])) {
                if (!empty($this->snmpTemplates[$oltModelId]['misc']['DISTINDEX'])) {
// processing distance data
                    $this->distanceParse($oltid, $distIndex, $onuIndex);
//processing interfaces data and interface description data
                    $this->interfaceParseBd($oltid, $intIndex, $macIndex, $ifaceCustDescrIndex);
//processing FDB data
                    if (!$oltNoFDBQ) {
                        if (isset($this->snmpTemplates[$oltModelId]['misc']['FDBMODE']) and $this->snmpTemplates[$oltModelId]['misc']['FDBMODE'] == 'FIRMWARE-F') {
                            $this->FDBParseBdFirmwareF($oltid, $FDBIndex, $macIndex, $oltModelId);
                        } else {
                            $this->FDBParseBd($oltid, $FDBIndex, $macIndex, $oltModelId);
                        }
                    }
//processing last dereg reason data                                        
                    if (isset($this->snmpTemplates[$oltModelId]['misc']['DEREGREASON'])) {
                        $this->lastDeregParseBd($oltid, $deregIndex, $onuIndex);
                    }
                }
            }
        }
    }

    /**
     * Parses & stores in cache OLT ONU interfaces
     *
     * @param int $oltid
     * @param array $intIndex
     * @param array $macIndex
     * @param array $ifaceCustDescrRaw
     *
     * @return void
     */
    protected function interfaceParseBd($oltid, $intIndex, $macIndex, $ifaceCustDescrRaw = array()) {
        $oltid = vf($oltid, 3);
        $intTmp = array();
        $macTmp = array();
        $result = array();
        $processIfaceCustDescr = !empty($ifaceCustDescrRaw);
        $ifaceCustDescrIdx = array();
        $ifaceCustDescrArr = array();

// olt iface descr extraction
        if ($processIfaceCustDescr) {
            foreach ($ifaceCustDescrRaw as $io => $each) {
                if (empty($each)) {
                    continue;
                }

                $ifDescr = explode('=', str_replace(array(" ", "\t", "\n", "\r", "\0", "\x0B"), '', $each));

                if ((empty($ifDescr[0]) && empty($ifDescr[1])) || intval($ifDescr[0]) < 7) {
                    continue;
                }
                if ($ifDescr[0] > 10) {
                    break;
                }

                $ifaceCustDescrIdx[$ifDescr[0] - 6] = $ifDescr[1];
            }
        }

//interface index preprocessing
        if ((!empty($intIndex)) and ( !empty($macIndex))) {
            foreach ($intIndex as $io => $eachint) {
                $line = explode('=', $eachint);
//interface is present
                if (isset($line[1])) {
                    $interfaceRaw = trim($line[1]); // interface
                    $devIndex = trim($line[0]); // device index
                    $intTmp[$devIndex] = $interfaceRaw;
                }
            }

//mac index preprocessing
            foreach ($macIndex as $io => $eachmac) {
                $line = explode('=', $eachmac);
//mac is present
                if (isset($line[1])) {
                    $macRaw = trim($line[1]); //mac address
                    $devIndex = trim($line[0]); //device index
                    $macRaw = str_replace(' ', ':', $macRaw);
                    $macRaw = strtolower($macRaw);
                    $macTmp[$devIndex] = $macRaw;
                }
            }

//storing results
            if (!empty($macTmp)) {
                foreach ($macTmp as $devId => $eachMac) {
                    if (isset($intTmp[$devId])) {
                        $interface = $intTmp[$devId];
                        $result[$eachMac] = $interface;
                        $cleanIface = strstr($interface, ':', true);
                        $tPONIfaceNum = substr($cleanIface, -1, 1);

                        if ($processIfaceCustDescr && !isset($ifaceCustDescrArr[$cleanIface]) && array_key_exists($tPONIfaceNum, $ifaceCustDescrIdx)) {
                            $ifaceCustDescrArr[$cleanIface] = $ifaceCustDescrIdx[$tPONIfaceNum];
                        }
                    }
                }

                $result = serialize($result);
                $ifaceCustDescrArr = serialize($ifaceCustDescrArr);
                file_put_contents(self::INTCACHE_PATH . $oltid . '_' . self::INTCACHE_EXT, $result);
                file_put_contents(self::INTCACHE_PATH . $oltid . '_' . self::INTDESCRCACHE_EXT, $ifaceCustDescrArr);
            }
        }
    }

    /**
     * Parses & stores in cache OLT ONU interfaces
     *
     * @param int $oltid
     * @param array $FDBIndex
     * @param array $macIndex
     * @param array $oltModelId
     *
     * @return void
     */
    protected function FDBParseBd($oltid, $FDBIndex, $macIndex, $oltModelId) {
        $oltid = vf($oltid, 3);
        $FDBTmp = array();
        $macTmp = array();
        $result = array();

//fdb index preprocessing
        if ((!empty($FDBIndex)) and ( !empty($macIndex))) {
            foreach ($FDBIndex as $io => $eachfdb) {
                if (preg_match('/' . $this->snmpTemplates[$oltModelId]['misc']['FDBVALUE'] . '/', $eachfdb)) {
                    $eachfdb = str_replace($this->snmpTemplates[$oltModelId]['misc']['FDBVALUE'], '', $eachfdb);
                    $line = explode('=', $eachfdb);
//fdb is present
                    if (isset($line[1])) {
                        $FDBRaw = trim($line[1]); // FDB
                        $devOID = trim($line[0]); // FDB last OID
                        $devline = explode('.', $devOID);
                        $devIndex = trim($devline[0]); // FDB index
                        $FDBvlan = trim($devline[1]); // Vlan
                        $FDBnum = trim($devline[7]); // Count number of MAC

                        $FDBRaw = str_replace(' ', ':', $FDBRaw);
                        $FDBRaw = strtolower($FDBRaw);

                        $FDBTmp[$devIndex][$FDBnum]['mac'] = $FDBRaw;
                        $FDBTmp[$devIndex][$FDBnum]['vlan'] = $FDBvlan;
                    }
                }
            }

//mac index preprocessing
            foreach ($macIndex as $io => $eachmac) {
                $line = explode('=', $eachmac);
//mac is present
                if (isset($line[1])) {
                    $macRaw = trim($line[1]); //mac address
                    $devIndex = trim($line[0]); //device index
                    $macRaw = str_replace(' ', ':', $macRaw);
                    $macRaw = strtolower($macRaw);
                    $macTmp[$devIndex] = $macRaw;
                }
            }

//storing results
            if (!empty($macTmp)) {
                foreach ($macTmp as $devId => $eachMac) {
                    if (isset($FDBTmp[$devId])) {
                        $fdb = $FDBTmp[$devId];
                        $result[$eachMac] = $fdb;
                    }
                }
                $result = serialize($result);
                file_put_contents(self::FDBCACHE_PATH . $oltid . '_' . self::FDBCACHE_EXT, $result);
            }
        }
    }

    /**
     * Parses & stores in cache OLT FDB
     *
     * @param int $oltid
     * @param array $FDBIndex
     * @param array $macIndex
     * @param array $oltModelId
     *
     * @return void
     */
    protected function FDBParseBdFirmwareF($oltid, $FDBIndex, $macIndex, $oltModelId) {
        $oltid = vf($oltid, 3);
        $TmpArr = array();
        $FDBTmp = array();
        $macTmp = array();
        $result = array();

//fdb index preprocessing
        if ((!empty($FDBIndex)) and ( !empty($macIndex))) {
            foreach ($FDBIndex as $io => $eachfdbRaw) {
                if (preg_match('/' . $this->snmpTemplates[$oltModelId]['misc']['FDBVALUE'] . '|INTEGER:/', $eachfdbRaw)) {
                    $eachfdbRaw = str_replace(array($this->snmpTemplates[$oltModelId]['misc']['FDBVALUE'], 'INTEGER:'), '', $eachfdbRaw);
                    $line = explode('=', $eachfdbRaw);
//fdb is present
                    if (isset($line[1])) {
                        $devOID = trim($line[0]); // FDB last OID
                        $lineRaw = trim($line[1]); // FDB
                        $devline = explode('.', $devOID);
                        $FDBvlan = trim($devline[1]); // Vlan
                        $FDBnum = trim($devline[7]); // Count number of MAC
                        if (preg_match('/^1/', $devOID)) {
                            $FDBRaw = str_replace(' ', ':', $lineRaw);
                            $FDBRaw = strtolower($FDBRaw);
                            $TmpArr[$devOID]['mac'] = $FDBRaw;
                            $TmpArr[$devOID]['vlan'] = $FDBvlan;
                            $TmpArr[$devOID]['FDBnum'] = $FDBnum;
                        } elseif (preg_match('/^2/', $devOID)) {
                            $devIndexOid = substr_replace($devOID, '1', 0, 1);
                            $TmpArr[$devIndexOid]['index'] = $lineRaw;
                        } else {
                            continue;
                        }
                    }
                }
            }
            if (!empty($TmpArr)) {
                // Crete tmp Ubilling PON FDB array
                foreach ($TmpArr as $io => $each) {
                    if (count($each) == 4) {
                        $FDBTmp[$each['index']][$each['FDBnum']]['mac'] = $each['mac'];
                        $FDBTmp[$each['index']][$each['FDBnum']]['vlan'] = $each['vlan'];
                    }
                }
            }
//mac index preprocessing
            foreach ($macIndex as $io => $eachmac) {
                $line = explode('=', $eachmac);
//mac is present
                if (isset($line[1])) {
                    $macRaw = trim($line[1]); //mac address
                    $devIndex = trim($line[0]); //device index
                    $macRaw = str_replace(' ', ':', $macRaw);
                    $macRaw = strtolower($macRaw);
                    $macTmp[$devIndex] = $macRaw;
                }
            }
//storing results
            if (!empty($macTmp)) {
                foreach ($macTmp as $devId => $eachMac) {
                    if (isset($FDBTmp[$devId])) {
                        $fdb = $FDBTmp[$devId];
                        $result[$eachMac] = $fdb;
                    }
                }

                $result = serialize($result);
                file_put_contents(self::FDBCACHE_PATH . $oltid . '_' . self::FDBCACHE_EXT, $result);
            }
        }
    }

    /**
     * Parses & stores in cache OLT ONU dereg reaesons
     *
     * @param int $oltid
     * @param array $distIndex
     * @param array $onuIndex
     *
     * @return void
     */
    protected function lastDeregParseBd($oltid, $deregIndex, $onuIndex) {
        $oltid = vf($oltid, 3);
        $deregTmp = array();
        $onuTmp = array();
        $result = array();
        $curDate = curdatetime();

//dereg index preprocessing
        if ((!empty($deregIndex)) and ( !empty($onuIndex))) {
            foreach ($deregIndex as $io => $eachdereg) {
                $line = explode('=', $eachdereg);

//dereg is present
                if (isset($line[1])) {
                    $deregRaw = trim($line[1]); // dereg
                    $devIndex = trim($line[0]); // device index

                    switch ($deregRaw) {
                        case 2:
                            $TxtColor = '"#00B20E"';
                            $tmpONULastDeregReasonStr = 'Normal';
                            break;

                        case 3:
                            $TxtColor = '"#F80000"';
                            $tmpONULastDeregReasonStr = 'MPCP down';
                            break;

                        case 4:
                            $TxtColor = '"#F80000"';
                            $tmpONULastDeregReasonStr = 'OAM down';
                            break;

                        case 5:
                            $TxtColor = '"#6500FF"';
                            $tmpONULastDeregReasonStr = 'Firmware download';
                            break;

                        case 6:
                            $TxtColor = '"#F80000"';
                            $tmpONULastDeregReasonStr = 'Illegal MAC';
                            break;

                        case 7:
                            $TxtColor = '"#FF4400"';
                            $tmpONULastDeregReasonStr = 'LLID admin down';
                            break;

                        case 8:
                            $TxtColor = '"#F80000"';
                            $tmpONULastDeregReasonStr = 'Wire down';
                            break;

                        case 9:
                            $TxtColor = '"#6500FF"';
                            $tmpONULastDeregReasonStr = 'Power off';
                            break;

                        default:
                            $TxtColor = '"#000000"';
                            $tmpONULastDeregReasonStr = 'Unknown';
                            break;
                    }

                    $tmpONULastDeregReasonStr = wf_tag('font', false, '', 'color=' . $TxtColor . '') .
                            $tmpONULastDeregReasonStr .
                            wf_tag('font', true);

                    $deregTmp[$devIndex] = $tmpONULastDeregReasonStr;
                }
            }

//mac index preprocessing
            foreach ($onuIndex as $io => $eachmac) {
                $line = explode('=', $eachmac);
//mac is present
                if (isset($line[1])) {
                    $macRaw = trim($line[1]); //mac address
                    $devIndex = trim($line[0]); //device index
                    $macRaw = str_replace(' ', ':', $macRaw);
                    $macRaw = strtolower($macRaw);
                    $onuTmp[$devIndex] = $macRaw;
                }
            }

//storing results
            if (!empty($onuTmp)) {
                foreach ($onuTmp as $devId => $eachMac) {
                    if (isset($deregTmp[$devId])) {
                        $lastDereg = $deregTmp[$devId];
                        $result[$eachMac] = $lastDereg;
                    }
                }

                $result = serialize($result);
                file_put_contents(self::DEREGCACHE_PATH . $oltid . '_' . self::DEREGCACHE_EXT, $result);
            }
        }
    }

}
