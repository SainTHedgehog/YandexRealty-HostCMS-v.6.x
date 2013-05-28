<?php
/* Описание схемы обмена Яндекс.Недвижимость: http://help.yandex.ru/webmaster/?id=1113400 */

@ini_set('display_errors', 1);
error_reporting(E_ALL);
@set_time_limit(90000);

// Идентификатор магазина
$iShopId = 2;

header("Content-Type: text/xml; charset=UTF-8");

require_once(dirname(__FILE__) . '/' . 'bootstrap.php'); //Подключаем bootstrap.php

echo '<?xml version="1.0" encoding="utf-8"?>'."\n";
echo '<realty-feed xmlns="http://webmaster.yandex.ru/schemas/feed/realty/2010-06">'."\n";
echo '<generation-date>'. date('c') . '</generation-date>'."\n";

$dateTime = Core_Date::timestamp2sql(time());

$oShop = Core_Entity::factory('Shop', $iShopId);

$oShop_Item_Property_List = Core_Entity::factory('Shop_Item_Property_List', $iShopId);

$oSite_Alias = $oShop->Site->getCurrentAlias();

$sShopPath = 'http://' . $oSite_Alias->name . $oShop->Structure->getPath();

$oShop_Items = $oShop->Shop_Items;
$oShop_Items->queryBuilder()
	->where('shortcut_id', '=', 0)
	->where('active', '=', 1)
	->where('siteuser_id', 'IN', array(0,-1))
	->open()
	->where('start_datetime', '<', $dateTime)
	->setOr()
	->where('start_datetime', '=', '0000-00-00 00:00:00')
	->close()
	->setAnd()
	->open()
	->where('end_datetime', '>', $dateTime)
	->setOr()
	->where('end_datetime', '=', '0000-00-00 00:00:00')
	->close()
	->where('price', '>', 0)
	->orderBy('sorting', 'ASC')
	->orderBy('name', 'ASC')
	;

/* Описание параметров, входящих в элемент */
$aListTags = array(
	/* Основные */
	'type',
	'property-type',
	'category',
	'payed-adv',
	'manually-added',
	'not-for-agents',
	'haggle',
	'quality',
	'mortgage',
	'prepayment',
	'rent-pledge',
	'agent-fee',
	'with-pets',
	'with-children',
	'renovation',
	'lot-type',

	/* Описание жилого помещения */
	'new-flat',
	'rooms',
	'rooms-offered',
	'open-plan',
	'rooms-type',
	'phone',
	'internet',
	'room-furniture',
	'kitchen-furniture',
	'television',
	'washing-machine',
	'refrigerator',
	'balcony',
	'bathroom-unit',
	'floor-covering',
	'window-view',

	/* Описание здания  */
	'floors-total',
	'building-name',
	'building-type',
	'building-series',
	'building-state',
	'built-year',
	'ready-quarter',
	'lift',
	'rubbish-chute',
	'is-elite',
	'parking',
	'alarm',
	'ceiling-height',

	/* Для загородной недвижимости */
	'pmg',
	'toilet',
	'shower',
	'kitchen',
	'pool',
	'sauna',
	'heating-supply',
	'water-supply',
	'sewerage-supply',
	'electricity-supply',
	'gas-supply',
);

$aShop_Items = $oShop_Items->findAll(FALSE);

/* Получаем свойства по имени */
$aListProperties = array();
foreach ($aListTags as $tagName)
{
	$aListProperties[$tagName] = $oShop_Item_Property_List->Properties->getByTag_name($tagName);
}

$aLocationTags = array(
	'country',
	'region',
	'district',
	'locality-name',
	'sub-locality-name',
	'non-admin-sub-locality',
	'address',
	'direction',
	'distance',
	'latitude',
	'longitude',
	'metro',
	'name',
	'time-on-transport',
	'time-on-foot',
	'railway-station',
);

/* Получаем свойства по имени */
$aLocationProperties = array();
foreach ($aLocationTags as $locationTagName)
{
	$aLocationProperties[$locationTagName] = $oShop_Item_Property_List->Properties->getByTag_name($locationTagName);
}

/* Информация о площадях объекта */
$aAreaTags = array(
	'area',
	'living-space',
	'kitchen-space',
	'lot-area',
);

foreach ($aAreaTags as $areaTagName)
{
	$aAreaProperties[$areaTagName]['value'] = $oShop_Item_Property_List->Properties->getByTag_name($areaTagName . '-value');
	$aAreaProperties[$areaTagName]['unit'] = $oShop_Item_Property_List->Properties->getByTag_name($areaTagName . '-unit');
}


foreach ($aShop_Items as $oShop_Item)
{
	/* Объявление */
	echo '<offer internal-id="'. $oShop_Item->id . '">'."\n";

		foreach ($aListTags as $tagName)
		{
			$oProperty = $aListProperties[$tagName];

			if (!is_null($oProperty))
			{
				$aPropertyValues = $oProperty->getValues($oShop_Item->id);
				if (isset($aPropertyValues[0]))
				{
					echo '<' . $tagName . '>' . $aPropertyValues[0]->value . '</' . $tagName . '>'."\n";
				}
			}
		}

		echo '<url>' . $sShopPath . $oShop_Item->getPath() . '</url>'."\n";
		echo '<creation-date>' . date('c', Core_Date::sql2timestamp($oShop_Item->datetime)) . '</creation-date>'."\n";
		echo '<expire-date>' . date('c', $oShop_Item->end_datetime == '0000-00-00 00:00:00'
			? time() + 60*60*24*30
			: Core_Date::sql2timestamp($oShop_Item->end_datetime)) . '</expire-date>'."\n";
		echo '<last-update-date>' . date('c', Core_Date::sql2timestamp($oShop_Item->datetime)) . '</last-update-date>'."\n";

		/* Информация о местоположении */
		echo '<location>'."\n";
			foreach ($aLocationTags as $locationTagName)
			{
				$oLocation = $aLocationProperties[$locationTagName];

				if (!is_null($oLocation))
				{
					$aLocationValues = $oLocation->getValues($oShop_Item->id);
					if(isset($aLocationValues[0]))
					{
						echo '<' . $locationTagName . '>' . $aLocationValues[0]->value . '</' . $locationTagName . '>'."\n";
					}
				}
			}
		echo '</location>'."\n";

		/* Информация о продавце */
		echo '<sales-agent>'."\n";
			if ($oShop_Item->Shop_Seller->contact_person != '')
			{
				echo '<name>' . $oShop_Item->Shop_Seller->contact_person . '</name>'."\n";
			}

			echo '<phone>' . $oShop_Item->Shop_Seller->phone . '</phone>'."\n";
			echo '<organization>' . $oShop_Item->Shop_Seller->name . '</organization>'."\n";

			if ($oShop_Item->Shop_Seller->site != '')
			{
				echo '<url>' . $oShop_Item->Shop_Seller->site . '</url>'."\n";
			}

			if ($oShop_Item->Shop_Seller->email != '')
			{
				echo '<email>' . $oShop_Item->Shop_Seller->email . '</email>'."\n";
			}
		echo '</sales-agent>'."\n";

		/* Информация о сделке */
		echo '<price>'."\n";
			echo '<value>' . $oShop_Item->price . '</value>'."\n";
			echo '<currency>' . $oShop_Item->Shop_Currency->code . '</currency>'."\n";
		echo '</price>'."\n";

		if ($oShop_Item->image_large != '')
		{
			echo '<image>' . 'http://' . $oSite_Alias->name . $oShop_Item->getLargeFileHref() . '</image>'."\n";
		}

		if ($oShop_Item->description != '')
		{
			echo '<description>' . $oShop_Item->description . '</description>'."\n";
		}

	foreach ($aAreaTags as $areaTagName)
	{
		echo '<'. $areaTagName . '>'."\n";
		foreach (array('value', 'unit') as $subAreaTagName)
		{
			$oArea = $aAreaProperties[$areaTagName][$subAreaTagName];

			if (!is_null($oArea))
			{
				$aAreaValues = $oArea->getValues($oShop_Item->id);
				if(isset($aAreaValues[0]))
				{
					echo '<' . $areaTagName . '-' . $subAreaTagName . '>' .
						$aAreaValues[0]->value .
					'</' . $areaTagName . '-' . $subAreaTagName . '>'."\n";
				}
			}
		}
	echo '</'. $areaTagName . '>'."\n";
	}
	echo '</offer>'."\n";
}
echo '</realty-feed>'."\n";