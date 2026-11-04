<?php

declare(strict_types=1);

use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketDecodeEvent;
use pocketmine\entity\Human;
use pocketmine\inventory\PlayerInventory;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\ContainerClosePacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\ActorEvent;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\inventory\NetworkInventoryAction;
use pocketmine\network\mcpe\protocol\types\inventory\NormalTransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
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

class PacketGuardSession extends NetworkSession {
    /** @var list<ClientboundPacket> */
    public array $sent = [];
    public function __destruct() {}
    public function sendDataPacket(ClientboundPacket $packet, bool $immediate = false): bool {
        $this->sent[] = $packet;
        return true;
    }
}

class PacketGuardNpc extends TradeNPC {
    public function __destruct() {}
}

$listener = new EventListener((new ReflectionClass(Main::class))->newInstanceWithoutConstructor());
$decodeTags = (new ReflectionMethod(EventListener::class, 'handleDecode'))->getDocComment();
if ($decodeTags === false || !str_contains($decodeTags, '@handleCancelled')) {
    throw new RuntimeException('Discarded actor events will not invoke the decode handler');
}
$session = (new ReflectionClass(PacketGuardSession::class))->newInstanceWithoutConstructor();
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
$listener->handleDataPacket(new DataPacketReceiveEvent(
    $session,
    (new ReflectionClass(LoginPacket::class))->newInstanceWithoutConstructor()
));

$player = (new ReflectionClass(PacketGuardPlayer::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(Player::class, 'username'))->setValue($player, 'PacketGuardTester');
(new ReflectionProperty(NetworkSession::class, 'player'))->setValue($session, $player);
$blockedDecode = new DataPacketDecodeEvent($session, ProtocolInfo::ACTOR_EVENT_PACKET, '');
$blockedDecode->cancel();
$listener->handleDecode($blockedDecode);
if (!$blockedDecode->isCancelled()) {
    throw new RuntimeException('Actor event decode was enabled without an active trade');
}
$npc = (new ReflectionClass(PacketGuardNpc::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(TradeNPC::class, 'shop'))->setValue(
    $npc,
    CompoundTag::create()->setTag('Recipes', new ListTag([CompoundTag::create()], NBT::TAG_Compound))
);
TradeDataPool::$interactNPCData[$player->getName()] = $npc;
$tradeDecode = new DataPacketDecodeEvent($session, ProtocolInfo::ACTOR_EVENT_PACKET, '');
$tradeDecode->cancel();
$listener->handleDecode($tradeDecode);
if ($tradeDecode->isCancelled()) {
    throw new RuntimeException('Active trade actor event remained blocked before decode');
}
$otherDecode = new DataPacketDecodeEvent($session, ProtocolInfo::LOGIN_PACKET, '');
$otherDecode->cancel();
$listener->handleDecode($otherDecode);
if (!$otherDecode->isCancelled()) {
    throw new RuntimeException('Unrelated packet was enabled during a trade');
}

$listener->handleDataPacket($completeTrade(-1));
$listener->handleDataPacket($completeTrade(1));
$completeEvent = $completeTrade(0);
$listener->handleDataPacket($completeEvent);
if (!$completeEvent->isCancelled()) {
    throw new RuntimeException('Completed trade actor event reached the default packet handler');
}

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
$inboundClose = (new ReflectionClass(ContainerClosePacket::class))->newInstanceWithoutConstructor();
$inboundClose->windowId = 42;
$inboundClose->server = false;
$listener->handleDataPacket(new DataPacketReceiveEvent($session, $inboundClose));
$sent = $session->sent;
if (count($sent) !== 1 || !$sent[0] instanceof ContainerClosePacket) {
    throw new RuntimeException('Trade window close response was not sent once');
}
$close = $sent[0];
if ($close->windowId !== 255 || $close->server !== false) {
    throw new RuntimeException('Trade window close packet changed its target or sender');
}
$closeReflection = new ReflectionClass($close);
if ($closeReflection->hasProperty('windowType') &&
    $closeReflection->getProperty('windowType')->getValue($close) !== WindowTypes::TRADING) {
    throw new RuntimeException('Trade window close packet has the wrong window type');
}

echo "PASS: invalid trade packets preserve inventory and close packet matches the protocol\n";
