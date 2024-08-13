<?php
//Время было в не правильном формате, минуты считал за секунды по дефолту Simple Банит в минутах типо 1-1 минута итд......
//Не правильный запрос был в бд не отображало стату 
namespace Flute\Modules\BansComms\Driver\Items;

use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;
use Flute\Core\Table\TableBuilder;
use Flute\Core\Table\TableColumn;
use Flute\Core\Table\TablePreparation;
use Flute\Modules\BansComms\Contracts\DriverInterface;
use Spiral\Database\Exception\StatementException;
use Spiral\Database\Injection\Fragment;
use Spiral\Database\Injection\Parameter;

class SimpleAdminDriver implements DriverInterface
{
    public function getCommsColumns(TableBuilder $tableBuilder)
    {

        $tableBuilder->addColumn((new TableColumn('type', __('banscomms.table.type')))
            ->setRender("{{ICON_TYPE}}", $this->typeFormatRender()));

        $tableBuilder->addColumn((new TableColumn('user_url'))->setVisible(false));
        $tableBuilder->addCombinedColumn('avatar', 'player_name', __('banscomms.table.loh'), 'user_url', true);

        $tableBuilder->addColumns([
            (new TableColumn('created', __('banscomms.table.created')))->setDefaultOrder()
                ->setRender("{{CREATED}}", $this->dateFormatRender()),
            (new TableColumn('reason', __('banscomms.table.reason')))->setType('text'),
        ]);

        $tableBuilder->addColumn((new TableColumn('admin_url'))->setVisible(false));
        $tableBuilder->addCombinedColumn('admin_avatar', 'admin_name', __('banscomms.table.admin'), 'admin_url', true);

        $tableBuilder->addColumns([
            (new TableColumn('ends', __('banscomms.table.end_date')))->setType('text')
                ->setRender("{{ENDS}}", $this->dateFormatRender()),
            (new TableColumn('duration', ''))->setType('text')->setVisible(false),
            (new TableColumn('', __('banscomms.table.length')))
                ->setSearchable(false)->setOrderable(false)
                ->setRender('{{KEY}}', $this->timeFormatRender()),
        ]);
    }

    public function getBansColumns(TableBuilder $tableBuilder)
    {
        $tableBuilder->addColumn((new TableColumn('user_url'))->setVisible(false));
        $tableBuilder->addCombinedColumn('avatar', 'player_name', __('banscomms.table.loh'), 'user_url', true);

        $tableBuilder->addColumns([
            (new TableColumn('created', __('banscomms.table.created')))->setDefaultOrder()
                ->setRender("{{CREATED}}", $this->dateFormatRender()),
            (new TableColumn('reason', __('banscomms.table.reason')))->setType('text'),
        ]);

        $tableBuilder->addColumn((new TableColumn('admin_url'))->setVisible(false));
        $tableBuilder->addCombinedColumn('admin_avatar', 'admin_name', __('banscomms.table.admin'), 'admin_url', true);

        $tableBuilder->addColumns([
            (new TableColumn('ends', __('banscomms.table.end_date')))->setType('text')
                ->setRender("{{ENDS}}", $this->dateFormatRender()),
            (new TableColumn('duration', ''))->setType('text')->setVisible(false),
            (new TableColumn('', __('banscomms.table.length')))
                ->setSearchable(false)->setOrderable(false)
                ->setRender('{{KEY}}', $this->timeFormatRenderBans()),
        ]);
    }

    private function dateFormatRender(): string
    {
        return '
            function(data, type) {
                if (type === "display") {
                    let date = new Date(data);
                    return ("0" + (date.getMonth() + 1)).slice(-2) + "-" +
                           ("0" + date.getDate()).slice(-2) + "-" +
                           date.getFullYear() + " " +
                           ("0" + date.getHours()).slice(-2) + ":" +
                           ("0" + date.getMinutes()).slice(-2);
                }
                return data;
            }
        ';
    }

    private function typeFormatRender(): string
    {
        return '
            function(data, type) {
                if (type === "display") {
                    return data == "MUTE" ? `<i class="type-icon ph-bold ph-microphone-slash"></i>` : `<i class="type-icon ph-bold ph-chat-circle-dots"></i>`;
                }
                return data;
            }
        ';
    }


private function timeFormatRender(): string
{
    return "
        function(data, type, full) {
            let time = full[12]; // Предполагаем, что время хранится в 12-м элементе массива
            let ends = full[11];

            if (time == '0') {
                return '<div class=\"ban-chip bans-forever\">'+ t(\"banscomms.table.forever\") +'</div>';
            } else if (Date.now() >= new Date(ends) && time != '0') {
                let seconds = time * 60; // Преобразуем минуты в секунды
                return '<div class=\"ban-chip bans-end\">' + secondsToReadable(seconds) + '</div>';
            } else {
                let seconds = time * 60; // Преобразуем минуты в секунды
                return '<div class=\"ban-chip\">' + secondsToReadable(seconds) + '</div>';
            }
        }
    ";
}

private function timeFormatRenderBans(): string
{
    return "
        function(data, type, full) {
            let time = full[11]; // Предполагаем, что время хранится в 11-м элементе массива
            let ends = full[10];

            if (time == '0') {
                return '<div class=\"ban-chip bans-forever\">'+ t(\"banscomms.table.forever\") +'</div>';
            } else if (Date.now() >= new Date(ends) && time != '0') {
                let seconds = time * 60; // Преобразуем минуты в секунды
                return '<div class=\"ban-chip bans-end\">' + secondsToReadable(seconds) + '</div>';
            } else {
                let seconds = time * 60; // Преобразуем минуты в секунды
                return '<div class=\"ban-chip\">' + secondsToReadable(seconds) + '</div>';
            }
        }
    ";
}

    public function getUserBans(
        User $user,
        Server $server,
        string $dbname,
        int $page,
        int $perPage,
        int $draw,
        array $columns = [],
        array $search = [],
        array $order = []
    ): array {
        $steam = $user->getSocialNetwork('Steam') ?? $user->getSocialNetwork('HttpsSteam');

        if (!$steam)
            return [];

        $select = $this->prepareSelectQuery($server, $dbname, $columns, $search, $order, 'bans')
            ->where('bans.player_steamid', (int) $steam->value);

        // Применение пагинации
        $paginator = new \Spiral\Pagination\Paginator($perPage);
        $paginate = $paginator->withPage($page)->paginate($select);

        // Получение данных
        $result = $select->fetchAll();

        $steamIds = $this->getSteamIds64($result);
        $usersData = steam()->getUsers($steamIds);

        $result = $this->mapUsersDataToResult($result, $usersData);

        // Формирование ответа
        return [
            'draw' => $draw,
            'recordsTotal' => $paginate->count(),
            'recordsFiltered' => $paginate->count(),
            'data' => TablePreparation::normalize(
                [
                    'user_url',
                    'avatar',
                    'player_name',
                    '',
                    'created',
                    'reason',
                    'admin_url',
                    'admin_avatar',
                    'admin_name',
                    '',
                    'ends',
                    'duration',
                    ''
                ],
                $result
            )
        ];
    }

    public function getUserComms(
        User $user,
        Server $server,
        string $dbname,
        int $page,
        int $perPage,
        int $draw,
        array $columns = [],
        array $search = [],
        array $order = []
    ): array {
        $steam = $user->getSocialNetwork('Steam') ?? $user->getSocialNetwork('HttpsSteam');

        if (!$steam)
            return [];

        $select = $this->prepareSelectQuery($server, $dbname, $columns, $search, $order, 'mutes');
        $select->where('mutes.player_steamid', (int) $steam->value);

        $paginator = new \Spiral\Pagination\Paginator($perPage);
        $paginate = $paginator->withPage($page)->paginate($select);

        $result = $select->fetchAll();

        $steamIds = $this->getSteamIds64($result);
        $usersData = steam()->getUsers($steamIds);

        $result = $this->mapUsersDataToResult($result, $usersData);

        return [
            'draw' => $draw,
            'recordsTotal' => $paginate->count(),
            'recordsFiltered' => $paginate->count(),
            'data' => TablePreparation::normalize(
                [
                    'type',
                    'user_url',
                    'avatar',
                    'player_name',
                    '',
                    'created',
                    'reason',
                    'admin_url',
                    'admin_avatar',
                    'admin_name',
                    '',
                    'ends',
                    'duration',
                    ''
                ],
                $result
            )
        ];
    }

    public function getComms(
        Server $server,
        string $dbname,
        int $page,
        int $perPage,
        int $draw,
        array $columns = [],
        array $search = [],
        array $order = []
    ): array {
        $select = $this->prepareSelectQuery($server, $dbname, $columns, $search, $order, 'mutes');

        $paginator = new \Spiral\Pagination\Paginator($perPage);
        $paginate = $paginator->withPage($page)->paginate($select);

        $result = $select->fetchAll();

        $steamIds = $this->getSteamIds64($result);
        $usersData = steam()->getUsers($steamIds);

        $result = $this->mapUsersDataToResult($result, $usersData);

        return [
            'draw' => $draw,
            'recordsTotal' => $paginate->count(),
            'recordsFiltered' => $paginate->count(),
            'data' => TablePreparation::normalize(
                [
                    'type',
                    'user_url',
                    'avatar',
                    'player_name',
                    '',
                    'created',
                    'reason',
                    'admin_url',
                    'admin_avatar',
                    'admin_name',
                    '',
                    'ends',
                    'duration',
                    ''
                ],
                $result
            )
        ];
    }

    public function getBans(
        Server $server,
        string $dbname,
        int $page,
        int $perPage,
        int $draw,
        array $columns = [],
        array $search = [],
        array $order = []
    ): array {
        $select = $this->prepareSelectQuery($server, $dbname, $columns, $search, $order);

        $paginator = new \Spiral\Pagination\Paginator($perPage);
        $paginate = $paginator->withPage($page)->paginate($select);

        $result = $select->fetchAll();

        $steamIds = $this->getSteamIds64($result);
        $usersData = steam()->getUsers($steamIds);

        $result = $this->mapUsersDataToResult($result, $usersData);

        return [
            'draw' => $draw,
            'recordsTotal' => $paginate->count(),
            'recordsFiltered' => $paginate->count(),
            'data' => TablePreparation::normalize(
                [
                    'user_url',
                    'avatar',
                    'player_name',
                    '',
                    'created',
                    'reason',
                    'admin_url',
                    'admin_avatar',
                    'admin_name',
                    '',
                    'ends',
                    'duration',
                    ''
                ],
                $result
            )
        ];
    }

    private function prepareSelectQuery(Server $server, string $dbname, array $columns, array $search, array $order, string $table = 'bans')
    {
        if ($table === 'comms') {
            // Initialize an array to hold select queries
            $selectQueries = [];

            foreach (['mutes', 'gags'] as $tableName) {
                $select = $this->buildSelectQuery($dbname, $tableName, $columns, $search, $order);
                array_push($selectQueries, $select);
            }

            return $selectQueries;
        } else {
            return $this->buildSelectQuery($dbname, "bans", $columns, $search, $order);
        }
    }

    private function buildSelectQuery(string $dbname, string $tableName, array $columns, array $search, array $order)
    {
        $select = dbal()->database($dbname)->table($tableName)->select()->columns([
            "$tableName.*",
            new Fragment("'$tableName' as source")
        ]);

        // Applying column-based search
        foreach ($columns as $column) {
            if ($column['searchable'] === 'true' && !empty($column['search']['value'])) {
                $select->where($column['name'], 'like', '%' . $column['search']['value'] . '%');
            }
        }

        // Applying global search
        if (isset($search['value']) && !empty($search['value'])) {
            $select->where(function ($select) use ($search, $tableName) {
                $select->where("$tableName.player_name", 'like', '%' . $search['value'] . '%')
                    ->orWhere("$tableName.player_steamid", 'like', '%' . $search['value'] . '%')
                    ->orWhere("$tableName.admin_steamid", 'like', '%' . $search['value'] . '%')
                    ->orWhere("$tableName.admin_name", 'like', '%' . $search['value'] . '%')
                    ->orWhere("$tableName.reason", 'like', '%' . $search['value'] . '%');
            });
        }

        // Applying ordering
        foreach ($order as $orderItem) {
            $columnIndex = $orderItem['column'];
            $columnName = $columns[$columnIndex]['name'];
            $direction = strtolower($orderItem['dir']) === 'asc' ? 'ASC' : 'DESC';

            if ($columns[$columnIndex]['orderable'] === 'true') {
                $select->orderBy("$tableName.$columnName", $direction);
            }
        }

        return $select;
    }

    public function getCounts(string $dbname, array &$excludeAdmins = [], bool $wasAll = false): array
    {
        $db = dbal()->database($dbname);

        $bansCount = $db->table('bans')->select();
        $mutesCount = $db->table('mutes')->select();
        $gagsCount = $db->table('gags')->select();

        if (!empty($excludeAdmins)) {
            $bansCount->andWhere([
                'admin_steamid' => [
                    'NOT IN' => new Parameter($excludeAdmins)
                ]
            ]);
            $mutesCount->andWhere([
                'admin_steamid' => [
                    'NOT IN' => new Parameter($excludeAdmins)
                ]
            ]);
            $gagsCount->andWhere([
                'admin_steamid' => [
                    'NOT IN' => new Parameter($excludeAdmins)
                ]
            ]);
        }

        try {
            $uniqueAdmins = $db->table('admins')->select()->distinct()->columns('player_steamid');

            $newAdmins = [];
            foreach ($uniqueAdmins->fetchAll() as $admin) {
                if (!in_array($admin['player_steamid'], $excludeAdmins)) {
                    $excludeAdmins[] = $admin['player_steamid'];
                    $newAdmins[] = $admin['player_steamid'];
                }
            }

            return [
                'bans' => $bansCount->count(),
                'mutes' => $mutesCount->count(),
                'gags' => $gagsCount->count(),
                'admins' => sizeof($newAdmins)
            ];
        } catch (StatementException $e) {
            logs()->error($e);

            return [
                'bans' => 0,
                'mutes' => 0,
                'gags' => 0,
                'admins' => 0
            ];
        }
    }

    private function getSteamIds64(array $results): array
    {
        $steamIds64 = [];

        foreach ($results as $result) {
            try {
                if (!isset($steamIds64[$result['player_steamid']])) {
                    $steamId64 = steam()->steamid($result['player_steamid'])->ConvertToUInt64();
                    $steamIds64[$result['player_steamid']] = $steamId64;
                }

                if (!isset($steamIds64[$result['admin_steamid']])) {
                    $steamId64 = steam()->steamid($result['admin_steamid'])->ConvertToUInt64();
                    $steamIds64[$result['admin_steamid']] = $steamId64;
                }
            } catch (\InvalidArgumentException $e) {
                logs()->error($e);
                unset($result);
            }
        }

        return $steamIds64;
    }

    private function mapUsersDataToResult(array $results, array $usersData): array
    {
        $mappedResults = [];

        foreach ($results as $result) {
            $steamId32 = $result['player_steamid'];

            if (isset($usersData[$steamId32])) {
                $user = $usersData[$steamId32];
                $result['player_steamid'] = $usersData[$steamId32]->steamid;
                $result['avatar'] = $user->avatar;
            } else {
                $result['avatar'] = url('assets/img/no_avatar.webp')->get();
            }

            $result['user_url'] = url('profile/search/' . $result['player_steamid'])->addParams([
                "else-redirect" => "https://steamcommunity.com/profiles/" . $result['player_steamid']
            ])->get();

            $adminSteam = $result['admin_steamid'];

            if (isset($usersData[$adminSteam])) {
                $user = $usersData[$adminSteam];
                $result['admin_steamid'] = $usersData[$adminSteam]->steamid;
                $result['admin_avatar'] = $user->avatar;

                $result['admin_url'] = url('profile/search/' . $result['admin_steamid'])->addParams([
                    "else-redirect" => "https://steamcommunity.com/profiles/" . $result['admin_steamid']
                ])->get();
            } else {
                $result['admin_avatar'] = url('assets/img/no_avatar.webp')->get();
            }

            $mappedResults[] = $result;
        }

        return $mappedResults;
    }
    public function getName(): string
    {
        return "SimpleAdmin";
    }
}
