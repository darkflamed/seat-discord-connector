<?php
/**
 * This file is part of SeAT Discord Connector.
 *
 * Copyright (C) 2019  Warlof Tutsimo <loic.leuilliot@gmail.com>
 *
 * SeAT Discord Connector  is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * SeAT Discord Connector is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Warlof\Seat\Connector\Drivers\Discord\Driver;

use Illuminate\Support\Str;
use Warlof\Seat\Connector\Drivers\ISet;
use Warlof\Seat\Connector\Drivers\IUser;

/**
 * Class DiscordMember.
 *
 * @package Warlof\Seat\Connector\Drivers\Discord\Driver
 */
class DiscordMember implements IUser
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $uid;

    /**
     * @var string
     */
    private $nick;

    /**
     * @var string[]
     */
    private $role_ids;

    /**
     * @var \Warlof\Seat\Connector\Drivers\ISet[]
     */
    private $roles;

    /**
     * DiscordMember constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->role_ids = [];
        $this->roles    = collect();
        $this->hydrate($attributes);
    }

    /**
     * @return string
     */
    public function getClientId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getUniqueId(): string
    {
        return $this->uid;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->nick;
    }

    /**
     * @param string $name
     * @return bool
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Seat\Services\Exceptions\SettingException
     * @throws \Warlof\Seat\Connector\Exceptions\DriverSettingsException
     */
    public function setName(string $name): bool
    {
        $nickname = Str::limit($name, 32, '');

        DiscordClient::getInstance()->sendCall('PATCH', '/guilds/{guild.id}/members/{user.id}', [
            'guild.id' => DiscordClient::getInstance()->getGuildId(),
            'user.id'  => $this->id,
            'nick'     => $nickname,
        ]);

        $this->nick = $nickname;

        return true;
    }

    /**
     * @return \Warlof\Seat\Connector\Drivers\ISet[]
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Seat\Services\Exceptions\SettingException
     * @throws \Warlof\Seat\Connector\Exceptions\DriverSettingsException
     */
    public function getSets(): array
    {
        if ($this->roles->isEmpty()) {
            foreach ($this->role_ids as $role_id) {
                $set = DiscordClient::getInstance()->getSet($role_id);

                if (is_null($set)) continue;

                $this->roles->put($role_id, $set);
            }
        }

        return $this->roles->toArray();
    }

    /**
     * @param array $attributes
     * @return \Warlof\Seat\Connector\Drivers\Discord\Driver\DiscordMember
     */
    public function hydrate(array $attributes = []): DiscordMember
    {
        $this->id    = $attributes['user']['id'];
        $this->uid   = $attributes['user']['id'];
        $this->nick  = $attributes['user']['username'];
        $this->role_ids = $attributes['roles'];

        if (array_key_exists('nick', $attributes) && ! is_null($attributes['nick']))
            $this->nick = $attributes['nick'];

        return $this;
    }

    /**
     * @param \Warlof\Seat\Connector\Drivers\ISet $group
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Seat\Services\Exceptions\SettingException
     * @throws \Warlof\Seat\Connector\Exceptions\DriverSettingsException
     */
    public function addSet(ISet $group)
    {
        if (in_array($group->getId(), $this->role_ids))
            return;

        DiscordClient::getInstance()->sendCall('PUT', '/guilds/{guild.id}/members/{user.id}/roles/{role.id}', [
            'guild.id' => DiscordClient::getInstance()->getGuildId(),
            'role.id'  => $group->getId(),
            'user.id'  => $this->id,
        ]);

        $this->role_ids[] = $group->getId();
        $this->roles->put($group->getId(), $group);
    }

    /**
     * @param \Warlof\Seat\Connector\Drivers\ISet $group
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Seat\Services\Exceptions\SettingException
     * @throws \Warlof\Seat\Connector\Exceptions\DriverSettingsException
     */
    public function removeSet(ISet $group)
    {
        if (! in_array($group->getId(), $this->role_ids))
            return;

        DiscordClient::getInstance()->sendCall('DELETE', '/guilds/{guild.id}/members/{user.id}/roles/{role.id}', [
            'guild.id' => DiscordClient::getInstance()->getGuildId(),
            'role.id'  => $group->getId(),
            'user.id'  => $this->id,
        ]);

        $this->roles->pull($group->getId());

        $key = array_search($group->getId(), $this->role_ids);

        if ($key !== false) {
            unset($this->role_ids[$key]);
        }
    }
}
