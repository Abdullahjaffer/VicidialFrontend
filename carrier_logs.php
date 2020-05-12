<?php
# AST_Carrier.php
#
# Copyright (C) 2008  Matt Florell <vicidial@gmail.com>    LICENSE: AGPLv2
#
# live real-time stats for the VICIDIAL Auto-Dialer
#
# CHANGES
#
# 60620-1037 - Added variable filtering to eliminate SQL injection attack threat
#            - Added required user/pass to gain access to this page
# 61114-2004 - Changed to display CLOSER and DEFAULT, added trunk shortage
# 80422-0305 - Added phone login to display, lower font size to 2
# 81013-2227 - Fixed Remote Agent display bug
# 90310-1945 - Admin header
# 90508-0644 - Changed to PHP long tags
#

header ("Content-type: text/html; charset=utf-8");

require("dbconnect.php");

$PHP_AUTH_USER=$_SERVER['PHP_AUTH_USER'];
$PHP_AUTH_PW=$_SERVER['PHP_AUTH_PW'];
$PHP_SELF=$_SERVER['PHP_SELF'];
if (isset($_GET["server_ip"]))            {$server_ip=$_GET["server_ip"];}
   elseif (isset($_POST["server_ip"]))      {$server_ip=$_POST["server_ip"];}
if (isset($_GET["reset_counter"]))         {$reset_counter=$_GET["reset_counter"];}
   elseif (isset($_POST["reset_counter"]))   {$reset_counter=$_POST["reset_counter"];}
if (isset($_GET["submit"]))               {$submit=$_GET["submit"];}
   elseif (isset($_POST["submit"]))      {$submit=$_POST["submit"];}
if (isset($_GET["SUBMIT"]))               {$SUBMIT=$_GET["SUBMIT"];}
   elseif (isset($_POST["SUBMIT"]))      {$SUBMIT=$_POST["SUBMIT"];}
if (isset($_GET["closer_display"]))            {$closer_display=$_GET["closer_display"];}
   elseif (isset($_POST["closer_display"]))   {$closer_display=$_POST["closer_display"];}

$PHP_AUTH_USER = ereg_replace("[^0-9a-zA-Z]","",$PHP_AUTH_USER);
$PHP_AUTH_PW = ereg_replace("[^0-9a-zA-Z]","",$PHP_AUTH_PW);

   $stmt="SELECT count(*) from vicidial_users where user='$PHP_AUTH_USER' and pass='$PHP_AUTH_PW' and user_level > 6 and view_reports='1';";
   if ($DB) {echo "|$stmt|\n";}
   $rslt=mysql_query($stmt, $link);
   $row=mysql_fetch_row($rslt);
   $auth=$row[0];

  if( (strlen($PHP_AUTH_USER)<2) or (strlen($PHP_AUTH_PW)<2) or (!$auth))
   {
    Header("WWW-Authenticate: Basic realm=\"VICI-PROJECTS\"");
    Header("HTTP/1.0 401 Unauthorized");
    echo "Invalid Username/Password: |$PHP_AUTH_USER|$PHP_AUTH_PW|\n";
    exit;
   }

$NOW_TIME = date("Y-m-d H:i:s");
$STARTtime = date("U");
$epochSIXhoursAGO = ($STARTtime - 21600);
$timeSIXhoursAGO = date("Y-m-d H:i:s",$epochSIXhoursAGO);

$reset_counter++;

if ($reset_counter > 7)
   {
   $reset_counter=0;

   $stmt="update park_log set status='HUNGUP' where hangup_time is not null;";
#   $rslt=mysql_query($stmt, $link);
   if ($DB) {echo "$stmt\n";}

   if ($DB)
      {   
      $stmt="delete from park_log where grab_time < '$timeSIXhoursAGO' and (hangup_time is null or hangup_time='');";
#      $rslt=mysql_query($stmt, $link);
       echo "$stmt\n";
      }
   }

?>

<HTML>
<HEAD>
<?php
echo "<STYLE type=\"text/css\">\n";
echo "<!--\n";

?>
   .DEAD       {color: white; background-color: black}
   .green {color: white; background-color: green}
   .red {color: white; background-color: red}
   .blue {color: white; background-color: blue}
   .purple {color: white; background-color: purple}
   .yellow {color: black; background-color: yellow}
-->
 </STYLE>

<?php
echo "<META HTTP-EQUIV=\"Content-Type\" CONTENT=\"text/html; charset=utf-8\">\n";
echo"<META HTTP-EQUIV=Refresh CONTENT=\"4; URL=$PHP_SELF?server_ip=$server_ip&DB=$DB&reset_counter=$reset_counter&closer_display=$closer_display\">\n";
echo "<TITLE>Server-Specific Real-Time Carrier Log Report</TITLE></HEAD><BODY BGCOLOR=WHITE marginheight=0 marginwidth=0 leftmargin=0 topmargin=0>\n";

$short_header=1;

require("admin_header.php");

echo "<TABLE CELLPADDING=4 CELLSPACING=0><TR><TD>";

echo "<PRE><FONT SIZE=2>";

###################################################################################
###### SERVER INFORMATION
###################################################################################

$stmt="select sum(local_trunk_shortage) from vicidial_campaign_server_stats where server_ip='" . mysql_real_escape_string($server_ip) . "';";
$rslt=mysql_query($stmt, $link);
$row=mysql_fetch_row($rslt);
$balanceSHORT = $row[0];

echo "SERVER: $server_ip\n";



###################################################################################
###### TIME ON SYSTEM
###################################################################################

if ($closer_display>0) {$closer_display_reverse=0;   $closer_reverse_link='DEFAULT';}
else {$closer_display_reverse=1;   $closer_reverse_link='CLOSER';}

echo "RealTime Carrier Log           $NOW_TIME    | <a href=\"./admin.php?ADD=999999\">REPORTS</a>\n\n";

$stmt="select vicidial_carrier_log.call_date,vicidial_carrier_log.server_ip, vicidial_carrier_log.lead_id, vicidial_carrier_log.dialstatus, Mid(vicidial_carrier_log.channel,7,2) as DialPrefix, vicidial_carrier_log.dial_time, vicidial_carrier_log.answered_time , vicidial_list.phone_number, CONCAT(vicidial_list.first_name , ' ' , vicidial_list.last_name) as Name from vicidial_carrier_log inner join vicidial_list on vicidial_list.lead_id=vicidial_carrier_log.lead_id where server_ip='" . mysql_real_escape_string($server_ip) . "'  order by call_date desc limit 60";
echo $stmt;

###################################################################################
###### OUTBOUND CALLS
###################################################################################
echo "\n\n";
echo "Server-Specific Real-Time Report                 $NOW_TIME\n\n";
echo "+---------------------+--------+------------+-----------------------------------------------------------------------+---------------+----------+----------+--------------+\n";
echo "| CALL DATE           | Lead ID| NUMBER     | NAME                                                                  | STATUS        | Prefix   | DIALTIME | ANSWER TIME  |\n";
echo "+---------------------+--------+------------+-----------------------------------------------------------------------+---------------+----------+----------+--------------+\n";

$rslt=mysql_query($stmt, $link);
if ($DB) {echo "$stmt\n";}
$parked_to_print = mysql_num_rows($rslt);
   if ($parked_to_print > 0)
   {
   $i=0;
   while ($i < $parked_to_print)
      {
      $row=mysql_fetch_row($rslt);

      $CallDate =         sprintf("%-19s", $row[0]);
      $leadid =         sprintf("%-6s", $row[2]);
      $PhoneNumber =         sprintf("%-10s", $row[7]);
      $Name =         sprintf("%-70s", $row[8]);
      $DialStatus =         sprintf("%-13s", $row[3]);
      $dialprefix =   sprintf("%-7s", $row[4]);
      $dialtime =   sprintf("%-8s", $row[5]);
      $answertime =   sprintf("%-10s", $row[6]);
      $G = '';      $EG = '';
      if (eregi("LIVE",$status)) {$G='<SPAN class="green"><B>'; $EG='</B></SPAN>';}
   #   if ($call_time_M_int >= 6) {$G='<SPAN class="red"><B>'; $EG='</B></SPAN>';}

      echo "| $G$CallDate$EG | $G$leadid$EG | $G$PhoneNumber$EG | $G$Name$EG | $G$DialStatus$EG | $G$dialprefix$EG | $G$dialtime$EG | $G$answertime$EG |\n";

      $i++;
      }

      echo "+----------------------------------------------------------------------------------------------------------------------------------------------------------------------+\n";
      echo "  $i calls being placed on server $server_ip\n\n";

      echo "  <SPAN class=\"green\"><B>          </SPAN> - LIVE CALL WAITING</B>\n";
   #   echo "  <SPAN class=\"red\"><B>          </SPAN> - Over 5 minutes on hold</B>\n";

      }
   else
   {
   echo "***************************************************************************************\n";
   echo "***************************************************************************************\n";
   echo "******************************* NO LIVE CALLS WAITING *********************************\n";
   echo "***************************************************************************************\n";
   echo "***************************************************************************************\n";
   }


?>
</PRE>
</TD></TR></TABLE>

</BODY></HTML>
