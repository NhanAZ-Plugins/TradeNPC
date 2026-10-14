<?php

declare(strict_types=1);

use pocketmine\inventory\SimpleInventory;
use pocketmine\item\VanillaItems;
use TradeNPC\TradeSettlement;

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

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$diamond = VanillaItems::DIAMOND();
$gold = VanillaItems::GOLD_INGOT();
$stick = VanillaItems::STICK();

$inventory = new SimpleInventory(2);
$inventory->setItem(0, $diamond);
$assert(!TradeSettlement::complete($inventory, $diamond, $diamond, $gold), 'One diamond must not pay two diamond costs');
$assert($inventory->getItem(0)->equals($diamond) && $inventory->getItem(0)->getCount() === 1, 'Failed trade changed the ingredient');
$assert($inventory->getItem(1)->isNull(), 'Failed trade created a reward');

$inventory->setItem(0, (clone $diamond)->setCount(2));
$assert(TradeSettlement::complete($inventory, $diamond, $diamond, $gold), 'Two diamonds should pay two diamond costs');
$assert($inventory->getItem(0)->equals($gold) && $inventory->getItem(0)->getCount() === 1, 'Completed trade has incorrect reward');

$inventory = new SimpleInventory(2);
$inventory->setItem(0, $diamond);
$inventory->setItem(1, $stick);
$assert(TradeSettlement::complete($inventory, $diamond, $stick, $gold), 'Distinct ingredients should trade');
$assert($inventory->contains($gold) && !$inventory->contains($diamond) && !$inventory->contains($stick), 'Distinct trade contents are wrong');

$inventory = new SimpleInventory(2);
$inventory->setItem(0, (clone $diamond)->setCount(64));
$inventory->setItem(1, (clone $stick)->setCount(64));
$assert(!TradeSettlement::complete($inventory, $diamond, $diamond, $gold), 'Reward must fit after ingredient removal');
$assert($inventory->getItem(0)->getCount() === 64 && $inventory->getItem(1)->getCount() === 64, 'Full inventory trade consumed ingredients');

$assert(!TradeSettlement::complete($inventory, VanillaItems::AIR(), $diamond, $gold), 'Empty ingredient must be rejected');
$assert(!TradeSettlement::complete($inventory, $diamond, $diamond, VanillaItems::AIR()), 'Empty reward must be rejected');

echo "PASS: trade settlement rejects partial costs and full rewards without changing inventory\n";
