<?
AddEventHandler("support", "OnAfterTicketAdd", array("HelpDeskExtension", "OnAfterTicketAddHandler")); 
AddEventHandler("support", "OnAfterTicketUpdate", array("HelpDeskExtension", "OnAfterTicketUpdateHandler"));
AddEventHandler("tasks", "OnTaskUpdate", array("HelpDeskExtension", "OnTaskUpdateHandler")); 

class HelpDeskExtension
{
	public static $disableHandler = false;
	
	function GetTicketIDByTask($TaskID,$MapIblockID){
		if(CModule::IncludeModule("iblock"))
			{
					$TicketTaskMap=array();
					$arSelect = Array("ID", "IBLOCK_ID", "NAME", "PROPERTY_TICKETID", "PROPERTY_TASKID", "PROPERTY_RESPONSIBLE", "PROPERTY_CONTACTID");
					$arFilter = Array("IBLOCK_ID"=>$MapIblockID);
					$res = CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);
					while($ob = $res->GetNextElement()){ 
						$arTicketTaskFields = $ob->GetFields();  
						$TicketTaskMap[$arTicketTaskFields['PROPERTY_TASKID_VALUE']]=$arTicketTaskFields['PROPERTY_TICKETID_VALUE'];
					}
				if (isset($TicketTaskMap[$TaskID]))
					return $TicketTaskMap[$TaskID];
				else return -1;
			}
		else return -1;
	}
	
	function GetTaskIDByTicket($TicketID,$MapIblockID){
		if(CModule::IncludeModule("iblock"))
			{
					$TicketTaskMap=array();
					$arSelect = Array("ID", "IBLOCK_ID", "NAME", "PROPERTY_TICKETID", "PROPERTY_TASKID", "PROPERTY_RESPONSIBLE", "PROPERTY_CONTACTID");
					$arFilter = Array("IBLOCK_ID"=>$MapIblockID);
					$res = CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);
					while($ob = $res->GetNextElement()){ 
						$arTicketTaskFields = $ob->GetFields();  
						$TicketTaskMap[$arTicketTaskFields['PROPERTY_TICKETID_VALUE']]=$arTicketTaskFields['PROPERTY_TASKID_VALUE'];
					}

				if (isset($TicketTaskMap[$TicketID]))
					return $TicketTaskMap[$TicketID];
				else return -1;
			}
		else return -1;
	}
	
	function OnAfterTicketAddHandler($arFields)
    {
		
		define("LOG_FILENAME", $_SERVER["DOCUMENT_ROOT"]."/upload/ticket_log.txt");
		AddMessage2Log($arFields, "support_init");
	   
		//Find existing contact
		$pattern = "/[-a-z0-9!#$%&'*_`{|}~]+[-a-z0-9!#$%&'*_`{|}~\.=?]*@[a-zA-Z0-9_-]+[a-zA-Z0-9\._-]+/i";
		$text = $arFields["MESSAGE_AUTHOR_SID"];
		preg_match_all($pattern, $text, $result);
		$r = array_unique(array_map(function ($i) { return $i[0]; }, $result));
		$sEmail=$r[0];
		AddMessage2Log($sEmail, "email");

		$ContactID=-1;
		$ResponsiblePersonID=1;

		CModule::IncludeModule('crm');
		$rsContact = CCrmFieldMulti::GetList(
		   array(),
		   array(
			  'ENTITY_ID' => 'CONTACT', // looking for only on contacts
			  "VALUE" => $sEmail,
		   )
		);
		if($arContact = $rsContact->Fetch()) 
		{
			AddMessage2Log($arContact['ELEMENT_ID'], "first_finded_contact_id");
			$ContactID=$arContact['ELEMENT_ID'];
			//find Contact details
			$ContactDetails = CCrmContact::GetByID($ContactID);
			if(!empty($ContactDetails))
			{
				$ResponsiblePersonID=$ContactDetails['ASSIGNED_BY_ID']; 	
			}
		}
		else{
			//new contact creation

			$ct=new CCrmContact(false);
			$arNewContactParams = array('HAS_PHONE'=>'N');
			$arNewContactParams['HAS_EMAIL']='Y';

			$arNewContactParams['FM']['EMAIL'] = array(
			   'n0' => array(
				'VALUE_TYPE' => 'WORK',
				'VALUE' => $sEmail,
			   )
			  );

			$arNewContactParams['FULL_NAME']=$arFields["MESSAGE_AUTHOR_SID"];
			$arNewContactParams['LAST_NAME']=$arFields["MESSAGE_AUTHOR_SID"];
			$arNewContactParams['TYPE_ID'] ='CLIENT';
			//$arParams['SOURCE_ID']= 'EMAIL';
			//$arParams['OPENED'] = 'Y';


			$ContactID=$ct->Add($arNewContactParams, true, array('DISABLE_USER_FIELD_CHECK' => true));

			if ($ContactID){
				AddMessage2Log($ContactID, "new contact created");
			}
			else{
				AddMessage2Log($ct->LAST_ERROR, "contact creation error");
			}

			$ResponsiblePersonID=1;
		}

		//TaskCreation
		if (CModule::IncludeModule("tasks") && $ContactID>0){
			$arFieldsTask = Array(
				"TITLE" => "Task for ticket ".$arFields['ID'],
				"DESCRIPTION" => $arFields['MESSAGE'].' <a href="/company/support/?ID='.$arFields['ID'].'&edit=1">Ticket>></a>',
				"RESPONSIBLE_ID" => $ResponsiblePersonID,
				"UF_CRM_TASK" => array('C_'.$ContactID),
				"GROUP_ID"=>2
				);

			$obTask = new CTasks;
			$TaskID = $obTask->Add($arFieldsTask);
			$success = ($TaskID>0);

			if($success)
			{
				AddMessage2Log($TaskID, "new task id");
			}
			else
			{
				if($e = $APPLICATION->GetException())
					AddMessage2Log($e->GetString(), "task creation error");
			}

		}//tesk creation end	

		//create an item in the list with task and ticket relationship

		if(CModule::IncludeModule("iblock"))
		   { 
				$el = new CIBlockElement;

				$PROP = array();
				$PROP['TICKETID'] = $arFields['ID'];
				$PROP['TASKID'] = $TaskID; 
				$PROP['RESPONSIBLE'] = $ResponsiblePersonID;    
				$PROP['CONTACTID'] = $ContactID;    				

				$arLoadListArray = Array(
				  "IBLOCK_ID"      => 25,
				  "PROPERTY_VALUES"=> $PROP,
				  "NAME"           => $sEmail." ".date(),
				  );

				if($ListItemID = $el->Add($arLoadListArray))
					AddMessage2Log($ListItemID, "list item created");
				else
					AddMessage2Log($el->LAST_ERROR, "list item creation error");
		   } 

	}//OnAfterTicketAddHandler end

	//closing ticket is task closed

	function OnTaskUpdateHandler($TaskID)
    {
	   
	   //$disableHandler ?
	   
		define("LOG_FILENAME", $_SERVER["DOCUMENT_ROOT"]."/upload/ticket_log.txt");
		AddMessage2Log($TaskID, "task_updated");
		$TicketID=self::GetTicketIDByTask($TaskID,25);
		if ($TicketID>0){
			if (CModule::IncludeModule("tasks"))
			{
				$rsTask = CTasks::GetByID($TaskID);
				if ($arTask = $rsTask->GetNext())
				{	
					AddMessage2Log($arTask['STATUS'], "task_status");
					AddMessage2Log($arTask['RESPONSIBLE_ID'], "task_responsible");
					//change ticket responsible and status
					if (CModule::IncludeModule("support")){
						$arFields = array(
							"RESPONSIBLE_USER_ID" => $arTask['RESPONSIBLE_ID'],
							//"CLOSE" => "Y",
							);	
						if ($arTask['STATUS']==CTasks::STATE_COMPLETED)
							$arFields["CLOSE"]="Y";
						else $arFields["CLOSE"]="N";	
						$$arFields["RESPONSIBLE_USER_ID"]=$arTask['RESPONSIBLE_ID'];
						CTicket::Set($arFields, $MID, $id=$TicketID, $checkRights="N", $sendEmailToAuthor="N", $sendEmailToTechsupport="N");
						AddMessage2Log($arFields, "ticket_updated_after_task_updated");
						//updating responsible in List
							
							
					}//if support module end
				}// if task details end
			}// if module tasks end
		}//If the task is attached to a ticket end  
		else{
			AddMessage2Log("ticket not found", "ticket_not_found_after_task_updated");
		}
    }//OnTaskUpdateHandler end

	//closing task if ticket closed

	function OnAfterTicketUpdateHandler($arFields)
    {
		if (self::$disableHandler)
            return;
		
		$TaskID=self::GetTaskIDByTicket((int)$arFields['ID'],25);
		if ($TaskID>0){
			
			define("LOG_FILENAME", $_SERVER["DOCUMENT_ROOT"]."/upload/ticket_log.txt");
			AddMessage2Log($arFields, "support_init_update");

			//get ticket details
			$ResponsiblePersonID=1;
			if (CModule::IncludeModule("support")){
				$rsTicket=CTicket::GetByID((int)$arFields['ID']);
				if ($arTicket = $rsTicket->GetNext()){
						$ResponsiblePersonID=$arTicket['RESPONSIBLE_USER_ID'];
						AddMessage2Log($arTicket['RESPONSIBLE_USER_ID'], "responsible from ticket");
					}
				}
			
			if (CModule::IncludeModule('tasks')){
			
				if ($arFields['CLOSE'] == 'Y'){ //closing task						
					$arTaskFields = array('STATUS' => CTasks::STATE_COMPLETED); 
				}
				else{ //opening task	
					$arTaskFields = array('STATUS' => CTasks::STATE_IN_PROGRESS); 
				}
				
				$arTaskFields['RESPONSIBLE_ID']=$ResponsiblePersonID;
				
				self::$disableHandler = true;
				$oTaskItem = CTaskItem::getInstance($TaskID, 1);
				$oTaskItem->update($arTaskFields);
							
				//add responsible updating in the list
				
			}// if module task end
		}//TaskID>0 end
		else{
			AddMessage2Log("task not found", "task_not_found_after_ticket_updated");
		}
    }

}// MyClass end
?>
