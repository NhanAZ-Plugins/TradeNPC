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


use nlog\trade\inventory\FakeInventory;
use pocketmine\inventory\transaction\action\InventoryAction;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\network\mcpe\protocol\types\inventory\NetworkInventoryAction;
use pocketmine\player\Player;
use UnexpectedValueException;

class NetworkFakeInventoryAction extends NetworkInventoryAction {
	public function createInventoryAction(Player $player): ?InventoryAction {
		if ($this->sourceType !== self::SOURCE_TODO) {
			throw new UnexpectedValueException("Unknown inventory source type $this->sourceType");
		}
		if ($this->windowId !== -30 && $this->windowId !== -31) {
			throw new UnexpectedValueException("No open container with window ID $this->windowId");
		}
		if ($this->inventorySlot !== 0) {
			throw new UnexpectedValueException("Invalid fake inventory slot $this->inventorySlot");
		}
		if ($this->oldItem->getItemStack()->equals($this->newItem->getItemStack())) {
			return null;
		}
		$converter = $player->getNetworkSession()->getTypeConverter();
		$oldItem = $converter->netItemStackToCore($this->oldItem->getItemStack());
		return new SlotChangeAction(
			new FakeInventory($oldItem),
			$this->inventorySlot,
			$oldItem,
			$converter->netItemStackToCore($this->newItem->getItemStack())
		);
	}
}
