<?php

declare(strict_types=1);

$autoload = getenv('AXOLOTL_AUTOLOAD');
if (!is_string($autoload) || !is_file($autoload)) {
	throw new RuntimeException('Set AXOLOTL_AUTOLOAD to a verified Axolotl-PM Composer autoloader');
}
require $autoload;
require __DIR__ . '/../src/nlog/trade/TradeAPI.php';
require __DIR__ . '/../src/nlog/trade/listener/InventoryListener.php';

use nlog\trade\inventory\PlayerTradeInventory;
use nlog\trade\listener\InventoryListener;
use nlog\trade\TradeAPI;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\event\server\CommandEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\ContainerClosePacket;
use pocketmine\network\mcpe\protocol\types\ActorEvent;
use pocketmine\player\Player;

final class FixturePlayer extends Player {
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
if ($plugin->inventoryCloseCalls !== 2) {
	throw new RuntimeException('Other actor event did not close the trade inventory');
}

$base = (new ReflectionClass(TradeAPI::class))->newInstanceWithoutConstructor();
$base->doCloseInventory(null);

echo "PASS: archived trade listener guards console, unbound sessions and absent inventories\n";
