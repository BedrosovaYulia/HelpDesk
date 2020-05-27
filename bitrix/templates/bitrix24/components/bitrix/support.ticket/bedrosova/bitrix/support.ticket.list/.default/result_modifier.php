<?
$TicketTaskMap=array();
$TicketRespMap=array();
$TicketContMap=array();

$arSelect = Array("ID", "IBLOCK_ID", "NAME", "PROPERTY_TICKETID", "PROPERTY_TASKID", "PROPERTY_RESPONSIBLE", "PROPERTY_CONTACTID");
$arFilter = Array("IBLOCK_ID"=>18);
$res = CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);
while($ob = $res->GetNextElement()){ 
	$arFields = $ob->GetFields();  

	$TicketTaskMap[$arFields['PROPERTY_TICKETID_VALUE']]=$arFields['PROPERTY_TASKID_VALUE'];
	$TicketRespMap[$arFields['PROPERTY_TICKETID_VALUE']]=$arFields['PROPERTY_RESPONSIBLE_VALUE'];
	$TicketContMap[$arFields['PROPERTY_TICKETID_VALUE']]=$arFields['PROPERTY_CONTACTID_VALUE'];

}

foreach($arResult['ROWS'] as $key=>$row){
	$arResult['ROWS'][$key]['data']['OWNER_SID']='';

	$strTask='';
	if ($TicketTaskMap[$row['data']['ID']]>0)
		$strTask='<a href="/company/personal/user/'.$TicketRespMap[$row['data']['ID']].'/tasks/task/view/'.$TicketTaskMap[$row['data']['ID']].'/">Task >></a>';
	$strCont='';
	if ($TicketContMap[$row['data']['ID']]>0)
		$strCont='<a href="/crm/contact/details/'.$TicketContMap[$row['data']].'/">Contact >></a>';

	$arResult['ROWS'][$key]['data']['OWNER_SID'].=$strTask." ".$strCont;

}
?>
