<?php //00e57
// *************************************************************************
// *																	   *
// * WHMCS - The Complete Client Management, Billing & Support Solution	*
// * Copyright (c) WHMCS Ltd. All Rights Reserved,						 *
// * Version: 5.3.14 (5.3.14-release.1)									*
// * BuildId: 0866bd1.62												   *
// * Build Date: 28 May 2015											   *
// *																	   *
// *************************************************************************
// *																	   *
// * Email: info@whmcs.com												 *
// * Website: http://www.whmcs.com										 *
// *																	   *
// *************************************************************************
// *																	   *
// * This software is furnished under a license and may be used and copied *
// * only  in  accordance  with  the  terms  of such  license and with the *
// * inclusion of the above copyright notice.  This software  or any other *
// * copies thereof may not be provided or otherwise made available to any *
// * other person.  No title to and  ownership of the  software is  hereby *
// * transferred.														  *
// *																	   *
// * You may not reverse  engineer, decompile, defeat  license  encryption *
// * mechanisms, or  disassemble this software product or software product *
// * license.  WHMCompleteSolution may terminate this license if you don't *
// * comply with any of the terms and conditions set forth in our end user *
// * license agreement (EULA).  In such event,  licensee  agrees to return *
// * licensor  or destroy  all copies of software  upon termination of the *
// * license.															  *
// *																	   *
// * Please see the EULA file for the full End User License Agreement.	 *
// *																	   *
// *************************************************************************
require(dirname(__FILE__) . "/../init.php");
include(ROOTDIR . "/includes/clientfunctions.php");
include(ROOTDIR . "/includes/modulefunctions.php");
include(ROOTDIR . "/includes/gatewayfunctions.php");
include(ROOTDIR . "/includes/ccfunctions.php");
include(ROOTDIR . "/includes/processinvoices.php");
include(ROOTDIR . "/includes/invoicefunctions.php");
include(ROOTDIR . "/includes/backupfunctions.php");
include(ROOTDIR . "/includes/ticketfunctions.php");
include(ROOTDIR . "/includes/currencyfunctions.php");
include(ROOTDIR . "/includes/domainfunctions.php");
include(ROOTDIR . "/includes/registrarfunctions.php");
define('IN_CRON', true);
$cron = WHMCS_Cron::init();
$cron->raiseLimits();
WHMCS_Session::release();
$escalations = is_array($_SERVER['argv']) && in_array('escalations', $_SERVER['argv']) || isset($_GET['escalations']) ? true : false;
if( $escalations )
{
	include(ROOTDIR . "/includes/adminfunctions.php");
	$lastruntime = $CONFIG['TicketEscalationLastRun'];
	$result = select_query('tblticketescalations', '', '');
	while( $data = mysqli_fetch_array($result) )
	{
		$id = $data['id'];
		$name = $data['name'];
		$departments = $data['departments'];
		$statuses = $data['statuses'];
		$priorities = $data['priorities'];
		$timeelapsed = $data['timeelapsed'];
		$newdepartment = $data['newdepartment'];
		$newpriority = $data['newpriority'];
		$newstatus = $data['newstatus'];
		$flagto = $data['flagto'];
		$notify = $data['notify'];
		$addreply = $data['addreply'];
		$ticketsqry = "SELECT * FROM tbltickets WHERE ";
		if( $departments )
		{
			$departments = explode(',', $departments);
			$ticketsqry .= "did IN (" . db_build_in_array($departments) . ") AND ";
		}
		if( $statuses )
		{
			$statuses = explode(',', $statuses);
			$ticketsqry .= "status IN (" . db_build_in_array($statuses) . ") AND ";
		}
		if( $priorities )
		{
			$priorities = explode(',', $priorities);
			$ticketsqry .= "urgency IN (" . db_build_in_array($priorities) . ") AND ";
		}
		if( $timeelapsed )
		{
			$tickettime = date("Y-m-d H:i:s", mktime(date('H'), date('i') - $timeelapsed, date('s'), date('m'), date('d'), date('Y')));
			$ticketlasttime = date("Y-m-d H:i:s", strtotime($lastruntime . " - " . $timeelapsed . " minutes"));
			$ticketsqry .= "lastreply>'" . $ticketlasttime . "' AND lastreply<='" . $tickettime . "' AND ";
		}
		$ticketsqry = substr($ticketsqry, 0, 0 - 5);
		$result2 = full_query($ticketsqry);
		while( $data = mysqli_fetch_array($result2) )
		{
			$ticketid = $data['id'];
			$tickettid = $data['tid'];
			$ticketsubject = $data['title'];
			$ticketuserid = $data['userid'];
			$ticketfromname = $data['name'];
			$ticketdeptid = $data['did'];
			$ticketpriority = $data['urgency'];
			$ticketstatus = $data['status'];
			$ticketmsg = $data['message'];
			$updateqry = array(  );
			if( $newdepartment )
			{
				$updateqry['did'] = $newdepartment;
			}
			if( $newpriority )
			{
				$updateqry['urgency'] = $newpriority;
			}
			if( $newstatus )
			{
				$updateqry['status'] = $newstatus;
			}
			if( $flagto )
			{
				$updateqry['flag'] = $flagto;
				sendAdminMessage("Support Ticket Flagged", array( 'ticket_id' => $ticketid, 'ticket_tid' => $tickettid, 'client_id' => $ticketuserid, 'client_name' => get_query_val('tblclients', "CONCAT(firstname,' ',lastname)", array( 'id' => $ticketuserid )), 'ticket_department' => getDepartmentName($newdepartment ? $newdepartment : $ticketdeptid), 'ticket_subject' => $ticketsubject, 'ticket_priority' => $newpriority ? $newpriority : $ticketpriority, 'ticket_message' => ticketMessageFormat($ticketmsg) ), 'support', $newdepartment ? $newdepartment : $ticketdeptid, $flagto);
			}
			if( count($updateqry) )
			{
				update_query('tbltickets', $updateqry, array( 'id' => $ticketid ));
			}
			if( $notify )
			{
				$notify = explode(',', $notify);
				if( in_array('all', $notify) )
				{
					sendAdminMessage("Escalation Rule Notification", array( 'rule_name' => $name, 'ticket_id' => $ticketid, 'ticket_tid' => $tickettid, 'client_id' => $ticketuserid, 'client_name' => get_query_val('tblclients', "CONCAT(firstname,' ',lastname)", array( 'id' => $ticketuserid )), 'ticket_department' => getDepartmentName($newdepartment ? $newdepartment : $ticketdeptid), 'ticket_subject' => $ticketsubject, 'ticket_priority' => $newpriority ? $newpriority : $ticketpriority, 'ticket_message' => ticketMessageFormat($ticketmsg) ), 'support', $newdepartment ? $newdepartment : $ticketdeptid);
				}
				foreach( $notify as $notifyid )
				{
					if( is_numeric($notifyid) )
					{
						sendAdminMessage("Escalation Rule Notification", array( 'rule_name' => $name, 'ticket_id' => $ticketid, 'ticket_tid' => $tickettid, 'client_id' => $ticketuserid, 'client_name' => get_query_val('tblclients', "CONCAT(firstname,' ',lastname)", array( 'id' => $ticketuserid )), 'ticket_department' => getDepartmentName($newdepartment ? $newdepartment : $ticketdeptid), 'ticket_subject' => $ticketsubject, 'ticket_priority' => $newpriority ? $newpriority : $ticketpriority, 'ticket_message' => ticketMessageFormat($ticketmsg), 'ticket_status' => $ticketstatus ), 'support', '', $notifyid);
					}
				}
			}
			if( $addreply )
			{
				if( !$newstatus )
				{
					$newstatus = $ticketstatus;
				}
				AddReply($ticketid, '', '', $addreply, 'System', '', '', $newstatus, '', true);
			}
			addTicketLog($ticketid, "Escalation Rule \"" . $name . "\" applied");
		}
	}
	update_query('tblconfiguration', array( 'value' => date("Y-m-d H:i:s") ), array( 'setting' => 'TicketEscalationLastRun' ));
	exit( dirname(__FILE__) . " | line".__LINE__ );
}
define('INCRONRUN', true);
$cron->logactivity('Starting');
$licensing = WHMCS_License::getinstance();
if( $licensing->isClientLimitsEnabled() && $licensing->isNearClientLimit() )
{
	$clientLimit = $licensing->getClientLimit();
	$numClients = $licensing->getNumberOfActiveClients();
	$helpLink = $whmcs->isSSLAvailable() ? $whmcs->getSystemSSLURL() : $whmcs->getSystemURL();
	$helpLink .= $whmcs->get_admin_folder_name() . "/systemlicense.php";
	if( $numClients < $clientLimit )
	{
		$subject = "License Limit Near";
		$message = "<p>You are nearing the maximum number of clients permitted by your current license. Upgrade now to avoid any interuptions in service.</p><p>Current Client Limit: " . $licensing->getTextClientLimit() . "<br />Total Number of Active Clients: " . $licensing->getTextNumberOfActiveClients() . "</p><p>For more information, visit <a href=\"" . $helpLink . "\">" . $helpLink . "</a></p>";
	}
	else
	{
		if( $clientLimit == $licensing->getNumberOfActiveClients() )
		{
			$subject = "License Limit Reached";
			$message = "<p>You have reached the number of clients permitted by your current license. Upgrade now to avoid any interuptions in service.</p><p>Current Client Limit: " . $licensing->getTextClientLimit() . "<br />Total Number of Active Clients: " . $licensing->getTextNumberOfActiveClients() . "</p><p>For more information, visit <a href=\"" . $helpLink . "\">" . $helpLink . "</a></p>";
		}
		else
		{
			$subject = "License Limit Exceeded - Urgent Action Required";
			$message = "<p>You have exceeded the number of clients permitted by your current license. Upgrade now to continue to have full access to manage your clients.</p><p>Current Client Limit: " . $licensing->getTextClientLimit() . "<br />Total Number of Active Clients: " . $licensing->getTextNumberOfActiveClients() . "</p><p>For more information, visit <a href=\"" . $helpLink . "\">" . $helpLink . "</a></p>";
		}
	}
	sendAdminNotification('system', $subject, $message);
}
full_query("DELETE FROM tblinvoices WHERE userid NOT IN (SELECT id FROM tblclients)");
full_query("UPDATE tbltickets SET did=(SELECT id FROM tblticketdepartments ORDER BY `order` ASC LIMIT 1) WHERE did NOT IN (SELECT id FROM tblticketdepartments)");
update_query('tblclients', array( 'currency' => '1' ), array( 'currency' => '0' ));
update_query('tblaccounts', array( 'currency' => '1' ), array( 'currency' => '0', 'userid' => '0' ));
$tables = array( 'tblhosting', 'tbldomains' );
foreach( $tables as $table )
{
	$result = select_query($table, 'id,userid', array( 'paymentmethod' => '' ));
	while( $data = simulate_fetch_assoc($result) )
	{
		ensurePaymentMethodIsSet($data['userid'], $data['id'], $table);
	}
}
$result = select_query('tblhostingaddons', "tblhostingaddons.id as id,tblhosting.userid as userid", array( "tblhostingaddons.paymentmethod" => '' ), '', '', '', "tblhosting on tblhostingaddons.hostingid = tblhosting.id");
while( $data = simulate_fetch_assoc($result) )
{
	ensurePaymentMethodIsSet($data['userid'], $data['id'], 'tblhostingaddons');
}
$cron->logactivity("Running Pre-Cron Hooks");
run_hook('PreCronJob', array(  ));
if( $whmcs->get_config('CurrencyAutoUpdateExchangeRates') && $cron->isScheduled('updaterates') )
{
	currencyUpdateRates();
	$cron->logActivity('Done', true);
}
if( $whmcs->get_config('CurrencyAutoUpdateProductPrices') && $cron->isScheduled('updatepricing') )
{
	currencyUpdatePricing();
	$cron->logActivity('Done', true);
}
if( $cron->isScheduled('invoices') )
{
	createInvoices();
}
if( $cron->isScheduled('latefees') )
{
	InvoicesAddLateFee();
}
if( $cron->isScheduled('ccprocessing') )
{
	ccProcessing();
}
if( $cron->isScheduled('invoicereminders') )
{
	if( $CONFIG['SendReminder'] == 'on' )
	{
		$reminders = '';
		if( $CONFIG['SendInvoiceReminderDays'] )
		{
			$invoiceids = array(  );
			$invoicedateyear = date('Ymd', mktime(0, 0, 0, date('m'), date('d') + $CONFIG['SendInvoiceReminderDays'], date('Y')));
			$query = "SELECT * FROM tblinvoices WHERE duedate='" . $invoicedateyear . "' AND `status`='Unpaid'";
			$result = full_query($query);
			while( $data = mysqli_fetch_array($result) )
			{
				$id = $data['id'];
				sendMessage("Invoice Payment Reminder", $id);
				run_hook('InvoicePaymentReminder', array( 'invoiceid' => $id, 'type' => 'reminder' ));
				$invoiceids[] = $id;
			}
			$invoicenums = count($invoiceids) ? " to Invoice Numbers " . implode(',', $invoiceids) : '';
			$cron->logActivity("Sent " . count($invoiceids) . " Unpaid Invoice Payment Reminders" . $invoicenums, true);
			$cron->emailLog(count($invoiceids) . " Unpaid Invoice Payment Reminders Sent" . $invoicenums);
		}
	}
	SendOverdueInvoiceReminders();
}
if( $cron->isScheduled('domainrenewalnotices') )
{
	$renewalsNoticesCount = 0;
	$renewals = explode(',', $whmcs->get_config('DomainRenewalNotices'));
	foreach( $renewals as $count => $renewal )
	{
		if( (int) $renewal != 0 )
		{
			$renewalDate = date('Y-m-d', mktime(0, 0, 0, date('m'), date('d') + (int) $renewal, date('Y')));
			$status = 'Active';
			$emailToSend = "Upcoming Domain Renewal Notice";
			if( $renewal < 0 )
			{
				$status = 'Expired';
				$emailToSend = "Expired Domain Notice";
			}
			for( $result = select_query('tbldomains', 'id,userid,domain,registrar,reminders', "status = '" . $status . "' AND nextduedate='" . $renewalDate . "' AND " . "recurringamount!='0.00' AND reminders NOT LIKE '%|" . (int) $renewal . "|%'"); $data = mysqli_fetch_array($result); $renewalsNoticesCount++ )
			{
				$params = array(  );
				$params['domainid'] = $data['id'];
				$domainParts = explode(".", $data['domain']);
				$params['sld'] = $domainParts[0];
				$params['tld'] = $domainParts[1];
				$params['registrar'] = $data['registrar'];
				$extra = RegGetRegistrantContactEmailAddress($params);
				$client = new WHMCS_Client($data['userid']);
				$details = $client->getDetails();
				$recipients = array(  );
				$recipients[] = $details['email'];
				if( isset($extra['registrantEmail']) )
				{
					$recipients[] = $extra['registrantEmail'];
				}
				if( sendMessage($emailToSend, $data['id'], $extra) === true )
				{
					update_query('tbldomains', array( 'reminders' => $data['reminders'] . "|" . (int) $renewal . "|" ), array( 'id' => $data['id'] ));
					insert_query('tbldomainreminders', array( 'domain_id' => $data['id'], 'date' => date('Y-m-d'), 'recipients' => implode(',', $recipients), 'type' => $count + 1, 'days_before_expiry' => $renewal ));
				}
			}
		}
	}
	$cron->logActivity("Sent " . $renewalsNoticesCount . " Notices", true);
	$cron->emailLog($renewalsNoticesCount . " Domain Renewal Notices Sent");
}
if( $CONFIG['AutoCancellationRequests'] && $cron->isScheduled('cancelrequests') )
{
	$i = 0;
	$terminatedate = date('Ymd', mktime(0, 0, 0, date('m'), date('d'), date('Y')));
	$query = "SELECT * FROM tblcancelrequests INNER JOIN tblhosting ON tblhosting.id = tblcancelrequests.relid WHERE (domainstatus!='Terminated' AND domainstatus!='Cancelled') AND (type='Immediate' OR ( type='End of Billing Period' AND nextduedate<='" . $terminatedate . "' )) AND (tblhosting.billingcycle='Free' OR tblhosting.billingcycle='Free Account' OR tblhosting.nextduedate != '0000-00-00') ORDER BY domain ASC";
	$result = full_query($query);
	while( $data = mysqli_fetch_array($result) )
	{
		$id = $data['id'];
		$userid = $data['userid'];
		$domain = $data['domain'];
		$nextduedate = $data['nextduedate'];
		$packageid = $data['packageid'];
		$regdate = fromMySQLDate($regdate);
		$nextduedate = fromMySQLDate($nextduedate);
		$result2 = select_query('tblclients', 'firstname,lastname', array( 'id' => $userid ));
		$data2 = mysqli_fetch_array($result2);
		$firstname = $data2['firstname'];
		$lastname = $data2['lastname'];
		$result2 = select_query('tblproducts', 'name,servertype,freedomain', array( 'id' => $packageid ));
		$data2 = mysqli_fetch_array($result2);
		$prodname = $data2['name'];
		$module = $data2['servertype'];
		$freedomain = $data2['freedomain'];
		if( $freedomain )
		{
			$result2 = select_query('tbldomains', 'id,registrationperiod', array( 'domain' => $domain, 'recurringamount' => "0.00" ));
			$data2 = mysqli_fetch_array($result2);
			$domainid = $data2['id'];
			$regperiod = $data2['registrationperiod'];
			if( $domainid )
			{
				$domainparts = explode(".", $domain, 2);
				$tld = $domainparts[1];
				$currency = getCurrency($userid);
				$temppricelist = getTLDPriceList("." . $tld);
				$renewprice = $temppricelist[$regperiod]['renew'];
				update_query('tbldomains', array( 'recurringamount' => $renewprice ), array( 'id' => $domainid ));
			}
		}
		$serverresult = "No Module";
		logActivity("Cron Job: Processing Cancellation Request - Service ID: " . $id);
		if( $module )
		{
			$serverresult = ServerTerminateAccount($id);
		}
		if( $domain )
		{
			$domain = " - " . $domain;
		}
		$loginfo = $prodname . $domain . " - " . $firstname . " " . $lastname . " (Due Date: " . $nextduedate . ")";
		if( $serverresult == 'success' )
		{
			update_query('tblhosting', array( 'domainstatus' => 'Cancelled' ), array( 'id' => $id ));
			update_query('tblhostingaddons', array( 'status' => 'Cancelled' ), array( 'hostingid' => $id ));
			run_hook('AddonCancelled', array( 'id' => 'all', 'userid' => $userid, 'serviceid' => $id, 'addonid' => '' ));
			$cron->emailLogSub("SUCCESS: " . $loginfo, true);
			$i++;
		}
		else
		{
			$cron->emailLogSub("ERROR: Manual Cancellation Required - " . $serverresult . " - " . $loginfo, true);
		}
	}
	$cron->logActivity("Processed " . $i . " Cancellations", true);
	$cron->emailLog($i . " Cancellation Requests Processed");
}
if( $CONFIG['AutoSuspension'] && $cron->isScheduled('suspensions') )
{
	update_query('tblhosting', array( 'overideautosuspend' => '' ), "(overideautosuspend='on' OR overideautosuspend='1') AND overidesuspenduntil<'" . date('Y-m-d') . "' AND overidesuspenduntil!='0000-00-00'");
	$i = 0;
	$suspenddate = date('Ymd', mktime(0, 0, 0, date('m'), date('d') - $CONFIG['AutoSuspensionDays'], date('Y')));
	$query3 = "SELECT * FROM tblhosting WHERE domainstatus='Active' AND billingcycle!='Free Account' AND billingcycle!='Free' AND billingcycle!='One Time' AND overideautosuspend!='on' AND overideautosuspend!='1' AND nextduedate<='" . $suspenddate . "' ORDER BY domain ASC";
	$result3 = full_query($query3);
	while( $data = mysqli_fetch_array($result3) )
	{
		$id = $data['id'];
		$userid = $data['userid'];
		$domain = $data['domain'];
		$packageid = $data['packageid'];
		$result2 = select_query('tblclients', '', array( 'id' => $userid ));
		$data2 = mysqli_fetch_array($result2);
		$firstname = $data2['firstname'];
		$lastname = $data2['lastname'];
		$groupid = $data2['groupid'];
		$result2 = select_query('tblproducts', 'name,servertype', array( 'id' => $packageid ));
		$data2 = mysqli_fetch_array($result2);
		$prodname = $data2['name'];
		$module = $data2['servertype'];
		$result2 = select_query('tblclientgroups', 'susptermexempt', array( 'id' => $groupid ));
		$data2 = mysqli_fetch_array($result2);
		$susptermexempt = $data2['susptermexempt'];
		if( !$susptermexempt )
		{
			$serverresult = "No Module";
			logActivity("Cron Job: Suspending Service - Service ID: " . $id);
			if( $module )
			{
				$serverresult = ServerSuspendAccount($id);
			}
			if( $domain )
			{
				$domain = " - " . $domain;
			}
			$loginfo = $prodname . $domain . " - " . $firstname . " " . $lastname . " (Service ID: " . $id . " - User ID: " . $userid . ")";
			if( $serverresult == 'success' )
			{
				sendMessage("Service Suspension Notification", $id);
				$cron->emailLogSub("SUCCESS: " . $loginfo, true);
				$i++;
			}
			else
			{
				$cron->emailLogSub("ERROR: Manual Suspension Required - " . $serverresult . " - " . $loginfo, true);
			}
		}
	}
	$service = new WHMCS_Service();
	$query3 = "SELECT tblhostingaddons.*,tblhosting.userid,tblhosting.packageid,tblhosting.domain,tblclients.firstname,tblclients.lastname,tblclients.groupid FROM tblhostingaddons INNER JOIN tblhosting ON tblhosting.id=tblhostingaddons.hostingid INNER JOIN tblclients ON tblclients.id=tblhosting.userid WHERE tblhostingaddons.status='Active' AND tblhostingaddons.billingcycle!='Free' AND tblhostingaddons.billingcycle!='Free Account' AND tblhostingaddons.billingcycle!='One Time' AND tblhostingaddons.nextduedate<='" . $suspenddate . "' AND tblhosting.overideautosuspend!='on' AND tblhosting.overideautosuspend!='1' ORDER BY tblhostingaddons.name ASC";
	$result3 = full_query($query3);
	while( $data = mysqli_fetch_array($result3) )
	{
		$id = $data['id'];
		$serviceid = $data['hostingid'];
		$addonid = $data['addonid'];
		$name = $data['name'];
		$nextduedate = $data['nextduedate'];
		$userid = $data['userid'];
		$packageid = $data['packageid'];
		$domain = $data['domain'];
		$firstname = $data['firstname'];
		$lastname = $data['lastname'];
		$groupid = $data['groupid'];
		$result2 = select_query('tblclientgroups', 'susptermexempt', array( 'id' => $groupid ));
		$data2 = mysqli_fetch_array($result2);
		$susptermexempt = $data2['susptermexempt'];
		if( !$susptermexempt )
		{
			if( !$name && $addonid )
			{
				$name = $service->getPredefinedAddonName($addonid);
			}
			update_query('tblhostingaddons', array( 'status' => 'Suspended' ), array( 'id' => $id ));
			$loginfo = $name . " - " . $firstname . " " . $lastname . " (Service ID: " . $serviceid . " - Addon ID: " . $id . ")";
			$cron->logActivity("SUCCESS: " . $loginfo, true);
			run_hook('AddonSuspended', array( 'id' => $id, 'userid' => $userid, 'serviceid' => $serviceid, 'addonid' => $addonid ));
			if( $addonid )
			{
				$result2 = select_query('tbladdons', 'suspendproduct', array( 'id' => $addonid ));
				$data2 = mysqli_fetch_array($result2);
				$suspendproduct = $data2[0];
				if( $suspendproduct )
				{
					$result2 = select_query('tblproducts', 'name,servertype', array( 'id' => $packageid ));
					$data2 = mysqli_fetch_array($result2);
					$prodname = $data2['name'];
					$module = $data2['servertype'];
					$serverresult = "No Module";
					logActivity("Cron Job: Suspending Parent Service - Service ID: " . $serviceid);
					if( $module )
					{
						$serverresult = ServerSuspendAccount($serviceid, "Parent Service Suspended due to Overdue Addon");
					}
					if( $domain )
					{
						$domain = " - " . $domain;
					}
					$loginfo = $firstname . " " . $lastname . " - " . $prodname . $domain . " (Service ID: " . $serviceid . " - User ID: " . $userid . ")";
					if( $serverresult == 'success' )
					{
						sendMessage("Service Suspension Notification", $serviceid);
						$cron->emailLogSub("SUCCESS: " . $loginfo, true);
					}
					else
					{
						$cron->emailLogSub("ERROR: Manual Parent Service Suspension Required - " . $serverresult . " - " . $loginfo, true);
					}
				}
			}
			$i++;
		}
	}
	$cron->logActivity("Processed " . $i . " Suspensions", true);
	$cron->emailLog($i . " Services Suspended");
}
if( $CONFIG['AutoTermination'] && $cron->isScheduled('terminations') )
{
	$i = 0;
	$terminatedate = date('Ymd', mktime(0, 0, 0, date('m'), date('d') - $CONFIG['AutoTerminationDays'], date('Y')));
	$query = "SELECT * FROM tblhosting WHERE (domainstatus='Active' OR domainstatus='Suspended') AND billingcycle!='Free Account' AND billingcycle!='One Time' AND nextduedate<='" . $terminatedate . "' AND tblhosting.nextduedate != '0000-00-00' AND overideautosuspend!='on' AND overideautosuspend!='1' ORDER BY domain ASC";
	$result = full_query($query);
	while( $data = mysqli_fetch_array($result) )
	{
		$serviceid = $data['id'];
		$userid = $data['userid'];
		$domain = $data['domain'];
		$packageid = $data['packageid'];
		$result2 = select_query('tblclients', '', array( 'id' => $userid ));
		$data2 = mysqli_fetch_array($result2);
		$firstname = $data2['firstname'];
		$lastname = $data2['lastname'];
		$groupid = $data2['groupid'];
		$result2 = select_query('tblproducts', 'name,servertype', array( 'id' => $packageid ));
		$data2 = mysqli_fetch_array($result2);
		$prodname = $data2['name'];
		$module = $data2['servertype'];
		$result2 = select_query('tblclientgroups', 'susptermexempt', array( 'id' => $groupid ));
		$data2 = mysqli_fetch_array($result2);
		$susptermexempt = $data2['susptermexempt'];
		if( !$susptermexempt )
		{
			$serverresult = "No Module";
			logActivity("Cron Job: Terminating Service - Service ID: " . $serviceid);
			if( $module )
			{
				$serverresult = ServerTerminateAccount($serviceid);
			}
			if( $domain )
			{
				$domain = " - " . $domain;
			}
			$loginfo = $prodname . $domain . " - " . $firstname . " " . $lastname . " (Service ID: " . $serviceid . " - User ID: " . $userid . ")";
			if( $serverresult == 'success' )
			{
				$cron->emailLogSub("SUCCESS: " . $loginfo, true);
				$i++;
			}
			else
			{
				$cron->emailLogSub("ERROR: Manual Terminate Required - " . $serverresult . " - " . $loginfo, true);
			}
		}
	}
	$query = "UPDATE tblhostingaddons SET status='Terminated' WHERE (status='Active' OR status='Suspended') AND billingcycle!='Free Account' AND billingcycle!='Free' AND billingcycle!='One Time' AND nextduedate!='0000-00-00' AND nextduedate<='" . $terminatedate . "'";
	$result = full_query($query);
	$cron->logActivity("Processed " . $i . " Terminations", true);
	$cron->emailLog($i . " Services Terminated");
}
if( $cron->isScheduled('fixedtermterminations') )
{
	$count = 0;
	$result = select_query('tblproducts', 'id,autoterminatedays,autoterminateemail,servertype,name', "autoterminatedays>0", 'id', 'ASC');
	while( $data = mysqli_fetch_array($result) )
	{
		$pid = $data[0];
		$autoterminatedays = $data[1];
		$autoterminateemail = $data[2];
		$module = $data[3];
		$prodname = $data[4];
		if( $autoterminateemail )
		{
			$result2 = select_query('tblemailtemplates', 'name', array( 'id' => $autoterminateemail ));
			$data = mysqli_fetch_array($result2);
			$emailtplname = $data[0];
		}
		$terminatebefore = date('Y-m-d', mktime(0, 0, 0, date('m'), date('d') - $autoterminatedays, date('Y')));
		$result2 = select_query('tblhosting', "tblhosting.id,userid,domain,firstname,lastname", "packageid=" . $pid . " AND regdate<='" . $terminatebefore . "' AND (domainstatus='Active' OR domainstatus='Suspended')", 'id', 'ASC', '', "tblclients ON tblclients.id=tblhosting.userid");
		while( $data = mysqli_fetch_array($result2) )
		{
			$serviceid = $data[0];
			$userid = $data[1];
			$domain = $data[2];
			$firstname = $data[3];
			$lastname = $data[4];
			$moduleresult = "No Module";
			logActivity("Cron Job: Auto Terminating Fixed Term Service - Service ID: " . $serviceid);
			if( $module )
			{
				$moduleresult = ServerTerminateAccount($serviceid);
			}
			if( $domain )
			{
				$domain = " - " . $domain;
			}
			$loginfo = $prodname . $domain . " - " . $firstname . " " . $lastname . " (Service ID: " . $serviceid . " - User ID: " . $userid . ")";
			if( $moduleresult == 'success' )
			{
				if( $autoterminateemail )
				{
					sendMessage($emailtplname, $serviceid);
				}
				$cron->logActivity("SUCCESS: " . $loginfo, true);
				$count++;
			}
			else
			{
				$cron->logActivity("ERROR: Manual Terminate Required - " . $moduleresult . " - " . $loginfo, true);
			}
		}
	}
	$cron->logActivity("Processed " . $count . " Terminations", true);
	$cron->emailLog($count . " Fixed Term Terminations Processed");
}
if( $cron->isScheduled('closetickets') )
{
	closeInactiveTickets();
}
if( $CONFIG['AffiliatesDelayCommission'] && $cron->isScheduled('affcommissions') )
{
	$affiliatepaymentscleared = 0;
	$query = "SELECT * FROM tblaffiliatespending WHERE clearingdate<='" . date('Y-m-d') . "'";
	$result = full_query($query);
	while( $data = mysqli_fetch_array($result) )
	{
		$affaccid = $data['affaccid'];
		$amount = $data['amount'];
		$result2 = select_query('tblaffiliatesaccounts', '', array( 'id' => $affaccid ));
		$data = mysqli_fetch_array($result2);
		$affaccid = $data['id'];
		$relid = $data['relid'];
		$affid = $data['affiliateid'];
		$result2 = select_query('tblhosting', 'domainstatus', array( 'id' => $relid ));
		$data = mysqli_fetch_array($result2);
		$domainstatus = $data['domainstatus'];
		if( $affaccid && $domainstatus == 'Active' )
		{
			update_query('tblaffiliates', array( 'balance' => "+=" . $amount ), array( 'id' => (int) $affid ));
			update_query('tblaffiliatesaccounts', array( 'lastpaid' => "now()" ), array( 'id' => (int) $affaccid ));
			insert_query('tblaffiliateshistory', array( 'affiliateid' => $affid, 'date' => "now()", 'affaccid' => $affaccid, 'amount' => $amount ));
			$affiliatepaymentscleared++;
		}
	}
	$cron->logActivity("Processed " . $affiliatepaymentscleared . " Pending Affiliate Payments", true);
	$cron->emailLog($affiliatepaymentscleared . " Pending Affiliate Payments Cleared");
	$query = "DELETE FROM tblaffiliatespending WHERE clearingdate<='" . date('Y-m-d') . "'";
	$result = full_query($query);
}
if( $CONFIG['SendAffiliateReportMonthly'] && date('d') == '1' && $cron->isScheduled('affreports') )
{
	$query = "SELECT aff.* FROM tblaffiliates aff join tblclients client on aff.clientid = client.id where client.status = 'Active'";
	$result = full_query($query);
	while( $data = mysqli_fetch_array($result) )
	{
		$id = $data['id'];
		sendMessage("Affiliate Monthly Referrals Report", $id);
	}
	$cron->logActivity("Sent Successfully", true);
	$cron->emailLog("Monthly Affiliate Reports Sent");
}
if( $cron->isScheduled('emailmarketing') )
{
	$emails = false;
	$result = select_query('tblemailmarketer', '', array( 'disable' => '0' ), 'id', 'ASC');
	while( $data = mysqli_fetch_array($result) )
	{
		$id = $data['id'];
		$name = $data['name'];
		$type = $data['type'];
		$settings = $data['settings'];
		$marketing = $data['marketing'];
		$settings = unserialize($settings);
		$clientnumdays = $settings['clientnumdays'];
		$clientsminactive = $settings['clientsminactive'];
		$clientsmaxactive = $settings['clientsmaxactive'];
		$clientemailtpl = $settings['clientemailtpl'];
		$prodpids = $settings['prodpids'];
		$prodstatus = $settings['prodstatus'];
		$prodnumdays = $settings['prodnumdays'];
		$prodfiltertype = $settings['prodfiltertype'];
		$prodexcludepid = $settings['prodexcludepid'];
		$prodexcludeaid = $settings['prodexcludeaid'];
		$prodemailtpl = $settings['prodemailtpl'];
		$query = $query1 = '';
		$criteria = array(  );
		if( $type == 'client' )
		{
			$emailtplid = $clientemailtpl;
			$query = "SELECT id FROM tblclients WHERE ";
			if( 0 < $clientnumdays )
			{
				$criteria[] = "datecreated='" . date('Y-m-d', mktime(0, 0, 0, date('m'), date('d') - $clientnumdays, date('Y'))) . "'";
			}
			if( strlen($clientsminactive) )
			{
				$criteria[] = "(SELECT COUNT(*) FROM tblhosting WHERE tblhosting.userid=tblclients.id AND tblhosting.domainstatus='Active')>=" . (int) $clientsminactive;
			}
			if( strlen($clientsmaxactive) )
			{
				$criteria[] = "(SELECT COUNT(*) FROM tblhosting WHERE tblhosting.userid=tblclients.id AND tblhosting.domainstatus='Active')<=" . (int) $clientsmaxactive;
			}
			if( $marketing )
			{
				$criteria[] = "emailoptout = '0'";
			}
			$query .= implode(" AND ", $criteria);
		}
		else
		{
			if( $type == 'product' )
			{
				$emailtplid = $prodemailtpl;
				$filterpids = $filteraids = array(  );
				foreach( $prodpids as $pid )
				{
					if( substr($pid, 0, 1) == 'P' )
					{
						$filterpids[] = (int) substr($pid, 1);
					}
					else
					{
						if( substr($pid, 0, 1) == 'A' )
						{
							$filteraids[] = (int) substr($pid, 1);
						}
					}
				}
				if( count($filterpids) )
				{
					$query = "SELECT id FROM tblhosting WHERE ";
					$criteria[] = "packageid IN (" . db_build_in_array($filterpids) . ")";
					if( 0 < $prodnumdays )
					{
						if( $prodfiltertype == 'afterorder' )
						{
							$criteria[] = "regdate='" . date('Y-m-d', mktime(0, 0, 0, date('m'), date('d') - $prodnumdays, date('Y'))) . "'";
						}
						else
						{
							if( $prodfiltertype == 'beforedue' )
							{
								$criteria[] = "nextduedate='" . date('Y-m-d', mktime(0, 0, 0, date('m'), date('d') + $prodnumdays, date('Y'))) . "'";
							}
						}
					}
					if( count($prodstatus) )
					{
						$criteria[] = "domainstatus IN (" . db_build_in_array($prodstatus) . ")";
					}
					if( count($prodexcludepid) && db_build_in_array($prodexcludepid) )
					{
						$criteria[] = "(SELECT COUNT(*) FROM tblhosting h2 WHERE h2.userid=tblhosting.userid AND h2.packageid IN (" . db_build_in_array($prodexcludepid) . ") AND h2.domainstatus='Active')=0";
					}
					if( count($prodexcludeaid) && db_build_in_array($prodexcludeaid) )
					{
						$criteria[] = "(SELECT COUNT(*) FROM tblhostingaddons WHERE tblhostingaddons.hostingid=tblhosting.id AND tblhostingaddons.addonid IN (" . db_build_in_array($prodexcludeaid) . ") AND tblhostingaddons.status='Active')=0";
					}
					if( $marketing )
					{
						$criteria[] = "(SELECT COUNT(*) FROM tblclients h3 WHERE h3.id=tblhosting.userid AND h3.emailoptout = '0')=1";
					}
					$query .= implode(" AND ", $criteria);
				}
				if( count($filteraids) )
				{
					$criteria = array(  );
					$query1 = "SELECT hostingid FROM tblhostingaddons WHERE ";
					$criteria[] = "addonid IN (" . db_build_in_array($filteraids) . ")";
					if( 0 < $prodnumdays )
					{
						if( $prodfiltertype == 'afterorder' )
						{
							$criteria[] = "regdate='" . date('Y-m-d', mktime(0, 0, 0, date('m'), date('d') - $prodnumdays, date('Y'))) . "'";
						}
						else
						{
							if( $prodfiltertype == 'beforedue' )
							{
								$criteria[] = "nextduedate='" . date('Y-m-d', mktime(0, 0, 0, date('m'), date('d') + $prodnumdays, date('Y'))) . "'";
							}
						}
					}
					if( count($prodstatus) )
					{
						$criteria[] = "status IN (" . db_build_in_array($prodstatus) . ")";
					}
					if( count($prodexcludepid) && db_build_in_array($prodexcludepid) )
					{
						$criteria[] = "(SELECT COUNT(*) FROM tblhosting h2 WHERE h2.userid=(SELECT userid FROM tblhosting WHERE tblhosting.id=tblhostingaddons.hostingid) AND h2.packageid IN (" . db_build_in_array($prodexcludepid) . ") AND h2.domainstatus='Active')=0";
					}
					if( count($prodexcludeaid) && db_build_in_array($prodexcludeaid) )
					{
						$criteria[] = "(SELECT COUNT(*) FROM tblhostingaddons h2 WHERE h2.hostingid=tblhostingaddons.hostingid AND tblhostingaddons.addonid IN (" . db_build_in_array($prodexcludeaid) . ") AND tblhostingaddons.status='Active')=0";
					}
					if( $marketing )
					{
						$criteria[] = "(SELECT COUNT(*) FROM tblclients h3 WHERE h3.id=(SELECT userid FROM tblhosting WHERE tblhosting.id=tblhostingaddons.hostingid) AND h3.emailoptout = '0')=1";
					}
					$query1 .= implode(" AND ", $criteria);
				}
			}
		}
		$result2 = select_query('tblemailtemplates', 'name', array( 'id' => $emailtplid ));
		$data = mysqli_fetch_array($result2);
		$emailtplname = $data[0];
		$count = 0;
		if( $query )
		{
			for( $result2 = full_query($query); $data = mysqli_fetch_array($result2); $count++ )
			{
				$id = $data[0];
				sendMessage($emailtplname, $id);
			}
		}
		if( $query1 )
		{
			for( $result2 = full_query($query1); $data = mysqli_fetch_array($result2); $count++ )
			{
				$id = $data[0];
				sendMessage($emailtplname, $id);
			}
		}
		$cron->logActivity("Email Rule \"" . $name . "\" sent to " . $count . " Users", true);
		$cron->emailLogSub("Email Rule \"" . $name . "\" sent to " . $count . " Users");
		$emails = true;
	}
	$cron->emailLog("Processed Email Marketer Rules");
}
if( date('d') == $CONFIG['CCDaySendExpiryNotices'] && $cron->isScheduled('ccexpirynotices') )
{
	$expiryemailcount = 0;
	$expirymonth = date('my', mktime(0, 0, 0, date('m'), date('d'), date('Y')));
	$result = select_query('tblclients', 'id', "cardtype!='' AND status='Active'");
	while( $data = mysqli_fetch_array($result) )
	{
		$userid = $data['id'];
		$cchash = md5($cc_encryption_hash . $userid);
		$result2 = select_query('tblclients', 'id', "id='" . $userid . "' AND AES_DECRYPT(expdate,'" . $cchash . "')='" . $expirymonth . "'");
		$data = mysqli_fetch_array($result2);
		$userid = $data['id'];
		if( $userid )
		{
			sendMessage("Credit Card Expiring Soon", $userid);
			if( !$CONFIG['CCDoNotRemoveOnExpiry'] )
			{
				update_query('tblclients', array( 'cardtype' => '', 'cardlastfour' => '', 'cardnum' => '', 'expdate' => '', 'issuenumber' => '', 'startdate' => '' ), array( 'id' => $userid ));
			}
			$expiryemailcount++;
		}
	}
	$cron->logActivity("Sent " . $expiryemailcount . " Credit Card Expiry Notices", true);
	$cron->emailLog($expiryemailcount . " Credit Card Expiry Notices Sent");
}
if( $CONFIG['UpdateStatsAuto'] && $cron->isScheduled('usagestats') )
{
	ServerUsageUpdate();
	$cron->logActivity('Done', true);
	$cron->emailLog("Updated Disk & Bandwidth Usage Stats");
}
$overagesbillingdate = date('Ymd', mktime(0, 0, 0, date('m') + 1, 0, date('Y')));
if( $overagesbillingdate == date('Ymd') && $cron->isScheduled('overagesbilling') )
{
	if( !function_exists('ModuleBuildParams') )
	{
		require(ROOTDIR . "/includes/modulefunctions.php");
	}
	$invoiceaction = $CONFIG['OverageBillingMethod'];
	if( !$invoiceaction )
	{
		$invoiceaction = '1';
	}
	$result = select_query('tblproducts', 'id,name,overagesenabled,overagesdisklimit,overagesbwlimit,overagesdiskprice,overagesbwprice', array( 'overagesenabled' => array( 'sqltype' => 'NEQ', 'value' => '' ) ));
	while( $data = mysqli_fetch_array($result) )
	{
		$pid = $data['id'];
		$prodname = $data['name'];
		$overagesenabled = $data['overagesenabled'];
		$overagesdisklimit = $data['overagesdisklimit'];
		$overagesbwlimit = $data['overagesbwlimit'];
		$overagesbasediskprice = $data['overagesdiskprice'];
		$overagesbasebwprice = $data['overagesbwprice'];
		$overagesenabled = explode(',', $overagesenabled);
		$result2 = select_query('tblhosting', "tblhosting.*,tblclients.currency", "packageid=" . $pid . " AND (domainstatus='Active' OR domainstatus='Suspended')", '', '', '', "tblclients ON tblclients.id=tblhosting.userid");
		while( $data = mysqli_fetch_array($result2) )
		{
			$serviceid = $data['id'];
			$userid = $data['userid'];
			$currency = $data['currency'];
			$domain = $data['domain'];
			$diskusage = $data['diskusage'];
			$disklimit = $data['disklimit'];
			$bwusage = $data['bwusage'];
			$bwlimit = $data['bwlimit'];
			$result3 = select_query('tblcurrencies', 'rate', array( 'id' => $currency ));
			$data = mysqli_fetch_array($result3);
			$convertrate = $data['rate'];
			if( !$convertrate )
			{
				$convertrate = 1;
			}
			$overagesdiskprice = $overagesbasediskprice * $convertrate;
			$overagesbwprice = $overagesbasebwprice * $convertrate;
			$moduleparams = ModuleBuildParams($serviceid);
			$thisoveragesdisklimit = $overagesdisklimit;
			$thisoveragesbwlimit = $overagesbwlimit;
			if( $moduleparams['customfields']["Disk Space"] )
			{
				$thisoveragesdisklimit = $moduleparams['customfields']["Disk Space"];
			}
			if( $moduleparams['customfields']['Bandwidth'] )
			{
				$thisoveragesbwlimit = $moduleparams['customfields']['Bandwidth'];
			}
			if( $moduleparams['configoptions']["Disk Space"] )
			{
				$thisoveragesdisklimit = $moduleparams['configoptions']["Disk Space"];
			}
			if( $moduleparams['configoptions']['Bandwidth'] )
			{
				$thisoveragesbwlimit = $moduleparams['configoptions']['Bandwidth'];
			}
			$diskunits = 'MB';
			if( $overagesenabled[1] == 'GB' )
			{
				$diskunits = 'GB';
				$diskusage = $diskusage / 1024;
			}
			else
			{
				if( $overagesenabled[1] == 'TB' )
				{
					$diskunits = 'TB';
					$diskusage = $diskusage / (1024 * 1024);
				}
			}
			$bwunits = 'MB';
			if( $overagesenabled[2] == 'GB' )
			{
				$bwunits = 'GB';
				$bwusage = $bwusage / 1024;
			}
			else
			{
				if( $overagesenabled[2] == 'TB' )
				{
					$bwunits = 'TB';
					$bwusage = $bwusage / (1024 * 1024);
				}
			}
			$diskoverage = $diskusage - $thisoveragesdisklimit;
			$bwoverage = $bwusage - $thisoveragesbwlimit;
			$overagedesc = $prodname;
			if( $domain )
			{
				$overagedesc .= " - " . $domain;
			}
			$overagesfrom = fromMySQLDate(date('Y-m-d', mktime(0, 0, 0, date('m'), 1, date('Y'))));
			$overagesto = getTodaysDate();
			$overagedesc .= " (" . $overagesfrom . " - " . $overagesto . ")";
			getUsersLang($userid);
			if( $thisoveragesdisklimit && 0 < $diskoverage )
			{
				if( $diskoverage < 0 )
				{
					$diskoverage = 0;
				}
				$diskoverage = round($diskoverage, 2);
				$diskoveragedesc = $overagedesc . "\n" . $_LANG['overagestotaldiskusage'] . " = " . $diskusage . " " . $diskunits . " - " . $_LANG['overagescharges'] . " = " . $diskoverage . " " . $diskunits . " @ " . $overagesdiskprice . '/' . $diskunits;
				$diskoverageamount = $diskoverage * $overagesdiskprice;
				insert_query('tblbillableitems', array( 'userid' => $userid, 'description' => $diskoveragedesc, 'amount' => $diskoverageamount, 'recur' => 0, 'recurcycle' => 0, 'recurfor' => 0, 'invoiceaction' => $invoiceaction, 'duedate' => date('Y-m-d') ));
			}
			if( $thisoveragesbwlimit && 0 < $bwoverage )
			{
				if( $bwoverage < 0 )
				{
					$bwoverage = 0;
				}
				$bwoverage = round($bwoverage, 2);
				$bwoveragedesc = $overagedesc . "\n" . $_LANG['overagestotalbwusage'] . " = " . $bwusage . " " . $bwunits . " - " . $_LANG['overagescharges'] . " = " . $bwoverage . " " . $bwunits . " @ " . $overagesbwprice . '/' . $bwunits;
				$bwoverageamount = $bwoverage * $overagesbwprice;
				insert_query('tblbillableitems', array( 'userid' => $userid, 'description' => $bwoveragedesc, 'amount' => $bwoverageamount, 'recur' => 0, 'recurcycle' => 0, 'recurfor' => 0, 'invoiceaction' => $invoiceaction, 'duedate' => date('Y-m-d') ));
			}
		}
	}
	$cron->emailLog("Calculated Disk & Bandwidth Overage Charges");
	createInvoices();
}
if( $CONFIG['AutoClientStatusChange'] != '1' && $cron->isScheduled('clientstatussync') )
{
	$result = full_query("SELECT id,lastlogin FROM tblclients WHERE status='Active' AND overrideautoclose='0' AND (SELECT COUNT(id) FROM tblhosting WHERE tblhosting.userid=tblclients.id AND domainstatus IN ('Active','Suspended'))=0" . ($CONFIG['AutoClientStatusChange'] == '3' ? " AND lastlogin<='" . date('Ymd', mktime(0, 0, 0, date('m') - 3, date('d'), date('Y'))) . "'" : ''));
	while( $data = mysqli_fetch_array($result) )
	{
		$userid = $data['id'];
		$result2 = full_query("SELECT (SELECT COUNT(*) FROM tblhosting WHERE userid=tblclients.id AND domainstatus IN ('Active','Suspended'))+(SELECT COUNT(*) FROM tblhostingaddons WHERE hostingid IN (SELECT id FROM tblhosting WHERE userid=tblclients.id) AND status IN ('Active','Suspended'))+(SELECT COUNT(*) FROM tbldomains WHERE userid=tblclients.id AND status IN ('Active')) AS activeservices FROM tblclients WHERE tblclients.id=" . (int) $userid . " LIMIT 1");
		$data = mysqli_fetch_array($result2);
		$totalactivecount = $data[0];
		if( $totalactivecount == 0 )
		{
			update_query('tblclients', array( 'status' => 'Inactive' ), array( 'id' => $userid ));
		}
	}
	$result = full_query("SELECT tblhosting.userid FROM tblhosting INNER JOIN tblclients ON tblclients.id=tblhosting.userid WHERE tblclients.status='Inactive' AND tblclients.overrideautoclose='0' AND tblhosting.domainstatus IN ('Active','Suspended')");
	while( $data = mysqli_fetch_array($result) )
	{
		$userid = $data['userid'];
		update_query('tblclients', array( 'status' => 'Active' ), array( 'id' => $userid ));
	}
	$result = full_query("SELECT tblhosting.userid FROM tblhostingaddons INNER JOIN tblhosting ON tblhosting.id=tblhostingaddons.hostingid INNER JOIN tblclients ON tblclients.id=tblhosting.userid WHERE tblclients.status='Inactive' AND tblclients.overrideautoclose='0' AND tblhostingaddons.status IN ('Active','Suspended')");
	while( $data = mysqli_fetch_array($result) )
	{
		$userid = $data['userid'];
		update_query('tblclients', array( 'status' => 'Active' ), array( 'id' => $userid ));
	}
	$result = full_query("SELECT tbldomains.userid FROM tbldomains INNER JOIN tblclients ON tblclients.id=tbldomains.userid WHERE tblclients.status='Inactive' AND tblclients.overrideautoclose='0' AND tbldomains.status IN ('Active','Pending-Transfer')");
	while( $data = mysqli_fetch_array($result) )
	{
		$userid = $data['userid'];
		update_query('tblclients', array( 'status' => 'Active' ), array( 'id' => $userid ));
	}
	$cron->logActivity('Done', true);
}
$query = "UPDATE tbldomains SET status='Expired' WHERE expirydate<'" . date('Y-m-d') . "' AND expirydate!='00000000' AND status='Active'";
$result = full_query($query);
$cron->logActivity('Completed');
$sendreport = true;
$hookresults = run_hook('DailyCronJobPreEmail', array(  ));
foreach( $hookresults as $hookresult )
{
	if( $hookresult == true )
	{
		$sendreport = false;
	}
}
if( $sendreport )
{
	$cron->emailReport();
}
run_hook('DailyCronJob', array(  ));
$cron->log("Cron Job Hooks Run...");
$clientStatus = $productModules = $domainModules = $invoiceModules = array(  );
$result = @mysqli_query($GLOBALS['whmcsmysql'],"SELECT DISTINCT status, COUNT(id) FROM tblclients GROUP BY status ASC");
if( $result )
{
	while( $data = @mysqli_fetch_array($result) )
	{
		if( is_array($data) && count($data) == 4 )
		{
			$clientStatus[$data[0]] = $data[1];
		}
	}
}
$result = @mysqli_query($GLOBALS['whmcsmysql'],"SELECT DISTINCT(tblproducts.servertype), COUNT(tblhosting.id) FROM tblhosting INNER JOIN tblproducts ON tblproducts.id=tblhosting.packageid WHERE domainstatus='Active' GROUP BY tblproducts.servertype ORDER BY tblproducts.servertype ASC");
if( $result )
{
	while( $data = @mysqli_fetch_array($result) )
	{
		if( is_array($data) && count($data) == 4 )
		{
			$productModules[$data[0]] = $data[1];
		}
	}
}
$result = @mysqli_query($GLOBALS['whmcsmysql'],"SELECT registrar, COUNT(id) FROM tbldomains WHERE status='Active' GROUP BY registrar ORDER BY registrar ASC");
if( $result )
{
	while( $data = @mysqli_fetch_array($result) )
	{
		if( is_array($data) && count($data) == 4 )
		{
			$domainModules[$data[0]] = $data[1];
		}
	}
}
$result = @mysqli_query($GLOBALS['whmcsmysql'],"SELECT paymentmethod, COUNT(id) FROM tblinvoices WHERE status='Paid' GROUP BY paymentmethod ORDER BY paymentmethod ASC");
if( $result )
{
	while( $data = @mysqli_fetch_array($result) )
	{
		if( is_array($data) && count($data) == 4 )
		{
			$invoiceModules[$data[0]] = $data[1];
		}
	}
}
$systemStats = array( 'clientStatus' => $clientStatus, 'productModules' => $productModules, 'domainModules' => $domainModules, 'invoiceModules' => $invoiceModules );
$whmcs->set_config('SystemStatsCache', json_encode($systemStats));
if( $cron->isScheduled('backups') )
{
	$backupdata = $db_name = '';
	require(ROOTDIR . "/configuration.php");
	if( $CONFIG['DailyEmailBackup'] || $CONFIG['FTPBackupHostname'] )
	{
		$cron->logActivity("Starting Backup Generation");
		$backupdata = generateBackup();
		$cron->logActivity("Backup Generation Completed");
	}
	if( $CONFIG['DailyEmailBackup'] )
	{
		$subject = "WHMCS Database Backup";
		try
		{
			$mail = new WHMCS_Mail($CONFIG['SystemEmailsFromName'], $CONFIG['SystemEmailsFromEmail']);
			$mail->Subject = $subject;
			$mail->Body = "Backup File Attached";
			$mail->AddAddress($CONFIG['DailyEmailBackup']);
			$mail->AddStringAttachment($backupdata, $db_name . '_backup_' . date('Ymd_His') . ".zip");
			if( $mail->Send() )
			{
				$cron->logActivity("Email Backup - Sent Successfully");
			}
			else
			{
				$cron->logActivity("Email Backup - Sending Failed - " . $mail->ErrorInfo);
			}
			$mail->ClearAddresses();
		}
		catch( phpmailerException $e )
		{
			logActivity("Database Backup Sending Failed - PHPMailer Exception - " . $e->getMessage() . " (Subject: " . $subject . ")");
		}
	}
	if( $CONFIG['FTPBackupHostname'] )
	{
		$ftp_server = $CONFIG['FTPBackupHostname'];
		$ftp_port = $CONFIG['FTPBackupPort'];
		$ftp_user = $CONFIG['FTPBackupUsername'];
		$ftp_pass = decrypt($CONFIG['FTPBackupPassword']);
		$ftp_filename = $CONFIG['FTPBackupDestination'] . $db_name . '_backup_' . date('Ymd_His') . ".zip";
		if( !$ftp_port )
		{
			$ftp_port = '21';
		}
		$ftp_server = str_replace("ftp://", '', $ftp_server);
		$ftpconnection = ftp_connect($ftp_server, $ftp_port);
		if( !$ftpconnection || !ftp_login($ftpconnection, $ftp_user, $ftp_pass) )
		{
			$cron->logActivity("FTP Backup - Login Failed");
			exit( dirname(__FILE__) . " | line".__LINE__ );
		}
		if( $CONFIG['FTPPassiveMode'] )
		{
			ftp_pasv($ftpconnection, true);
		}
		$tmp = tmpfile();
		fwrite($tmp, $backupdata);
		fseek($tmp, 0);
		$upload = ftp_fput($ftpconnection, $ftp_filename, $tmp, FTP_BINARY);
		if( !$upload )
		{
			$cron->logActivity("FTP Backup - Uploading Failed");
			exit( dirname(__FILE__) . " | line".__LINE__ );
		}
		fclose($tmp);
		ftp_close($ftpconnection);
		$cron->logActivity("FTP Backup - Completed Successfully");
	}
	$cron->log("Backup Complete...");
}
$cron->log("Running After Cron Hooks");
run_hook('AfterCronJob', array(  ));
$cron->log('Goodbye');