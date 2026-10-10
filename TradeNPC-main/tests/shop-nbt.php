<?php

declare(strict_types=1);

use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\player\Player;
use TradeNPC\entity\TradeNPC;
use TradeNPC\inventory\TradeInventory;
use TradeNPC\Main;

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

class ShopNbtNpc extends TradeNPC {
    public function __destruct() {}
}

class ShopNbtPlayer extends Player {
    /** @var list<TradeInventory> */
    public array $openedWindows = [];
    public bool $allowWindow = true;
    public function __destruct() {}
    public function setCurrentWindow(\pocketmine\inventory\Inventory $inventory): bool {
        if (!$this->allowWindow) {
            return false;
        }
        if (!$inventory instanceof TradeInventory) {
            throw new RuntimeException('Unexpected inventory');
        }
        $this->openedWindows[] = $inventory;
        return true;
    }
}

$npc = (new ReflectionClass(ShopNbtNpc::class))->newInstanceWithoutConstructor();
$shopProperty = new ReflectionProperty(TradeNPC::class, 'shop');
$shop = CompoundTag::create()->setTag('Recipes', new ListTag([], NBT::TAG_Compound));
$shopProperty->setValue($npc, $shop);
$npc->addTradeItem(VanillaItems::DIAMOND(), VanillaItems::STICK(), VanillaItems::GOLD_INGOT());
$saved = (new LittleEndianNbtSerializer())->read($npc->getSaveNBT())->mustGetCompoundTag();
$recipes = $saved->getListTag('Recipes');
$recipe = $recipes?->get(0);
if (!$recipe instanceof CompoundTag || count($recipes) !== 1) {
    throw new RuntimeException('Trade recipe did not survive NBT serialization');
}
$buy = $recipe->getCompoundTag('buyA');
if ($buy === null || !Item::nbtDeserialize($buy)->equals(VanillaItems::DIAMOND())) {
    throw new RuntimeException('Trade ingredient changed during NBT serialization');
}

$shopProperty->setValue($npc, new TreeRoot($shop));
if ($npc->getShopCompoundTag() !== $shop || $npc->getSaveNBT() !== (new LittleEndianNbtSerializer())->write(new TreeRoot($shop))) {
    throw new RuntimeException('TreeRoot-backed shop changed during serialization');
}
$inventory = new TradeInventory($npc);
$compoundOffers = $inventory->getOffers($shop);
$treeOffers = $inventory->getOffers(new TreeRoot($shop));
if ($compoundOffers === null || $treeOffers === null ||
    $compoundOffers->getRoot() !== $shop || $treeOffers->getRoot() !== $shop) {
    throw new RuntimeException('Trade offers did not preserve the shop tag');
}

$plugin = (new ReflectionClass(Main::class))->newInstanceWithoutConstructor();
$firstPlayer = (new ReflectionClass(ShopNbtPlayer::class))->newInstanceWithoutConstructor();
$secondPlayer = (new ReflectionClass(ShopNbtPlayer::class))->newInstanceWithoutConstructor();
if (!$plugin->setCWindow($inventory, $firstPlayer) || !$plugin->setCWindow($inventory, $secondPlayer) ||
    $firstPlayer->openedWindows !== [$inventory] || $secondPlayer->openedWindows !== [$inventory]) {
    throw new RuntimeException('Shared NPC inventory did not open for each player');
}
$firstPlayer->allowWindow = false;
if ($plugin->setCWindow($inventory, $firstPlayer) || count($firstPlayer->openedWindows) !== 1) {
    throw new RuntimeException('Rejected inventory open was not respected');
}

$shopProperty->setValue($npc, CompoundTag::create());
try {
    $npc->addTradeItem(VanillaItems::DIAMOND(), VanillaItems::STICK(), VanillaItems::GOLD_INGOT());
    throw new RuntimeException('Shop without Recipes should fail');
} catch (UnexpectedValueException) {
}
$shopProperty->setValue($npc, null);
try {
    $npc->getSaveNBT();
    throw new RuntimeException('Uninitialized shop should fail');
} catch (LogicException) {
}
$player = (new ReflectionClass(ShopNbtPlayer::class))->newInstanceWithoutConstructor();
try {
    $inventory->onOpen($player);
    throw new RuntimeException('Uninitialized shop should not open a trade window');
} catch (LogicException) {
}

echo "PASS: trade shop NBT, invalid states and per-player window opening\n";
