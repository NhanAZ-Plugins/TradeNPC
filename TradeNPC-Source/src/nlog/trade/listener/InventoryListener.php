<?php

/*
 * TradeAPI, simple to provide Trade UI V2.
 * Copyright (C) 2020  Organic (nnnlog)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace nlog\trade\listener;


use nlog\trade\TradeAPI;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\ContainerClosePacket;
use pocketmine\player\Player;

class InventoryListener implements Listener {

	/** @var TradeAPI */
	private $plugin;

	public function __construct(TradeAPI $plugin) {
		$this->plugin = $plugin;
	}

	public function onDataPacketReceive(DataPacketReceiveEvent $event): void {
		$pk = $event->getPacket();
		$player = $event->getOrigin()->getPlayer();
		if ($player === null) {
			return;
		}
		if ($pk instanceof ContainerClosePacket && $pk->windowId === 255) {
			$this->plugin->closeWindow($player, false);
		}
	}

	public function onPlayerCommandPreprocess(CommandEvent $event): void {
		$sender = $event->getSender();
		if ($sender instanceof Player) {
			$this->plugin->doCloseInventory($this->plugin->getInventory($sender));
		}
	}

	public function onInventoryClose(InventoryCloseEvent $event): void {
		$player = $event->getPlayer();
		if ($event->getInventory() === $this->plugin->getInventory($player)) {
			$this->plugin->closeWindow($player, false);
		}
	}

	public function onBlockBreak(BlockBreakEvent $event): void {
		$this->plugin->doCloseInventory($this->plugin->getInventory($event->getPlayer()));
	}

	public function onPlayerDeath(PlayerDeathEvent $event): void {
		$this->plugin->doCloseInventory($this->plugin->getInventory($event->getPlayer()));
	}

	public function onPlayerJoin(PlayerJoinEvent $event): void {
		$this->plugin->addInventory($event->getPlayer());
	}

	public function onPlayerQuit(PlayerQuitEvent $event): void {
		$this->plugin->doCloseInventory($this->plugin->getInventory($event->getPlayer()));
		$this->plugin->removeInventory($event->getPlayer());
	}
}
