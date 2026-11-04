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

namespace nlog\trade\inventory\action;


use nlog\trade\TradeAPI;
use pocketmine\inventory\transaction\action\InventoryAction;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\NetworkInventoryAction;
use pocketmine\player\Player;
use UnexpectedValueException;

class NetworkTradeInventoryAction extends NetworkInventoryAction {
	public function createInventoryAction(Player $player): ?InventoryAction {
		if ($this->sourceType !== self::SOURCE_CONTAINER) {
			throw new UnexpectedValueException("Unknown inventory source type $this->sourceType");
		}
		$slot = $this->inventorySlot;
		if ($this->windowId === ContainerIds::UI && ($slot === 4 || $slot === 5 || $slot === 51)) {
			$window = TradeAPI::getInventory($player);
			$slot = $slot === 51 ? 2 : $slot - 4;
		} else {
			$window = null;
		}
		if ($window === null) {
			throw new UnexpectedValueException("No open container with window ID $this->windowId");
		}
		if ($this->oldItem->getItemStack()->equals($this->newItem->getItemStack())) {
			return null;
		}
		$converter = $player->getNetworkSession()->getTypeConverter();
		return new SlotChangeAction(
			$window,
			$slot,
			$converter->netItemStackToCore($this->oldItem->getItemStack()),
			$converter->netItemStackToCore($this->newItem->getItemStack())
		);
	}
}
