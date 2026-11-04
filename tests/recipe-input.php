<?php

declare(strict_types=1);

use pocketmine\event\player\PlayerChatEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\lang\Translatable;
use pocketmine\player\chat\StandardChatFormatter;
use pocketmine\player\Player;
use TradeNPC\entity\TradeNPC;
use TradeNPC\listener\EventListener;
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

class RecipeTestPlayer extends Player {
    public function __destruct() {}
    public function sendMessage(Translatable|string $message): void {}
}

class RecipeTestNpc extends TradeNPC {
    /** @var list<array{Item, Item, Item}> */
    public array $recipes = [];
    public function __destruct() {}
    public function addTradeItem(Item $buyA, Item $buyB, Item $sell): void {
        $this->recipes[] = [$buyA, $buyB, $sell];
    }
}

class RecipeTestPlugin extends Main {
    public RecipeTestNpc $target;
    public function getEntityName(string $chat): ?TradeNPC {
        return $chat === 'shop' ? $this->target : null;
    }
    public function saveAll(): void {}
}

function recipeEditorReset(Main $plugin): bool {
    return !$plugin->turn && $plugin->fullItem === [];
}

function assertRecipeCount(RecipeTestNpc $npc, int $expected): void {
    if (count($npc->recipes) !== $expected) {
        throw new RuntimeException('Unexpected number of trade recipes');
    }
}

$player = (new ReflectionClass(RecipeTestPlayer::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(Player::class, 'username'))->setValue($player, 'RecipeTester');
$npc = (new ReflectionClass(RecipeTestNpc::class))->newInstanceWithoutConstructor();
$plugin = (new ReflectionClass(RecipeTestPlugin::class))->newInstanceWithoutConstructor();
$plugin->target = $npc;
$plugin->name = $player->getName();
$plugin->turn = true;
$plugin->fullItem = array_fill(0, 26, VanillaItems::AIR());
$plugin->fullItem[0] = VanillaItems::DIAMOND();
$plugin->fullItem[9] = VanillaItems::STICK();
$plugin->fullItem[18] = VanillaItems::GOLD_INGOT();
$event = new PlayerChatEvent($player, 'shop', [], new StandardChatFormatter());
(new EventListener($plugin))->onChat($event);
assertRecipeCount($npc, 1);
if (!$event->isCancelled() || !recipeEditorReset($plugin)) {
    throw new RuntimeException('Valid recipe did not finish once and reset the editor');
}

$plugin->turn = true;
$plugin->name = $player->getName();
$plugin->fullItem = [VanillaItems::DIAMOND()];
$event = new PlayerChatEvent($player, 'shop', [], new StandardChatFormatter());
(new EventListener($plugin))->onChat($event);
assertRecipeCount($npc, 1);
if (!$event->isCancelled()) {
    throw new RuntimeException('Incomplete recipe created an offer or failed to close the editor');
}

echo "PASS: recipe input uses bounded complete rows and ignores missing slots\n";
