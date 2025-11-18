<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}


use \Bitrix\Main\UI\Extension;
use Bitrix\UI\Buttons\Color;
use Bitrix\UI\Toolbar\ButtonLocation;
use Bitrix\UI\Toolbar\Facade\Toolbar;

Extension::load('ui.buttons');
Extension::load('jquery');
//Extension::load('aclips.ui-grid-collapse');
Extension::load(['ui.entity-selector']);

//echo Toolbar::render();

$APPLICATION->IncludeComponent(
	'bitrix:main.ui.grid',
	'',
	[
		'GRID_ID'   	   => $arResult["GRID_ID"],
		'COLUMNS'    	   => $arResult["COLUMNS"],
		'ROWS'       	   => $arResult["ROWS"],
		'SORT'       	   => isset($arParams['SORT']) ? $arParams['SORT'] : array(),
		'NAV_OBJECT' 	   => $arResult['NAV'],
		//'PAGE_SIZES'       => $arResult['PAGE_SIZES'],
		//"CURRENT_PAGE"	   => isset($arResult['CURRENT_PAGE']) ? $arResult['CURRENT_PAGE'] : 1,
		'TOTAL_ROWS_COUNT' => $arResult['TOTAL_ROWS_COUNT'],

		'AJAX_MODE' => 'Y',
		'AJAX_ID'   => \CAjax::getComponentID('bitrix:main.ui.grid', '.default', ''),
		'AJAX_OPTION_JUMP'          => 'N',
		'AJAX_OPTION_HISTORY'       => 'N' ,

		'SHOW_ROW_CHECKBOXES' => false,
		'SHOW_CHECK_ALL_CHECKBOXES' => false,
		'SHOW_ROW_ACTIONS_MENU'     => true,
		'SHOW_GRID_SETTINGS_MENU'   => true,
		'SHOW_NAVIGATION_PANEL'     => true,
		'SHOW_PAGINATION'           => false,

		'SHOW_SELECTED_COUNTER'     => false,
		'SHOW_TOTAL_COUNTER'        => true,
		'SHOW_PAGESIZE'             => false,
		'ALLOW_COLUMNS_SORT'        => false,
		'ALLOW_COLUMNS_RESIZE'      => true,
		'ALLOW_HORIZONTAL_SCROLL'   => true,
		'ALLOW_SORT'                => false,
		'ALLOW_PIN_HEADER'          => true,
	]
);
?>

<?php if (!empty($arParams['AJAX_LOADER'])) { ?>
    <script>
        BX.addCustomEvent('Grid::beforeRequest', function (gridData, argse) {
            if (argse.gridId != '<?=$arResult['GRID_ID'];?>') {
                return;
            }

            argse.method = 'POST'
            argse.data = <?= \Bitrix\Main\Web\Json::encode($arParams['AJAX_LOADER']['data']) ?>
        });
    </script>
<?php } ?>
