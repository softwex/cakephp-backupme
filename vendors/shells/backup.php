<?php
/*
 * CakePHP shell application to create SQL databases dump backups
 * Copyright (c) 2013 Mohammed Mahgoub
 * www.mmahgoub.com
 * https://bitbucket.org/mmahgoub/cakephp-backupme
 *
 * @author      Mohammed Mahgoub <mmahgoub@mmahgoub.com>
 * @license     MIT
 *
 */

/**
 * BackupShell class
 *
 * @uses          Shell
 * @package       cakephp-backupme
 * @subpackage    cakephp-backupme.vendors.shells
 */
class BackupShell extends Shell {
    
    var $tasks = array('ProgressBar');

    public function main() {
        //database configuration, default is "default"
        if(!isset($this->args[0])){
            $this->args[0] = 'default';
        }
        
        //rows per query (less rows = less ram usage but more running time), default is 0 which means all rows
        if(!isset($this->args[1])){
            $this->args[1] = 0;
        }
        
        //directory to save your backup, it will be created automatically if not found., default is webroot/db-backups/yyyy-mm-dd
        if(!isset($this->args[2])){
            $this->args[2] = 'webroot/db-backups/'.date('Y-m-d',time());
        }

        App::import('Core', 'ConnectionManager');
        $db = ConnectionManager::getDataSource($this->args[0]);
        $backupdir = $this->args[2];
        $seleced_entities = '*';
        //$seleced_entities = array('users', 'users_view');
        $key = 0;
        if ($seleced_entities == '*') {
            //$sources = $db->query("show full tables where Table_Type = 'BASE TABLE'", false);
            $sources = $db->query("show full tables", false);

            foreach($sources as $entity){
                $entity = array_shift($entity);
                $entities[$key]['type'] = $entity['Table_type'];
                $entities[$key]['name'] = array_shift($entity);
                $key++;
            }
        } else {
            //$seleced_entities = is_array($seleced_entities) ? $seleced_entities : explode(',', $seleced_entities);
            foreach($seleced_entities as $entity){
                
                $info =$db->query("SHOW TABLE STATUS WHERE Name = '".$entity."'", false);
                $info = array_shift(array_shift($info));
                if(isset($info['Engine'])){
                     $entities[$key]['type'] = 'BASE TABLE';
                }else{
                    $entities[$key]['type'] = $info['Comment'];
                }
                $entities[$key]['name'] = $entity;
                $key++;
            }
        }
        
        $filename = 'db-backup-' . date('Y-m-d-H-i-s',time()) .'_' . (md5(time())) . '.sql';
        
        $return = '';
        $limit = $this->args[1];
        $start = 0;
        
        if(!is_dir($backupdir)) {
            $this->out(' ', 1);
            $this->out('Will create "'.$backupdir.'" directory!', 2);
            if(mkdir($backupdir,0755,true)){
                $this->out('Directory created!', 2);
            }else{
                $this->out('Failed to create destination directory! Can not proceed with the backup!', 2);
                die;
            }
        }
            
        if ($this->__isDbConnected($this->args[0])) {
            
            $this->out('---------------------------------------------------------------');
            $this->out(' Starting Backup..');
            $this->out('---------------------------------------------------------------');

            foreach ($entities as $entity) {
                $this->out(" ",2);
                $this->out($entity['name']);
                $handle = fopen($backupdir.'/'.$filename, 'a+');
                
                if($entity['type']=='BASE TABLE'){
    
                    $return= 'DROP TABLE IF EXISTS `' . $entity['name'] . '`;';
                    $row2 = $db->query('SHOW CREATE TABLE ' . $entity['name'].';');
                    $return.= "\n\n" . $row2[0][0]['Create Table'] . ";\n\n";
                    fwrite($handle, $return);

                    for(;;){
                        if($limit == 0){
                            $limitation = '';
                        }else{
                            $limitation = ' Limit '.$start.', '.$limit;
                        }

                        $result = $db->query('SELECT * FROM ' . $entity['name'].$limitation.';', false);
                        $num_fields = count($result);
                        $this->ProgressBar->start($num_fields);

                        if($num_fields == 0){
                            $start = 0;
                            break;
                        }
                        foreach ($result as $row) {
                            $this->ProgressBar->next();
                            $return2 = 'INSERT INTO ' . $entity['name'] . ' VALUES(';
                            $j = 0;
                            foreach ($row[$entity['name']] as $key => $inner) {
                                $j++;
                                if(isset($inner)){
                                    if ($inner == NULL){
                                        $return2 .= 'NULL';
                                    }else{
                                        $inner = addslashes($inner);
                                        $inner = ereg_replace("\n", "\\n", $inner);
                                        $return2.= '"' . $inner . '"';
                                    }
                                }else {
                                    $return2.= '""';
                                }

                                if ($j < (count($row[$entity['name']]))) {
                                    $return2.= ',';
                                }
                            }
                            $return2.= ");\n";
                            fwrite($handle, $return2);

                        }
                        $start+=$limit;
                        if($limit == 0){
                            break;
                        }
                    }

                    $return.="\n\n\n";
                }elseif($entity['type']=='VIEW') {
                    
                    $return= 'DROP VIEW IF EXISTS `' . $entity['name'] . '`;';
                    $row2 = $db->query('SHOW CREATE VIEW ' . $entity['name'].';');
                    
                    $return.= "\n\n" . $row2[0][0]['Create View'] . ";\n\n";
                    $this->ProgressBar->start(1);
                    if(fwrite($handle, $return)){
                        $this->ProgressBar->set(1); 
                    }
                    //debug($row2); die();
                }
                fclose($handle);
            }

            $this->out(" ",2);
            $this->out('---------------------------------------------------------------');
            $this->out(' Yay! Backup Completed!');
            $this->out('---------------------------------------------------------------');
            
        }else{
            $this->out(' ', 2);
            $this->out('Error! Can\'t connect to "'.$this->args[0].'" database!', 2);
        }
    }

    function __isDbConnected($db = NULL) {
        $datasource = ConnectionManager::getDataSource($db);
        return $datasource->isConnected();
    }

}
?>