<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

require_once($_SERVER["DOCUMENT_ROOT"].$componentPath."/functions.php");

if (!CModule::IncludeModule("support"))
{
	ShowError(GetMessage("MODULE_NOT_INSTALL"));
	return;
}

//Permissions
if ( !($USER->IsAuthorized() && (CTicket::IsSupportClient() || CTicket::IsAdmin() || CTicket::IsSupportTeam() || CTicket::IsDemo())) )
	$APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));

global $USER_FIELD_MANAGER;
$arrUF = $USER_FIELD_MANAGER->GetUserFields( "SUPPORT", 0, LANGUAGE_ID );

//Post
$strError = "";

$arParams["TICKET_EDIT_TEMPLATE"] = trim($arParams["TICKET_EDIT_TEMPLATE"]);
$arParams["TICKET_EDIT_TEMPLATE"] = (strlen($arParams["TICKET_EDIT_TEMPLATE"]) > 0 ? htmlspecialcharsbx($arParams["TICKET_EDIT_TEMPLATE"]) : "ticket_edit.php?ID=#ID#");

$arParams['SHOW_COUPON_FIELD'] = (array_key_exists('SHOW_COUPON_FIELD', $arParams) && $arParams['SHOW_COUPON_FIELD'] == 'Y') ? 'Y' : 'N';

if ((strlen($_REQUEST["save"])>0 || strlen($_REQUEST["save_task_me"])>0 || strlen($_REQUEST["save_task_resp"])>0 || strlen($_REQUEST["apply"])>0) && $_SERVER["REQUEST_METHOD"]=="POST" && check_bitrix_sessid())
{
	/*print "<pre>";
	print_r($_REQUEST);
print "</pre>";*/

	//die();

	$ID = intval($_REQUEST["ID"]);

	if ($ID <=0)
	{
		if (strlen(trim($_REQUEST["TITLE"]))<=0){ 
			$strError .= GetMessage("SUP_FORGOT_TITLE")."<br>";
			//$_REQUEST["TITLE"]="Ticket created from Bitrix24";
		}

		if (strlen(trim($_REQUEST["MESSAGE"]))<=0) 
			$strError .= GetMessage("SUP_FORGOT_MESSAGE")."<br>";
		
		if (strlen(trim($_REQUEST["OWNER_SID"]))<=0){ 
			$strError .= "Please fill email in the Author email field"."<br>";
			//$_REQUEST["TITLE"]="Ticket created from Bitrix24";
		}
	}

	$arFILES = array();
	if (is_array($_FILES) && count($_FILES)>0)
	{
		foreach ($_FILES as $key => $arFILE)
		{
			if (strlen($arFILE["name"])>0)
			{
				$arFILE["MODULE_ID"] = "support";
				$arFILES[] = $arFILE;
			}
		}
	}

	if (is_array($arFILES) && count($arFILES)>0)
	{
		$max_size = COption::GetOptionString("support", "SUPPORT_MAX_FILESIZE");
		$max_size = intval($max_size)*1024;

		foreach ($arFILES as $key => $arFILE)
		{
			if (intval($arFILE["size"])>$max_size || intval($arFILE["error"])>0)
				$strError .= str_replace("#FILE_NAME#", $arFILE["name"], GetMessage("SUP_MAX_FILE_SIZE_EXCEEDING"))."<br>";
		}
	}

	$arParams["TICKET_LIST_URL"] = trim($arParams["TICKET_LIST_URL"]);
	$arParams["TICKET_LIST_URL"] = (strlen($arParams["TICKET_LIST_URL"]) > 0 ? htmlspecialcharsbx($arParams["TICKET_LIST_URL"]) : "ticket_list.php");

	if ($strError == "")
	{
		// check before writing,  user access to ticket
		$bSetTicket = false;
		if ($arParams["ID"] > 0) 
		{
			if (CTicket::IsAdmin())
				$bSetTicket = true;
			else
			{
				$rsTicket = CTicket::GetByID($arParams["ID"], SITE_ID, $check_rights = "Y", $get_user_name = "N", $get_extra_names = "N");
				if ($arTicket = $rsTicket->GetNext())
					$bSetTicket = true;
			}
		} 
		else 
		{
			$bSetTicket = true;
		}

		if ($bSetTicket)
		{
			if ($_REQUEST["OPEN"]=="Y")
				$_REQUEST["CLOSE"]="N";
			if ($_REQUEST["CLOSE"]=="Y")
				$_REQUEST["OPEN"]="N";

			$arFields = array(
				'SITE_ID'					=> SITE_ID,
				'CLOSE'						=> $_REQUEST['CLOSE'],
				'TITLE'						=> $_REQUEST['TITLE'],
				'CRITICALITY_ID'			=> $_REQUEST['CRITICALITY_ID'],
				'CATEGORY_ID'				=> $_REQUEST['CATEGORY_ID'],
				'MARK_ID'					=> $_REQUEST['MARK_ID'],
				'MESSAGE'					=> $_REQUEST['MESSAGE'],
				'HIDDEN'					=> 'N',
				'FILES'						=> $arFILES,
				'COUPON'					=> $_REQUEST['COUPON'],
				'PUBLIC_EDIT_URL'			=> $APPLICATION->GetCurPage(),
				'RESPONSIBLE_USER_ID' => $_REQUEST['RESPONSIBLE_USER_ID']
			);

			if (strlen($_REQUEST['OWNER_SID'])>0){
				$arFields['OWNER_SID']=$_REQUEST['OWNER_SID'];

			}


			if (strlen($_REQUEST["save_task_me"])>0){
				global $USER;
				$arFields['RESPONSIBLE_USER_ID']=$USER->GetID();
			}



			foreach( $_REQUEST as $k => $v )
			{
				if( array_key_exists( $k, $arrUF ) )
				{
					$arFields[$k] = $v;
				}
			}
			
			/*print "<pre>";
			print_r($arFields);
			print "</pre>";
			
			die();*/

			$ID = CTicket::SetTicket($arFields, $ID, "Y", $NOTIFY = "Y");


			//**************************************create task if need****************************************

			$rsMessage = CTicket::GetMessageList($by="s_id", $order="asc", array("TICKET_ID" => $ID), $CHECK_RIGHTS="N");

			$FirstMessage="";
			if($arMessage = $rsMessage->GetNext())
			{
				/*print "<pre>";
				print_r($arMessage['~MESSAGE']);
				print "</pre>";*/
				$FirstMessage=$arMessage['~MESSAGE'];
			}

			//die();

			if (strlen($_REQUEST["save_task_me"])>0 ||  strlen($_REQUEST["save_task_resp"])>0){

				$ResponsiblePersonID=$arFields['RESPONSIBLE_USER_ID'];

				CModule::IncludeModule("support");
				$rsTicket=CTicket::GetByID($ID);
				$arTicket = $rsTicket->GetNext();



				$arFields=$arTicket;

				print "<pre>";
				print_r($arFields);
				print "</pre>";


				define("LOG_FILENAME", $_SERVER["DOCUMENT_ROOT"]."/upload/ticket_log.txt");
				AddMessage2Log($arFields, "support_init");

				//Find existing contact
				$pattern = "/[-a-z0-9!#$%&'*_`{|}~]+[-a-z0-9!#$%&'*_`{|}~\.=?]*@[a-zA-Z0-9_-]+[a-zA-Z0-9\._-]+/i";
				$text = $arFields["OWNER_SID"];
				preg_match_all($pattern, $text, $result);
				$r = array_unique(array_map(function ($i) { return $i[0]; }, $result));
				$sEmail=$r[0];

				//print $sEmail."<br/>";

				AddMessage2Log($sEmail, "email");

				$ContactID=-1;


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
					/*$ContactDetails = CCrmContact::GetByID($ContactID);
					if(!empty($ContactDetails))
					{
						$ResponsiblePersonID=$ContactDetails['ASSIGNED_BY_ID']; 	
					}*/
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

					$arNewContactParams['FULL_NAME']=$arFields["~OWNER_SID"];
					$arNewContactParams['LAST_NAME']=$arFields["~OWNER_SID"];
					$arNewContactParams['TYPE_ID'] ='CLIENT';
					$arNewContactParams['ASSIGNED_BY_ID'] = $ResponsiblePersonID;



					$ContactID=$ct->Add($arNewContactParams, true, array('DISABLE_USER_FIELD_CHECK' => true));

					if ($ContactID){
						AddMessage2Log($ContactID, "new contact created");
					}
					else{
						AddMessage2Log($ct->LAST_ERROR, "contact creation error");
					}

				}

				//print $ContactID."<br/>";
				//print $ResponsiblePersonID."<br/>";

				//die();


				//TaskCreation
				if (CModule::IncludeModule("tasks") && $ContactID>0){
					$entities = get_html_translation_table(HTML_ENTITIES);
					$translate = array_flip($entities);
					$string123 = "&amp;";
					$arFieldsTask = Array(
						"TITLE" => "HD#".$arFields['ID'].": ".$arFields['TITLE'],
						"DESCRIPTION" => $FirstMessage,
						"RESPONSIBLE_ID" => $ResponsiblePersonID,
						"UF_CRM_TASK" => array('C_'.$ContactID),
						"GROUP_ID"=>2,
						"UF_TICKET_LINK" => "https://".$_SERVER['SERVER_NAME']."/company/helpdesk/?ID=".$arFields['ID'].strtr($string123, $translate)."edit=1",
						"PRIORITY"=>2,
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


			}



			//***********************end task creation*************************************************


			if (intval($ID)>0)
			{
				if (strlen($_REQUEST["save"])>0)
				{
					LocalRedirect($arParams["TICKET_LIST_URL"]);
				}
				elseif (strlen($_REQUEST["apply"])>0)
				{
					LocalRedirect(
						CComponentEngine::MakePathFromTemplate(
							$arParams["TICKET_EDIT_TEMPLATE"], 
							Array(
								"ID" => $ID
							)
						)
					);
				}
			}
			else 
			{
				$ex = $APPLICATION->GetException();
				if ($ex)
				{
					$strError .= $ex->GetString() . '<br>';
				}
				else 
				{
					$strError .= GetMessage('SUP_ERROR') . '<br>';
				}
			}
		}
		else
		{
			LocalRedirect($arParams["TICKET_LIST_URL"]);
		}
	}
}

//Result array
$arResult = Array(
	"TICKET" => Array(),
	"MESSAGES" => Array(),
	"ONLINE" => Array(),
	"DICTIONARY" => Array(
		"MARK" => Array(),
		"CRITICALITY" => Array(),
		"CRITICALITY_DEFAULT" => "",
		"CATEGORY" => Array(),
		"CATEGORY_DEFAULT" => "",
	),
	"ERROR_MESSAGE" => $strError,
	"REAL_FILE_PATH" => (strlen($_SERVER["REAL_FILE_PATH"]) > 0 ? htmlspecialcharsbx($_SERVER["REAL_FILE_PATH"]) : htmlspecialcharsbx($APPLICATION->GetCurPage())),
	"NAV_STRING" => "",
	"NAV_RESULT" => null,
	"OPTIONS" => Array(
		"ONLINE_INTERVAL" => intval(COption::GetOptionString("support", "ONLINE_INTERVAL")),
		"MAX_FILESIZE" => intval(COption::GetOptionString("support", "SUPPORT_MAX_FILESIZE")),
	),
);

$arParams["ID"] = (intval($arParams["ID"]) > 0 ? intval($arParams["ID"]) : intval($_REQUEST["ID"]));

$UFA = array();
$UFAT = array();

if( isset( $arParams["SET_SHOW_USER_FIELD"] ) )
{
	foreach( $arParams["SET_SHOW_USER_FIELD"] as $k => $v )
	{
		if( strlen( trim( $v ) ) > 0 )
		{
			$UFAT[$v] = array(
							"NAME_C" => $arrUF[$v]["LIST_COLUMN_LABEL"],
							"NAME_F" => $arrUF[$v]["EDIT_FORM_LABEL"],
							"ALL" => $arrUF[$v],
			);
			$UFA[] = $v;
		}
	}
}
$arParams["SET_SHOW_USER_FIELD_T"] = $UFAT;
$rsTicket = CTicket::GetByID($arParams["ID"], SITE_ID, $check_rights = "Y", $get_user_name = "N", $get_extra_names = "N", array( "SELECT" => $UFA ) );

if ($arTicket = $rsTicket->GetNext())
{
	foreach( $UFA as $k => $v )
	{
		$arParams[$v] = $arTicket[$v];
	}
	//+Ticket and user names
	$arResult["TICKET"] = $arTicket +
	_GetUserInfo($arTicket["RESPONSIBLE_USER_ID"], "RESPONSIBLE") +
	_GetUserInfo($arTicket["OWNER_USER_ID"], "OWNER") +
	_GetUserInfo($arTicket["CREATED_USER_ID"], "CREATED") +
	_GetUserInfo($arTicket["MODIFIED_USER_ID"], "MODIFIED_BY");


	//Dictionary table
	$arDictionary = Array(
		"C" => Array("CATEGORY", intval($arTicket["CATEGORY_ID"])),
		"K" => Array("CRITICALITY", intval($arTicket["CRITICALITY_ID"])),
		"S" => Array("STATUS", intval($arTicket["STATUS_ID"])),
		"M" => Array("MARK", intval($arTicket["MARK_ID"])),
		"SR" => Array("SOURCE", intval($arTicket["SOURCE_ID"]))
	);

	//+Ticket dictionary
	$arResult["TICKET"] += _GetDictionaryInfoEx($arDictionary);


	//+Sla
	$arResult["TICKET"]["SLA_NAME"] = $arResult["TICKET"]["SLA_DESCRIPTION"] = "";
	$rsSla = CTicketSLA::GetByID($arTicket["SLA_ID"]);
	if ($rsSla && $arSla = $rsSla->Fetch())
	{
		$arResult["TICKET"]["SLA_NAME"] = htmlspecialcharsbx($arSla["NAME"]);
		$arResult["TICKET"]["SLA_DESCRIPTION"] = htmlspecialcharsbx($arSla["DESCRIPTION"]);
	}

	//Messages files
	$arMessagesFiles = Array();
	$rsFiles = CTicket::GetFileList($v1="s_id", $v2="asc", array("TICKET_ID" => $arParams["ID"]));
	{
		while ($arFile = $rsFiles->Fetch())
		{
			$name = strlen($arFile["ORIGINAL_NAME"])>0 ? $arFile["ORIGINAL_NAME"] : $arFile["FILE_NAME"];
			if (strlen($arFile["EXTENSION_SUFFIX"]) > 0)
			{
				$suffix_length = strlen($arFile["EXTENSION_SUFFIX"]);
				$name = substr($name, 0, strlen($name)-$suffix_length);
			}
			$arMessagesFiles[$arFile["MESSAGE_ID"]][] = array("ID" => $arFile["ID"], "HASH" => $arFile["HASH"], "NAME" => htmlspecialcharsbx($name), "FILE_SIZE" => $arFile["FILE_SIZE"]);
		}
	}

	//+Messages
	$arParams["MESSAGES_PER_PAGE"] = (intval($arParams["MESSAGES_PER_PAGE"]) <= 0 ? 20 : intval($arParams["MESSAGES_PER_PAGE"]));

	$arFilter = Array(
		"TICKET_ID" => $arParams["ID"],
		"TICKET_ID_EXACT_MATCH" => "Y",
		"IS_MESSAGE" => "Y"
	);

	CPageOption::SetOptionString("main", "nav_page_in_session", "N");

	//sort config
	$order = $arParams["MESSAGE_SORT_ORDER"];
	
	$rsMessage = CTicket::GetMessageList($by, $order, $arFilter, $is_filtered, $check_rights = "Y", $get_user_name = "N");
	$rsMessage->NavStart($arParams["MESSAGES_PER_PAGE"]);

	$arResult["NAV_STRING"] = $rsMessage->GetPageNavString(GetMessage("SUP_PAGES"));
	$arResult["NAV_RESULT"] = $rsMessage;

	while ($arMessage = $rsMessage->GetNext())
	{
		if (array_key_exists($arMessage["ID"], $arMessagesFiles)) 
			$arFiles["FILES"] = $arMessagesFiles[$arMessage["ID"]];
		else
			$arFiles["FILES"] = Array();
			
		$arMessage["MESSAGE"] =TxtToHTML(
			$arMessage["~MESSAGE"], 
			$bMakeUrls = true, 
			$iMaxStringLen = $arParams["MESSAGE_MAX_LENGTH"], 
			$QUOTE_ENABLED = "Y", 
			$NOT_CONVERT_AMPERSAND = "N", 
			$CODE_ENABLED = "Y", 
			$BIU_ENABLED ="Y",
			$quote_table_class		= "support-quote-table",
			$quote_head_class		= "support-quote-head",
			$quote_body_class		= "support-quote-body",
			$code_table_class		= "support-code-table",
			$code_head_class		= "support-code-head",
			$code_body_class		= "support-code-body",
			$code_textarea_class	= "support-code-textarea",
			$link_class					= ""
		);

		$arResult["MESSAGES"][] = 
			$arMessage + 
			$arFiles +
			_GetUserInfo($arMessage["OWNER_USER_ID"], "OWNER") +
			_GetUserInfo($arMessage["CREATED_USER_ID"], "CREATED") +
			_GetUserInfo($arMessage["MODIFIED_USER_ID"], "MODIFIED_BY");
	}


	//Online
	CTicket::UpdateOnline($arParams["ID"], $USER->GetID());
	$rsOnline = CTicket::GetOnline($arParams["ID"]);
	while ($arOnline = $rsOnline->GetNext())
	{
		$arResult["ONLINE"][] = $arOnline;
	}

	$ticketSite = $arTicket["SITE_ID"];
	$ticketSla = $arTicket["SLA_ID"];
}
else
{
	$ticketSite = SITE_ID;
	$ticketSla = CTicketSLA::GetForUser();
	$arResult["DICTIONARY"]["CRITICALITY_DEFAULT"] = CTicketDictionary::GetDefault("K", $ticketSite);
	$arResult["DICTIONARY"]["CATEGORY_DEFAULT"] = CTicketDictionary::GetDefault("C", $ticketSite);
}


//Mark, Category, Criticality dictionary list
$ticketDictionary = CTicketDictionary::GetDropDownArray($ticketSite, $ticketSla);
$arResult["DICTIONARY"]["MARK"] = _GetDropDownDictionary("M", $ticketDictionary);
$arResult["DICTIONARY"]["CRITICALITY"] = _GetDropDownDictionary("K", $ticketDictionary);
$arResult["DICTIONARY"]["CATEGORY"] = _GetDropDownDictionary("C", $ticketDictionary);


unset($rsTicket);
unset($rsMessage);
unset($arMessagesFiles);
unset($ticketDictionary);


//Set Title
$arParams["SET_PAGE_TITLE"] = ($arParams["SET_PAGE_TITLE"] == "N" ? "N" : "Y" );

if ($arParams["SET_PAGE_TITLE"] == "Y")
{
	if (empty($arResult["TICKET"]))
		$APPLICATION->SetTitle(GetMessage("SUP_NEW_TICKET_TITLE"));
	else
		$APPLICATION->SetTitle(GetMessage("SUP_EDIT_TICKET_TITLE", array("#ID#" => $arResult["TICKET"]["ID"], "#TITLE#" => $arResult["TICKET"]["TITLE"])));
}

$this->IncludeComponentTemplate();

?>
