<?php

declare(strict_types=1);

$autoload = getenv('AXOLOTL_AUTOLOAD');
if (!is_string($autoload) || !is_file($autoload)) {
	throw new RuntimeException('Set AXOLOTL_AUTOLOAD to a verified Axolotl-PM Composer autoloader');
}
require $autoload;
require __DIR__ . '/../src/nlog/trade/inventory/PlayerTradeInventory.php';
require __DIR__ . '/../src/nlog/trade/inventory/FakeInventory.php';
require __DIR__ . '/../src/nlog/trade/TradeAPI.php';
require __DIR__ . '/../src/nlog/trade/inventory/action/NetworkTradeInventoryAction.php';
require __DIR__ . '/../src/nlog/trade/inventory/action/NetworkFakeInventoryAction.php';

use nlog\trade\inventory\action\NetworkFakeInventoryAction;
use nlog\trade\inventory\action\NetworkTradeInventoryAction;
use nlog\trade\TradeAPI;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\inventory\NetworkInventoryAction;
use pocketmine\player\Player;

final class ActionFixtureConverter extends TypeConverter {
	public int $calls = 0;
	public function __construct() {}
	public function netItemStackToCore(ItemStack $itemStack): Item {
		++$this->calls;
		return $itemStack->isNull() ? VanillaItems::AIR() : VanillaItems::DIAMOND();
	}
}

final class ActionFixtureSession extends NetworkSession {
	public ActionFixtureConverter $converter;
	public function getTypeConverter(): TypeConverter {
		return $this->converter;
	}
}

final class ActionFixturePlayer extends Player {
	public ActionFixtureSession $session;
	public function __destruct() {}
	public function getName(): string {
		return 'trade-action-fixture';
	}
	public function getNetworkSession(): NetworkSession {
		return $this->session;
	}
}

$player = (new ReflectionClass(ActionFixturePlayer::class))->newInstanceWithoutConstructor();
$session = (new ReflectionClass(ActionFixtureSession::class))->newInstanceWithoutConstructor();
$session->converter = new ActionFixtureConverter();
$player->session = $session;
TradeAPI::addInventory($player);
$inventory = TradeAPI::getInventory($player);
if ($inventory === null) {
	throw new RuntimeException('Trade inventory was not registered');
}
$itemStackParameters = (new ReflectionClass(ItemStack::class))->getConstructor()?->getNumberOfRequiredParameters();
$networkDiamond = $itemStackParameters === 5
	? new ItemStack(1, 0, 1, 0, '')
	: new ItemStack(1, 0, 1, 0, null, [], []);
$diamond = ItemStackWrapper::legacy($networkDiamond);
$air = ItemStackWrapper::legacy(ItemStack::null());

foreach ([4 => 0, 5 => 1, 51 => 2] as $networkSlot => $inventorySlot) {
	$inventory->setItem($inventorySlot, VanillaItems::DIAMOND());
	$action = new NetworkTradeInventoryAction();
	$action->sourceType = NetworkInventoryAction::SOURCE_CONTAINER;
	$action->windowId = ContainerIds::UI;
	$action->inventorySlot = $networkSlot;
	$action->oldItem = $diamond;
	$action->newItem = $air;
	$result = $action->createInventoryAction($player);
	if (!$result instanceof SlotChangeAction || $result->getInventory() !== $inventory || $result->getSlot() !== $inventorySlot) {
		throw new RuntimeException("Trade slot $networkSlot mapped incorrectly");
	}
	$result->validate($player);
	if (!$result->getSourceItem()->equalsExact(VanillaItems::DIAMOND()) || !$result->getTargetItem()->equalsExact(VanillaItems::AIR())) {
		throw new RuntimeException('Trade action did not convert protocol item stacks to core items');
	}
}

$before = $session->converter->calls;
$action->newItem = $diamond;
if ($action->createInventoryAction($player) !== null || $session->converter->calls !== $before) {
	throw new RuntimeException('Unchanged trade action was decoded or applied');
}

$fake = new NetworkFakeInventoryAction();
$fake->sourceType = NetworkInventoryAction::SOURCE_TODO;
$fake->windowId = -30;
$fake->inventorySlot = 0;
$fake->oldItem = $diamond;
$fake->newItem = $air;
$result = $fake->createInventoryAction($player);
if (!$result instanceof SlotChangeAction || $result->getSlot() !== 0) {
	throw new RuntimeException('Fake inventory action was not created');
}
$result->validate($player);

$fake->inventorySlot = 1;
try {
	$fake->createInventoryAction($player);
	throw new RuntimeException('Out-of-range fake inventory slot was accepted');
} catch (UnexpectedValueException) {
}

TradeAPI::removeInventory($player);
echo "PASS: archived trade action item conversion, slots and validation guards\n";
