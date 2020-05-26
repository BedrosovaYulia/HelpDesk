<?

AddEventHandler("support", "OnAfterTicketAdd", array("MyClass", "OnAfterTicketUpdateHandler")); //В обоих случаях один и тот же обработчик
AddEventHandler("support", "OnAfterTicketUpdate", array("MyClass", "OnAfterTicketUpdateHandler")); //В обоих случаях один и тот же обработчик

AddEventHandler("tasks", "OnBeforeTaskAdd", array("MyClass", "OnBeforeTaskAddHandler")); 

class MyClass
{
   
   function OnBeforeTaskAddHandler($arFields)
    {
	   define("LOG_FILENAME", $_SERVER["DOCUMENT_ROOT"]."/upload/ticket_log.txt");
	   AddMessage2Log($arFields, "task_add");
    }
   

   function OnAfterTicketAddHandler($arFields)
    {
	 
	
	   define("LOG_FILENAME", $_SERVER["DOCUMENT_ROOT"]."/upload/ticket_log.txt");
	   AddMessage2Log($arFields, "support_init");
	   
	   
	   
    }
	
	 function OnAfterTicketUpdateHandler($arFields)
    {
		
		  /*
   Data Example:
   
   array (
  'CLOSE' => 'N',
  'TITLE' => 'test test new',
  'MESSAGE' => '
 
 vbnn nb 
--
Юлия Шеховцова',
  'MESSAGE_AUTHOR_SID' => 'Юлия <shehovcova@list.ru>',
  'MESSAGE_SOURCE_SID' => 'email',
  'MODIFIED_MODULE_NAME' => 'mail',
  'EXTERNAL_ID' => 26,
  'EXTERNAL_FIELD_1' => 'Delivered-To: test@bedrosova.ru
Return-path: <shehovcova@list.ru>
Received: by f452.i.mail.ru with local (envelope-from <shehovcova@list.ru>)id 1jdbtn-0005xD-Bcfor test@bedrosova.ru; Tue, 26 May 2020 18:53:51 +0300
Received: by e.mail.ru with HTTP;Tue, 26 May 2020 18:53:51 +0300
From: Юлия <some_email@list.ru>
To: test@bedrosova.ru
Subject: test test new
MIME-Version: 1.0
X-Mailer: Mail.Ru Mailer 1.0
Date: Tue, 26 May 2020 18:53:51 +0300
Reply-To: Юлия <shehovcova@list.ru>
X-Priority: 3 (Normal)
Message-ID: <1590508431.673976416@f452.i.mail.ru>
Content-Type: multipart/alternative;boundary="--ALT--62a0901EDb3F8AdAF4e4139a7Bd3675A1590508431"
Authentication-Results: f452.i.mail.ru; auth=pass smtp.auth=shehovcova@list.ru smtp.mailfrom=shehovcova@list.ru
X-7564579A: B8F34718100C35BD
X-77F55803: 119C1F4DF6A9251C4499230945D9DCC784E0AA4DC6EC9003115969D73A70FFCBABF6EAE57C0FACE94877F3B648ADDCEB8207895943C10DCEE08825861BC67217978AC9872AA98EF9
X-7FA49CB5: 70AAF3C13DB7016878DA827A17800CE79CD4B9156FA2FBACD82A6BABE6F325ACA6888451BEE1CACABCF491FFA38154B613377AFFFEAFD269A417C69337E82CC2BCF491FFA38154B6C8A9BA7A39EFB7666BA297DBC24807EAC2A783ECEC0211AD725E5C173C3A84C34899019BC014AC0DEA1F7E6F0F101C674E70A05D1297E1BBC6CDE5D1141D2B1C411DBEDE95CBAF0C5DA00C91357FAD25F8D65F5A0756E79F9FA2833FD35BB23D9E625A9149C048EE33AC447995A7AD18C26CFBAC0749D213D2E47CDBA5A96583BD4B6F7A4D31EC0BB23A54CFFDBC96A8389733CBF5DBD5E9D5E8D9A59859A8B652D31B9D28593E51CC7F00164DA146DA6F5DAA56C3B73B23E7DDDDC251EA7DABAAAE862A0553A39223F8577A6DFFEA7CA35FE21102777C0943847C11F186F3C5E7DDDDC251EA7DABCC89B49CDF41148FA8EF81845B15A4842623479134186CDE6BA297DBC24807EABDAD6C7F3747799A
X-C8649E89: 832B869C7E5C2BA14F51D05F21345D167FA4B01750F19C96DE84D33A35B738DBA7FD6275D3EC543C
X-D57D3AED: 3ZO7eAau8CL7WIMRKs4sN3D3tLDjz0dLbV79QFUyzQ2Ujvy7cMT6pYYqY16iZVKkSc3dCLJ7zSJH7+u4VD18S7Vl4ZUrpaVfd2+vE6kuoey4m4VkSEu530nj6fImhcD4MUrOEAnl0W826KZ9Q+tr5+wYjsrrSY/u8Y3PrTqANeitKFiSd6Yd7yPpbiiZ/d5BsxIjK0jGQgCHUM3Ry2Lt2G3MDkMauH3h0dBdQGj+BB/iPzQYh7XS329fgu+/vnDhl394cLg4tENpUfeghdHxXQ==
X-F696D7D5: ldxwl1fEMMtAJTxxxO1wDbr1JLSL03p7yf5dbEjWIYLUFkXSrMTKzQ==
X-Mailru-Sender: 854DA2B808706ED383D92B5B39B602CECCAFCBC55905A3A11BD9F321F92E1B0E99FEC8F7F84A468019D79A19A9AB709F6141F902A1BBEEC0128D89E61688AA6EC003ACAD5D01E9CE250CB99736602231A0314A86C444CF3BB4A721A3011E896F
X-Mras: Ok
X-Spam: undefined
X-Mailru-Intl-Transport: d,41d7dec',
  'CURRENT_USER_ID' => NULL,
  'SITE_ID' => 's1',
  'OWNER_USER_ID' => NULL,
  'OWNER_SID' => 'Юлия <some_email@list.ru>',
  'CREATED_MODULE_NAME' => 'mail',
  'SOURCE_SID' => 'email',
  'CATEGORY_ID' => '22',
  'CRITICALITY_ID' => '5',
  'SOURCE_ID' => '14',
  'MESSAGE_SOURCE_ID' => 14,
  'AUTO_CLOSE_DAYS' => 7,
  'MESSAGE_AUTHOR_USER_ID' => 0,
  'MESSAGE_CREATED_MODULE_NAME' => 'mail',
  'HIDDEN' => 'N',
  'LOG' => 'N',
  'IS_LOG' => 'N',
  'ID' => 5,
  'MID' => 11,
)
   
   */	
		
       define("LOG_FILENAME", $_SERVER["DOCUMENT_ROOT"]."/upload/ticket_log.txt");
	   AddMessage2Log($arFields, "support_init_update");
	   
	   
	   //find contact
	   
	   /*if(\Bitrix\Main\Loader::includeModule('crm')):
	   
	    //CCrmContact::GetList
		//CCrmCompany::GetList
		//CCrmLead::GetList
		//CCrmDeal::GetList

			$arFilter = array();
			$arSelect = array();
			$dbFields = CCrmContact::GetList(array('DATE_CREATE' => 'DESC'), $arFilter, $arSelect, false);
			if($arResultContact = $dbFields->Fetch()) 
			{ 
				AddMessage2Log($arResultContact, "contact finded");
				//creatin task 
				
				
				$task = new \Bitrix\Tasks\Item\Task();

				$task["TITLE"]        = htmlspecialcharsbx($this->request["task_title"]);
				$task["DESCRIPTION"]     = htmlspecialchars($this->request["task_description"]);
				$task["DEADLINE"]       = $date->format("d.m.Y H:i:s");
				$task["CREATED_BY"]      = 1;

				$task["RESPONSIBLE_ID"]  = 1; //responsible shoul be from contact
				$result = $task->save();

				if ($result->isSuccess()){
					$baseTaskId = $task->getID();
				}

				
			}

		endif;*/
	   
	   
	}
	
}
?>
