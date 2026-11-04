<?php

declare(strict_types=1);

namespace TradeNPC;

use pocketmine\inventory\Inventory;
use pocketmine\inventory\SimpleInventory;
use pocketmine\item\Item;

final class TradeSettlement
{
    public static function complete(Inventory $inventory, Item $buyA, Item $buyB, Item $sell): bool
    {
        if ($buyA->isNull() || $buyB->isNull() || $sell->isNull()) {
            return false;
        }

        $trial = new SimpleInventory($inventory->getSize());
        $trial->setMaxStackSize($inventory->getMaxStackSize());
        $trial->setContents($inventory->getContents());

        if ($trial->removeItem($buyA, $buyB) !== []) {
            return false;
        }
        if ($trial->addItem($sell) !== []) {
            return false;
        }

        $inventory->setContents($trial->getContents());
        return true;
    }
}
