<?
/*print "<pre>";
print_r($arResult);
print"</pre>";*/

$TicketTaskMap=array();

$arSelect = Array("ID", "IBLOCK_ID", "NAME", "PROPERTY_TICKETID", "PROPERTY_TASKID", "PROPERTY_RESPONSIBLE", "PROPERTY_CONTACTID");
$arFilter = Array("IBLOCK_ID"=>25);
$res = CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);
while($ob = $res->GetNextElement()){ 
	$arFields = $ob->GetFields();  
	$TicketTaskMap[$arFields['PROPERTY_TICKETID_VALUE']]=$arFields['PROPERTY_TASKID_VALUE'];
}

$TicketID=$arResult['TICKET']['ID'];
$TaskID=$TicketTaskMap[$TicketID];

#print $TaskID;
$arResult['TICKET']['TASKID']=$TaskID;


?>
