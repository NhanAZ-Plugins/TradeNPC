<?php

declare(strict_types=1);

namespace TradeNPC\inventory;

use pocketmine\inventory\Inventory;
use pocketmine\inventory\BaseInventory;
use pocketmine\inventory\SimpleInventory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\ContainerOpenPacket;
use pocketmine\network\mcpe\protocol\UpdateTradePacket;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\Position;
use TradeNPC\entity\TradeNPC;
use TradeNPC\Main;
use TradeNPC\TradeDataPool;

class TradeInventory extends SimpleInventory
{

    protected TradeNPC $npc;
    private static ?\Closure $openCallback = null;

    public function __construct(TradeNPC $holder)
    {
        parent::__construct(3);
        $this->npc = $holder;
    }

    public function getNetworkType(): int
    {
        return WindowTypes::TRADING;
    }

    public static function registerNetworkOpen(Player $player): void
    {
        $manager = $player->getNetworkSession()->getInvManager();
        if ($manager === null) {
            throw new \LogicException('Network inventory manager is unavailable');
        }
        self::$openCallback ??= self::createContainerOpen(...);
        $manager->getContainerOpenCallbacks()->add(self::$openCallback);
    }

    /** @return list<ClientboundPacket>|null */
    private static function createContainerOpen(int $windowId, Inventory $inventory): ?array
    {
        if (!$inventory instanceof self) {
            return null;
        }
        return [ContainerOpenPacket::entityInv($windowId, WindowTypes::TRADING, $inventory->npc->getId())];
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

        $this->sendTradeOffer($who, $offers, 0);
    }

    /** @param CacheableNbt<CompoundTag> $offers */
    private function sendTradeOffer(Player $who, CacheableNbt $offers, int $attempt): void
    {
        $manager = $who->getNetworkSession()->getInvManager();
        $windowId = $manager?->getWindowId($this);
        if ($windowId === null) {
            if ($attempt >= 100) {
                $who->removeCurrentWindow();
                return;
            }
            Main::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($who, $offers, $attempt): void {
                if ($who->isConnected() && $who->getCurrentWindow() === $this) {
                    $this->sendTradeOffer($who, $offers, $attempt + 1);
                }
            }), 1);
            return;
        }

        $pk = new UpdateTradePacket();
        $pk->displayName = $this->npc->getNameTag();
        $pk->windowId = $windowId;
        $pk->isV2Trading = true;
        $pk->tradeTier = 3;
        $pk->playerActorUniqueId = $who->getId();
        $pk->traderActorUniqueId = $this->npc->getId();
        $pk->offers = $offers;
        $pk->isEconomyTrading = false;
        $who->getNetworkSession()->sendDataPacket($pk);
        TradeDataPool::$windowIdData[$who->getName()] = $windowId;
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
