<?php //00e57
// *************************************************************************
// *                                                                       *
// * WHMCS - The Complete Client Management, Billing & Support Solution    *
// * Copyright (c) WHMCS Ltd. All Rights Reserved,                         *
// * Version: 5.3.14 (5.3.14-release.1)                                    *
// * BuildId: 0866bd1.62                                                   *
// * Build Date: 28 May 2015                                               *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * Email: info@whmcs.com                                                 *
// * Website: http://www.whmcs.com                                         *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software is furnished under a license and may be used and copied *
// * only  in  accordance  with  the  terms  of such  license and with the *
// * inclusion of the above copyright notice.  This software  or any other *
// * copies thereof may not be provided or otherwise made available to any *
// * other person.  No title to and  ownership of the  software is  hereby *
// * transferred.                                                          *
// *                                                                       *
// * You may not reverse  engineer, decompile, defeat  license  encryption *
// * mechanisms, or  disassemble this software product or software product *
// * license.  WHMCompleteSolution may terminate this license if you don't *
// * comply with any of the terms and conditions set forth in our end user *
// * license agreement (EULA).  In such event,  licensee  agrees to return *
// * licensor  or destroy  all copies of software  upon termination of the *
// * license.                                                              *
// *                                                                       *
// * Please see the EULA file for the full End User License Agreement.     *
// *                                                                       *
// *************************************************************************
define('ADMINAREA', true);
require("../init.php");
$aInt = new WHMCS_Admin("Configure Spam Control");
$aInt->title = $aInt->lang('stspamcontrol', 'stspamcontroltitle');
$aInt->sidebar = 'config';
$aInt->icon = 'spamcontrol';
$aInt->helplink = "Email Piping Spam Control";
if( $action == 'add' )
{
    check_token("WHMCS.admin.default");
    insert_query('tblticketspamfilters', array( 'type' => $type, 'content' => $spamvalue ));
    redir("added=1");
}
if( $action == 'delete' )
{
    check_token("WHMCS.admin.default");
    delete_query('tblticketspamfilters', array( 'id' => $id ));
    redir("deleted=1");
}
ob_start();
$jscode = "function doDelete(id,num) {\nif (confirm(\"" . $aInt->lang('stspamcontrol', 'delsurespamcontrol', 1) . "\")) {\nwindow.location='" . $_SERVER['PHP_SELF'] . "?action=delete&id='+id+'&tabnum='+num+'" . generate_token('link') . "';\n}}";
if( $added )
{
    infoBox($aInt->lang('stspamcontrol', 'spamcontrolupdatedtitle'), $aInt->lang('stspamcontrol', 'spamcontrolupdatedadded'));
}
if( $deleted )
{
    infoBox($aInt->lang('stspamcontrol', 'spamcontrolupdatedtitle'), $aInt->lang('stspamcontrol', 'spamcontrolupdateddel'));
}
echo $infobox;
echo "\n<form method=\"post\" action=\"";
echo $whmcs->getPhpSelf();
echo "?action=add\">\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr><td width=\"20%\" class=\"fieldlabel\"><b>";
echo $aInt->lang('global', 'add');
echo ":</b> ";
echo $aInt->lang('stspamcontrol', 'typeval');
echo "</td><td class=\"fieldarea\"><select name=\"type\"><option value=\"sender\">";
echo $aInt->lang('stspamcontrol', 'sender');
echo "</option><option value=\"subject\">";
echo $aInt->lang('stspamcontrol', 'subject');
echo "</option><option value=\"phrase\">";
echo $aInt->lang('stspamcontrol', 'phrase');
echo "</option></select> <input type=\"text\" name=\"spamvalue\" size=\"50\"> <input type=\"submit\" value=\"";
echo $aInt->lang('stspamcontrol', 'addnewsc');
echo "\" class=\"button\"></td></tr>\n</table>\n</form>\n\n<br>\n\n";
echo $aInt->Tabs(array( $aInt->lang('stspamcontrol', 'tab1'), $aInt->lang('stspamcontrol', 'tab2'), $aInt->lang('stspamcontrol', 'tab3') ));
$nums = array( '0', '1', '2' );
foreach( $nums as $num )
{
    echo "<div id=\"tab" . $num . "box\" class=\"tabbox\">\n  <div id=\"tab_content\">";
    if( $num == 0 )
    {
        $filtertype = 'sender';
    }
    else
    {
        if( $num == 1 )
        {
            $filtertype = 'subject';
        }
        else
        {
            if( $num == 2 )
            {
                $filtertype = 'phrase';
            }
        }
    }
    $result = select_query('tblticketspamfilters', "COUNT(*)", array( 'type' => $filtertype ));
    $data = mysql_fetch_array($result);
    $numrows = $data[0];
    $aInt->sortableTableInit('id', 'ASC');
    $tabledata = '';
    $result = select_query('tblticketspamfilters', '', array( 'type' => $filtertype ), 'content', 'ASC', $page * $limit . ',' . $limit);
    while( $data = mysql_fetch_array($result) )
    {
        $id = $data['id'];
        $content = $data['content'];
        $tabledata[] = array( $content, "<a href=\"#\" onClick=\"doDelete('" . $id . "','" . $num . "');return false\"><img src=\"images/delete.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"" . $aInt->lang('global', 'delete') . "\"></a>" );
    }
    echo $aInt->sortableTable(array( $aInt->lang('fields', 'content'), '' ), $tabledata);
    echo "  </div>\n</div>";
}
$content = ob_get_contents();
ob_end_clean();
$aInt->content = $content;
$aInt->jquerycode = $jquerycode;
$aInt->jscode = $jscode;
$aInt->display();