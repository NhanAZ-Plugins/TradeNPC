<?php

declare(strict_types=1);

namespace TradeNPC\inventory;

use pocketmine\block\inventory\BlockInventory;
use pocketmine\inventory\BaseInventory;
use pocketmine\inventory\SimpleInventory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\network\mcpe\protocol\UpdateTradePacket;
use pocketmine\player\Player;
use pocketmine\world\Position;
use TradeNPC\entity\TradeNPC;
use TradeNPC\TradeDataPool;

class TradeInventory extends SimpleInventory implements BlockInventory
{

    protected TradeNPC $npc;

    public function __construct(TradeNPC $holder)
    {
        parent::__construct(3);
        $this->npc = $holder;
    }

    public function getNetworkType(): int
    {
        return WindowTypes::TRADING;
    }

    public function onOpen(Player $who): void
    {
        $shop = $this->npc->getShopCompoundTag();
        if ($shop === null) {
            throw new \LogicException('Trade shop is not initialized');
        }
        $offers = $this->getOffers($shop);
        if ($offers === null) {
            throw new \LogicException('Trade offers could not be created');
        }
        BaseInventory::onOpen($who);

        $pk = new UpdateTradePacket();
        $pk->displayName = $this->npc->getNameTag();
        $pk->windowId = $id = $who->getId();
        $pk->isV2Trading = true;
        $pk->tradeTier = 3;
        $pk->playerActorUniqueId = $id;
        $pk->traderActorUniqueId = $this->npc->getId();
        $pk->offers = $offers;
        $pk->isEconomyTrading = false;
        $who->getNetworkSession()->sendDataPacket($pk);
        TradeDataPool::$windowIdData[$who->getName()] = $id;
        TradeDataPool::$interactNPCData[$who->getName()] = $this->npc;
    }

    /** @param TreeRoot|CompoundTag $nbt
     *  @return CacheableNbt<CompoundTag>
     */
    public function getOffers($nbt): ?CacheableNbt
    {
        return new CacheableNbt($nbt instanceof TreeRoot ? $nbt->mustGetCompoundTag() : $nbt);
    }

    public function getName(): string
    {
        return "TradeInventory";
    }

    public function onClose(Player $who): void
    {
        BaseInventory::onClose($who);
        unset(TradeDataPool::$windowIdData[$who->getName()]);
        unset(TradeDataPool::$interactNPCData[$who->getName()]);
    }

    /**
     * @return Position
     */
    public function getHolder(): Position
    {
        return $this->npc->getPosition();
    }
}
