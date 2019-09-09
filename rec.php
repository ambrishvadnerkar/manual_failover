#!/usr/bin/php
<?php

$servers=array (array("server" => "server1", "ip" => "192.168.10.1", "network"=>"ifcfg-eth0:2"),
        array("server" => "server2", "ip" => "192.168.10.2", "network"=>"ifcfg-bond3"),
        array("server" => "localhost", "ip" => "127.0.0.1", "network"=>"ifcfg-bond5") 
		);

$backup_dir = '/opt/backups/recovery-script/';
function get_server_ips()
{
    $addr_line = shell_exec( 'ip addr | grep \'inet \'');

    $server_ips = array();
    foreach(preg_split("/((\r?\n)|(\r\n?))/", $addr_line) as $line){
        $ln =  substr(trim($line),strpos(trim($line)," "), strpos(trim($line),"/")-strpos(trim($line)," "));
        if(trim($ln) != '')
            $server_ips [] =  trim($ln); 
    } 

    return  $server_ips;
}

function chk_ip($serverlst)
{
    $srv =  get_server_ips();
    $chk_ip = array();   
    if (count($srv) > 0  && count($serverlst)>0)
    {
        foreach ($serverlst as $s)
        {
            if(!in_array($s["ip"],$srv))
                array_push($chk_ip,array("server" => $s["server"], "ip"=>$s["ip"], "network"=>$s["network"]));
        }
    }
    else
        $chk_ip = array();

    return $chk_ip;
}

function chk_ping($serverlst)
{

$srv = array();
    foreach ($serverlst as $lst)
    {
        if(!check_server_status($lst["ip"], "80", "12"))
        {
            $str = exec("ping -c 1 ".$lst["ip"],$result);

            if ($str == "")
            {
                array_push($srv,array("server" => $lst["server"], "ip"=>$lst["ip"], "network"=>$lst["network"]));
            }else{
                send_update_email($lst["server"]." (".$lst["ip"].") Apache server is Down....", $lst["server"]." (".$lst["ip"].") Apache server is Down...");
                
            }
        }
    }
return $srv;
}

function check_server_status($host, $port, $timeout) { 
  $start_time = microtime(TRUE); 
  $status = @fsockopen($host, $port, $errno, $errstr, $timeout); 
  if (!$status) { 
      return false; 
  } 
   
  $end_time = microtime(TRUE);
  $time_taken = $end_time - $start_time;
  $time_taken = round($time_taken,5);
   
     return true; 
}
function switch_server($srv,$dir)
{
    $server_switched = false;
    if(isset($srv) && is_array($srv) && count($srv) > 0 )
    {
        echo "\n\nYour server may be down....\n\n";

        $i=1;
        foreach($srv as $srvlst)
        {
        
            $recNetworkFile = $dir.$srvlst['server']."/".$srvlst['network'];
            $recNetworkFile_nm = "/etc/sysconfig/network-scripts/";
           
            $recProjectFile = $dir.$srvlst['server']."/home/*";
            $recProjectFile_nm = "/home/";
            
            $recHttpFile = $dir.$srvlst['server']."/httpd/*";
            $recHttpFile_nm = "/etc/httpd/conf.d/";
            
            $recMysqlFile = $dir.$srvlst['server']."/mysql/fullschema/";
            $recMysqlDBFile = $dir.$srvlst['server']."/mysql/daily/mysql/";
            $recMysqlFile_nm = "/etc/httpd/conf.d/";
            
         //   echo  'find '.$recMysqlFile.'  -name "*.gz" -type f | xargs ls -Art | tail -n 1 | xargs -i gunzip -cd {} ';
            
            $restore_file  = $recMysqlFile.'full_db.sql';
            $restore_MyDbfile  = $recMysqlDBFile.'mysql_db.sql';
            $server_name   = "localhost";
            $username      = "ROOT_DB_USER";
            $password      = "ROOT_DB_PASSWORD";
            $database_name = "";
                
            
             $cmd_mysql = shell_exec( 'find '.$recMysqlFile.'  -name "*.gz" -type f | xargs ls -Art | tail -n 1 | xargs -i gunzip -cd {}  >  '.$recMysqlFile.'full_db.sql &&  echo -e "\n\nflush privileges;\n" >> '.$recMysqlFile.'full_db.sql && '."mysql -h {$server_name} -u{$username} -p{$password}  < $restore_file".' && find '.$recMysqlDBFile.'  -name "*.gz" -type f | xargs ls -Art | tail -n 1 | xargs -i gunzip -cd {}  >  '.$recMysqlDBFile.'mysql_db.sql  && '."mysql -h {$server_name} -u{$username} -p{$password}  mysql < $restore_MyDbfile");
            
            echo "\n\nwait...\n\n";
            sleep(10);

            
           
            echo "\n\nDatabase restored\n\n";
            
            $cmd1 = shell_exec( 'mkdir /etc/cron_bkp');
            $cmd2 = shell_exec( 'mv /etc/cron.hourly/p-backups.sh /etc/cron_bkp/');
            $cmd3 = shell_exec( 'mkdir /etc/httpd/conf_org');
            $cmd4 = shell_exec( 'mv /etc/httpd/conf.d/* /etc/httpd/conf_org/');
        
            echo $srvlst['server']." server is down\n\n";
            echo "Processing to move all projects to backup server\n\n";
            
            
            $pr_cmd = shell_exec( 'mv '. $recProjectFile . ' '.$recProjectFile_nm . ' && ' .'cp '. $recNetworkFile. ' '.$recNetworkFile_nm . ' && ' . 'cp -R '. $recHttpFile. ' '.$recHttpFile_nm . ' && systemctl restart network && systemctl restart httpd');
            
        }
    }
    
}
function send_update_email($subject, $message)
{
    $Name = "SENDER_USERNAME"; //senders name
    $email = "SENDER_EMAIL_ADDRESS"; //senders e-mail adress
    $recipient = "RECIPIENT_EMAIL_ADDRESS"; //recipient
    $mail_body = $message; //mail body
    $subject = $subject; //subject
    $header = "From: ". $Name . " <" . $email . ">\r\n"; //optional headerfields

    mail($recipient, $subject, $mail_body, $header); //mail command :) 
    
}
$srv_chk = chk_ip($servers);
$srv_png = chk_ping($srv_chk);


if(isset($srv_png) && count($srv_png) > 0 )
{
   switch_server($srv_png,$backup_dir);
}
	
?>