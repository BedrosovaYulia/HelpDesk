<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("support");
?><?$APPLICATION->IncludeComponent(
	"bitrix:support.ticket", 
	"bedrosova", 
	array(
		"MESSAGES_PER_PAGE" => "20",
		"MESSAGE_MAX_LENGTH" => "70",
		"MESSAGE_SORT_ORDER" => "asc",
		"SEF_MODE" => "N",
		"SET_PAGE_TITLE" => "Y",
		"SET_SHOW_USER_FIELD" => array(
		),
		"SHOW_COUPON_FIELD" => "N",
		"TICKETS_PER_PAGE" => "10",
		"COMPONENT_TEMPLATE" => "bedrosova",
		"VARIABLE_ALIASES" => array(
			"ID" => "ID",
		)
	),
	false
);?><?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
