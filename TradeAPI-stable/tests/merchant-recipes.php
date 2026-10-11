<?php

declare(strict_types=1);

$autoload = getenv('AXOLOTL_AUTOLOAD');
if (!is_string($autoload) || !is_file($autoload)) {
	throw new RuntimeException('Set AXOLOTL_AUTOLOAD to a verified Axolotl-PM Composer autoloader');
}
require $autoload;
require __DIR__ . '/../src/nlog/trade/merchant/MerchantRecipe.php';
require __DIR__ . '/../src/nlog/trade/merchant/MerchantRecipeList.php';

use nlog\trade\merchant\MerchantRecipe;
use nlog\trade\merchant\MerchantRecipeList;
use pocketmine\item\VanillaItems;

$first = new MerchantRecipe(VanillaItems::DIAMOND(), VanillaItems::GOLD_INGOT(), maxUses: 7);
$second = new MerchantRecipe(VanillaItems::STICK(), VanillaItems::EMERALD(), maxUses: 3);
$list = new MerchantRecipeList($first);
$initial = $list->toNBT();
if (count($initial) !== 1 || $initial->getValue()[0]->getInt('maxUses') !== 7) {
	throw new RuntimeException('Initial merchant recipe or maxUses was not serialized');
}
if ($list->toNBT() !== $initial) {
	throw new RuntimeException('Unchanged merchant recipe list did not reuse its cache');
}

$list->push($second);
$expanded = $list->toNBT();
if (count($expanded) !== 2 || $expanded->getValue()[1]->getInt('maxUses') !== 3) {
	throw new RuntimeException('Pushed merchant recipe was not serialized');
}
if ($list->pop() === null || count($list->toNBT()) !== 1) {
	throw new RuntimeException('Popped merchant recipe remained in the cache');
}
$cached = $list->toNBT();
if ($list->toNBT(false) === $cached) {
	throw new RuntimeException('Explicit cache bypass reused the same NBT list');
}

echo "PASS: merchant recipe serialization and cache lifecycle\n";
