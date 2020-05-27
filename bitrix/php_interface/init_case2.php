<?

AddEventHandler("support", "OnAfterTicketAdd", array("MyClass", "OnAfterTicketAddHandler")); 
//AddEventHandler("support", "OnAfterTicketUpdate", array("MyClass", "OnAfterTicketUpdateHandler"));
//AddEventHandler("tasks", "OnBeforeTaskAdd", array("MyClass", "OnBeforeTaskAddHandler")); 

class MyClass
{
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
			
			AddMessage2Log($ContactID, "new contact created");
			$ResponsiblePersonID=1;
		}
		
		//TaskCreation
		if (CModule::IncludeModule("tasks") && $ContactID>0){
			$arFieldsTask = Array(
				"TITLE" => "Task for ticket ".$arFields['ID'],
				"DESCRIPTION" => $arFields['MESSAGE']." /company/support/?ID=".$arFields['ID']."&edit=1",
				"RESPONSIBLE_ID" => $ResponsiblePersonID,
				"UF_CRM_TASK" => array('C_'.$ContactID)
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
		
	}//OnAfterTicketAddHandler end
	
	//closing ticket is task closed
	
		/*function OnBeforeTaskAddHandler($arFields)
    {
	   define("LOG_FILENAME", $_SERVER["DOCUMENT_ROOT"]."/upload/ticket_log.txt");
	   AddMessage2Log($arFields, "task_add");
    }*/

	//closing task if ticket closed
	
	/*function OnAfterTicketUpdateHandler($arFields)
    {
	   define("LOG_FILENAME", $_SERVER["DOCUMENT_ROOT"]."/upload/ticket_log.txt");
	   AddMessage2Log($arFields, "support_init_update");   
    }*/
	
}// MyClass end
