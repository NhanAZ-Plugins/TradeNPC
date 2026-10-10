<?php

declare(strict_types=1);

use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\entity\Human;
use pocketmine\inventory\PlayerInventory;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\ActorEvent;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\inventory\NetworkInventoryAction;
use pocketmine\network\mcpe\protocol\types\inventory\NormalTransactionData;
use pocketmine\player\Player;
use TradeNPC\entity\TradeNPC;
use TradeNPC\listener\EventListener;
use TradeNPC\Main;
use TradeNPC\TradeDataPool;

$autoload = getenv('AXOLOTL_AUTOLOAD');
if ($autoload === false || !is_file($autoload)) {
    throw new RuntimeException('Set AXOLOTL_AUTOLOAD to the pinned server Composer autoload file.');
}
require $autoload;
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'TradeNPC\\')) {
        $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $class) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
});

class PacketGuardPlayer extends Player {
    public function __destruct() {}
}

class PacketGuardNpc extends TradeNPC {
    public function __destruct() {}
}

$listener = new EventListener((new ReflectionClass(Main::class))->newInstanceWithoutConstructor());
$session = (new ReflectionClass(NetworkSession::class))->newInstanceWithoutConstructor();
$completeTrade = static function (int $index) use ($session): DataPacketReceiveEvent {
    $packet = (new ReflectionClass(ActorEventPacket::class))->newInstanceWithoutConstructor();
    $packet->actorRuntimeId = 0;
    $packet->eventId = ActorEvent::COMPLETE_TRADE;
    $packet->eventData = $index;
    return new DataPacketReceiveEvent($session, $packet);
};

$listener->handleDataPacket($completeTrade(0));
$listener->handleDataPacket(new DataPacketReceiveEvent(
    $session,
    (new ReflectionClass(InventoryTransactionPacket::class))->newInstanceWithoutConstructor()
));

$player = (new ReflectionClass(PacketGuardPlayer::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(Player::class, 'username'))->setValue($player, 'PacketGuardTester');
(new ReflectionProperty(NetworkSession::class, 'player'))->setValue($session, $player);
$npc = (new ReflectionClass(PacketGuardNpc::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(TradeNPC::class, 'shop'))->setValue(
    $npc,
    CompoundTag::create()->setTag('Recipes', new ListTag([CompoundTag::create()], NBT::TAG_Compound))
);
TradeDataPool::$interactNPCData[$player->getName()] = $npc;

$listener->handleDataPacket($completeTrade(-1));
$listener->handleDataPacket($completeTrade(1));
$listener->handleDataPacket($completeTrade(0));

$inventory = new PlayerInventory($player);
(new ReflectionProperty(Human::class, 'inventory'))->setValue($player, $inventory);
$inventory->setItem(0, VanillaItems::DIAMOND());
$action = new NetworkInventoryAction();
$action->sourceType = NetworkInventoryAction::SOURCE_CONTAINER;
$action->windowId = 42;
$action->inventorySlot = 0;
$action->oldItem = ItemStackWrapper::legacy(ItemStack::null());
$action->newItem = ItemStackWrapper::legacy(ItemStack::null());
TradeDataPool::$windowIdData[$player->getName()] = 42;
$listener->handleDataPacket(new DataPacketReceiveEvent(
    $session,
    InventoryTransactionPacket::create(0, [], NormalTransactionData::new([$action]))
));
if (!$inventory->getItem(0)->equals(VanillaItems::DIAMOND()) || $inventory->getItem(0)->getCount() !== 1) {
    throw new RuntimeException('Normal transaction changed the live inventory');
}

echo "PASS: invalid trade packets and normal inventory action do not change inventory\n";
