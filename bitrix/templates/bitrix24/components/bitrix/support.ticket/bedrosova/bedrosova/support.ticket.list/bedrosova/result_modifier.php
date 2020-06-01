<?
global $USER;
$CurrentUser=$USER->GetLogin();

$TicketTaskMap=array();
$TicketRespMap=array();
$TicketContMap=array();

$arSelect = Array("ID", "IBLOCK_ID", "NAME", "PROPERTY_TICKETID", "PROPERTY_TASKID", "PROPERTY_RESPONSIBLE", "PROPERTY_CONTACTID");
$arFilter = Array("IBLOCK_ID"=>25);
$res = CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);
while($ob = $res->GetNextElement()){ 
	$arFields = $ob->GetFields();  

	$TicketTaskMap[$arFields['PROPERTY_TICKETID_VALUE']]=$arFields['PROPERTY_TASKID_VALUE'];
	$TicketRespMap[$arFields['PROPERTY_TICKETID_VALUE']]=$arFields['PROPERTY_RESPONSIBLE_VALUE'];
	$TicketContMap[$arFields['PROPERTY_TICKETID_VALUE']]=$arFields['PROPERTY_CONTACTID_VALUE'];

}

foreach($arResult['ROWS'] as $key=>$row){
	//$arResult['ROWS'][$key]['data']['OWNER_SID']='';
	//print $row['data']['RESPONSIBLE_LOGIN'];
	//print $row['data']['LAST_MESSAGE_BY_SUPPORT_TEAM'];
	
	if ($row['data']['RESPONSIBLE_LOGIN']==$CurrentUser && $row['data']['LAST_MESSAGE_SID']==$row['data']['OWNER_SID']){
		$arResult['ROWS'][$key]['data']['LAMP']="red";
		$arResult['ROWS'][$key]['data']['~LAMP']="red";
		$arResult['ROWS'][$key]['columns']['LAMP']='<div class="support-lamp-red" title=" last posted by a techsupport client (you are responsible)"></div>';
		//$arResult['ROWS'][$key]['columns']['LAMP']='<div class="support-lamp-yellow" title=" last posted by a techsupport client (you are not responsible)"></div>';	
	}
	
	if ($row['data']['RESPONSIBLE_LOGIN']!=$CurrentUser && $row['data']['LAST_MESSAGE_SID']==$row['data']['OWNER_SID']){
		$arResult['ROWS'][$key]['data']['LAMP']="yellow";
		$arResult['ROWS'][$key]['data']['~LAMP']="yellow";
		//$arResult['ROWS'][$key]['columns']['LAMP']='<div class="support-lamp-red" title=" last posted by a techsupport client (you are responsible)"></div>';
		$arResult['ROWS'][$key]['columns']['LAMP']='<div class="support-lamp-yellow" title=" last posted by a techsupport client (you are not responsible)"></div>';	
	}

	if (!empty($TicketTaskMap)){
		$strTask='';
		if ($TicketTaskMap[$row['data']['ID']]>0)
			$strTask='<a href="/company/personal/user/'.$TicketRespMap[$row['data']['ID']].'/tasks/task/view/'.$TicketTaskMap[$row['data']['ID']].'/">Task>></a>';
		$strCont='';
		if ($TicketContMap[$row['data']['ID']]>0)
			$strCont='<a href="/crm/contact/details/'.$TicketContMap[$row['data']['ID']].'/">Contact>></a>';

		$arResult['ROWS'][$key]['data']['OWNER_SID'].="<br/>".$strTask." ".$strCont;
	}		

}

?>
