<?php


use Bitrix\Main\Engine\Contract\Controllerable;
use \Bitrix\Main\Grid\Options;
use \Bitrix\Main\UI\PageNavigation;
use \Bitrix\Main\Loader;
use \Bitrix\Main\UserTable;
use \Bitrix\Crm\Service;
use Bitrix\Main\UI\Extension;
use Bitrix\Main\UI\Filter\FieldAdapter;
use \Bitrix\Tasks\Internals\TaskTable;

use Bitrix\UI\Toolbar\Facade\Toolbar;
use Bitrix\UI\Toolbar\ButtonLocation;

Extension::load(['ui.entity-selector']);

class TasksList extends \CBitrixComponent implements Controllerable
{
    protected $settings = null;
    protected static array $requiredModules = ['tasks'];
    private const TASK_LINK_TEMPLATE = '{protocol}://{host}/company/personal/user/0/tasks/task/view/{id}/';
    private const USER_LINK_TEMPLATE = '{protocol}://{host}/company/personal/user/{id}/';
    private const TASK_STATUSES = [
        1 => 'Новая',
        2 => 'Ждет выполнения',
        3 => 'Выполняется',
        4 => 'Ждет контроля',
        5 => 'Завершена',
    ];
    private const DEFAULT_STATUS = 'Неопределено';

    public function __construct($component = null)
    {
        parent::__construct($component);
    }
    public function configureActions()
    {
        return [];
    }

    private function getProtocolHost(): array
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return [$protocol, $host];
    }

    private function buildUrl(string $template, array $replacements): string
    {
        return strtr($template, $replacements);
    }

    protected function getFilterParams(): ?array
    {
        return [
            [
                'id'     => 'Employees',
                'name'   => "Сотрудники",
                'addEntityIdToResult'    => true,
                'default' => true,
                'type'   => FieldAdapter::ENTITY_SELECTOR,
                'params' => [
                    'addEntityIdToResult'    => true,
                    'multiple'               => true,
                    'showDialogOnEmptyInput' => true,
                    'dialogOptions'          => [
                        'height'       => 400,
                        //'context'      => "MY_FILTER_CONTEXT",
                        'entities'     => [
                            [
                                'id'      => 'user',
                                'options' => [
                                    'inviteEmployeeLink' => false,
                                    //'selectMode'    => 'usersOnly',
                                    'intranetUsersOnly'  => true
                                ]
                            ],
                        ],
                        'showAvatars'  => true,
                        'dropdownMode' => false,
                    ]
                ]
            ],
            [
                'id'     => 'departments',
                'name'   => "Отделы",
                'addEntityIdToResult'    => true,
                'default' => true,
                'type'   => FieldAdapter::ENTITY_SELECTOR,
                'params' => [
                    'addEntityIdToResult'    => true,
                    'multiple'               => true,
                    'showDialogOnEmptyInput' => true,
                    'dialogOptions'          => [
                        'height'       => 400,
                        //'context'      => "MY_FILTER_CONTEXT",
                        'entities'     => [
                            [
                                'id'      => 'department',
                                'options' => [
                                    'selectMode'    => 'departmentsOnly',
                                ]
                            ],
                        ],
                        'dropdownMode' => false,
                    ]
                ]
            ],
        ];
    }
    public function getData(array $filter = []): array
    {

        if (!Loader::includeModule("tasks")) {
            return [];
        }

        if (!Loader::includeModule("intranet")) {
            $this->logEvent("WARNING", "TasksList::getData - Модуль intranet не загружен");
            return [];
        }

        if (empty($this->arParams['ENTITY_TYPE_ID']) || empty($this->arParams['ELEMENT_ID'])) {
            $this->logEvent("WARNING", "TasksList::getData - Отсутствуют необходимые параметры ENTITY_TYPE_ID или ELEMENT_ID");
            return [];
        }

        try {
            
            $elementType = \CCrmOwnerType::ResolveName($this->arParams['ENTITY_TYPE_ID']);
            $elementId = $this->arParams['ELEMENT_ID'];

            $arFilter = [
                'BINDINGS' => [
                    [
                        'OWNER_ID' => $elementId,
                        'OWNER_TYPE_ID' => $this->arParams['ENTITY_TYPE_ID'],
                        'TYPE_ID' => 6 // задачи
                    ],
                ],
            ];

            $result = CCrmActivity::getList(
                    $arOrder = [], 
                    $arFilter, 
                    $arGroupBy = false, 
                    $arNavStartParams = false, 
                    $arSelectFields = [], 
                    $arOptions = []
                );

            $taskIds = [];
            while ($entry = $result->fetch()) {
                $taskIds[] = $entry['ASSOCIATED_ENTITY_ID'];
            }

            if (empty($taskIds)) {
                return [];
            }
        } catch (Exception $e) {
            $this->logEvent("ERROR", "TasksList::getData - Ошибка в try-catch: " . $e->getMessage());
            return [];
        }

        $arFilter = ['ID' => $taskIds];
        $arSelect = [
            "ID",
            'TITLE',
            'STATUS',
            'RESPONSIBLE_ID',
            'CREATED_BY',
            'DEADLINE',
        ];

        $res = TaskTable::GetList([
            'select' => $arSelect,
            'filter' => $arFilter
        ]);

        $arTasks = [];
        $userIds = [];
        [$protocol, $host] = $this->getProtocolHost();
        $baseReplacements = [
            '{protocol}' => $protocol,
            '{host}' => $host,
        ];

        while ($arTask = $res->fetch()) {
            $arTask['TASK_LINK'] = $this->buildUrl(self::TASK_LINK_TEMPLATE, array_merge($baseReplacements, ['{id}' => $arTask['ID']]));
            $arTask['TEXT_STATUS'] = $this->getTextTaskStatus($arTask['STATUS']);
            $arTasks[$arTask['ID']] = $arTask;
            $userIds[$arTask['RESPONSIBLE_ID']] = true;
            $userIds[$arTask['CREATED_BY']] = true;
        }
        $userList = [];

        if (!empty($userIds)) {
            $userIds = array_keys($userIds);
            $userRes = UserTable::getList([
                'filter' => ['ID' => $userIds],
                'select' => ['ID', 'NAME', 'LAST_NAME']
            ]);


            while ($user = $userRes->fetch()) {
                $userList[$user['ID']] = [
                    'ID' => $user['ID'],
                    'DISPLAY_NAME' => $user['LAST_NAME'] . ' ' . $user['NAME'],
                    'USER_LINK' => $this->buildUrl(self::USER_LINK_TEMPLATE, array_merge($baseReplacements, ['{id}' => $user['ID']]))
                ];
            }
        }
        // Добавляем информацию о пользователях в задачи
        foreach ($arTasks as &$task) {
            if (isset($userList[$task['RESPONSIBLE_ID']])) {
                $task['RESPONSIBLE_NAME'] = $userList[$task['RESPONSIBLE_ID']]['DISPLAY_NAME'];
                $task['RESPONSIBLE_LINK'] = $userList[$task['RESPONSIBLE_ID']]['USER_LINK'];
            }

            if (isset($userList[$task['CREATED_BY']])) {
                $task['CREATED_BY_NAME'] = $userList[$task['CREATED_BY']]['DISPLAY_NAME'];
                $task['CREATED_BY_LINK'] = $userList[$task['CREATED_BY']]['USER_LINK'];
            }
        }
        unset($task);

        return $arTasks;
    }

    protected function getTextTaskStatus(int $status): string
    {
        return self::TASK_STATUSES[$status] ?? self::DEFAULT_STATUS;
    }

    private function logEvent(string $severity, string $description, string $auditTypeId = "TASKS_LIST", string $moduleId = "tasks"): void
    {
        \CEventLog::Add(array(
            "SEVERITY" => $severity,
            "AUDIT_TYPE_ID" => $auditTypeId,
            "MODULE_ID" => $moduleId,
            "DESCRIPTION" => $description,
        ));
    }

    public function executeComponent(array $filter = [])
    {
        $gridData = $this->prepareGridData();
        if ($gridData) {
            $this->includeComponentTemplate();
        }
        else {
            // Если нет данных, все равно отображаем шаблон с пустым GRID
            $this->includeComponentTemplate();
        }
    }

    protected function prepareGridData(): bool
    {
        $arTasks = $this->getData();
        if (!$arTasks) {
            $this->logEvent("WARNING", "TasksList::prepareGridData - Нет задач");
        }

        global $APPLICATION;
        global $USER;

        $APPLICATION->setTitle('Список задач');

        $this->arResult["GRID_ID"] = 'uds_construction_tasks';

        // Добавляем фильтр
        Toolbar::addFilter([
            'FILTER_ID' => 'uds_construction_tasks_filter',
            'GRID_ID' => $this->arResult["GRID_ID"],
            'FILTER' => $this->getFilterParams(),
            'ENABLE_LABEL' => true,
            'FILTER_PRESETS' => [],
        ]);

        $gridOptions = new Options($this->arResult["GRID_ID"]);

        $sort = $gridOptions->getSorting([
            'sort' => ['ID' => 'DESC'],
            'vars' => ['by' => 'by', 'order' => 'order']
        ]);

        $this->arResult['PAGE_SIZES'] = [
            ['NAME' => "5", 'VALUE' => '5'],
            ['NAME' => '10', 'VALUE' => '10'],
            ['NAME' => '20', 'VALUE' => '20'],
            ['NAME' => '50', 'VALUE' => '50'],
            ['NAME' => '100', 'VALUE' => '100']
        ];

        $navOption = $gridOptions->GetNavParams();

        $this->arResult['NAV'] = (new PageNavigation('uds_construction_tasks_nav'))
            ->allowAllRecords(false)
            ->setPageSize($navOption['nPageSize'])
            ->setPageSizes($this->arResult['PAGE_SIZES'])
        ;

        $this->arResult['NAV']->initFromUri();

        $this->arResult["COLUMNS"] = [
            ['id' => 'ID', 'name' => 'ID', 'sort' => 'ID', 'default' => true],
            ['id' => 'task_name', 'name' => 'Задача', 'sort' => false, 'default' => true],
            ['id' => 'created_by', 'name' => 'Постановщик', 'sort' => '', 'default' => true],
            ['id' => 'responsible', 'name' => 'Ответственный', 'sort' => '', 'default' => true],
            ['id' => 'task_stage', 'name' => 'Статус', 'sort' => '', 'default' => true],
            ['id' => 'deadline', 'name' => 'Крайний срок', 'sort' => '', 'default' => true]
        ];

        $i = 1;
        $tasksCounter = 0;
        $rows = [];

        foreach ($arTasks as $task)
        {
            $tasksCounter++;
            $rows[$i] =
                [
                    'id' => "task_row_id_{$i}",
                    'data' => [
                        'ID'          => $task['ID'],
                        'task_name' => "<a href='" . htmlspecialchars($task['TASK_LINK']) . "'>" . htmlspecialchars($task['TITLE']) . "</a>",
                        'responsible' => "<a href='" . htmlspecialchars($task['RESPONSIBLE_LINK']) . "'>" . htmlspecialchars($task['RESPONSIBLE_NAME']) . "</a>",
                        'created_by' => "<a href='" . htmlspecialchars($task['CREATED_BY_LINK']) . "'>" . htmlspecialchars($task['CREATED_BY_NAME']) . "</a>",
                        'task_stage' => $task['TEXT_STATUS'],
                        'deadline'    => $task['DEADLINE']
                    ],
                ];
            $i++;
        }
        $this->arResult["TOTAL_ROWS_COUNT"] = $tasksCounter;
        $this->arResult["ROWS"] = $rows;

        return true;
    }
}
