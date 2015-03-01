<?require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

// Переменные настроек
// $site_id = "s1"; // ID сайта интернет-магазина
// $sleep_time = 2; // пауза между отправлениями через SendImmediate() - при отсутствии отправка через Send()
$send_step_quantity = 10; // количество писем "за раз" при отправке через SendImmediate()
$is_HTML = true; // флаг того, что почтовое отправление в формате HTML
$month_ago_date = date("d.m.Y 00:00:00", strtotime("-30 days")); // начальное время актуальности данных
$eventType = "DELAYED_LIST"; // тип почтового события
// $sendCopy = false; // если не нужно отправлять копию писем на E-mail для копий писем, то установить $sendCopy в false или аналогичное значение
// $messageID = ""; // ID почтового шаблона

// Установка константы ID сайта
if (!defined("SITE_ID")) {
	if (!empty($site_id)) {
		define("SITE_ID", $site_id);
	} else {
		$sites_res = CSite::GetList($by="sort", $order="desc", Array("ACTIVE" => "Y"));
		if ($sites_ar = $sites_res->Fetch()) {
			define("SITE_ID", $sites_ar['LID']);
		}
	}
}


if (CModule::IncludeModule('sale') && defined("SITE_ID")) {

	// Подготавливаем список пользователей
	$users = array();
	$buyers_list = array();
	$users_res = CUser::GetList(($by="sort"), ($order="asc"), array("ACTIVE" => "Y"), array("SELECT" => array("ID", "NAME", "LAST_NAME", "EMAIL")));
	while ($users_ar = $users_res->Fetch()) {
		$fuser_ar = CSaleUser::GetList(array("USER_ID" => $users_ar['ID']));
		if (is_array($fuser_ar) && $fuser_ar['ID']) {
			$buyers_list[$fuser_ar['ID']] = array("ID" => $fuser_ar['ID'], "EMAIL" => $users_ar['EMAIL'], "NAME" => $users_ar['NAME'] . ' ' . $users_ar['LAST_NAME']);
			$fuser_ids[] = $fuser_ar['ID'];
		}
	}
	
	// Подготавливаем список ID всех купленных пользователями товаров
	$bought_items_id_list = array();
	$bought_res = CSaleBasket::GetList(array(), array("LID" => SITE_ID, "!ORDER_ID" => false, ">=DATE_UPDATE" => $month_ago_date), false, false, array("PRODUCT_ID", "NAME", "FUSER_ID"));
	while ($bought_ar = $bought_res->Fetch()) {
		$bought_items_id_list[$bought_ar['FUSER_ID']][] = $bought_ar['PRODUCT_ID'];
	}
	
	// Подготавливаем список отложенных товаров для каждого пользователя
	$items = array();
	$basket_items_res = CSaleBasket::GetList(
		array("NAME" => "ASC", "ID" => "ASC"),
		array("FUSER_ID" => $fuser_ids, "LID" => SITE_ID, "ORDER_ID" => "NULL", "DELAY" => "Y", ">=DATE_UPDATE" => $month_ago_date),
		false, false, array("PRODUCT_ID", "NAME", "DATE_UPDATE", "FUSER_ID")
	);
	while ($basket_items_ar = $basket_items_res->GetNext()) {
	$items[$basket_items_ar['FUSER_ID']] = "";
		if (!in_array($basket_items_ar['PRODUCT_ID'], $bought_items_id_list[$fuser_id])) {
			$items[$basket_items_ar['FUSER_ID']] .= $basket_items_ar['NAME'] . ' [' . $basket_items_ar['PRODUCT_ID'] . ']' . ($is_HTML ? "<br/>" : "\n");
		}
	}
	
	// Отправляем письма со списками пользователям
	$i = 0;
	if (isset($sleep_time) && $sleep_time > 0) {
		$send_immediate = true;
	} else {
		$send_immediate = false;
	}
	$step = $send_step_quantity > 0 ? intVal($send_step_quantity) : 1;
	foreach ($items as $fuser_id => $item_text) {
		if (strlen($item_text) > 0) {
			$arFields = array(
				"EMAIL" => $buyers_list[$fuser_id]['EMAIL'],
				"NAME" => $buyers_list[$fuser_id]['NAME'],
				"ITEMS" => $item_text,
			);
			if ($send_immediate) {
				$i++;
				CEvent::SendImmediate($eventType, SITE_ID, $arFields, ((isset($sendCopy) && $sendCopy === false) ? "N" : "Y"), $messageID ? $messageID : "");
				if ($i >= $step) {
					sleep($sleep_time);
					$i = 0;
				}
			} else {
				CEvent::Send($eventType, SITE_ID, $arFields, ((isset($sendCopy) && $sendCopy === false) ? "N" : "Y"), $messageID ? $messageID : "");
			}
		}
	}
}

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
