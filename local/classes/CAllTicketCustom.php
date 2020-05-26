<?
IncludeModuleLangFile(__FILE__);

global $SUPPORT_CACHE_USER_ROLES;
$SUPPORT_CACHE_USER_ROLES  = Array();

CModule::IncludeModule('support');

class CAllTicketCustom extends CTicket
{



	function SetTicket($arFields, $ticketID="", $checkRights="Y", $sendEmailToAuthor="Y", $sendEmailToTechsupport="Y")
	{
		//global $DB;
		//$DB->DebugToFile = true;
		$messageID = null;
		$x = CAllTicketCustom::Set($arFields, $messageID, $ticketID, $checkRights, $sendEmailToAuthor, $sendEmailToTechsupport);
		//$DB->DebugToFile = false;
		return $x;
	}
	
	
	
	function Set($arFields, &$MID, $id="", $checkRights="Y", $sendEmailToAuthor="Y", $sendEmailToTechsupport="Y")
	{						
		
		define("LOG_FILENAME", $_SERVER["DOCUMENT_ROOT"]."/log.txt");
		
		
		global $DB, $APPLICATION, $USER;
		
		$err_mess = (CAllTicket::err_mess()) . "<br>Function: Set<br>Line: ";
		
		$v0 = self::Set_InitVar($arFields, $id, $checkRights, $sendEmailToAuthor, $sendEmailToTechsupport);
		if(!is_array($v0)) return $v0;
		$v = $v0["v"]; /* isNew, CHECK_RIGHTS, SEND_EMAIL_TO_AUTHOR, SEND_EMAIL_TO_TECHSUPPORT, bAdmin, bSupportTeam, bSupportClient, bDemo, bOwner, uid, bActiveCoupon, IsSpam */
		/** @var CSupportTableFields $f */
		$f = $v0["f"]; /* ID, SITE_ID, MODIFIED_GUEST_ID, OWNER_USER_ID, OWNER_SID, HOLD_ON, IS_SPAM */

		// если модифицируем обращение то
		if(!$v->isNew)
		{
			unset($arFields['COUPON']);
			$arFields['ID'] = $f->ID;
			$arFields = CTicket::ExecuteEvents('OnBeforeTicketUpdate', $arFields, false);
			$v->closeDate = (isset($arFields["CLOSE"]) && $arFields["CLOSE"] == "Y"); //$close
			
			// запоминаем предыдущие важные значения
			$v->arrOldFields = array();
			$arr = array(
				"TITLE" => "T.TITLE",
				"RESPONSIBLE_USER_ID" => "T.RESPONSIBLE_USER_ID",
				"SLA_ID" => "T.SLA_ID",
				"CATEGORY_ID" => "T.CATEGORY_ID",
				"CRITICALITY_ID" => "T.CRITICALITY_ID",
				"STATUS_ID" => "T.STATUS_ID",
				"MARK_ID" => "T.MARK_ID",
				"DIFFICULTY_ID" => "T.DIFFICULTY_ID",
				"DATE_CLOSE" => "T.DATE_CLOSE",
				"HOLD_ON" => "T.HOLD_ON",
				"RESPONSE_TIME" => "S.RESPONSE_TIME",
				"RESPONSE_TIME_UNIT" => "S.RESPONSE_TIME_UNIT"
				);
			$str = "T.ID";
			foreach ($arr as $s) $str .= "," . $s;
			$strSql = "SELECT " . $str . ", SITE_ID FROM b_ticket T LEFT JOIN b_ticket_sla S ON T.SLA_ID = S.ID WHERE T.ID='" . $f->ID . "'";
			$z = $DB->Query($strSql, false, $err_mess . __LINE__);
			if($zr=$z->Fetch())
			{
				$f->SITE_ID = $zr["SITE_ID"];
				if(intval($v->uid) == $zr["RESPONSIBLE_USER_ID"]) $v->bSupportTeam = "Y";
				foreach ($arr as $key=>$s) $v->arrOldFields[$key] = $zr[$key];
			}
						
			$f->FromArray(
				$arFields,
				"SITE_ID,MODIFIED_MODULE_NAME,SLA_ID,SOURCE_ID",
				array(CSupportTableFields::MORE0,CSupportTableFields::NOT_EMTY_STR)
			);

			if (!$f->MODIFIED_MODULE_NAME)
			{
				$f->MODIFIED_MODULE_NAME = '';
			}

			$f->FromArray(
				$arFields,
				"CATEGORY_ID,RESPONSIBLE_USER_ID,STATUS_ID,DIFFICULTY_ID,CRITICALITY_ID,SUPPORT_COMMENTS"
			);
			if (isset($arFields['CHANGE_TITLE']))
			{
				$f->set('TITLE', $arFields['CHANGE_TITLE']);
			}
			$f->set("MODIFIED_USER_ID", $v->uid, array(CSupportTableFields::MORE0));
			$f->setCurrentTime("TIMESTAMP_X");
			if($v->closeDate)
			{
				$f->setCurrentTime("DATE_CLOSE");
			}
						
			// ?remake? {
			$v->IS_GROUP_USER = 'N';
			if($v->bAdmin) $IS_GROUP_USER = 'Y';
			elseif($v->CHECK_RIGHTS == 'Y' && ($v->bSupportClient || $v->bSupportTeam))
			{
				if($v->bSupportTeam) $join_query = '(T.RESPONSIBLE_USER_ID IS NOT NULL AND T.RESPONSIBLE_USER_ID=O.USER_ID)';
				else $join_query = '(T.OWNER_USER_ID IS NOT NULL AND T.OWNER_USER_ID=O.USER_ID)';
				
				$strSql = "SELECT 'x'
				FROM b_ticket T
				INNER JOIN b_ticket_user_ugroup O ON $join_query
				INNER JOIN b_ticket_user_ugroup C ON (O.GROUP_ID=C.GROUP_ID)
				INNER JOIN b_ticket_ugroups G ON (O.GROUP_ID=G.ID)
				WHERE T.ID='" . $f->ID . "' AND C.USER_ID='" . $v->uid . "' AND C.CAN_VIEW_GROUP_MESSAGES='Y' AND G.IS_TEAM_GROUP='" . ($v->bSupportTeam ? "Y" : "N") . "'";
				$z = $DB->Query($strSql);
				if($zr = $z->Fetch()) $v->IS_GROUP_USER = 'Y';
			}
			// }
			
			if(isset($arFields["AUTO_CLOSE_DAYS"]) && intval($arFields["AUTO_CLOSE_DAYS"]) >= 0)
			{
				if (intval($arFields["AUTO_CLOSE_DAYS"]) == 0)
				{
					// get from module settings
					$f->AUTO_CLOSE_DAYS = COption::GetOptionString('support', "DEFAULT_AUTO_CLOSE_DAYS");
				}
				else
				{
					$f->AUTO_CLOSE_DAYS = $arFields["AUTO_CLOSE_DAYS"];
				}
			}

			if(is_array($v->arrOldFields) && is_array($arFields) && $arFields["CLOSE"] == "N" && strlen($v->arrOldFields["DATE_CLOSE"] ) > 0)
			{
				$f->DATE_CLOSE = null;
				$f->REOPEN = "Y";
			}
				
			// Если есть что и мы Аднины или из группы ТП, запишем в базу
			$v->FirstUpdateRes = false;
			
			if($v->bSupportTeam || $v->bAdmin)
			{
				$arFields_i = $f->ToArray(CSupportTableFields::ALL, array(CSupportTableFields::ONLY_CHANGED), true);
				if($v->CHECK_RIGHTS == "N" && isset($arFields["MARK_ID"]) && intval($arFields["MARK_ID"]) > 0)
				{
					$arFields_i["MARK_ID"] = intval($arFields["MARK_ID"]);
				}
				if(count($arFields_i) > 0)
				{
					$v->SupportTeamUpdateRes = $DB->Update("b_ticket", $arFields_i, "WHERE ID='" . $f->ID . "'", $err_mess . __LINE__); //$rows1
					$GLOBALS["USER_FIELD_MANAGER"]->Update("SUPPORT", $f->ID, $arFields);
					
					// если указана отметка о спаме то установим отметку о спаме
					if (strlen($f->IS_SPAM) > 0) CTicket::MarkAsSpam($f->ID, $f->IS_SPAM, $v->CHECK_RIGHTS);
					
					$v->newSLA = (isset($arFields_i["SLA_ID"]) && $v->arrOldFields["SLA_ID"] != $arFields_i["SLA_ID"]);
				}
			}
			elseif($v->bOwner || $v->bSupportClient)
			{
				$arFields_i = $f->ToArray("TIMESTAMP_X,DATE_CLOSE,CRITICALITY_ID,MODIFIED_USER_ID,MODIFIED_GUEST_ID,MODIFIED_MODULE_NAME,REOPEN", array(CSupportTableFields::ONLY_CHANGED), true);
				$arFields_i["MARK_ID"] = intval($arFields["MARK_ID"]);
				if(count($arFields_i) > 0)
				{
					$v->SupportClientUpdateRes = $DB->Update("b_ticket",
												$arFields_i,
												"WHERE ID='" . $f->ID . "' AND (OWNER_USER_ID='" . $v->uid . "' OR CREATED_USER_ID='" . $v->uid . "' OR '" . $v->CHECK_RIGHTS . "'='N' OR '" . $v->IS_GROUP_USER . "'='Y')",
												$err_mess . __LINE__
					);
					$GLOBALS["USER_FIELD_MANAGER"]->Update("SUPPORT", $f->ID, $arFields);
				}
			}
			
			// поля для записи лога
			/*$arFields_log = array(
				"LOG"							=> "Y",
				"MESSAGE_CREATED_USER_ID"		=> $MODIFIED_USER_ID,
				"MESSAGE_CREATED_MODULE_NAME"	=> $MODIFIED_MODULE_NAME,
				"MESSAGE_CREATED_GUEST_ID"		=> $MODIFIED_GUEST_ID,
				"MESSAGE_SOURCE_ID"				=> intval($arFields["SOURCE_ID"])
			);*/
			
			$v->arFields_log = array(
				"LOG"							=> "Y",
				"MESSAGE_CREATED_USER_ID"		=> $f->MODIFIED_USER_ID,
				"MESSAGE_CREATED_MODULE_NAME"	=> $f->MODIFIED_MODULE_NAME,
				"MESSAGE_CREATED_GUEST_ID"		=> $f->MODIFIED_GUEST_ID,
				"MESSAGE_SOURCE_ID"				=> $f->SOURCE_ID
			);
			
			// если необходимо соблюдать права то
			if($v->CHECK_RIGHTS == "Y")
			{
				// если update техподдержки не прошел то
				if(intval($v->SupportTeamUpdateRes) <= 0)
				{
					// убираем из массива исходных значений то что может менять только техподдержка
					unset($v->arrOldFields["RESPONSIBLE_USER_ID"]);
					unset($v->arrOldFields["SLA_ID"]);
					unset($v->arrOldFields["CATEGORY_ID"]);
					unset($v->arrOldFields["DIFFICULTY_ID"]);
					unset($v->arrOldFields["STATUS_ID"]);
				}
				// если update автора не прошел то
				if (intval($v->SupportClientUpdateRes) <=0)
				{
					// убираем из массива исходных значений то что может менять только автор
					unset($v->arrOldFields["MARK_ID"]);
				}
			}
			
			// если состоялся один из updat'ов то
			if(intval($v->SupportTeamUpdateRes) > 0 || intval($v->SupportClientUpdateRes) > 0)
			{
				
				// добавляем сообщение
				$arFields["MESSAGE_CREATED_MODULE_NAME"] = $arFields["MODIFIED_MODULE_NAME"];
				if(is_set($arFields, "IMAGE")) $arFields["FILES"][] = $arFields["IMAGE"];
				$arFiles = null;
				$MID = CTicket::AddMessage($f->ID, $arFields, $arFiles, $v->CHECK_RIGHTS);
				$v->arrFILES = $arFiles;
				$MID = intval($MID);
				
				$dateType = array();
				$dateType["EVENT"] = array(CTicket::UPDATE);
				if($v->newSLA) 
				{
					$dateType["EVENT"][] = CTicket::NEW_SLA;
					$dateType["OLD_SLA_RESPONSE_TIME"] = $v->arrOldFields["RESPONSE_TIME"];
					$dateType["OLD_SLA_RESPONSE_TIME_UNIT"] = $v->arrOldFields["RESPONSE_TIME_UNIT"];
				}
				if($f->REOPEN == "Y") 
				{
					$dateType["EVENT"][] = CTicket::REOPEN;
				}
				//CTicket::UpdateLastParams2($f->ID, $dateType);
				CAllTicketCustom::UpdateLastParamsN($f->ID, $dateType, true, true);

				/*// если обращение закрывали то
				if($v->closeDate)
				{
					// удалим агентов-напоминальщиков и обновим параметры обращения
					CTicketReminder::Remove($f->ID);
				}*/
				
				if(is_array($v->arrOldFields) && is_array($arFields))
				{
					// определяем что изменилось
					$v->arChange = array();
					if ($MID > 0)
					{
						if($arFields["HIDDEN"] != "Y") $v->arChange["MESSAGE"] = "Y";
						else $v->arChange["HIDDEN_MESSAGE"] = "Y";
					}
					if($arFields["CLOSE"] == "Y" && strlen($v->arrOldFields["DATE_CLOSE"]) <= 0)
					{
						$v->arChange["CLOSE"] = "Y";
					}
					elseif($arFields["CLOSE"] == "N" && strlen($v->arrOldFields["DATE_CLOSE"]) > 0)
					{
						$v->arChange["OPEN"] = "Y";
					}
					
					if(array_key_exists("HOLD_ON", $arFields))
					{
						if($v->arrOldFields["HOLD_ON"] == null)
						{
							$v->arrOldFields["HOLD_ON"] = 'N';
						}
						if($arFields["HOLD_ON"] == null)
						{
							$arFields["HOLD_ON"] = 'N';
						}
						if($v->arrOldFields["HOLD_ON"] != $arFields["HOLD_ON"])
						{
							if($arFields["HOLD_ON"] == "Y")
							{
								$v->arChange["HOLD_ON_ON"] = "Y";
							}
							else
							{
								$v->arChange["HOLD_ON_OFF"] = "Y";
							}
							
						}
						unset($v->arrOldFields["HOLD_ON"]);
					}
							
					foreach($v->arrOldFields as $key => $value)
					{
						if(isset($arFields[$key]))
						{
							if ($key === 'TITLE' && $value !== $arFields[$key])
							{
								$v->arChange[$key] = "Y";
							}
							elseif (intval($value) != intval($arFields[$key]))
							{
								$v->arChange[$key] = "Y";
							}
						}
					}
					
					// получим текущие значения обращения
					CTimeZone::Disable();
					$z = CTicket::GetByID($f->ID, $f->SITE_ID, "N");
					CTimeZone::Enable();

					if($zr = $z->Fetch())
					{
						$nf = (object)$zr;
					
						$rsSite = CSite::GetByID($nf->SITE_ID);
						$v->arrSite = $rsSite->Fetch();
						
						self::Set_sendMails($nf, $v, $arFields);
						
						//if ($v->arChange['SLA_ID'] == 'Y' || $v->arChange['OPEN'] == 'Y') CTicketReminder::Update($nf->ID, true);
					}
				}
				
				/*$StrFields=print_r($arFields,true);
				AddMessage2Log($StrFields, "support");*/
		
				CTicket::ExecuteEvents('OnAfterTicketUpdate', $arFields, false);
			}
		}
		else
		{
			// restrict to set SLA_ID directly, allow through events or automatically
			if (isset($arFields['SLA_ID']) && !($v->bSupportTeam || $v->bAdmin || $v->bDemo || $v->bActiveCoupon))
			{
				unset($arFields['SLA_ID']);
			}

			$arFields = CTicket::ExecuteEvents('OnBeforeTicketAdd', $arFields, false);
			if(!$arFields) return false;
			
						
			if(!((strlen(trim($arFields["OWNER_SID"])) > 0 || intval($arFields["OWNER_USER_ID"]) > 0) && ($v->bSupportTeam || $v->bAdmin)))
			{
				$f->OWNER_USER_ID = ($v->uid > 0) ? $v->uid : null;
				$f->OWNER_SID = null;
				$f->OWNER_GUEST_ID = intval($_SESSION["SESS_GUEST_ID"]) > 0 ? intval($_SESSION["SESS_GUEST_ID"]) : null;
			}
						
			$f->FromArray($arFields, "CREATED_USER_ID,CREATED_MODULE_NAME,CATEGORY_ID,STATUS_ID,DIFFICULTY_ID,CRITICALITY_ID,SOURCE_ID,TITLE", array(CSupportTableFields::MORE0,CSupportTableFields::NOT_EMTY_STR));

			if (!$f->CREATED_USER_ID)
			{
				$f->set("CREATED_USER_ID", $v->uid, array(CSupportTableFields::MORE0));
			}

			$f->setCurrentTime("LAST_MESSAGE_DATE,DAY_CREATE,TIMESTAMP_X,DEADLINE_SOURCE_DATE");

			$f->DATE_CREATE = time() + CTimeZone::GetOffset();
			
			// если обращение создается сотрудником техподдержки, администратором или демо пользователем
			if($v->bSupportTeam || $v->bAdmin || $v->Demo)
			{
				$f->FromArray($arFields, "SUPPORT_COMMENTS", array(CSupportTableFields::NOT_EMTY_STR));
			}
			
			if(!self::Set_getCOUPONandSLA($v, $f, $arFields)) return false;
			// $f +SLA_ID $v +V_COUPON +bActiveCoupon
			
			if ($v->bActiveCoupon) $f->COUPON = $v->V_COUPON;
			
			self::Set_getResponsibleUser($v, $f, $arFields);
			// $f +RESPONSIBLE_USER_ID  $v +T_EVENT1 +T_EVENT2 +T_EVENT3
			
			// поля для записи лога
			$v->arFields_log = array(
				"LOG"							=> "Y",
				"MESSAGE_CREATED_USER_ID"		=> $f->CREATED_USER_ID,
				"MESSAGE_CREATED_MODULE_NAME"	=> $f->CREATED_MODULE_NAME,
				"MESSAGE_CREATED_GUEST_ID"		=> $f->MODIFIED_GUEST_ID,
				"MESSAGE_SOURCE_ID"				=> $f->SOURCE_ID
			);
			
			
			$acd0 = intval(COption::GetOptionString("support", "DEFAULT_AUTO_CLOSE_DAYS"));
			$f->AUTO_CLOSE_DAYS = (($acd0 <= 0) ? 7 : $acd0);
			$arFields["AUTO_CLOSE_DAYS"] = $f->AUTO_CLOSE_DAYS;
			
			$arFields_i = $f->ToArray(CSupportTableFields::ALL, array(CSupportTableFields::NOT_NULL,CSupportTableFields::NOT_DEFAULT), true);
			
			//Смотрим, что мы пишем в базу на данном этапе
			
			/*$STR=print_r($arFields_i,true);
			AddMessage2Log("Добавление", "my_module_id");
			AddMessage2Log($STR, "my_module_id");*/
			
			$id = $DB->Insert("b_ticket", $arFields_i, $err_mess . __LINE__);
			if(!($id > 0)) return $id;
			$f->ID = $id;
			$GLOBALS["USER_FIELD_MANAGER"]->Update("SUPPORT", $f->ID, $arFields);
						
			$arFields["MESSAGE_AUTHOR_SID"] = $f->OWNER_SID;
			$arFields["MESSAGE_AUTHOR_USER_ID"] = $f->OWNER_USER_ID;
			$arFields["MESSAGE_CREATED_MODULE_NAME"] = $f->CREATED_MODULE_NAME;
			$arFields["MESSAGE_SOURCE_ID"] = $f->SOURCE_ID;
			$arFields["HIDDEN"] = "N";
			$arFields["LOG"] = "N";
			$arFields["IS_LOG"] = "N";

			if (is_set($arFields, "IMAGE")) $arFields["FILES"][] = $arFields["IMAGE"];
			$arFiles = null;
			$MID = CTicket::AddMessage($f->ID, $arFields, $arFiles, $v->CHECK_RIGHTS);
			$v->arrFILES = $arFiles;
			$MID = intval($MID);
			
			if(intval($MID) > 0)
			{
				//CTicket::UpdateLastParams2($f->ID, array("EVENT"=>array(CTicket::ADD)));
				CAllTicketCustom::UpdateLastParamsN($f->ID, array("EVENT"=>array(CTicket::ADD)), true, true);
				
				// если указана отметка о спаме то установим отметку о спаме
				if (strlen($f->IS_SPAM) > 0) CTicket::MarkAsSpam($f->ID, $f->IS_SPAM, $v->CHECK_RIGHTS);
				
				/********************************************
					$nf - Заново прочитанные из базы поля
				********************************************/

				CTimeZone::Disable();
				$z = CTicket::GetByID($f->ID, $f->SITE_ID, "N", "N");
				CTimeZone::Enable();
				
				if($zr = $z->Fetch())
				{
					$nf = (object)$zr;

					$rsSite = CSite::GetByID($nf->SITE_ID);
					$v->arrSite = $rsSite->Fetch();

					self::Set_sendMails($nf, $v, $arFields);

					// создаем событие в модуле статистики
					if(CModule::IncludeModule("statistic"))
					{
						if(!$v->category_set)
						{
							$v->T_EVENT1 = "ticket";
							$v->T_EVENT2 = "";
							$v->T_EVENT3 = "";
						}
						if(strlen($v->T_EVENT3) <= 0) $v->T_EVENT3 = "http://" . $_SERVER["HTTP_HOST"] . "/bitrix/admin/ticket_edit.php?ID=" . $f->ID . "&lang=" . $v->arrSite["LANGUAGE_ID"];
						CStatEvent::AddCurrent($v->T_EVENT1, $v->T_EVENT2, $v->T_EVENT3);
					}
					
				}
			}
			// !!! ПРОВЕРИТЬ $arFields ТОЧНО ЛИ ВСЕ $arFields[..] = .. ТАКИЕ ЖЕ КАК В ОРИГИНАЛЕ !!!
			$arFields['ID'] = $f->ID;
			$arFields['MID'] = $MID;
			
				/*$StrFields=print_r($arFields,true);
				AddMessage2Log($StrFields, "support");*/
				
			CTicket::ExecuteEvents('OnAfterTicketAdd', $arFields, true);

		}
		return $f->ID;	
	}
	
	
		function UpdateLastParamsN($ticketID, $dateType, $recalculateSupportDeadline = true, $setReopenDefault = true)
	{	
		$err_mess = (CAllTicket::err_mess())."<br>Function: UpdateLastParamsN<br>Line: ";
		global $DB, $USER;
		$ticketID = intval($ticketID);
		if ($ticketID<=0) return;
		
		$arSupportTeam = CTicket::GetSupportTeamAndAdminUsers();
		
		$arFields = array(
			"LAST_MESSAGE_DATE" => "null",
			"LAST_MESSAGE_USER_ID" => "null",
			"LAST_MESSAGE_GUEST_ID" => "null",
			"LAST_MESSAGE_SID" => "null",
			"D_1_USER_M_AFTER_SUP_M" => "null",
			"ID_1_USER_M_AFTER_SUP_M" => "null",
			"LAST_MESSAGE_BY_SUPPORT_TEAM" => "'Y'",
		);
		if ($setReopenDefault)
		{
			$arFields["REOPEN"] = "'N'";
		}

		$DB->StartUsingMasterOnly();
		
		$strSql = "
			SELECT
				T.ID,
				T.SLA_ID,
				T.DATE_CLOSE,
				" . $DB->DateToCharFunction("T.DEADLINE_SOURCE_DATE", "FULL") . " DEADLINE_SOURCE_DATE,
				" . $DB->DateToCharFunction("T.D_1_USER_M_AFTER_SUP_M", "FULL") . " DATE_OLD,
				T.IS_OVERDUE,
				SLA.RESPONSE_TIME_UNIT,
				SLA.RESPONSE_TIME,
				SLA.NOTICE_TIME_UNIT,
				SLA.NOTICE_TIME
			FROM
				b_ticket T
				INNER JOIN b_ticket_sla SLA
					ON T.SLA_ID = SLA.ID
						AND T.ID = $ticketID
			";
		$rs = $DB->Query($strSql, false, $err_mess . __LINE__);
		$arTicket = $rs->Fetch();
		if(!$arTicket)
		{
			$DB->StopUsingMasterOnly();
			return;
		}

		$arMessagesAll = array();
		$arLastMess = null;
		$arFirstUserMessAfterSupportMess = null;
		$allTime = 0;
		$messages = 0;
		$messAfterSupportMess = true;

		$strSql = "
			SELECT
				ID,
				".$DB->DateToCharFunction("DATE_CREATE","FULL")." DATE_CREATE,
				OWNER_USER_ID,
				OWNER_GUEST_ID,
				OWNER_SID,
				TASK_TIME,
				IS_OVERDUE,
				IS_HIDDEN,
				NOT_CHANGE_STATUS
			FROM
				b_ticket_message
			WHERE
				TICKET_ID=$ticketID
			AND(NOT(IS_LOG='Y'))
			ORDER BY
				C_NUMBER
			";
			//NOT_CHANGE_STATUS
			//IS_HIDDEN
			//IS_OVERDUE
			
		$rs = $DB->Query($strSql,false,$err_mess.__LINE__);
		$DB->StopUsingMasterOnly();
		
		while($arM = $rs->Fetch())
		{
			$arMessagesAll[] = $arM;
			if($arM["IS_OVERDUE"] !== 'Y')
			{
				if($arM["IS_HIDDEN"] !== 'Y')
				{
					if($arM["NOT_CHANGE_STATUS"] !== 'Y')
					{
						$arLastMess = $arM;
					}
					$messages++;
				}
				$allTime += intval($arM["TASK_TIME"]);
			}
			if($arM["IS_HIDDEN"] !== 'Y' && $arM["NOT_CHANGE_STATUS"] !== 'Y')
			{
				if(in_array(intval($arM["OWNER_USER_ID"]), $arSupportTeam))
				{
					$arFirstUserMessAfterSupportMess = null;
					$messAfterSupportMess = true;
				}
				elseif($messAfterSupportMess)
				{
					$arFirstUserMessAfterSupportMess = $arM;
					$messAfterSupportMess = false;
				}
			}
		}

		if($arLastMess !== null)
		{
			$arFields["LAST_MESSAGE_USER_ID"] = $arLastMess["OWNER_USER_ID"];
			//if ($changeLastMessageDate)
			//{
				$arFields["LAST_MESSAGE_DATE"] = $DB->CharToDateFunction($arLastMess["DATE_CREATE"]);
			//}
			$arFields["LAST_MESSAGE_GUEST_ID"] = intval($arLastMess["OWNER_GUEST_ID"]);
			$arFields["LAST_MESSAGE_SID"] = "'" . $DB->ForSql($arLastMess["OWNER_SID"], 255) . "'";
		}
		$arFields["MESSAGES"] = $messages;
		$arFields["PROBLEM_TIME"] = $allTime;
		
		if($arFirstUserMessAfterSupportMess !== null)
		{
			$arFields["D_1_USER_M_AFTER_SUP_M"] = $DB->CharToDateFunction($arFirstUserMessAfterSupportMess["DATE_CREATE"]);
			$arFields["ID_1_USER_M_AFTER_SUP_M"] = intval($arFirstUserMessAfterSupportMess["ID"]);
			$arFields["LAST_MESSAGE_BY_SUPPORT_TEAM"] = "'N'";
		}
		
		if(is_array($dateType["EVENT"]) && in_array(CTicket::REOPEN, $dateType["EVENT"]))
		{
			$arFields["DEADLINE_SOURCE_DATE"] = $DB->CharToDateFunction(GetTime(time() + CTimeZone::GetOffset(),"FULL"));
		}
		elseif($arTicket["IS_OVERDUE"] == "Y")
		{
			$recalculateSupportDeadline = false;
		}
		
		$recalculateSupportDeadline = $recalculateSupportDeadline && (intval($arTicket["DATE_CLOSE"]) <= 0) && ($arFields["LAST_MESSAGE_BY_SUPPORT_TEAM"] == "'N'");
		
		if(!$recalculateSupportDeadline)
		{
			if ($arFields["LAST_MESSAGE_BY_SUPPORT_TEAM"] == "'Y'" || intval($arTicket["DATE_CLOSE"]) > 0)
			{
				$arFields["SUPPORT_DEADLINE_NOTIFY"] = "null";
				$arFields["SUPPORT_DEADLINE"] = "null";
				$arFields["IS_OVERDUE"] = "'N'";
				$arFields["IS_NOTIFIED"] = "'N'";
			}
		}
		
		/*AddMessage2Log("апдейт в функции UpdateLastParamsN", "support");
		$StrFields=print_r($arFields,true);
		AddMessage2Log($StrFields, "support");*/
		
		$DB->Update("b_ticket", $arFields, "WHERE ID='" . $ticketID . "'", $err_mess . __LINE__);
		
		if($recalculateSupportDeadline)
		{
			$arTicket["M_ID"] = $arFirstUserMessAfterSupportMess["ID"];
			$arTicket["D_1_USER_M_AFTER_SUP_M"] = $arFirstUserMessAfterSupportMess["DATE_CREATE"];
			
			/*AddMessage2Log("апдейт в функции UpdateLastParamsN", "support");
		$StrFields=print_r($arFields,true);
		AddMessage2Log($StrFields, "support");*/
			
			CTicketReminder::RecalculateSupportDeadlineForOneTicket($arTicket, $arFields, $dateType);
		}
		
		/*
		LAST_MESSAGE_DATE
		LAST_MESSAGE_USER_ID
		LAST_MESSAGE_GUEST_ID
		LAST_MESSAGE_SID
		MESSAGES
		REOPEN
		PROBLEM_TIME
		D_1_USER_M_AFTER_SUP_M
		ID_1_USER_M_AFTER_SUP_M
		LAST_MESSAGE_BY_SUPPORT_TEAM
		
		DEADLINE_SOURCE_DATE
		SUPPORT_DEADLINE_NOTIFY
		SUPPORT_DEADLINE
		IS_OVERDUE
		IS_NOTIFIED
		*/
	
	}
	
}

?>
