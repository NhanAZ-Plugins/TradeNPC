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
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\IntMetadataProperty;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\network\mcpe\protocol\UpdateTradePacket;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;

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
		$inventoryManager = $player->getNetworkSession()->getInvManager();
		if ($inventoryManager === null) {
			throw new \LogicException("Cannot open a trade window before the player inventory is ready");
		}
		$this->closeWindow($player);
		if(($inventory = self::getInventory($player)) === null){
			self::addInventory($player);
			$inventory = self::getInventory($player);
		}
		if($inventory === null){
			throw new \LogicException("Trade inventory was not created");
		}
		$properties->eid = $properties->entity instanceof Entity ? $properties->entity->getId() : Entity::nextRuntimeId();
		$inventory->setTraderActorId($properties->eid);
		PlayerTradeInventory::registerNetworkOpen($player);
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
		$metadata = [
			EntityMetadataProperties::TRADE_TIER         => new IntMetadataProperty($properties->tradeTier),
			EntityMetadataProperties::TRADE_XP           => new IntMetadataProperty($properties->xp),
			EntityMetadataProperties::MAX_TRADE_TIER     => new IntMetadataProperty($properties->maxTradeTier),
			EntityMetadataProperties::TRADING_PLAYER_EID => new IntMetadataProperty($player->getId())
		];

		if ($properties->entity instanceof Entity) {
			foreach ($metadata as $k => $metadataProperty) {
				$properties->entity->getNetworkProperties()->setInt($k, $metadataProperty->getValue());
			}
		} else {
			$player->getNetworkSession()->sendDataPacket(TradePacketFactory::fakeTrader(
				$properties->eid,
				$player->getPosition()->add(0, -2, 0),
				$metadata
			));
		}

		try{
			if(!$player->setCurrentWindow($inventory)){
				throw new \LogicException("Trade inventory opening was cancelled");
			}
		}catch(\Throwable $error){
			if($properties->entity instanceof Entity){
				$properties->entity->getNetworkProperties()->setInt(EntityMetadataProperties::TRADING_PLAYER_EID, -1);
			}else{
				$player->getNetworkSession()->sendDataPacket(TradePacketFactory::removeFakeTrader($properties->eid));
			}
			throw $error;
		}
		$this->process[$player->getName()] = clone $properties;
		$this->sendTradeUpdate($player, $inventory, $properties, $offers, 0);
	}

	/** @param CacheableNbt<CompoundTag> $offers */
	private function sendTradeUpdate(Player $player, PlayerTradeInventory $inventory, TraderProperties $properties, CacheableNbt $offers, int $attempt) : void{
		if(!$player->isConnected() || $player->getCurrentWindow() !== $inventory || !$this->isTrading($player)){
			return;
		}
		$windowId = $player->getNetworkSession()->getInvManager()?->getWindowId($inventory);
		if($windowId === null){
			if($attempt >= 100){
				$this->closeWindow($player);
				return;
			}
			$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player, $inventory, $properties, $offers, $attempt) : void{
				$this->sendTradeUpdate($player, $inventory, $properties, $offers, $attempt + 1);
			}), 1);
			return;
		}
		$player->getNetworkSession()->sendDataPacket(UpdateTradePacket::create(
			windowId: $windowId,
			windowType: WindowTypes::TRADING,
			windowSlotCount: 0,
			tradeTier: $properties->tradeTier,
			traderActorUniqueId: $properties->eid,
			playerActorUniqueId: $player->getId(),
			displayName: $properties->traderName,
			isV2Trading: true,
			isEconomyTrading: true,
			offers: $offers
		));
	}

	public function closeWindow(Player $player, bool $sendPacket = true): void {
		if (($prop = $this->process[$player->getName()] ?? null) instanceof TraderProperties) {
			unset($this->process[$player->getName()]);
			$inventory = self::getInventory($player);
			if($sendPacket && $inventory !== null && $player->getCurrentWindow() === $inventory){
				$player->removeCurrentWindow();
			}

			if ($prop->entity instanceof Entity) {
				$prop->entity->getNetworkProperties()->setInt(EntityMetadataProperties::TRADING_PLAYER_EID, -1);
			} else {
				$player->getNetworkSession()->sendDataPacket(TradePacketFactory::removeFakeTrader($prop->eid));
			}

			$this->doCloseInventory($inventory);
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
