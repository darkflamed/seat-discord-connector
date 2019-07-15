<?php
/**
 * This file is part of discord-connector and provides user synchronization between both SeAT and a Discord Guild
 *
 * Copyright (C) 2016, 2017, 2018  Loïc Leuilliot <loic.leuilliot@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Warlof\Seat\Connector\Discord\Http\Controllers;

use Seat\Eveapi\Models\Character\CharacterInfo;
use Seat\Services\Models\UserSetting;
use Seat\Web\Http\Controllers\Controller;
use Warlof\Seat\Connector\Discord\Models\DiscordUser;

/**
 * Class DiscordController
 * @package Warlof\Seat\Connector\Discord\Http\Controllers
 */
class DiscordController extends Controller
{
    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getUsers()
    {
        return view('discord-connector::users.list');
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function removeUserMapping()
    {
        $discord_id = request()->input('discord_id');

        if ($discord_id != '') {

            if (($discord_user = DiscordUser::where('discord_id', $discord_id)->first()) != null) {
                $msg = sprintf('System successfully remove the mapping between SeAT (%s) and Discord (%s)',
                    optional($discord_user->group->main_character)->name, $discord_user->nick);

                $discord_user->delete();

                return redirect()->back()->with('success', $msg);
            }

            return redirect()->back()->with('error', sprintf(
                'System cannot find any suitable mapping for Discord (%s).', $discord_id));
        }

        return redirect()->back('error', 'An error occurred while processing the request.');
    }

    /**
     * @return mixed
     * @throws \Seat\Services\Exceptions\SettingException
     */
    public function getUsersData()
    {
        if (is_null(setting('warlof.discord-connector.credentials.bot_token', true)))
            return app('DataTables')::of(collect([]))->make(true);

        $discord_users = DiscordUser::query()
            ->leftJoin((new UserSetting())->getTable(), function ($join) {
                $join->on((new DiscordUser())->getTable() . '.group_id', '=', (new UserSetting())->getTable() . '.group_id')
                     ->where((new UserSetting())->getTable() . '.name', '=', 'main_character_id');
            })
            ->leftJoin((new CharacterInfo())->getTable(), 'character_id', '=', 'value')
            ->select(
                (new DiscordUser())->getTable() . '.*',
                (new UserSetting())->getTable() . '.value AS user_id',
                (new CharacterInfo())->getTable() . '.name AS user_name'
            );

        return app('DataTables')::of($discord_users)
            ->editColumn('group_id', function($row){
                return $row->group_id;
            })
            ->addColumn('user_id', function($row){
                return $row->group->main_character_id;
            })
            ->addColumn('username', function($row){
                return optional($row->group->main_character)->name ?: 'Unknown Character';
            })
            ->editColumn('discord_id', function($row){
                return (string) $row->discord_id;
            })
            ->editColumn('nick', function($row){
                return $row->nick;
            })
            ->filterColumn('user_name', function ($query, $keyword) {
                $sql = 'LOWER(' . (new CharacterInfo())->getTable() . '.name) LIKE ?';
                $query->orWhereRaw($sql, ["%{$keyword}%"]);
            })
            ->orderColumn('user_id', '-value $1')
            ->filterColumn('user_id', function ($query, $keyword) {
                $sql = 'LOWER(' . (new UserSetting())->getTable() . '.value) LIKE ?';
                $query->orWhereRaw($sql, ["%{$keyword}%"]);
            })
            ->removeColumn('refresh_token')
            ->removeColumn('access_token')
            ->removeColumn('expires_at')
            ->make(true);
    }

}
