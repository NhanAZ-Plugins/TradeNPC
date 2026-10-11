<?php

declare(strict_types=1);

$autoload = getenv('AXOLOTL_AUTOLOAD');
if (!is_string($autoload) || !is_file($autoload)) {
	throw new RuntimeException('Set AXOLOTL_AUTOLOAD to a verified Axolotl-PM Composer autoloader');
}
require $autoload;
require __DIR__ . '/../src/nlog/trade/inventory/FakeInventory.php';
require __DIR__ . '/../src/nlog/trade/inventory/PlayerTradeInventory.php';

use nlog\trade\inventory\FakeInventory;
use nlog\trade\inventory\PlayerTradeInventory;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;

final class FixturePlayer extends Player {
	public function __destruct() {}
}

$holder = (new ReflectionClass(FixturePlayer::class))->newInstanceWithoutConstructor();
$trade = new PlayerTradeInventory($holder);
if ($trade->getHolder() !== $holder || $trade->getSize() !== 3) {
	throw new RuntimeException('Trade inventory holder or size is wrong');
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

$fake = new FakeInventory(VanillaItems::EMERALD());
if ($fake->getSize() !== 1 || !$fake->getItem(0)->equalsExact(VanillaItems::EMERALD())) {
	throw new RuntimeException('Fake inventory did not store its item in one slot');
}
$fake->setItem(0, VanillaItems::STICK());
if (!$fake->getItem(0)->equalsExact(VanillaItems::STICK())) {
	throw new RuntimeException('Fake inventory did not update its slot');
}

echo "PASS: archived trade inventory stores, reads and clears items\n";
