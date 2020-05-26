<?

AddEventHandler("support", "OnAfterTicketAdd", array("MyClass", "OnAfterTicketUpdateHandler")); //В обоих случаях один и тот же обработчик
AddEventHandler("support", "OnAfterTicketUpdate", array("MyClass", "OnAfterTicketUpdateHandler")); //В обоих случаях один и тот же обработчик

AddEventHandler("tasks", "OnBeforeTaskAdd", array("MyClass", "OnBeforeTaskAddHandler")); 

class MyClass
{
   
   function OnBeforeTaskAddHandler($arFields)
    {
      
	   AddMessage2Log($arFields, "task_add");
    }
   
   function OnAfterTicketAddHandler($arFields)
    {
      
	   AddMessage2Log($arFields, "support_init");
    }
	
	 function OnAfterTicketUpdateHandler($arFields)
    {
       
	   AddMessage2Log($arFields, "support_init_update");
	   
	   //Выбираем данные из хайлоадблока настроек
	   if (CModule::IncludeModule("highloadblock")){
	   
			CModule::IncludeModule("main");
			//Уровни поддержки
			$arSLAIDbyXMLID=array();
			$rsSLA = CUserFieldEnum::GetList(array(), array(
					"USER_FIELD_ID" => 85,
				));
			while($arSLA = $rsSLA->GetNext()){
				$arSLAIDbyXMLID[$arSLA['XML_ID']]=$arSLA['ID'];
			}


			//Критичность
			$arCritIDbyXMLID=array();
			$rsCrit = CUserFieldEnum::GetList(array(), array(
					"USER_FIELD_ID" => 87,
				));
			while($arCrit = $rsCrit->GetNext()){
				$arCritIDbyXMLID[$arCrit['XML_ID']]=$arCrit['ID'];
			}

			//Категория
			$arCatIDbyXMLID=array();
			$rsCat = CUserFieldEnum::GetList(array(), array(
					"USER_FIELD_ID" => 89,
				));
			while($arCat = $rsCat->GetNext()){
				$arCatIDbyXMLID[$arCat['XML_ID']]=$arCat['ID'];
			}

	
			$HLData = \Bitrix\Highloadblock\HighloadBlockTable::getList(array('filter'=>array('TABLE_NAME'=>"ts_time")));
			if ($HLBlock = $HLData->fetch())
				{
					$HLBlock_entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($HLBlock);
					$main_query = new \Bitrix\Main\Entity\Query($HLBlock_entity);
					$main_query->setSelect(array('*'));
				
					
					if (empty($arFields[ 'CATEGORY_ID'])) $arFields[ 'CATEGORY_ID']=0; 
					if (empty($arFields[ 'SLA_ID'])) $arFields[ 'SLA_ID']=1; 
					if (empty($arFields[ 'CRITICALITY_ID'])) $arFields[ 'CRITICALITY_ID']=0; 
					
						$main_query->setFilter(array('UF_TS_LEVEL'=>$arSLAIDbyXMLID[$arFields[ 'SLA_ID']],"UF_SECTION"=>$arCatIDbyXMLID[$arFields[ 'CATEGORY_ID']],"UF_CRITICALITY"=>$arCritIDbyXMLID[$arFields['CRITICALITY_ID']]));
					
					
					 //Выполним запрос
					$res_query = $main_query->exec();

					//Получаем результат по привычной схеме
					$res_query = new CDBResult($res_query);  
					if ($row = $res_query->Fetch())
						{
							$TIME_FOR_RESPONSE=$row['UF_TIME'];
							if ($TIME_FOR_RESPONSE>0){
							
									AddMessage2Log($TIME_FOR_RESPONSE, "support_init_find_in_hl");
									
									global $DB;
									$res_ticket = $DB->Query("select ID, LAST_MESSAGE_BY_SUPPORT_TEAM, LAST_MESSAGE_DATE from b_ticket where ID=".$arFields['ID'], false, $err_mess.__LINE__);
									if ($arTicket = $res_ticket->GetNext()){


											if ($arTicket['LAST_MESSAGE_BY_SUPPORT_TEAM']=='N'){
													
													//Вычисляем новый дедлайн
													
													$new_dedlain=ConvertTimeStamp(CalculateDeadlineHelper::CalculateDeadline($arTicket['LAST_MESSAGE_DATE'],$TIME_FOR_RESPONSE*3600), "FULL", "ru");
													$new_dedlain_for_bd=ConvertDateTime($new_dedlain, "YYYY-MM-DD HH:MI:SS", "ru");
													
													//$new_dedlain=ConvertTimeStamp(MakeTimeStamp($arTicket['LAST_MESSAGE_DATE'], "YYYY-MM-DD HH:MI:SS")+$TIME_FOR_RESPONSE*3600,"FULL","ru");
													//$new_dedlain_for_bd=ConvertDateTime($new_dedlain, "YYYY-MM-DD HH:MI:SS", "ru");

													//Вычисляем новое время для уведомления
													
													$new_notify=$new_dedlain-(15*60);
													$new_notify_for_bd=ConvertDateTime($new_notify, "YYYY-MM-DD HH:MI:SS", "ru");
													
													//$new_notify=ConvertTimeStamp(MakeTimeStamp($arTicket['LAST_MESSAGE_DATE'], "YYYY-MM-DD HH:MI:SS")+$TIME_FOR_RESPONSE*3600-15*60,"FULL","ru");
													//$new_notify_for_bd=ConvertDateTime($new_notify, "YYYY-MM-DD HH:MI:SS", "ru");
													
													//Все нашли - фигачим в базу (потому что отдельного апи для этого нет)
													$res_result= $DB->Query("update b_ticket set SUPPORT_DEADLINE='".$new_dedlain_for_bd."', SUPPORT_DEADLINE_NOTIFY='".$new_notify_for_bd."' where ID=".$arFields['ID'], false, $err_mess.__LINE__);
											
										
													AddMessage2Log($new_dedlain_for_bd, "support_init_new_dedlain_for_bd");
													AddMessage2Log($new_notify_for_bd, "support_init_new_notify_for_bd");
											 }//последний раз в тикете писал клиент

									}//тикет по ID найден
							}//кол-во часов для прибавки найдено и больше 0
						}//Найдена строка настройки
					else{
						AddMessage2Log("Not found", "support_init_not_find_in_hl");
					
					}
	  
				}
		}
    }
}

class CalculateDeadlineHelper
{
//returns MICROTIME or FALSE...
//inputdate must be in 25.09.2015 12:00:00 format
public static function CalculateDeadline($inputdate, $microtime)
	{
		$result=false;
		//$inputdate='25.09.2015 12:00:00';
		$format='d.m.Y. H:i:s';
		$Current_Day=strtotime(substr($inputdate,0,10));
		$Start_Day=strtotime($inputdate)-$Current_Day;
		$day_of_week=date('N', $Current_Day)-1;
		$PeriodWork=$microtime;
		
		//-------DB CONNECT---------

		if(!$DBcon=mysql_connect('localhost', 'root', ''))
			{
				exit;
			}
		else
			{		
				mysql_select_db('sitemanager0',$DBcon);
			}	
			
		$query="SELECT * FROM b_ticket_sla_shedule";
		$execute_query=mysql_query($query,$DBcon);
		$shedule=array();
		$i=0;
		while ($res=mysql_fetch_assoc($execute_query))
			{
				
				foreach ($res as $key=>$value)
				{
					$shedule[$i]=$res;
				}
				$i++;
			}
			
			for($i=0; $i<count($shedule); $i++)
			{
				
				if(strcasecmp($shedule[$i]['OPEN_TIME'],'24H')==0)
				{
					$shedule[$i]['MINUTE_TILL']='1440';			
				}
				$shedule[$i]['MINUTE_TILL']=$shedule[$i]['MINUTE_TILL']*60;
				$shedule[$i]['MINUTE_FROM']=$shedule[$i]['MINUTE_FROM']*60;	
				
				
			}

			//---------------------------
			while ($PeriodWork>0)
			{
				$DayPeriods=self::GetDayPeriods($day_of_week,$Start_Day,$shedule);
				
				foreach($DayPeriods as $period)
				{

					$PeriodWork-=$period['MINUTE_TILL']-$period['MINUTE_FROM'];
					if($PeriodWork<=0) {
						
					$result=$Current_Day+$period['MINUTE_TILL']+$PeriodWork;
					
					Break;
					}
				}
				$Current_Day+=86400;
				$day_of_week++;
				if($day_of_week>6) $day_of_week=0;
				$Start_Day=0;		
			}
return $result;


	}
	
	private static function  GetDayPeriods($DayNum,$Start=0,$shedule)
	{
		$result=array();
		foreach ($shedule as $val)
		{
			if ($val['WEEKDAY_NUMBER']==$DayNum)
				{
					if($val['MINUTE_TILL']>$Start)
					{
						if($val['MINUTE_FROM']<$Start) $val['MINUTE_FROM']=$Start;						
						$result[$val['MINUTE_FROM']]=$val;
					}
					
				}
		}
		ksort($result);
		return $result;
	}
}




?>