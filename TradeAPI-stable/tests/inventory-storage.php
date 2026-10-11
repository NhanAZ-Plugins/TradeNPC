<?php

declare(strict_types=1);

$autoload = getenv('AXOLOTL_AUTOLOAD');
if (!is_string($autoload) || !is_file($autoload)) {
	throw new RuntimeException('Set AXOLOTL_AUTOLOAD to a verified Axolotl-PM Composer autoloader');
}
require $autoload;
require __DIR__ . '/../src/nlog/trade/inventory/FakeInventory.php';
require __DIR__ . '/../src/nlog/trade/inventory/PlayerTradeInventory.php';
require __DIR__ . '/../src/nlog/trade/TradeAPI.php';

use nlog\trade\inventory\FakeInventory;
use nlog\trade\inventory\PlayerTradeInventory;
use nlog\trade\TradeAPI;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\TemporaryInventory;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\ContainerOpenPacket;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\player\Player;

final class FixturePlayer extends Player {
	public PlayerInventory $fixtureInventory;
	public function __destruct() {}
	public function getInventory(): PlayerInventory {
		return $this->fixtureInventory;
	}
}

$holder = (new ReflectionClass(FixturePlayer::class))->newInstanceWithoutConstructor();
$holder->fixtureInventory = new PlayerInventory($holder);
$trade = new PlayerTradeInventory($holder);
if ($trade->getHolder() !== $holder || $trade->getSize() !== 3) {
	throw new RuntimeException('Trade inventory holder or size is wrong');
}
if ($trade instanceof TemporaryInventory) {
	throw new RuntimeException('Core would return an unearned output slot when a trade window closes');
}
$open = (new ReflectionClass(PlayerTradeInventory::class))->getMethod('createContainerOpen');
try {
	$open->invoke(null, 7, $trade);
	throw new RuntimeException('Trade window opened without an actor ID');
} catch (LogicException) {
}
$trade->setTraderActorId(73);
$packets = $open->invoke(null, 7, $trade);
if ($packets === null || count($packets) !== 1 || !$packets[0] instanceof ContainerOpenPacket ||
	$packets[0]->windowId !== 7 || $packets[0]->windowType !== WindowTypes::TRADING ||
	$packets[0]->actorUniqueId !== 73) {
	throw new RuntimeException('Trade window does not use the assigned network and actor IDs');
}
$diamond = VanillaItems::DIAMOND()->setCount(2);
$trade->setItem(0, $diamond);
if (!$trade->getItem(0)->equalsExact($diamond) || count($trade->getContents()) !== 1) {
	throw new RuntimeException('Trade inventory lost the placed item');
}
$readback = $trade->getItem(0);
$readback->setCount(1);
if ($trade->getItem(0)->getCount() !== 2) {
	throw new RuntimeException('Reading a trade slot mutated stored inventory');
}
$trade->clearAll();
if (!$trade->getItem(0)->isNull() || $trade->getContents() !== []) {
	throw new RuntimeException('Trade inventory did not clear its contents');
}
$trade->setItem(0, VanillaItems::DIAMOND());
$trade->setItem(2, VanillaItems::GOLD_INGOT());
$api = (new ReflectionClass(TradeAPI::class))->newInstanceWithoutConstructor();
$api->doCloseInventory($trade);
if (!$holder->getInventory()->getItem(0)->equalsExact(VanillaItems::DIAMOND()) ||
	count($holder->getInventory()->getContents()) !== 1 || $trade->getContents() !== []) {
	throw new RuntimeException('Closing a trade did not return only the paid ingredient');
}

$fake = new FakeInventory(VanillaItems::EMERALD());
if ($fake->getSize() !== 1 || !$fake->getItem(0)->equalsExact(VanillaItems::EMERALD())) {
	throw new RuntimeException('Fake inventory did not store its item in one slot');
}
$fake->setItem(0, VanillaItems::STICK());
if (!$fake->getItem(0)->equalsExact(VanillaItems::STICK())) {
	throw new RuntimeException('Fake inventory did not update its slot');
}

echo "PASS: archived trade inventory stores items, opens by actor ID and returns inputs without granting output\n";
