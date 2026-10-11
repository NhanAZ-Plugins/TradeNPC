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

namespace nlog\trade;

use nlog\trade\inventory\PlayerTradeInventory;
use nlog\trade\listener\InventoryListener;
use nlog\trade\listener\TransactionInjector;
use nlog\trade\merchant\MerchantRecipeList;
use nlog\trade\merchant\TraderProperties;
use pocketmine\entity\Entity;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\ContainerClosePacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\IntMetadataProperty;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\network\mcpe\protocol\UpdateTradePacket;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;

class TradeAPI extends PluginBase {

	/** @var array<string, PlayerTradeInventory> */
	private static $inventory = [];

	public static function addInventory(Player $player): void {
		self::$inventory[$player->getName()] = new PlayerTradeInventory($player);
	}

	public static function removeInventory(Player $player): void {
		if (isset(self::$inventory[$player->getName()])) {
			unset(self::$inventory[$player->getName()]);
		}
	}

	public static function getInventory(Player $player): ?PlayerTradeInventory {
		return self::$inventory[$player->getName()] ?? null;
	}

	/** @var TradeAPI|null */
	private static $instance = null;

	/**
	 * @return TradeAPI|null
	 */
	public static function getInstance(): ?TradeAPI {
		return self::$instance;
	}

	/** @var TraderProperties[] */
	private $process = [];

	public function onLoad(): void {
		self::$instance = $this;
	}

	public function onEnable(): void {
		$this->getServer()->getPluginManager()->registerEvents(new InventoryListener($this), $this);
		$this->getServer()->getPluginManager()->registerEvents(new TransactionInjector(), $this);
	}

	public function onDisable(): void {
		foreach (self::$inventory as $name => $inventory) {
			if ($this->getServer()->getPlayerExact($name) instanceof Player) {
				$this->doCloseInventory($inventory);
			}
		}
	}

	public function isTrading(Player $player): bool {
		return isset($this->process[$player->getName()]);
	}

	/**
	 * Send Trade UI to Player
	 *
	 * @param Player             $player
	 * @param MerchantRecipeList $recipeList
	 * @param TraderProperties   $properties
	 */
	public function sendWindow(Player $player, MerchantRecipeList $recipeList, TraderProperties $properties): void {
		$this->closeWindow($player);
		$offers = new CacheableNbt(
			CompoundTag::create()
				->setTag("Recipes", $recipeList->toNBT())
				->setTag("TierExpRequirements", new ListTag([
					CompoundTag::create()->setInt("0", 0),
					CompoundTag::create()->setInt("1", 10),
					CompoundTag::create()->setInt("2", 60),
					CompoundTag::create()->setInt("3", 160),
					CompoundTag::create()->setInt("4", 310),
				])) //TODO: move to merchant recipes list
		);
		$pk = UpdateTradePacket::create(
			windowId: $player->getNetworkSession()->getInvManager()->getCurrentWindowId(),
			windowType: WindowTypes::TRADING,
			windowSlotCount: 0,
			tradeTier: $properties->tradeTier,
			traderActorUniqueId: $properties->eid,
			playerActorUniqueId: $player->getId(),
			displayName: $properties->traderName,
			isV2Trading: true,
			isEconomyTrading: true,
			offers: $offers
		);

		$metadata = [
			EntityMetadataProperties::TRADE_TIER         => new IntMetadataProperty($pk->tradeTier),
			EntityMetadataProperties::TRADE_XP           => new IntMetadataProperty($properties->xp),
			EntityMetadataProperties::MAX_TRADE_TIER     => new IntMetadataProperty($properties->maxTradeTier),
			EntityMetadataProperties::TRADING_PLAYER_EID => new IntMetadataProperty($player->getId())
		];

		if ($properties->entity instanceof Entity) {
			$pk->traderEid = $properties->entity->getId();

			foreach ($metadata as $k => $metadataProperty) {
				$properties->entity->getNetworkProperties()->setInt($k, $metadataProperty->getValue());
			}
		} else {
			$apk = new AddActorPacket();
			$apk->type = EntityIds::NPC;
			$apk->position = $player->getPosition()->add(0, -2, 0);
			$apk->metadata = $metadata;

			$properties->eid = $apk->entityRuntimeId = $pk->traderEid = Entity::nextRuntimeId();

			$player->getNetworkSession()->sendDataPacket($apk);
		}

		$this->process[$player->getName()] = clone $properties;

		$player->getNetworkSession()->sendDataPacket($pk);
	}

	public function closeWindow(Player $player, bool $sendPacket = true): void {
		if (($prop = $this->process[$player->getName()] ?? null) instanceof TraderProperties) {
			if ($sendPacket) {
				$pk = new ContainerClosePacket();
				$pk->windowId = WindowTypes::TRADING;
				$player->getNetworkSession()->sendDataPacket($pk);
			}

			if ($prop->entity instanceof Entity) {
				$prop->entity->getNetworkProperties()->setInt(EntityMetadataProperties::TRADING_PLAYER_EID, -1);
			} else {
				$pk = new RemoveActorPacket();
				$pk->entityUniqueId = $prop->eid;
				$player->getNetworkSession()->sendDataPacket($pk);
			}

			$this->doCloseInventory(self::getInventory($player));

			unset($this->process[$player->getName()]);
		}
	}

	public function doCloseInventory(?PlayerTradeInventory $inventory): void {
		if ($inventory === null) {
			return;
		}
		for ($slot = 0; $slot <= 1; $slot++) {
			$item = $inventory->getItem($slot);

			if ($inventory->getHolder()->getInventory()->canAddItem($item)) {
				$inventory->getHolder()->getInventory()->addItem($item);
			} else {
				$inventory->getHolder()->dropItem($item);
			}
		}

		$inventory->clearAll();
	}
}
