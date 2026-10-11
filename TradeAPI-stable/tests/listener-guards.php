<?php

declare(strict_types=1);

$autoload = getenv('AXOLOTL_AUTOLOAD');
if (!is_string($autoload) || !is_file($autoload)) {
	throw new RuntimeException('Set AXOLOTL_AUTOLOAD to a verified Axolotl-PM Composer autoloader');
}
require $autoload;
require __DIR__ . '/../src/nlog/trade/inventory/PlayerTradeInventory.php';
require __DIR__ . '/../src/nlog/trade/TradeAPI.php';
require __DIR__ . '/../src/nlog/trade/inventory/action/NetworkTradeInventoryAction.php';
require __DIR__ . '/../src/nlog/trade/inventory/action/NetworkFakeInventoryAction.php';
require __DIR__ . '/../src/nlog/trade/listener/InventoryListener.php';
require __DIR__ . '/../src/nlog/trade/listener/TransactionInjector.php';

use nlog\trade\inventory\PlayerTradeInventory;
use nlog\trade\listener\InventoryListener;
use nlog\trade\listener\TransactionInjector;
use nlog\trade\TradeAPI;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\event\server\CommandEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\ContainerClosePacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\inventory\NetworkInventoryAction;
use pocketmine\network\mcpe\protocol\types\inventory\NormalTransactionData;
use pocketmine\network\mcpe\protocol\types\ActorEvent;
use pocketmine\player\Player;

final class FixturePlayer extends Player {
	public ?\pocketmine\inventory\Inventory $fixtureWindow = null;
	public function getName(): string { return 'FixtureTrader'; }
	public function getCurrentWindow(): ?\pocketmine\inventory\Inventory { return $this->fixtureWindow; }
	public function __destruct() {}
}

final class FixtureSession extends NetworkSession {
	public ?Player $fixturePlayer = null;
	public function getPlayer(): ?Player {
		return $this->fixturePlayer;
	}
}

final class FixtureTradeAPI extends TradeAPI {
	public int $closeCalls = 0;
	public int $inventoryCloseCalls = 0;
	public bool $active = false;
	public function isTrading(Player $player): bool { return $this->active; }
	public static function getInventory(Player $player): ?PlayerTradeInventory {
		return null;
	}
	public function closeWindow(Player $player, bool $sendPacket = true): void {
		++$this->closeCalls;
	}
	public function doCloseInventory(?PlayerTradeInventory $inventory): void {
		++$this->inventoryCloseCalls;
	}
}

$plugin = (new ReflectionClass(FixtureTradeAPI::class))->newInstanceWithoutConstructor();
$listener = new InventoryListener($plugin);
$console = (new ReflectionClass(ConsoleCommandSender::class))->newInstanceWithoutConstructor();
$listener->onPlayerCommandPreprocess(new CommandEvent($console, 'list'));
if ($plugin->inventoryCloseCalls !== 0) {
	throw new RuntimeException('Console command touched a player trade inventory');
}

$player = (new ReflectionClass(FixturePlayer::class))->newInstanceWithoutConstructor();
$listener->onPlayerCommandPreprocess(new CommandEvent($player, 'help'));
if ($plugin->inventoryCloseCalls !== 1) {
	throw new RuntimeException('Player command did not close its trade inventory');
}

$session = (new ReflectionClass(FixtureSession::class))->newInstanceWithoutConstructor();
$close = new ContainerClosePacket();
$close->windowId = 255;
$listener->onDataPacketReceive(new DataPacketReceiveEvent($session, $close));
if ($plugin->closeCalls !== 0) {
	throw new RuntimeException('Unattached network session closed a trade window');
}

$session->fixturePlayer = $player;
$listener->onDataPacketReceive(new DataPacketReceiveEvent($session, $close));
if ($plugin->closeCalls !== 1) {
	throw new RuntimeException('Player close packet did not close the trade window');
}

$actor = new ActorEventPacket();
$actor->eventId = ActorEvent::COMPLETE_TRADE;
$listener->onDataPacketReceive(new DataPacketReceiveEvent($session, $actor));
if ($plugin->inventoryCloseCalls !== 1) {
	throw new RuntimeException('Completed trade closed the trade inventory');
}
$actor->eventId = ActorEvent::JUMP;
$listener->onDataPacketReceive(new DataPacketReceiveEvent($session, $actor));
if ($plugin->inventoryCloseCalls !== 1) {
	throw new RuntimeException('Unrelated actor event cleared trade inventory');
}

$base = (new ReflectionClass(TradeAPI::class))->newInstanceWithoutConstructor();
$base->doCloseInventory(null);

$action = new NetworkInventoryAction();
$action->sourceType = NetworkInventoryAction::SOURCE_CONTAINER;
$action->windowId = ContainerIds::UI;
$action->inventorySlot = 50;
$transaction = NormalTransactionData::new([$action]);
$packet = InventoryTransactionPacket::create(0, [], $transaction);
$injector = new TransactionInjector();
$session->fixturePlayer = null;
$injector->onDataPacketReceive(new DataPacketReceiveEvent($session, $packet));
if ($packet->trData !== $transaction || $action->inventorySlot !== 50) {
	throw new RuntimeException('Unattached session rewrote a trade transaction');
}
$session->fixturePlayer = $player;
$injector->onDataPacketReceive(new DataPacketReceiveEvent($session, $packet));
if ($packet->trData !== $transaction || $action->inventorySlot !== 50) {
	throw new RuntimeException('Absent TradeAPI instance rewrote a trade transaction');
}

$actions = [];
foreach ([4, 5, 50] as $slot) {
	$other = new NetworkInventoryAction();
	$other->sourceType = NetworkInventoryAction::SOURCE_CONTAINER;
	$other->windowId = ContainerIds::UI;
	$other->inventorySlot = $slot;
	$other->oldItem = ItemStackWrapper::legacy(ItemStack::null());
	$other->newItem = ItemStackWrapper::legacy(ItemStack::null());
	$actions[] = $other;
}
$otherTransaction = NormalTransactionData::new($actions);
$otherPacket = InventoryTransactionPacket::create(0, [], $otherTransaction);
$plugin->onLoad();
$plugin->active = true;
$injector->onDataPacketReceive(new DataPacketReceiveEvent($session, $otherPacket));
if ($otherPacket->trData !== $otherTransaction || array_map(static fn(NetworkInventoryAction $item): int => $item->inventorySlot, $actions) !== [4, 5, 50]) {
	throw new RuntimeException('Trade action rewrite touched a player with no open trade window');
}

TradeAPI::addInventory($player);
$inventory = TradeAPI::getInventory($player);
if ($inventory === null) {
	throw new RuntimeException('Fixture trade inventory was not created');
}
$player->fixtureWindow = new \pocketmine\inventory\SimpleInventory(3);
$injector->onDataPacketReceive(new DataPacketReceiveEvent($session, $otherPacket));
if ($otherPacket->trData !== $otherTransaction || $actions[2]->inventorySlot !== 50) {
	throw new RuntimeException('Trade action rewrite touched another open inventory');
}
$player->fixtureWindow = $inventory;
$listener->onDataPacketReceive(new DataPacketReceiveEvent($session, $actor));
if ($plugin->inventoryCloseCalls !== 1) {
	throw new RuntimeException('Actor event cleared an active trade window');
}
$plugin->active = false;
$injector->onDataPacketReceive(new DataPacketReceiveEvent($session, $otherPacket));
if ($otherPacket->trData !== $otherTransaction || $actions[2]->inventorySlot !== 50) {
	throw new RuntimeException('Trade action rewrite touched an inactive trade window');
}
$plugin->active = true;
$injector->onDataPacketReceive(new DataPacketReceiveEvent($session, $otherPacket));
if ($otherPacket->trData === $otherTransaction || $actions[2]->inventorySlot !== 51) {
	throw new RuntimeException('Active trade window did not route the legacy trade actions');
}
TradeAPI::removeInventory($player);

echo "PASS: archived trade listener guards console, unbound sessions and active trade routing\n";
