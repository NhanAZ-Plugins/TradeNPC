<?php

declare(strict_types=1);

namespace TradeNPC;

use pocketmine\item\Item;
use TradeNPC\entity\TradeNPC;

class TradeDataPool
{

    /** @var TradeNPC[] */
    public static $interactNPCData = [];

    /** @var int[] */
    public static $windowIdData = [];

    /** @var array<string, array<string, Item>> */
    public static $editNPCData = [];
}
